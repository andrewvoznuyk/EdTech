<?php

namespace App\Entity;

use App\Repository\PaymentsRepository;
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

#[Entity(repositoryClass: PaymentsRepository::class)]
#[SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class Payments
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
    #[ManyToOne(inversedBy: 'payments')]
    #[JoinColumn(onDelete: 'CASCADE')]
    private ?Order $customerOrder = null;

    /**
     * @var string|null
     */
    #[Column(length: 255)]
    private ?string $transactionID = null;

    /**
     * @var string|null
     */
    #[Column(length: 255)]
    private ?string $provider = null;

    /**
     * @var string|null
     */
    #[Column(length: 255)]
    private ?string $currency = null;

    /**
     * @var string|null
     */
    #[Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private ?string $price = null;

    /**
     * @var int|null
     */
    #[Column]
    private ?int $status = null;

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
     * @return string|null
     */
    public function getTransactionID(): ?string
    {
        return $this->transactionID;
    }

    /**
     * @param string $transactionID
     * @return $this
     */
    public function setTransactionID(string $transactionID): static
    {
        $this->transactionID = $transactionID;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * @param string $provider
     * @return $this
     */
    public function setProvider(string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    /**
     * @param string $currency
     * @return $this
     */
    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

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
}
