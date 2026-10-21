<?php

namespace App\Entity;

use App\Enum\AssetType;
use App\Repository\ProductRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\CustomIdGenerator;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;
use Gedmo\Mapping\Annotation\SoftDeleteable;
use App\Entity\Trait\SoftDeleteableEntity;
use App\Entity\Trait\TimestampableEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Entity(repositoryClass: ProductRepository::class)]
#[SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class Product
{
    use TimestampableEntity;
    use SoftDeleteableEntity;

    public const string GET_COLLECTION_PRODUCT = 'get:collection:product';
    public const string GET_ITEM_PRODUCT = 'get:item:product';
    public const string POST_COLLECTION_PRODUCT = 'post:collection:product';
    public const string PATCH_ITEM_PRODUCT = 'patch:item:product';

    /**
     * @var Uuid|null
     */
    #[Id]
    #[Column(type: 'uuid', unique: true)]
    #[GeneratedValue(strategy: 'CUSTOM')]
    #[CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups([self::GET_ITEM_PRODUCT, self::GET_COLLECTION_PRODUCT])]
    private ?Uuid $id = null;

    /**
     * @var string|null
     */
    #[NotBlank]
    #[Column(length: 255)]
    #[Groups([self::GET_ITEM_PRODUCT, self::GET_COLLECTION_PRODUCT, self::POST_COLLECTION_PRODUCT, self::PATCH_ITEM_PRODUCT])]
    private ?string $title = null;

    /**
     * @var string|null
     */
    #[NotBlank]
    #[Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Groups([self::GET_ITEM_PRODUCT, self::GET_COLLECTION_PRODUCT, self::POST_COLLECTION_PRODUCT, self::PATCH_ITEM_PRODUCT])]
    private ?string $price = null;

    /**
     * @var string|null
     */
    #[NotBlank]
    #[Choice(callback: [AssetType::class, 'values'])]
    #[Column(length: 50, nullable: true)]
    #[Groups([self::GET_ITEM_PRODUCT, self::GET_COLLECTION_PRODUCT, self::POST_COLLECTION_PRODUCT, self::PATCH_ITEM_PRODUCT])]
    private ?string $assetType = null;

    /**
     * @var int|null
     */
    #[NotBlank]
    #[Column(type: Types::INTEGER, nullable: true)]
    #[Groups([self::GET_ITEM_PRODUCT, self::GET_COLLECTION_PRODUCT, self::POST_COLLECTION_PRODUCT, self::PATCH_ITEM_PRODUCT])]
    private ?int $durationDays = 30;

    /**
     * @var array|null
     */
    #[Column(type: Types::JSON, nullable: true)]
    #[Groups([self::GET_ITEM_PRODUCT, self::GET_COLLECTION_PRODUCT, self::POST_COLLECTION_PRODUCT, self::PATCH_ITEM_PRODUCT])]
    private ?array $options = null;

    /**
     * @var Collection<int, CartItem>
     */
    #[OneToMany(targetEntity: CartItem::class, mappedBy: 'product')]
    private Collection $cartItems;

    /**
     * @var Collection<int, Asset>
     */
    #[OneToMany(targetEntity: Asset::class, mappedBy: 'product')]
    private Collection $assets;

    /**
     * @var Collection<int, OrderItem>
     */
    #[OneToMany(targetEntity: OrderItem::class, mappedBy: 'product')]
    private Collection $orderItems;

    /**
     *
     */
    public function __construct()
    {
        $this->cartItems = new ArrayCollection();
        $this->assets = new ArrayCollection();
        $this->orderItems = new ArrayCollection();
    }

    /**
     * @return Uuid|null
     */
    public function getId(): ?Uuid
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return $this
     */
    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getPrice(): ?string
    {
        return $this->price;
    }

    /**
     * @param string $price
     * @return $this
     */
    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    /**
     * @return Collection<int, CartItem>
     */
    public function getCartItems(): Collection
    {
        return $this->cartItems;
    }

    /**
     * @param CartItem $cartItem
     * @return $this
     */
    public function addCartItem(CartItem $cartItem): static
    {
        if (!$this->cartItems->contains($cartItem)) {
            $this->cartItems->add($cartItem);
            $cartItem->setProduct($this);
        }

        return $this;
    }

    /**
     * @param CartItem $cartItem
     * @return $this
     */
    public function removeCartItem(CartItem $cartItem): static
    {
        if ($this->cartItems->removeElement($cartItem)) {
            if ($cartItem->getProduct() === $this) {
                $cartItem->setProduct(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Asset>
     */
    public function getAssets(): Collection
    {
        return $this->assets;
    }

    /**
     * @param Asset $asset
     * @return $this
     */
    public function addAsset(Asset $asset): static
    {
        if (!$this->assets->contains($asset)) {
            $this->assets->add($asset);
            $asset->setProduct($this);
        }

        return $this;
    }

    /**
     * @param Asset $asset
     * @return $this
     */
    public function removeAsset(Asset $asset): static
    {
        if ($this->assets->removeElement($asset)) {
            if ($asset->getProduct() === $this) {
                $asset->setProduct(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    /**
     * @param OrderItem $orderItem
     * @return $this
     */
    public function addOrderItem(OrderItem $orderItem): static
    {
        if (!$this->orderItems->contains($orderItem)) {
            $this->orderItems->add($orderItem);
            $orderItem->setProduct($this);
        }

        return $this;
    }

    /**
     * @param OrderItem $orderItem
     * @return $this
     */
    public function removeOrderItem(OrderItem $orderItem): static
    {
        if ($this->orderItems->removeElement($orderItem)) {
            if ($orderItem->getProduct() === $this) {
                $orderItem->setProduct(null);
            }
        }

        return $this;
    }

    /**
     * @return string|null
     */
    public function getAssetType(): ?string
    {
        return $this->assetType;
    }

    /**
     * @param string|null $assetType
     * @return $this
     */
    public function setAssetType(?string $assetType): static
    {
        $this->assetType = $assetType;

        return $this;
    }

    /**
     * Alias for backward compatibility with AssetFulfillmentSubscriber
     */
    public function getAsset(): ?string
    {
        return $this->assetType;
    }

    /**
     * @return int|null
     */
    public function getDurationDays(): ?int
    {
        return $this->durationDays;
    }

    /**
     * @param int|null $durationDays
     * @return $this
     */
    public function setDurationDays(?int $durationDays): static
    {
        $this->durationDays = $durationDays;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getOptions(): ?array
    {
        return $this->options;
    }

    /**
     * @param array|null $options
     * @return $this
     */
    public function setOptions(?array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    #[Groups([self::GET_ITEM_PRODUCT, self::GET_COLLECTION_PRODUCT])]
    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return DateTimeImmutable|null
     */
    #[Groups([self::GET_ITEM_PRODUCT, self::GET_COLLECTION_PRODUCT])]
    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
