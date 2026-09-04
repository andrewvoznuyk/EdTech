<?php

namespace App\Entity;

use App\Entity\Trait\SoftDeleteableEntity;
use App\Enum\TwoFactorMethodEnum;
use App\Entity\Trait\TimestampableEntity;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\CustomIdGenerator;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Gedmo\Mapping\Annotation\SoftDeleteable;
use Symfony\Component\Uid\Uuid;

#[Entity]
#[SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class UserTwoFactorMethod
{
    use SoftDeleteableEntity;
    use TimestampableEntity;

    /**
     * @var Uuid|null
     */
    #[Id]
    #[Column(type: 'uuid', unique: true)]
    #[GeneratedValue(strategy: 'CUSTOM')]
    #[CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /**
     * @var User|null
     */
    #[ManyToOne(targetEntity: User::class, inversedBy: 'twoFactorMethods')]
    #[JoinColumn(nullable: false)]
    private ?User $user = null;

    /**
     * @var string
     */
    #[Column(type: 'string', length: 50)]
    private string $method;


    /**
     * @var bool
     */
    #[Column(type: 'boolean', options: ['default' => false])]
    private bool $isActive = false;

    /**
     * @var bool
     */
    #[Column(type: 'boolean')]
    private bool $isDefault = false;

    /**
     * @return Uuid|null
     */
    public function getId(): ?Uuid
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
    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return TwoFactorMethodEnum
     */
    public function getMethod(): TwoFactorMethodEnum
    {
        return TwoFactorMethodEnum::from($this->method);
    }

    /**
     * @param TwoFactorMethodEnum $method
     * @return $this
     */
    public function setMethod(TwoFactorMethodEnum $method): self
    {
        $this->method = $method->value;
        return $this;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * @param bool $isActive
     * @return $this
     */
    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    /**
     * @param bool $isDefault
     * @return $this
     */
    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;
        return $this;
    }
}
