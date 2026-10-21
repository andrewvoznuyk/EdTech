<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use App\Service\Money\MoneyService;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\CustomIdGenerator;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Gedmo\Mapping\Annotation\SoftDeleteable;
use App\Entity\Trait\SoftDeleteableEntity;
use App\Entity\Trait\TimestampableEntity;
use Money\Money;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Positive;

#[Entity(repositoryClass: OrderItemRepository::class)]
#[SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class OrderItem
{
    use TimestampableEntity;
    use SoftDeleteableEntity;

    public const string GET_COLLECTION_ORDER_ITEM = 'get:collection:order_item';
    public const string GET_ITEM_ORDER_ITEM = 'get:item:order_item';
    public const string POST_COLLECTION_ORDER_ITEM = 'post:collection:order_item';
    public const string PATCH_ITEM_ORDER_ITEM = 'patch:item:order_item';

    /**
     * @var Uuid|null
     */
    #[Id]
    #[Column(type: 'uuid', unique: true)]
    #[GeneratedValue(strategy: 'CUSTOM')]
    #[CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups([self::GET_ITEM_ORDER_ITEM, self::GET_COLLECTION_ORDER_ITEM])]
    private ?Uuid $id = null;

    /**
     * @var Order|null
     */
    #[ManyToOne(inversedBy: 'orderItems')]
    #[JoinColumn(onDelete: 'CASCADE')]
    #[Groups([self::POST_COLLECTION_ORDER_ITEM])]
    #[NotNull(groups: [self::POST_COLLECTION_ORDER_ITEM])]
    private ?Order $customerOrder = null;

    /**
     * @var Product|null
     */
    #[ManyToOne(inversedBy: 'orderItems')]
    #[Groups([self::GET_ITEM_ORDER_ITEM, self::GET_COLLECTION_ORDER_ITEM, self::POST_COLLECTION_ORDER_ITEM])]
    #[NotNull(groups: [self::POST_COLLECTION_ORDER_ITEM])]
    private ?Product $product = null;

    /**
     * @var Asset|null
     */
    #[ManyToOne(inversedBy: 'orderItems')]
    #[JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups([self::GET_ITEM_ORDER_ITEM, self::GET_COLLECTION_ORDER_ITEM])]
    private ?Asset $asset = null;

    /**
     * @var int|null
     */
    #[Column]
    #[Groups([self::GET_ITEM_ORDER_ITEM, self::GET_COLLECTION_ORDER_ITEM, self::POST_COLLECTION_ORDER_ITEM, self::PATCH_ITEM_ORDER_ITEM])]
    #[NotNull(groups: [self::PATCH_ITEM_ORDER_ITEM])]
    #[Positive(groups: [self::POST_COLLECTION_ORDER_ITEM, self::PATCH_ITEM_ORDER_ITEM])]
    #[LessThanOrEqual(value: 99, groups: [self::POST_COLLECTION_ORDER_ITEM, self::PATCH_ITEM_ORDER_ITEM])]
    private ?int $quantity = null;

    /**
     * @var string|null
     */
    #[Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    #[Groups([self::GET_ITEM_ORDER_ITEM, self::GET_COLLECTION_ORDER_ITEM])]
    private ?string $unitPrice = null;

    /**
     * @var string|null
     */
    #[Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Groups([self::GET_ITEM_ORDER_ITEM, self::GET_COLLECTION_ORDER_ITEM])]
    private ?string $totalPrice = null;

    /**
     * @return Uuid|null
     */
    public function getId(): ?Uuid
    {
        return $this->id;
    }

    /**
     * @return Order|null
     */
    public function getCustomerOrder(): ?Order
    {
        return $this->customerOrder;
    }

    /**
     * @param Order|null $customerOrder
     * @return $this
     */
    public function setCustomerOrder(?Order $customerOrder): static
    {
        $this->customerOrder = $customerOrder;

        return $this;
    }

    /**
     * @param Order|null $customerOrder
     * @return $this
     */
    #[SerializedName('orderId')]
    #[Groups([self::POST_COLLECTION_ORDER_ITEM])]
    public function setOrderId(?Order $customerOrder): static
    {
        $this->customerOrder = $customerOrder;

        return $this;
    }

    /**
     * @return Uuid|null
     */
    #[SerializedName('orderId')]
    #[Groups([self::GET_ITEM_ORDER_ITEM, self::GET_COLLECTION_ORDER_ITEM])]
    public function getOrderId(): ?Uuid
    {
        return $this->customerOrder?->getId();
    }

    /**
     * @return Product|null
     */
    public function getProduct(): ?Product
    {
        return $this->product;
    }

    /**
     * @param Product|null $product
     * @return $this
     */
    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    /**
     * @param Product|null $product
     * @return $this
     */
    #[SerializedName('productId')]
    #[Groups([self::POST_COLLECTION_ORDER_ITEM])]
    public function setProductId(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    /**
     * @return Asset|null
     */
    public function getAsset(): ?Asset
    {
        return $this->asset;
    }

    /**
     * @param Asset|null $asset
     * @return $this
     */
    public function setAsset(?Asset $asset): static
    {
        $this->asset = $asset;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    /**
     * @param int $quantity
     * @return $this
     */
    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    /**
     * @param string|null $unitPrice
     * @return $this
     */
    public function setUnitPrice(?string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getTotalPrice(): ?string
    {
        return $this->totalPrice;
    }

    /**
     * @param string $totalPrice
     * @return $this
     */
    public function setTotalPrice(string $totalPrice): static
    {
        $this->totalPrice = $totalPrice;

        return $this;
    }

    /**
     * @param string $currency
     * @return Money
     */
    public function getUnitPriceMoney(string $currency = MoneyService::DEFAULT_CURRENCY): Money
    {
        return MoneyService::toMoney($this->unitPrice, $currency);
    }

    /**
     * @param string $currency
     * @return Money
     */
    public function getTotalMoney(string $currency = MoneyService::DEFAULT_CURRENCY): Money
    {
        return MoneyService::toMoney($this->totalPrice, $currency);
    }

    /**
     * @return $this
     */
    public function calculateTotalPrice(): static
    {
        if ($this->product && $this->quantity) {
            $this->unitPrice = $this->product->getPrice();
            $unitMoney = MoneyService::toMoney($this->unitPrice);
            $totalMoney = MoneyService::multiply($unitMoney, $this->quantity);
            $this->totalPrice = MoneyService::format($totalMoney);
        }

        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    #[Groups([self::GET_ITEM_ORDER_ITEM, self::GET_COLLECTION_ORDER_ITEM])]
    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return DateTimeImmutable|null
     */
    #[Groups([self::GET_ITEM_ORDER_ITEM, self::GET_COLLECTION_ORDER_ITEM])]
    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
