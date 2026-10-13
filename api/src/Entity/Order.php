<?php

namespace App\Entity;

use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Service\Interface\UserAwareInterface;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\CustomIdGenerator;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\Version;
use Gedmo\Mapping\Annotation\SoftDeleteable;
use App\Entity\Trait\SoftDeleteableEntity;
use App\Entity\Trait\TimestampableEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\NotNull;

#[Entity(repositoryClass: OrderRepository::class)]
#[Table(name: 'orders')]
#[SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class Order implements UserAwareInterface
{
    use TimestampableEntity;
    use SoftDeleteableEntity;

    public const string GET_COLLECTION_ORDER = 'get:collection:order';
    public const string GET_ITEM_ORDER = 'get:item:order';
    public const string POST_COLLECTION_ORDER = 'post:collection:order';
    public const string PATCH_ITEM_ORDER = 'patch:item:order';
    public const string PATCH_ITEM_ORDER_STATUS = 'patch:item:order:status';

    /**
     * @var Uuid|null
     */
    #[Id]
    #[Column(type: 'uuid', unique: true)]
    #[GeneratedValue(strategy: 'CUSTOM')]
    #[CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups([self::GET_ITEM_ORDER, self::GET_COLLECTION_ORDER])]
    private ?Uuid $id = null;

    /**
     * @var User|null
     */
    #[ManyToOne(inversedBy: 'orders')]
    #[Groups([self::GET_ITEM_ORDER, self::GET_COLLECTION_ORDER])]
    private ?User $user = null;

    /**
     * @var string|null
     */
    #[Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Groups([self::GET_ITEM_ORDER, self::GET_COLLECTION_ORDER])]
    private ?string $totalPrice = null;

    /**
     * @var OrderStatus
     */
    #[Column(type: Types::STRING, length: 20, enumType: OrderStatus::class, options: ['default' => OrderStatus::PENDING->value])]
    #[Groups([self::GET_ITEM_ORDER, self::GET_COLLECTION_ORDER, self::PATCH_ITEM_ORDER, self::PATCH_ITEM_ORDER_STATUS])]
    #[NotNull(groups: [self::PATCH_ITEM_ORDER_STATUS])]
    private OrderStatus $status = OrderStatus::PENDING;

    /**
     * @var int|null
     */
    #[Version]
    #[Column(type: Types::INTEGER, options: ['default' => 1])]
    private ?int $version = 1;

    /**
     * @var Collection<int, OrderItem>
     */
    #[OneToMany(targetEntity: OrderItem::class, mappedBy: 'customerOrder', cascade: ['persist', 'remove'])]
    #[Groups([self::GET_ITEM_ORDER, self::GET_COLLECTION_ORDER, self::POST_COLLECTION_ORDER])]
    private Collection $orderItems;

    /**
     *
     */
    public function __construct()
    {
        $this->orderItems = new ArrayCollection();
    }

    /**
     * @return Uuid
     */
    public function getId(): Uuid
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
     * @return OrderStatus
     */
    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    /**
     * @param OrderStatus $status
     * @return $this
     */
    public function setStatus(OrderStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getVersion(): ?int
    {
        return $this->version;
    }

    /**
     * @return $this
     */
    public function recalculateTotalPrice(): static
    {
        $total = '0.00';
        foreach ($this->orderItems as $item) {
            $total = bcadd($total, $item->getTotalPrice() ?? '0.00', 2);
        }
        $this->totalPrice = $total;

        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    #[Groups([self::GET_ITEM_ORDER, self::GET_COLLECTION_ORDER])]
    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return DateTimeImmutable|null
     */
    #[Groups([self::GET_ITEM_ORDER, self::GET_COLLECTION_ORDER])]
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
            $orderItem->setCustomerOrder($this);
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
            if ($orderItem->getCustomerOrder() === $this) {
                $orderItem->setCustomerOrder(null);
            }
        }

        return $this;
    }
}
