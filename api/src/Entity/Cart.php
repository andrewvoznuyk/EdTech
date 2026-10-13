<?php

namespace App\Entity;

use App\Repository\CartRepository;
use App\Service\Interface\UserAwareInterface;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\CustomIdGenerator;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;
use App\Entity\Trait\TimestampableEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[Entity(repositoryClass: CartRepository::class)]
class Cart implements UserAwareInterface
{
    use TimestampableEntity;

    public const string GET_COLLECTION_CART = 'get:collection:cart';
    public const string GET_ITEM_CART = 'get:item:cart';
    public const string POST_COLLECTION_CART = 'post:collection:cart';
    public const string PATCH_ITEM_CART = 'patch:item:cart';

    /**
     * @var Uuid|null
     */
    #[Id]
    #[Column(type: 'uuid', unique: true)]
    #[GeneratedValue(strategy: 'CUSTOM')]
    #[CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups([self::GET_ITEM_CART, self::GET_COLLECTION_CART])]
    private ?Uuid $id = null;

    /**
     * @var User|null
     */
    #[OneToOne(inversedBy: 'cart')]
    #[Groups([self::GET_ITEM_CART, self::GET_COLLECTION_CART, self::POST_COLLECTION_CART])]
    private ?User $user = null;

    /**
     * @var Collection<int, CartItem>
     */
    #[OneToMany(targetEntity: CartItem::class, mappedBy: 'cart', cascade: ['persist', 'remove'])]
    #[Groups([self::GET_ITEM_CART, self::GET_COLLECTION_CART])]
    private Collection $cartItems;

    /**
     * @return string
     */
    #[Groups([self::GET_ITEM_CART, self::GET_COLLECTION_CART])]
    public function getTotalPrice(): string
    {
        $total = 0.00;
        foreach ($this->cartItems as $item) {
            $total += (float) $item->getTotalPrice();
        }

        return number_format($total, 2, '.', '');
    }

    /**
     * @return int
     */
    #[Groups([self::GET_ITEM_CART, self::GET_COLLECTION_CART])]
    public function getTotalQuantity(): int
    {
        $qty = 0;
        foreach ($this->cartItems as $item) {
            $qty += $item->getQuantity() ?? 0;
        }
        return $qty;
    }

    /**
     * @return DateTimeImmutable|null
     */
    #[Groups([self::GET_ITEM_CART, self::GET_COLLECTION_CART])]
    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return DateTimeImmutable|null
     */
    #[Groups([self::GET_ITEM_CART, self::GET_COLLECTION_CART])]
    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     *
     */
    public function __construct()
    {
        $this->cartItems = new ArrayCollection();
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
            $cartItem->setCart($this);
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
            if ($cartItem->getCart() === $this) {
                $cartItem->setCart(null);
            }
        }

        return $this;
    }
}
