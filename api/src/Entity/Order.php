<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;
use Gedmo\Mapping\Annotation\SoftDeleteable;
use App\Entity\Trait\SoftDeleteableEntity;
use App\Entity\Trait\TimestampableEntity;

#[ApiResource]
#[Entity(repositoryClass: OrderRepository::class)]
#[Table(name: 'orders')]
#[SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class Order
{
    use TimestampableEntity;
    use SoftDeleteableEntity;

    /**
     * @var int|null
     */
    #[Id]
    #[GeneratedValue]
    #[Column]
    private ?int $id = null;

    /**
     * @var User|null
     */
    #[ManyToOne(inversedBy: 'orders')]
    private ?User $user = null;

    /**
     * @var string|null
     */
    #[Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private ?string $totalPrice = null;

    /**
     * @var Collection<int, OrderItem>
     */
    #[OneToMany(targetEntity: OrderItem::class, mappedBy: 'customerOrder', cascade: ['persist', 'remove'])]
    private Collection $orderItems;

    /**
     * @var Collection<int, Payments>
     */
    #[OneToMany(targetEntity: Payments::class, mappedBy: 'customerOrder', cascade: ['persist', 'remove'])]
    private Collection $payments;

    /**
     *
     */
    public function __construct()
    {
        $this->orderItems = new ArrayCollection();
        $this->payments = new ArrayCollection();
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

    /**
     * @return Collection<int, Payments>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payments $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setCustomerOrder($this);
        }

        return $this;
    }

    public function removePayment(Payments $payment): static
    {
        if ($this->payments->removeElement($payment)) {
            // set the owning side to null (unless already changed)
            if ($payment->getCustomerOrder() === $this) {
                $payment->setCustomerOrder(null);
            }
        }

        return $this;
    }
}
