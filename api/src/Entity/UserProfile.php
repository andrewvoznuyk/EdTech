<?php

namespace App\Entity;

use App\Entity\Trait\SoftDeleteableEntity;
use App\Entity\Trait\TimestampableEntity;
use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\CustomIdGenerator;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\OneToOne;
use Gedmo\Mapping\Annotation\SoftDeleteable;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\Iban;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\Regex;

#[Entity]
#[SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
#[UniqueEntity(fields: ['rnokpp'], message: 'validator.profile.unique_rnokpp', ignoreNull: true)]
#[UniqueEntity(fields: ['iban'], message: 'validator.profile.unique_iban', ignoreNull: true)]
class UserProfile
{
    use TimestampableEntity;
    use SoftDeleteableEntity;

    /**
     * @var Uuid|null
     */
    #[Id]
    #[Column(type: 'uuid', unique: true)]
    #[GeneratedValue(strategy: 'CUSTOM')]
    #[CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups([User::GET_ITEM_USER,  User::GET_COLLECTION_USER])]
    private ?Uuid $id = null;

    /**
     * @var File|null
     */
    #[OneToOne(targetEntity: File::class)]
    #[JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups([User::GET_ITEM_USER,  User::GET_COLLECTION_USER, User::POST_COLLECTION_USER, User::PATCH_COLLECTION_USER])]
    private ?File $avatar = null;

    /**
     * @var User|null
     */
    #[OneToOne(inversedBy: 'userProfile')]
    #[JoinColumn(nullable: false)]
    private ?User $user = null;

    /**
     * @var string|null
     */
    #[Column(length: 500, nullable: true)]
    #[Groups([User::GET_ITEM_USER,  User::GET_COLLECTION_USER, User::POST_COLLECTION_USER, User::PATCH_COLLECTION_USER])]
    private ?string $actualAddress = null;

    /**
     * @var string|null
     */
    #[Column(length: 255, nullable: true)]
    #[Groups([User::GET_ITEM_USER,  User::GET_COLLECTION_USER, User::POST_COLLECTION_USER, User::PATCH_COLLECTION_USER])]
    private ?string $firstName = null;

    /**
     * @var string|null
     */
    #[Column(length: 20, nullable: true)]
    #[Groups([User::GET_ITEM_USER,  User::GET_COLLECTION_USER, User::POST_COLLECTION_USER, User::PATCH_COLLECTION_USER])]
    private ?string $gender = null;

    /**
     * @var string|null
     */
    #[Iban(message: 'validator.profile.invalid_iban')]
    #[Column(length: 34, unique: true, nullable: true)]
    #[Groups([User::GET_ITEM_USER, User::POST_COLLECTION_USER])]
    private ?string $iban = null;

    /**
     * @var string|null
     */
    #[Column(length: 255, nullable: true)]
    #[Groups([User::GET_ITEM_USER,  User::GET_COLLECTION_USER, User::POST_COLLECTION_USER, User::PATCH_COLLECTION_USER])]
    private ?string $lastName = null;

    /**
     * @var string|null
     */
    #[Column(length: 20, nullable: true)]
    #[Groups([User::GET_ITEM_USER, User::POST_COLLECTION_USER])]
    private ?string $passportNumber = null;

    /**
     * @var string|null
     */
    #[Column(length: 255, nullable: true)]
    #[Groups([User::GET_ITEM_USER,  User::GET_COLLECTION_USER, User::POST_COLLECTION_USER, User::PATCH_COLLECTION_USER])]
    private ?string $patronymic = null;

    /**
     * @var string|null
     */
    #[PhoneNumber(message: 'validator.profile.invalid_phone')]
    #[Column(length: 20, nullable: true)]
    #[Groups([User::GET_ITEM_USER,  User::GET_COLLECTION_USER, User::POST_COLLECTION_USER, User::PATCH_COLLECTION_USER])]
    private ?string $phone = null;

    /**
     * @var string|null
     */
    #[Column(length: 255, nullable: true)]
    #[Groups([User::GET_ITEM_USER,  User::GET_COLLECTION_USER, User::POST_COLLECTION_USER, User::PATCH_COLLECTION_USER])]
    private ?string $position = null;

    /**
     * @var string|null
     */
    #[Column(length: 500, nullable: true)]
    #[Groups([User::GET_ITEM_USER,  User::GET_COLLECTION_USER, User::POST_COLLECTION_USER, User::PATCH_COLLECTION_USER])]
    private ?string $registrationAddress = null;

    /**
     * @var string|null
     */
    #[Regex(pattern: '/^\d{10}$/', message: 'validator.profile.invalid_rnokpp')]
    #[Column(length: 10, unique: true, nullable: true)]
    #[Groups([User::GET_ITEM_USER,  User::GET_COLLECTION_USER, User::POST_COLLECTION_USER, User::PATCH_COLLECTION_USER])]
    private ?string $rnokpp = null;

    /**
     * @var DateTimeImmutable|null
     */
    #[LessThanOrEqual('today')]
    #[Column(type: 'date_immutable', nullable: true)]
    #[Groups([User::GET_ITEM_USER,  User::GET_COLLECTION_USER, User::POST_COLLECTION_USER, User::PATCH_COLLECTION_USER])]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'U'])]
    private ?DateTimeImmutable $dateOfBirth = null;

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
    public function getAvatar(): ?File
    {
        return $this->avatar;
    }

    /**
     * @param File|null $avatar
     * @return $this
     */
    public function setAvatar(?File $avatar): self
    {
        $this->avatar = $avatar;

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
     * @return string|null
     */
    public function getActualAddress(): ?string
    {
        return $this->actualAddress;
    }

    /**
     * @param string|null $actualAddress
     * @return $this
     */
    public function setActualAddress(?string $actualAddress): self
    {
        $this->actualAddress = $actualAddress;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    /**
     * @param string|null $firstName
     * @return $this
     */
    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getGender(): ?string
    {
        return $this->gender;
    }

    /**
     * @param string|null $gender
     * @return $this
     */
    public function setGender(?string $gender): self
    {
        $this->gender = $gender;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getIban(): ?string
    {
        return $this->iban;
    }

    /**
     * @param string|null $iban
     * @return $this
     */
    public function setIban(?string $iban): self
    {
        $this->iban = $iban;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    /**
     * @param string|null $lastName
     * @return $this
     */
    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getPassportNumber(): ?string
    {
        return $this->passportNumber;
    }

    /**
     * @param string|null $passportNumber
     * @return $this
     */
    public function setPassportNumber(?string $passportNumber): self
    {
        $this->passportNumber = $passportNumber;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getPatronymic(): ?string
    {
        return $this->patronymic;
    }

    /**
     * @param string|null $patronymic
     * @return $this
     */
    public function setPatronymic(?string $patronymic): self
    {
        $this->patronymic = $patronymic;

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
     * @return $this
     */
    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
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
     * @return $this
     */
    public function setPosition(?string $position): self
    {
        $this->position = $position;

        return $this;
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
     * @return $this
     */
    public function setRegistrationAddress(?string $registrationAddress): self
    {
        $this->registrationAddress = $registrationAddress;

        return $this;
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
     * @return $this
     */
    public function setRnokpp(?string $rnokpp): self
    {
        $this->rnokpp = $rnokpp;

        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getDateOfBirth(): ?DateTimeImmutable
    {
        return $this->dateOfBirth;
    }

    /**
     * @param DateTimeImmutable|null $dateOfBirth
     * @return $this
     */
    public function setDateOfBirth(?DateTimeImmutable $dateOfBirth): self
    {
        $this->dateOfBirth = $dateOfBirth;

        return $this;
    }
}
