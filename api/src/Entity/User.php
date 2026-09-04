<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Action\RegisterUserAction;
use App\Action\Resend2FAAction;
use App\Action\ResendVerificationAction;
use App\Action\VerifyEmailAction;
use App\Enum\TwoFactorMethodEnum;
use App\Entity\Trait\SoftDeleteableEntity;
use App\Entity\Trait\TimestampableEntity;
use App\Repository\UserRepository;
use DateTime;
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
use Doctrine\ORM\Mapping\UniqueConstraint;
use Gedmo\Mapping\Annotation\SoftDeleteable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Valid;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: "/users",
            paginationEnabled: true,
            paginationItemsPerPage: 10,
            paginationMaximumItemsPerPage: 100,
            paginationClientItemsPerPage: true,
            normalizationContext: ['groups' => [User::GET_COLLECTION_USER]],
            security: "is_granted('" .USER::ROLE_ADMIN. "')",
        ),
        new Get(
            normalizationContext: ['groups' => [User::GET_ITEM_USER, CompanyProfile::GET_ITEM_COMPANY]],
            security: "is_granted('" .User::ROLE_ADMIN. "') or is_granted('" .User::ROLE_USER. "') and object == user",
        ),
        new Post(
            uriTemplate: '/users/register',
            normalizationContext: ['groups' => [User::GET_ITEM_USER]],
            denormalizationContext: ['groups' => [User::POST_COLLECTION_USER]],
            processor: RegisterUserAction::class
        ),
        new Post(
            uriTemplate: '/users/verify-email',
            denormalizationContext: ['groups' => [User::POST_VERIFY_EMAIL]],
            validationContext: ['groups' => [User::POST_VERIFY_EMAIL]],
            output: false,
            processor: VerifyEmailAction::class,
        ),
        new Post(
            uriTemplate: '/users/resend-verification',
            status: 202,
            denormalizationContext: ['groups' => [User::POST_RESEND_VERIFICATION_USER]],
            validationContext: ['groups' => [User::POST_RESEND_VERIFICATION_USER]],
            output: false,
            processor: ResendVerificationAction::class,
        ),
        new Post(
            uriTemplate: '/users/resend-2fa',
            status: 202,
            denormalizationContext: ['groups' => [User::POST_RESET_2FA_USER]],
            validationContext: ['groups' => [User::POST_RESET_2FA_USER]],
            output: false,
            processor: Resend2FAAction::class,
        ),
        new Patch(
            uriTemplate: '/users/{id}',
            normalizationContext: ['groups' => [User::GET_COLLECTION_USER]],
            denormalizationContext: ['groups' => [User::PATCH_COLLECTION_USER]],
            security: "is_granted('" .User::ROLE_ADMIN. "') or (is_granted('" .User::ROLE_USER. "') and object == user)",
        ),
        new Delete(
            security: "is_granted('" .User::ROLE_ADMIN. "') or is_granted('" .User::ROLE_USER. "') and object == user"
        ),
    ],
)]
#[Entity(repositoryClass: UserRepository::class)]
#[UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'user.email_unique')]
#[SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use SoftDeleteableEntity;
    use TimestampableEntity;

    public const string ROLE_USER = 'ROLE_USER';
    public const string ROLE_ADMIN = 'ROLE_ADMIN';

    public const string GET_COLLECTION_USER = 'get:collection:user';
    public const string GET_ITEM_USER = 'get:item:user';
    public const string POST_COLLECTION_USER = 'post:collection:user';
    public const string PATCH_COLLECTION_USER = 'patch:item:user:profile';
    public const string POST_RESET_PASSWORD_USER = 'user:reset-password';
    public const string POST_FORGOT_PASSWORD_USER = 'user:forgot-password';
    public const string POST_RESET_2FA_USER = 'user:resend-2fa';
    public const string POST_VERIFY_EMAIL = 'post:user:verify-email';
    public const string POST_RESEND_VERIFICATION_USER = 'post:user:resend-verification';
    public const string POST_RESTORE_REQUEST = 'post:user:restore-request';
    public const string POST_RESTORE_CONFIRM = 'post:user:restore-confirm';
    /**
     * @var Uuid|null
     */
    #[Id]
    #[Column(type: 'uuid', unique: true)]
    #[GeneratedValue(strategy: 'CUSTOM')]
    #[CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups([User::GET_ITEM_USER,  User::GET_COLLECTION_USER, CompanyProfile::GET_COLLECTION_COMPANY, CompanyProfile::GET_ITEM_COMPANY])]
    private ?Uuid $id = null;

    /**
     * @var Collection<int, Asset>
     */
    #[OneToMany(targetEntity: Asset::class, mappedBy: 'user')]
    private Collection $assets;

    /**
     * @var Cart|null
     */
    #[OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?Cart $cart = null;

    /**
     * @var Collection|null
     */
    #[OneToMany(targetEntity: CompanyProfile::class, mappedBy: 'user')]
    #[Groups([User::GET_ITEM_USER, CompanyProfile::GET_ITEM_COMPANY])]
    private ?Collection $companyProfile = null;

    /**
     * @var string
     */
    #[Column(type: 'string', length: 5, options: ['default' => 'en'])]
    #[Groups([User::GET_ITEM_USER, User::GET_COLLECTION_USER, User::PATCH_COLLECTION_USER])]
    private string $locale = 'en';

    /**
     * @var Collection<int, Order>
     */
    #[OneToMany(targetEntity: Order::class, mappedBy: 'user')]
    private Collection $orders;

    /**
     * @var UserProfile|null
     */
    #[Valid]
    #[OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    #[Groups([User::GET_ITEM_USER, User::GET_COLLECTION_USER, User::PATCH_COLLECTION_USER, User::POST_COLLECTION_USER])]
    private ?UserProfile $userProfile = null;

    /**
     * @var Collection<int, UserTwoFactorMethod>
     */
    #[OneToMany(targetEntity: UserTwoFactorMethod::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $twoFactorMethods;

    /**
     * @var string|null
     */
    #[Column(length: 6, nullable: true)]
    private ?string $authCode = null;

    /**
     * @var string|null
     */
    #[Column(length: 180)]
    #[Groups([User::GET_ITEM_USER, User::GET_COLLECTION_USER, User::POST_COLLECTION_USER, CompanyProfile::GET_COLLECTION_COMPANY, CompanyProfile::GET_ITEM_COMPANY, User::POST_FORGOT_PASSWORD_USER, User::POST_RESET_2FA_USER, User::POST_RESEND_VERIFICATION_USER, User::POST_RESTORE_REQUEST])]
    #[NotBlank(groups: [User::POST_FORGOT_PASSWORD_USER, User::POST_RESET_2FA_USER, User::POST_RESTORE_REQUEST])]
    #[Email]
    private ?string $email = null;

    /**
     * @var string|null
     */
    #[Column(nullable: true)]
    private ?string $password = null;

    /**
     * @var string|null
     */
    #[Groups([User::POST_COLLECTION_USER, User::POST_RESET_PASSWORD_USER])]
    #[NotBlank(groups: [User::POST_COLLECTION_USER, User::POST_RESET_PASSWORD_USER])]
    #[Length(min: 8, groups: [User::POST_COLLECTION_USER, User::POST_RESET_PASSWORD_USER])]
    #[SerializedName('password')]
    private ?string $plainPassword = null;

    /**
     * @var string|null
     */
    #[Groups([User::POST_RESET_PASSWORD_USER])]
    #[NotBlank(groups: [User::POST_RESET_PASSWORD_USER])]
    public ?string $resetToken = null;

    /**
     * @var string|null
     */
    #[Groups([User::POST_VERIFY_EMAIL, User::POST_RESTORE_CONFIRM])]
    #[NotBlank(groups: [User::POST_VERIFY_EMAIL, User::POST_RESTORE_CONFIRM])]
    public ?string $token = null;

    /**
     * @var array
     */
    #[Column]
    #[Groups([User::GET_ITEM_USER, CompanyProfile::GET_COLLECTION_COMPANY, CompanyProfile::GET_ITEM_COMPANY, User::GET_COLLECTION_USER])]
    #[ApiProperty(security: "is_granted('". User::ROLE_ADMIN ."') or object == user")]
    private array $roles = [];

    /**
     * @var DateTimeImmutable|null
     */
    #[Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $authCodeExpiresAt = null;

    /**
     * @var bool
     */
    #[Column(type: 'boolean')]
    #[Groups([User::GET_ITEM_USER, CompanyProfile::GET_COLLECTION_COMPANY, CompanyProfile::GET_ITEM_COMPANY, User::GET_COLLECTION_USER, User::PATCH_COLLECTION_USER])]
    #[ApiProperty(security: "is_granted('ROLE_ADMIN')")]
    private bool $isActive = true;

    /**
     * @var bool
     */
    #[Column(type: 'boolean')]
    #[Groups([User::GET_ITEM_USER, CompanyProfile::GET_COLLECTION_COMPANY, CompanyProfile::GET_ITEM_COMPANY, User::GET_COLLECTION_USER])]
    private bool $isVerified = false;

    /**
     * @var DateTimeImmutable|null
     */
    #[Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $lastVerificationEmailSentAt = null;

    /**
     *
     */
    public function __construct()
    {
        $this->orders = new ArrayCollection();
        $this->assets = new ArrayCollection();
        $this->companyProfile = new ArrayCollection();
        $this->twoFactorMethods = new ArrayCollection();

        $this->roles = [User::ROLE_USER];
    }

    /**
     * @return Uuid|null
     */
    public function getId(): ?Uuid
    {
        return $this->id;
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
            $asset->setUser($this);
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
            if ($asset->getUser() === $this) {
                $asset->setUser(null);
            }
        }

        return $this;
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
        if ($cart === null && $this->cart !== null) {
            $this->cart->setUser(null);
        }

        if ($cart !== null && $cart->getUser() !== $this) {
            $cart->setUser($this);
        }

        $this->cart = $cart;

        return $this;
    }

    /**
     * @param CompanyProfile|null $companyProfile
     * @return $this
     */
    public function setCompanyProfile(?CompanyProfile $companyProfile): self
    {
        $this->companyProfile = $companyProfile;

        if ($companyProfile !== null && $companyProfile->getUser() !== $this) {
            $companyProfile->setUser($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, CompanyProfile>
     */
    public function getCompanyProfile(): Collection
    {
        return $this->companyProfile;
    }

    public function addCompanyProfile(CompanyProfile $companyProfile): self
    {
        if (!$this->companyProfile->contains($companyProfile)) {
            $this->companyProfile->add($companyProfile);
            $companyProfile->setUser($this);
        }

        return $this;
    }

    public function removeCompanyProfile(CompanyProfile $companyProfile): self
    {
        if ($this->companyProfile->removeElement($companyProfile)) {
            if ($companyProfile->getUser() === $this) {
                $companyProfile->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @param string $locale
     * @return $this
     */
    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @return Collection<int, Order>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    /**
     * @param Order $order
     * @return $this
     */
    public function addOrder(Order $order): self
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setUser($this);
        }

        return $this;
    }

    /**
     * @param Order $order
     * @return $this
     */
    public function removeOrder(Order $order): self
    {
        if ($this->orders->removeElement($order)) {
            if ($order->getUser() === $this) {
                $order->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return UserProfile|null
     */
    public function getUserProfile(): ?UserProfile
    {
        return $this->userProfile;
    }

    /**
     * @param UserProfile|null $userProfile
     * @return $this
     */
    public function setUserProfile(?UserProfile $userProfile): self
    {
        $this->userProfile = $userProfile;

        if ($userProfile !== null && $userProfile->getUser() !== $this) {
            $userProfile->setUser($this);
        }

        return $this;
    }

    /**
     * @return string|null
     */
    public function getAuthCode(): ?string
    {
        return $this->authCode;
    }

    /**
     * @param string|null $authCode
     * @return $this
     */
    public function setAuthCode(?string $authCode): self
    {
        $this->authCode = $authCode;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @param string $email
     * @return $this
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return string
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @return string|null
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @param string $password
     * @return $this
     */
    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    /**
     * @param string|null $plainPassword
     * @return $this
     */
    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    /**
     * @return void
     */
    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    /**
     * @return string|null
     */
    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    /**
     * @param string|null $resetToken
     * @return void
     */
    public function setResetToken(?string $resetToken): void
    {
        $this->resetToken = $resetToken;
    }

    /**
     * @return array|string[]
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = self::ROLE_USER;

        return array_unique($roles);
    }

    /**
     * @param array $roles
     * @return $this
     */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getAuthCodeExpiresAt(): ?DateTimeImmutable
    {
        return $this->authCodeExpiresAt;
    }

    /**
     * @param DateTimeImmutable|null $authCodeExpiresAt
     * @return $this
     */
    public function setAuthCodeExpiresAt(?DateTimeImmutable $authCodeExpiresAt): self
    {
        $this->authCodeExpiresAt = $authCodeExpiresAt;

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
    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    /**
     * @return Collection<int, UserTwoFactorMethod>
     */
    public function getTwoFactorMethods(): Collection
    {
        return $this->twoFactorMethods;
    }

    public function addTwoFactorMethod(UserTwoFactorMethod $method): self
    {
        if (!$this->twoFactorMethods->contains($method)) {
            $this->twoFactorMethods->add($method);
            $method->setUser($this);
        }

        return $this;
    }

    public function removeTwoFactorMethod(UserTwoFactorMethod $method): self
    {
        if ($this->twoFactorMethods->removeElement($method)) {
            if ($method->getUser() === $this) {
                $method->setUser(null);
            }
        }

        return $this;
    }

    public function has2faEnabled(): bool
    {
        foreach ($this->twoFactorMethods as $method) {
            if ($method->isActive()) {
                return true;
            }
        }
        return false;
    }

    public function getDefaultTwoFactorMethod(): ?TwoFactorMethodEnum
    {
        if ($this->twoFactorMethods->isEmpty()) {
            return null;
        }

        $activeMethods = [];
        foreach ($this->twoFactorMethods as $method) {
            if ($method->isActive()) {
                if ($method->isDefault()) {
                    return $method->getMethod();
                }
                $activeMethods[] = $method;
            }
        }

        if (count($activeMethods) > 0) {
            return $activeMethods[0]->getMethod();
        }

        return null;
    }

    /**
     * @return array<string>
     */
    public function getActiveTwoFactorMethods(): array
    {
        $methods = [];
        foreach ($this->twoFactorMethods as $method) {
            if ($method->isActive()) {
                $methods[] = $method->getMethod()->value;
            }
        }
        return array_unique($methods);
    }

    /**
     * @param bool $isVerified
     * @return void
     */
    public function setIsVerified(bool $isVerified): void
    {
        $this->isVerified = $isVerified;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getLastVerificationEmailSentAt(): ?DateTimeImmutable
    {
        return $this->lastVerificationEmailSentAt;
    }

    /**
     * @param DateTimeImmutable|null $lastVerificationEmailSentAt
     * @return $this
     */
    public function setLastVerificationEmailSentAt(?DateTimeImmutable $lastVerificationEmailSentAt): self
    {
        $this->lastVerificationEmailSentAt = $lastVerificationEmailSentAt;

        return $this;
    }

    /**
     * @return DateTime|null
     */
    #[Groups([User::GET_ITEM_USER, User::GET_COLLECTION_USER])]
    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return DateTime|null
     */
    #[Groups([User::GET_ITEM_USER, User::GET_COLLECTION_USER])]
    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
