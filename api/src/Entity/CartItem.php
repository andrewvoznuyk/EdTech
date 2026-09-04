<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\CartItemRepository;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Gedmo\Mapping\Annotation\SoftDeleteable;
use App\Entity\Trait\SoftDeleteableEntity;
use App\Entity\Trait\TimestampableEntity;
#[ApiResource]
#[Entity(repositoryClass: CartItemRepository::class)]
#[UniqueConstraint(name: 'uniq_cart_product', columns: ['cart_id', 'product_id'])]
#[SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class CartItem
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
    private ?Product $product = null;

    /**
     * @var int|null
     */
    #[Column]
    private ?int $quantity = null;

    /**
     * @return int|null
     */
    public function getId(): ?int
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
}
