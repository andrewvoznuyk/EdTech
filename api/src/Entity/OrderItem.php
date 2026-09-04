<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\OrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Gedmo\Mapping\Annotation\SoftDeleteable;
use App\Entity\Trait\SoftDeleteableEntity;
use App\Entity\Trait\TimestampableEntity;

#[ApiResource]
#[Entity(repositoryClass: OrderItemRepository::class)]
#[SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class OrderItem
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
     * @var Order|null
     */
    #[ManyToOne(inversedBy: 'orderItems')]
    #[JoinColumn(onDelete: 'CASCADE')]
    private ?Order $customerOrder = null;

    /**
     * @var Product|null
     */
    #[ManyToOne(inversedBy: 'orderItems')]
    private ?Product $product = null;

    /**
     * @var Asset|null
     */
    #[ManyToOne(inversedBy: 'orderItems')]
    #[JoinColumn(nullable: false)]
    private ?Asset $asset = null;

    /**
     * @var int|null
     */
    #[Column]
    private ?int $quantity = null;

    /**
     * @var string|null
     */
    #[Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private ?string $totalPrice = null;

    /**
     * @return int|null
     */
    public function getId(): ?int
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
}
