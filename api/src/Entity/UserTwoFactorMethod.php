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
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Entity]
#[SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class UserTwoFactorMethod
{
    use SoftDeleteableEntity;
    use TimestampableEntity;

    public const string GET_COLLECTION_USER_TWO_FACTOR_METHOD = 'get:collection:user_two_factor_method';
    public const string GET_ITEM_USER_TWO_FACTOR_METHOD = 'get:item:user_two_factor_method';
    public const string POST_COLLECTION_USER_TWO_FACTOR_METHOD = 'post:collection:user_two_factor_method';

    /**
     * @var Uuid|null
     */
    #[Id]
    #[Column(type: 'uuid', unique: true)]
    #[GeneratedValue(strategy: 'CUSTOM')]
    #[CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups([self::GET_ITEM_USER_TWO_FACTOR_METHOD, self::GET_COLLECTION_USER_TWO_FACTOR_METHOD])]
    private ?Uuid $id = null;

    /**
     * @var User|null
     */
    #[ManyToOne(targetEntity: User::class, inversedBy: 'twoFactorMethods')]
    #[JoinColumn(nullable: false)]
    #[Groups([self::GET_ITEM_USER_TWO_FACTOR_METHOD, self::GET_COLLECTION_USER_TWO_FACTOR_METHOD])]
    private ?User $user = null;

    /**
     * @var string
     */
    #[Column(type: 'string', length: 50)]
    #[Groups([self::POST_COLLECTION_USER_TWO_FACTOR_METHOD, self::GET_ITEM_USER_TWO_FACTOR_METHOD, self::GET_COLLECTION_USER_TWO_FACTOR_METHOD])]
    #[NotBlank(groups: [self::POST_COLLECTION_USER_TWO_FACTOR_METHOD])]
    private string $method;

    /**
     * @var bool
     */
    #[Column(type: 'boolean', options: ['default' => false])]
    #[Groups([self::GET_ITEM_USER_TWO_FACTOR_METHOD, self::GET_COLLECTION_USER_TWO_FACTOR_METHOD])]
    private bool $isActive = false;

    /**
     * @var bool
     */
    #[Column(type: 'boolean')]
    #[Groups([self::POST_COLLECTION_USER_TWO_FACTOR_METHOD, self::GET_ITEM_USER_TWO_FACTOR_METHOD, self::GET_COLLECTION_USER_TWO_FACTOR_METHOD])]
    private bool $isDefault = false;

    /**
     * @var string|null
     */
    #[Column(type: 'string', length: 255, nullable: true)]
    private ?string $secret = null;

    /**
     * @var string|null
     */
    #[Column(type: 'string', length: 255, nullable: true)]
    private ?string $pendingSecret = null;

    /**
     * @var int|null
     */
    #[Column(type: 'integer', nullable: true)]
    private ?int $lastUsedSlice = null;

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
     * @param TwoFactorMethodEnum|string $method
     * @return $this
     */
    public function setMethod(TwoFactorMethodEnum|string $method): self
    {
        $this->method = $method instanceof TwoFactorMethodEnum ? $method->value : $method;
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

    /**
     * @return string|null
     */
    public function getSecret(): ?string
    {
        return $this->secret;
    }

    /**
     * @param string|null $secret
     * @return $this
     */
    public function setSecret(?string $secret): self
    {
        $this->secret = $secret;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPendingSecret(): ?string
    {
        return $this->pendingSecret;
    }

    /**
     * @param string|null $pendingSecret
     * @return $this
     */
    public function setPendingSecret(?string $pendingSecret): self
    {
        $this->pendingSecret = $pendingSecret;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getLastUsedSlice(): ?int
    {
        return $this->lastUsedSlice;
    }

    /**
     * @param int|null $lastUsedSlice
     * @return $this
     */
    public function setLastUsedSlice(?int $lastUsedSlice): self
    {
        $this->lastUsedSlice = $lastUsedSlice;
        return $this;
    }
}
