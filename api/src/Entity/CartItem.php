<?php

namespace App\Entity;

use App\Repository\CartItemRepository;
use App\Service\Money\MoneyService;
use DateTimeImmutable;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\CustomIdGenerator;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\UniqueConstraint;
use App\Entity\Trait\TimestampableEntity;
use Money\Money;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Positive;

#[Entity(repositoryClass: CartItemRepository::class)]
#[UniqueConstraint(name: 'uniq_cart_product', columns: ['cart_id', 'product_id'])]
class CartItem
{
    use TimestampableEntity;

    public const string GET_COLLECTION_CART_ITEM = 'get:collection:cart_item';
    public const string GET_ITEM_CART_ITEM = 'get:item:cart_item';
    public const string POST_COLLECTION_CART_ITEM = 'post:collection:cart_item';
    public const string PATCH_ITEM_CART_ITEM = 'patch:item:cart_item';

    /**
     * @var Uuid|null
     */
    #[Id]
    #[Column(type: 'uuid', unique: true)]
    #[GeneratedValue(strategy: 'CUSTOM')]
    #[CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups([self::GET_ITEM_CART_ITEM, self::GET_COLLECTION_CART_ITEM])]
    private ?Uuid $id = null;

    /**
     * @var Cart|null
     */
    #[ManyToOne(inversedBy: 'cartItems')]
    #[JoinColumn(name: 'cart_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Cart $cart = null;

    /**
     * @var Product|null
     */
    #[ManyToOne(inversedBy: 'cartItems')]
    #[JoinColumn(name: 'product_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups([self::GET_ITEM_CART_ITEM, self::GET_COLLECTION_CART_ITEM, self::POST_COLLECTION_CART_ITEM])]
    #[NotNull(groups: [self::POST_COLLECTION_CART_ITEM])]
    private ?Product $product = null;

    /**
     * @var int|null
     */
    #[Column]
    #[Groups([self::GET_ITEM_CART_ITEM, self::GET_COLLECTION_CART_ITEM, self::POST_COLLECTION_CART_ITEM, self::PATCH_ITEM_CART_ITEM])]
    #[NotNull(groups: [self::PATCH_ITEM_CART_ITEM])]
    #[Positive(groups: [self::POST_COLLECTION_CART_ITEM, self::PATCH_ITEM_CART_ITEM])]
    #[LessThanOrEqual(value: 99, groups: [self::POST_COLLECTION_CART_ITEM, self::PATCH_ITEM_CART_ITEM])]
    private ?int $quantity = 1;

    /**
     * @param string $currency
     * @return Money
     */
    public function getTotalMoney(string $currency = MoneyService::DEFAULT_CURRENCY): Money
    {
        try {
            if (!$this->product || $this->product->isDeleted()) {
                return MoneyService::toMoney('0.00', $currency);
            }
            $price = $this->product->getPrice();
        } catch (EntityNotFoundException) {
            return MoneyService::toMoney('0.00', $currency);
        }

        $money = MoneyService::toMoney($price, $currency);
        $itemQuantity = $this->quantity ?? 1;

        return MoneyService::multiply($money, $itemQuantity);
    }

    /**
     * @return string
     */
    #[Groups([self::GET_ITEM_CART_ITEM, self::GET_COLLECTION_CART_ITEM])]
    public function getTotalPrice(): string
    {
        return MoneyService::format($this->getTotalMoney());
    }

    /**
     * @return Uuid|null
     */
    public function getId(): ?Uuid
    {
        return $this->id;
    }

    /**
     * @return Cart|null
     */
    public function getCart(): ?Cart
    {
        return $this->cart;
    }

    /**
     * @param Cart|null $cart
     * @return $this
     */
    public function setCart(?Cart $cart): static
    {
        $this->cart = $cart;

        return $this;
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
    #[Groups([self::POST_COLLECTION_CART_ITEM])]
    public function setProductId(?Product $product): static
    {
        $this->product = $product;

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
     * @return DateTimeImmutable|null
     */
    #[Groups([self::GET_ITEM_CART_ITEM, self::GET_COLLECTION_CART_ITEM])]
    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return DateTimeImmutable|null
     */
    #[Groups([self::GET_ITEM_CART_ITEM, self::GET_COLLECTION_CART_ITEM])]
    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
