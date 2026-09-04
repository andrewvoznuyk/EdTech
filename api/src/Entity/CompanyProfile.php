<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Trait\SoftDeleteableEntity;
use App\Entity\Trait\TimestampableEntity;
use App\Service\Interface\UserAwareInterface;
use App\State\MyCompaniesProvider;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\CustomIdGenerator;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Gedmo\Mapping\Annotation\SoftDeleteable;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\Expression;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Entity]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/public/companies',
            paginationEnabled: true,
            paginationItemsPerPage: 10,
            normalizationContext: ['groups' => [CompanyProfile::GET_COLLECTION_COMPANY, File::GET_COLLECTION_FILE]]
        ),
        new Get(
            uriTemplate: '/public/companies/{id}',
            normalizationContext: ['groups' => [CompanyProfile::GET_ITEM_COMPANY, File::GET_COLLECTION_FILE]],
        ),
        new GetCollection(
            uriTemplate: '/my-companies',
            normalizationContext: ['groups' => [CompanyProfile::GET_COLLECTION_COMPANY, File::GET_COLLECTION_FILE]],
            security: "is_granted('ROLE_USER')",
            name: 'my_companies',
            provider: MyCompaniesProvider::class,
        ),
        new Post(
            uriTemplate: '/companies',
            normalizationContext: ['groups' => [CompanyProfile::GET_ITEM_COMPANY, File::GET_COLLECTION_FILE]],
            denormalizationContext: ['groups' => [CompanyProfile::POST_COLLECTION_COMPANY]],
            security: "is_granted('ROLE_USER')",
        ),
        new Patch(
            uriTemplate: '/companies/{id}',
            normalizationContext: ['groups' => [CompanyProfile::GET_ITEM_COMPANY, File::GET_COLLECTION_FILE]],
            denormalizationContext: ['groups' => [CompanyProfile::PATCH_COLLECTION_COMPANY]],
            security: "is_granted('" .User::ROLE_ADMIN. "') or (is_granted('" .User::ROLE_USER. "') and object.getUser() == user)"
        ),
        new Delete(
            uriTemplate: '/companies/{id}',
            security: "is_granted('" .User::ROLE_ADMIN. "') or object.getUser() == user"
        )
    ]
)]
#[ApiFilter(SearchFilter::class, properties: ['id' => 'exact', 'companyName' => 'partial',])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'companyName', 'edrpou', 'createdAt', 'updatedAt', 'deletedAt'])]
#[SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
#[UniqueEntity(fields: ['companyName'], message: 'company.name_unique')]
#[UniqueEntity(fields: ['edrpou'], message: 'company.edrpou_unique')]
#[UniqueEntity(fields: ['rnokpp'], message: 'company.rnokpp_unique')]
#[Expression("(this.getEdrpou() != null and this.getRnokpp() == null) or (this.getEdrpou() == null and this.getRnokpp() != null)", message: "company.edrpou_rnokpp_conflict")]
class CompanyProfile implements UserAwareInterface
{
    use TimestampableEntity;
    use SoftDeleteableEntity;

    public const string GET_COLLECTION_COMPANY = 'get:collection:company';
    public const string GET_ITEM_COMPANY = 'get:item:company';
    public const string POST_COLLECTION_COMPANY = 'post:collection:company';
    public const string PATCH_COLLECTION_COMPANY = 'patch:item:company';

    /**
     * @var Uuid|null
     */
    #[Id]
    #[Column(type: 'uuid', unique: true)]
    #[GeneratedValue(strategy: 'CUSTOM')]
    #[CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups([User::GET_COLLECTION_USER, CompanyProfile::GET_COLLECTION_COMPANY, CompanyProfile::GET_ITEM_COMPANY])]
    private ?Uuid $id = null;

    /**
     * @var File|null
     */
    #[ManyToOne(targetEntity: File::class)]
    #[JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups([CompanyProfile::GET_COLLECTION_COMPANY, CompanyProfile::GET_ITEM_COMPANY, CompanyProfile::POST_COLLECTION_COMPANY, CompanyProfile::PATCH_COLLECTION_COMPANY])]
    private ?File $logo = null;

    /**
     * @var User|null
     */
    #[ManyToOne(inversedBy: 'companyProfile')]
    #[JoinColumn(nullable: false)]
    #[Groups([CompanyProfile::GET_ITEM_COMPANY])]
    private ?User $user = null;

    /**
     * @var File|null
     */
    #[ManyToOne(targetEntity: File::class)]
    #[JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups([CompanyProfile::GET_COLLECTION_COMPANY, CompanyProfile::GET_ITEM_COMPANY, CompanyProfile::POST_COLLECTION_COMPANY, CompanyProfile::PATCH_COLLECTION_COMPANY])]
    private ?File $wallpaper = null;

    /**
     * @var string|null
     */
    #[NotBlank]
    #[Column(length: 500, nullable: true)]
    #[Groups([CompanyProfile::GET_COLLECTION_COMPANY, CompanyProfile::GET_ITEM_COMPANY, CompanyProfile::POST_COLLECTION_COMPANY, CompanyProfile::PATCH_COLLECTION_COMPANY])]
    private ?string $actualAddress = null;

    /**
     * @var string|null
     */
    #[NotBlank]
    #[Length(min: 3, max: 255)]
    #[Column(length: 255, unique: true)]
    #[Groups([CompanyProfile::GET_COLLECTION_COMPANY, CompanyProfile::GET_ITEM_COMPANY, CompanyProfile::POST_COLLECTION_COMPANY, CompanyProfile::PATCH_COLLECTION_COMPANY])]
    private ?string $companyName = null;

    /**
     * @var string|null
     */
    #[Length(exactly: 8, exactMessage: 'company.edrpou_length')]
    #[Column(length: 50, unique: true, nullable: true)]
    #[Groups([CompanyProfile::GET_COLLECTION_COMPANY, CompanyProfile::GET_ITEM_COMPANY, CompanyProfile::POST_COLLECTION_COMPANY, CompanyProfile::PATCH_COLLECTION_COMPANY])]
    private ?string $edrpou = null;

    /**
     * @var string|null
     */
    #[PhoneNumber(message: 'company.phone_invalid')]
    #[Column(length: 20, nullable: true)]
    #[Groups([CompanyProfile::GET_COLLECTION_COMPANY, CompanyProfile::GET_ITEM_COMPANY, CompanyProfile::POST_COLLECTION_COMPANY, CompanyProfile::PATCH_COLLECTION_COMPANY])]
    private ?string $phone = null;

    /**
     * @var string|null
     */
    #[Length(min: 3, max: 255)]
    #[Column(length: 255, nullable: true)]
    #[Groups([CompanyProfile::GET_COLLECTION_COMPANY, CompanyProfile::GET_ITEM_COMPANY, CompanyProfile::POST_COLLECTION_COMPANY, CompanyProfile::PATCH_COLLECTION_COMPANY])]
    private ?string $position = null;

    /**
     * @var string|null
     */
    #[NotBlank]
    #[Column(length: 500, nullable: true)]
    #[Groups([CompanyProfile::GET_COLLECTION_COMPANY, CompanyProfile::GET_ITEM_COMPANY, CompanyProfile::POST_COLLECTION_COMPANY, CompanyProfile::PATCH_COLLECTION_COMPANY])]
    private ?string $registrationAddress = null;

    /**
     * @var string|null
     */
    #[Length(exactly: 10, exactMessage: 'company.rnokpp_length')]
    #[Column(length: 10, unique: true, nullable: true)]
    #[Groups([CompanyProfile::GET_COLLECTION_COMPANY, CompanyProfile::GET_ITEM_COMPANY, CompanyProfile::POST_COLLECTION_COMPANY, CompanyProfile::PATCH_COLLECTION_COMPANY])]
    private ?string $rnokpp = null;

    /**
     * @return Uuid|null
     */
    public function getId(): ?Uuid
    {
        return $this->id;
    }

    /**
     * @return File|null
     */
    public function getLogo(): ?File
    {
        return $this->logo;
    }

    /**
     * @param File|null $logo
     * @return $this
     */
    public function setLogo(?File $logo): self
    {
        $this->logo = $logo;

        return $this;
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
     * @return File|null
     */
    public function getWallpaper(): ?File
    {
        return $this->wallpaper;
    }

    /**
     * @param File|null $wallpaper
     * @return $this
     */
    public function setWallpaper(?File $wallpaper): self
    {
        $this->wallpaper = $wallpaper;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getActualAddress(): ?string
    {
        return $this->actualAddress;
    }

    /**
     * @param string|null $actualAddress
     * @return void
     */
    public function setActualAddress(?string $actualAddress): void
    {
        $this->actualAddress = $actualAddress;
    }

    /**
     * @return string|null
     */
    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    /**
     * @param string $companyName
     * @return $this
     */
    public function setCompanyName(string $companyName): self
    {
        $this->companyName = $companyName;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getEdrpou(): ?string
    {
        return $this->edrpou;
    }

    /**
     * @param string|null $edrpou
     * @return $this
     */
    public function setEdrpou(?string $edrpou): self
    {
        $this->edrpou = $edrpou === '' ? null : $edrpou;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getPhone(): ?string
    {
        return $this->phone;
    }

    /**
     * @param string|null $phone
     * @return void
     */
    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    /**
     * @return string|null
     */
    public function getPosition(): ?string
    {
        return $this->position;
    }

    /**
     * @param string|null $position
     * @return void
     */
    public function setPosition(?string $position): void
    {
        $this->position = $position;
    }

    /**
     * @return string|null
     */
    public function getRegistrationAddress(): ?string
    {
        return $this->registrationAddress;
    }

    /**
     * @param string|null $registrationAddress
     * @return void
     */
    public function setRegistrationAddress(?string $registrationAddress): void
    {
        $this->registrationAddress = $registrationAddress;
    }

    /**
     * @return string|null
     */
    public function getRnokpp(): ?string
    {
        return $this->rnokpp;
    }

    /**
     * @param string|null $rnokpp
     * @return void
     */
    public function setRnokpp(?string $rnokpp): void
    {
        $this->rnokpp = $rnokpp === '' ? null : $rnokpp;
    }

    /**
     * @return DateTime|null
     */
    #[Groups([CompanyProfile::GET_COLLECTION_COMPANY, CompanyProfile::GET_ITEM_COMPANY])]
    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return DateTime|null
     */
    #[Groups([CompanyProfile::GET_COLLECTION_COMPANY, CompanyProfile::GET_ITEM_COMPANY])]
    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
