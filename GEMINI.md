# Perevireno Backend Coding Guidelines & Architectural Rules

Цей документ містить обов'язкові правила написання бекенд-коду (Symfony / Doctrine / API Platform) для проєкту **Perevireno**. Всі наступні сесії та генерації коду повинні суворо дотримуватися цих вимог.

---

## 1. Архітектура та чистота контролерів і процесорів (Thin Controllers)
- **Заборона оператора `new` для сутностей:** У контролерах і процесорах суворо заборонено створювати об'єкти сутностей вручну через `new Entity()` (наприклад, `new User()`, `new Order()`). Об'єкти мають створюватися через десеріалізацію API Platform / Serializer або спеціалізовані фабрики/сервіси (як-от `AssetService`).
- **RESTful підхід: «Один контролер / процесор — одна сутність»:**
  - Заборонено змішувати створення кількох бізнес-сутностей в одному методі (наприклад, не створювати `Order`, `Payments` та `Asset` одночасно). Для кожної сутності має бути свій контролер/ендпоінт, а клієнт викликає їх послідовно за потреби.
  - CRUD-набір: `getCollection`, `getItem`, `create` (POST), `delete` (DELETE) та `patch` (PATCH).
- **Порядок оголошення роутів та методів у контролерах:**
  Методи дій (action-методи) у контролерах обов'язково повинні розміщуватися у суворо визначеному порядку зверху вниз:
  1. `get_collection_<entity>` (GET колекції / списку ресурсу)
  2. `get_item_<entity>` (GET одного елемента за ідентифікатором)
  3. `post_collection_<entity>` (POST створення ресурсу / чекаут)
  4. `put_collection_<entity>` / `put_item_<entity>` (PUT повна заміна, якщо використовується)
  5. `patch_collection_<entity>` / `patch_item_<entity>` (PATCH часткове оновлення / зміна статусу / скасування)
  6. `delete_item_<entity>` (DELETE видалення)
- **Чисті відповіді контролера:**
  - Уникати зайвих обгорток на кшталт `{"message": "...", "success": true}` — повертати безпосередньо серіалізовану сутність або колекцію.
- **Заборона `try-catch`:** Використання блоків `try-catch` у контролерах та процесорах є антипатерном і заборонено. Помилки мають оброблятися на рівні exception listeners або валідатора.
- **Інжекція сервісів тільки через конструктор:** Навіть якщо в контролері лише один роут, усі залежності та сервіси повинні інжектуватися виключно через `__construct()`, а не як аргументи action-методів.

---

## 2. Серіалізація, десеріалізація та валідація
- **Відмова від DTO-класів:** Не створювати окремі класи DTO. Ціллю для десеріалізації є самі сутності (Entities). Доступ до полів регулюється групами серіалізації (`#[Groups]`).
- **Формат і неймінг груп серіалізації (Serialization Groups):**
  - Усі назви груп серіалізації обов'язково оголошуються публічними типізованими константами (`public const string ...`) всередині відповідної сутності.
  - Значення групи суворо відповідає схемі:
    `<тип_запиту>:<collection_або_item>:<назва_сутності>[:підціль/піддомен]`
  - Формат назв констант та їхніх значень:
    - `public const string GET_COLLECTION_<ENTITY> = 'get:collection:<entity>';`
    - `public const string GET_ITEM_<ENTITY> = 'get:item:<entity>';`
    - `public const string POST_COLLECTION_<ENTITY> = 'post:collection:<entity>';`
    - `public const string PATCH_ITEM_<ENTITY> = 'patch:item:<entity>';` (або з уточненням мети, наприклад `public const string PATCH_COLLECTION_USER = 'patch:item:user:profile';`)
  - Використовувати виключно константи: `#[Groups([self::GET_ITEM_<ENTITY>, self::POST_COLLECTION_<ENTITY>])]`.
- **Контексти в операціях API Platform:**
  - Завжди явно вказувати контексти з використанням цих констант:
    `denormalizationContext: ['groups' => [self::POST_COLLECTION_<ENTITY>]]` (або `PATCH_ITEM_...`)
    `normalizationContext: ['groups' => [self::GET_ITEM_<ENTITY>, ...]]` (або `GET_COLLECTION_...`).
- **Робота з `$data` у Processor:**
  - У `process(mixed $data, ...)` API Platform уже передає десеріалізовану сутність. Не створювати новий об'єкт — працювати безпосередньо з `$data`.
- **Валідація через атрибути валідатора (прямий імпорт без псевдонімів `Assert\`):**
  - Логіка перевірки полів має бути на рівні сутностей за допомогою атрибутів Symfony Validator, а не вручну в коді контролерів.
  - **Заборона префіксів `Assert\`:** Категорично заборонено використовувати скорочення або префікси на кшталт `#[Assert\NotBlank]`, `#[Assert\NotNull]`. Усі атрибути пишуться без префіксів: `#[NotBlank]`, `#[NotNull]`, `#[Positive]`.
  - **Максимальне винесення в імпорти:** Усі класи обмежень (constraints) та інші атрибути мають явно імпортуватися через `use` на початку файлу (наприклад, `use Symfony\Component\Validator\Constraints\NotBlank;`).
- **Єдиний формат помилок (HTTP 400):**
  - Формат відповідей при помилках валідації:
    `{"errors": {"field_name": ["Повідомлення про помилку"]}}`
- **Заборона ручного розбору полів:**
  - Не робити `json_decode` вручну (використовувати Serializer або `$request->getPayload()`).
  - Позбутися конструкцій на кшталт `$productId = $data['product_id'] ?? $data['productId'] ?? null;` або `$assetId = $payload->get('assetId'); $orderId = $payload->get('orderId');`.

---

## 3. Робота з базою даних (Doctrine ORM)
- **Повні назви змінних (заборона скорочень):** Завжди писати назви змінних повністю: `EntityManagerInterface $entityManager` (категорично заборонено скорочення `$em`), `$queryBuilder` (категорично заборонено скорочення `$qb`), `$productQueryDto` замість `$dto` тощо. Жодних скорочень.
- **Повні назви сутностей як аліаси у QueryBuilder:** Категорично заборонено використовувати однолітерні скорочення таблиць/сутностей (як-от `p`, `c`, `u`). Завжди вказувати повну назву сутності:
  - `$this->createQueryBuilder('product')` замість `('p')`
  - `product.price` замість `p.price`
  - `cart.createdAt` замість `c.createdAt`
  - `user.email` замість `u.email`
- **Заборона виклику `find()`:**
  - Замість `find($id)` завжди використовувати `findOneBy(['id' => $id])` або `findBy([...])`.
- **Пошук і збереження через EntityManager:** У кастомних контролерах діставати репозиторії через `$this->entityManager->getRepository(...)`, не інжектувати окремі репозиторії в конструктор (за винятком спеціалізованих State Providers).
- **Інкапсуляція розрахунків у сутностях:** Логіку розрахунків (наприклад, підрахунок `totalPrice` замовлення чи позиції кошика) виносити в методи сутностей (`calculateTotalPrice()`, `recalculateTotalPrice()`), а не рахувати процедурно в контролерах/процесорах.

---

## 4. Безпека та авторизація
- **Відсутність зайвих перевірок користувача:** Якщо роут захищено атрибутом `#[IsGranted('ROLE_USER')]` або `security: "is_granted('ROLE_USER')"`, заборонено дублювати перевірки:
  ```php
  $user = $this->getUser();
  if (!$user instanceof User) { ... } // ЗАБОРОНЕНО
  ```

---

## 5. Оформлення та документація коду (PHPDoc)
- **Заборона описових коментарів:** У PHPDoc-блоках над класами, інтерфейсами та методами категорично заборонено писати розмовні/текстові описи (наприклад: `* Provisions a pending asset and attaches it to the order item.` чи `* Domain service managing shopping cart operations.`).
- **Лише технічні типові анотації:** PHPDoc повинен містити виключно строгі анотації параметрів, результатів і винятків:
  - `@param Type $variable`
  - `@return Type`
  - `@throws ExceptionClass`
  - `@var Type`
  - `@implements InterfaceName` / `@extends ClassName`
- **Обов'язковість анотацій:** Усі класи, методи, конструктори та властивості повинні мати такі типізовані PHPDoc-блоки (за винятком констант).

---

## 6. Робота з Git та контроль версій
- **Заборона автоматичних commit та push:** Категорично заборонено виконувати команди `git commit` та `git push` самостійно без прямої, явної вказівки або попереднього погодження з користувачем. Всі зміни коду мають залишатися в робочому дереві для перевірки користувачем.

---

## 7. Неймінг та архітектура сервісного шару (Service Layer Guidelines)
Неймінг у Service Layer має відображати бізнес-логіку (domain logic), а не технічні деталі реалізації чи роботу з базою даних. Правильно названі сервіси та їхні методи діють як жива документація до бізнес-можливостей застосунку.

- **Назви класів сервісів (Services):**
  - **Предметний підхід (Сутність + Service):** Використовується для базових доменних операцій (`CartService`, `PaymentService`).
  - **Діяльнісний підхід / Single Responsibility (Сутність + Роль / Дія + Сутність):** Сервіс називається за конкретною бізнес-операцією для суворого дотримання SOLID (`OrderPlacementService`, `CertificateGenerator`, `AssetOrderItemFulfiller`).
  - **Заборона слів-смітників у Service Layer:** Категорично уникати абстрактних слів-смітників `Manager`, `Helper`, `Utils`, `Processor` (слово `Processor` допустиме виключно у технічному шарі API Platform `App\State`, але заборонено в бізнес-сервісах). Замінювати їх на смислові доменні ролі: `Registrar`, `Fulfiller`, `Publisher`, `Generator`, `Calculator`.
  - **Агностичність до інфраструктури та фреймворків:** Заборонені назви з прив'язкою до технологій на кшталт `MysqlOrderService`, `SymfonyCartService`, `ApiPaymentService`.
- **Назви методів у сервісах:**
  - **Бізнес-термінологія (Ubiquitous Language):** Називати методи так, як процеси називаються в реальному житті:
    - `addItem(...)` замість `addCartItemToCart(...)`
    - `placeOrder(...)` замість `createOrderFromCart(...)`
    - `getCart(...)` замість `getOrCreateCartFromDatabase(...)`
  - **Дієслово на початку:** Назва методу обов'язково відповідає на питання «Що робить ця дія?» (`get`, `add`, `place`, `fulfill`, `clear`, `remove`).
  - **Уникнення інфраструктурних термінів:** Заборонено використовувати технічні назви збереження чи БД (`saveToDatabase()`, `updateInDb()`, `fetchFromMysql()`).
  - **Уникнення тавтології:** Не дублювати назву класу/сутності в назві методу (`CartService->addItem()` замість `CartService->addCartItem()`, `OrderPlacementService->placeOrder()` замість `OrderPlacementService->placeOrderOrder()`).
- **Контракти (Interfaces):**
  - Для доменних сервісів оголошувати інтерфейси (`CartServiceInterface`, `OrderPlacementServiceInterface`, `OrderItemFulfillerInterface`) для забезпечення слабкого зв'язування (loose coupling), зручного тестування та застосування патернів (Strategy, Decorator).

