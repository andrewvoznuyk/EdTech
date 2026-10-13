<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use App\Entity\Trait\SoftDeleteableEntity;
use App\Entity\Trait\TimestampableEntity;
use App\Repository\AssetRepository;
use App\Service\Interface\UserAwareInterface;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\DiscriminatorColumn;
use Doctrine\ORM\Mapping\DiscriminatorMap;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\InheritanceType;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Gedmo\Mapping\Annotation\SoftDeleteable;
use Symfony\Component\Serializer\Attribute\Groups;

#[Entity(repositoryClass: AssetRepository::class)]
#[InheritanceType('JOINED')]
#[DiscriminatorColumn(name: 'asset_type', type: 'string')]
#[DiscriminatorMap([
    'widget' => Widget::class,
])]
#[SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
#[ApiFilter(SearchFilter::class, properties: ['id' => 'exact', 'status' => 'exact', 'user.id' => 'exact', 'user.email' => 'partial', 'product.id' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'status', 'createdAt', 'updatedAt', 'startsAt', 'expiresAt'])]
abstract class Asset implements UserAwareInterface
{
    use TimestampableEntity;
    use SoftDeleteableEntity;

    public const int STATUS_PENDING = 0;
    public const int STATUS_ACTIVE = 1;
    public const int STATUS_EXPIRED = 2;
    public const int STATUS_CANCELLED = 3;

    public const string GET_COLLECTION_ASSET = 'get:collection:asset';
    public const string GET_ITEM_ASSET = 'get:item:asset';
    public const string GET_COLLECTION_ASSET_ADMIN = 'get:collection:asset:admin';

    /**
     * @var int|null
     */
    #[Id]
    #[GeneratedValue]
    #[Column]
    #[Groups([Asset::GET_COLLECTION_ASSET, Asset::GET_ITEM_ASSET, Asset::GET_COLLECTION_ASSET_ADMIN])]
    private ?int $id = null;

    /**
     * @var User|null
     */
    #[ManyToOne(inversedBy: 'assets')]
    #[Groups([Asset::GET_COLLECTION_ASSET_ADMIN])]
    private ?User $user = null;

    /**
     * @var Product|null
     */
    #[ManyToOne(inversedBy: 'assets')]
    #[Groups([Asset::GET_ITEM_ASSET, Asset::GET_COLLECTION_ASSET_ADMIN])]
    private ?Product $product = null;

    /**
     * @var int|null
     */
    #[Column(type: Types::INTEGER, options: ['default' => self::STATUS_PENDING])]
    #[Groups([Asset::GET_COLLECTION_ASSET, Asset::GET_ITEM_ASSET, Asset::GET_COLLECTION_ASSET_ADMIN])]
    private ?int $status = self::STATUS_PENDING;

    /**
     * @var DateTimeImmutable|null
     */
    #[Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([Asset::GET_COLLECTION_ASSET, Asset::GET_ITEM_ASSET, Asset::GET_COLLECTION_ASSET_ADMIN])]
    private ?DateTimeImmutable $startsAt = null;

    /**
     * @var DateTimeImmutable|null
     */
    #[Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([Asset::GET_COLLECTION_ASSET, Asset::GET_ITEM_ASSET, Asset::GET_COLLECTION_ASSET_ADMIN])]
    private ?DateTimeImmutable $expiresAt = null;

    /**
     * Duration in days
     *
     * @var int|null
     */
    #[Column(type: Types::INTEGER, nullable: true)]
    #[Groups([Asset::GET_COLLECTION_ASSET, Asset::GET_ITEM_ASSET, Asset::GET_COLLECTION_ASSET_ADMIN])]
    private ?int $durationDays = 30;

    /**
     * @var Collection<int, OrderItem>
     */
    #[OneToMany(targetEntity: OrderItem::class, mappedBy: 'asset')]
    private Collection $orderItems;

    /**
     *
     */
    public function __construct()
    {
        $this->orderItems = new ArrayCollection();
        $this->status = self::STATUS_PENDING;
    }

    /**
     * Type identifier from discriminator column (e.g. 'widget')
     */
    #[Groups([Asset::GET_COLLECTION_ASSET, Asset::GET_ITEM_ASSET, Asset::GET_COLLECTION_ASSET_ADMIN])]
    abstract public function getAssetType(): string;

    /**
     * Human-readable status label
     */
    #[Groups([Asset::GET_COLLECTION_ASSET, Asset::GET_ITEM_ASSET, Asset::GET_COLLECTION_ASSET_ADMIN])]
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => $this->isExpired() ? 'expired' : 'active',
            self::STATUS_EXPIRED => 'expired',
            self::STATUS_CANCELLED => 'cancelled',
            default => 'pending_payment',
        };
    }

    /**
     * Checks whether asset access is currently active and not expired
     */
    #[Groups([Asset::GET_COLLECTION_ASSET, Asset::GET_ITEM_ASSET, Asset::GET_COLLECTION_ASSET_ADMIN])]
    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        return true;
    }

    /**
     * Checks whether asset validity term has passed
     */
    #[Groups([Asset::GET_COLLECTION_ASSET, Asset::GET_ITEM_ASSET, Asset::GET_COLLECTION_ASSET_ADMIN])]
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new DateTimeImmutable();
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User|null $user
     * @return $this
     */
    public function setUser(?User $user): static
    {
        $this->user = $user;

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
     * @return int|null
     */
    public function getStatus(): ?int
    {
        return $this->status;
    }

    /**
     * @param int $status
     * @return $this
     */
    public function setStatus(int $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getStartsAt(): ?DateTimeImmutable
    {
        return $this->startsAt;
    }

    /**
     * @param DateTimeImmutable|null $startsAt
     * @return $this
     */
    public function setStartsAt(?DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * @param DateTimeImmutable|null $expiresAt
     * @return $this
     */
    public function setExpiresAt(?DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
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
     * @return DateTimeImmutable|null
     */
    #[Groups([Asset::GET_COLLECTION_ASSET, Asset::GET_ITEM_ASSET, Asset::GET_COLLECTION_ASSET_ADMIN])]
    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return DateTimeImmutable|null
     */
    #[Groups([Asset::GET_COLLECTION_ASSET, Asset::GET_ITEM_ASSET, Asset::GET_COLLECTION_ASSET_ADMIN])]
    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
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
            $orderItem->setAsset($this);
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
            if ($orderItem->getAsset() === $this) {
                $orderItem->setAsset(null);
            }
        }

        return $this;
    }

    /**
     * @param int|null $durationDays
     * @return $this
     * @throws DateMalformedStringException
     */
    public function activate(?int $durationDays = null): static
    {
        $this->status = self::STATUS_ACTIVE;
        $now = new DateTimeImmutable();
        $this->startsAt = $now;

        if ($durationDays !== null && $durationDays > 0) {
            $this->durationDays = $durationDays;
        }

        $duration = $this->durationDays ?? 30;
        $this->expiresAt = $now->modify("+{$duration} days");

        return $this;
    }
}
