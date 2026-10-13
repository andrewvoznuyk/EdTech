<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Trait\SoftDeleteableEntity;
use App\Entity\Trait\TimestampableEntity;
use App\Enum\FileStatus;
use App\Enum\FileType;
use App\Enum\MimeType;
use App\Repository\FileRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\CustomIdGenerator;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Gedmo\Mapping\Annotation\SoftDeleteable;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

#[ApiResource(
    operations: [
        new Get(
            normalizationContext: ['groups' => [File::GET_ITEM_FILE]],
            security: "is_granted('" . User::ROLE_ADMIN . "')"
        ),
        new GetCollection(
            normalizationContext: ['groups' => [File::GET_COLLECTION_FILE]],
            security: "is_granted('" . User::ROLE_ADMIN . "')"
        ),
    ],
    security: "is_granted('" . User::ROLE_ADMIN . "')"
)]
#[Entity(repositoryClass: FileRepository::class)]
#[SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class File
{
    use TimestampableEntity;
    use SoftDeleteableEntity;

    public const string GET_COLLECTION_FILE = 'get:collection:file';
    public const string GET_ITEM_FILE = 'get:item:file';
    public const string POST_COLLECTION_FILE = 'post:collection:file';
    public const string PATCH_ITEM_FILE = 'patch:item:file';
    public const string PATCH_COLLECTION_FILE = self::PATCH_ITEM_FILE;

    /**
     * @var Uuid|null
     */
    #[Id]
    #[Column(type: 'uuid', unique: true)]
    #[GeneratedValue(strategy: 'CUSTOM')]
    #[CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups([File::GET_ITEM_FILE, File::GET_COLLECTION_FILE])]
    private ?Uuid $id = null;

    /**
     * @var User|null
     */
    #[NotNull]
    #[ManyToOne(inversedBy: 'files')]
    #[JoinColumn(nullable: false)]
    #[Groups([File::GET_ITEM_FILE])]
    private ?User $user = null;

    /**
     * @var string|null
     */
    #[NotBlank]
    #[Column(length: 255)]
    #[Groups([File::GET_ITEM_FILE, File::GET_COLLECTION_FILE])]
    private ?string $fileKey = null;

    /**
     * @var string|null
     */
    #[NotBlank]
    #[Column(length: 255)]
    #[Groups([File::GET_ITEM_FILE, File::GET_COLLECTION_FILE, File::POST_COLLECTION_FILE, File::PATCH_ITEM_FILE])]
    private ?string $originalName = null;

    /**
     * @var MimeType|null
     */
    #[NotBlank]
    #[Column(enumType: MimeType::class)]
    #[Groups([File::GET_ITEM_FILE, File::GET_COLLECTION_FILE, File::POST_COLLECTION_FILE, File::PATCH_ITEM_FILE])]
    private ?MimeType $mimeType = null;

    /**
     * @var int|null
     */
    #[NotNull]
    #[PositiveOrZero]
    #[LessThanOrEqual(value: 5242880, message: 'validator.file.size_exceeded')]
    #[Column]
    #[Groups([File::GET_ITEM_FILE, File::GET_COLLECTION_FILE, File::POST_COLLECTION_FILE, File::PATCH_ITEM_FILE])]
    private ?int $size = null;

    /**
     * @var FileType|null
     */
    #[NotNull]
    #[Column(enumType: FileType::class)]
    #[Groups([File::GET_ITEM_FILE, File::GET_COLLECTION_FILE, File::POST_COLLECTION_FILE, File::PATCH_ITEM_FILE])]
    private ?FileType $type = null;

    /**
     * @var FileStatus|null
     */
    #[Column(enumType: FileStatus::class)]
    #[Groups([File::GET_ITEM_FILE, File::GET_COLLECTION_FILE])]
    private ?FileStatus $status = null;

    /**
     * @var File|null
     */
    #[ManyToOne(targetEntity: self::class, inversedBy: 'files')]
    #[Groups([File::GET_ITEM_FILE, File::GET_COLLECTION_FILE, File::POST_COLLECTION_FILE, File::PATCH_ITEM_FILE])]
    #[JoinColumn(onDelete: 'CASCADE')]
    private ?self $parent = null;

    /**
     * @var ArrayCollection|Collection
     */
    #[OneToMany(targetEntity: self::class, mappedBy: 'parent', cascade: ['persist', 'remove'])]
    #[Groups([File::GET_ITEM_FILE, File::GET_COLLECTION_FILE])]
    private Collection|ArrayCollection $files;

    /**
     * @var int|null
     */
    #[NotNull]
    #[PositiveOrZero]
    #[Column]
    #[Groups([File::GET_ITEM_FILE, File::GET_COLLECTION_FILE,])]
    private ?int $version = null;

    /**
     * @var array
     */
    #[Column]
    #[Groups([File::GET_ITEM_FILE, File::POST_COLLECTION_FILE, File::PATCH_ITEM_FILE])]
    private array $metadata = [];

    /**
     *
     */
    public function __construct()
    {
        $this->files = new ArrayCollection();
        $this->status = FileStatus::PENDING;
        $this->type = FileType::IMAGE;
        $this->mimeType = MimeType::JPEG;
        $this->version = 0;
        $this->metadata = [];
    }

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
     * @return string|null
     */
    public function getFileKey(): ?string
    {
        return $this->fileKey;
    }

    /**
     * @param string $fileKey
     * @return $this
     */
    public function setFileKey(string $fileKey): self
    {
        $this->fileKey = $fileKey;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    /**
     * @param string $originalName
     * @return $this
     */
    public function setOriginalName(string $originalName): self
    {
        $this->originalName = $originalName;

        return $this;
    }

    /**
     * @return MimeType|null
     */
    public function getMimeType(): ?MimeType
    {
        return $this->mimeType;
    }

    /**
     * @param MimeType $mimeType
     * @return $this
     */
    public function setMimeType(MimeType $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getSize(): ?int
    {
        return $this->size;
    }

    /**
     * @param int $size
     * @return $this
     */
    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    /**
     * @return FileType|null
     */
    public function getType(): ?FileType
    {
        return $this->type;
    }

    /**
     * @param FileType $type
     * @return $this
     */
    public function setType(FileType $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return FileStatus|null
     */
    public function getStatus(): ?FileStatus
    {
        return $this->status;
    }

    /**
     * @param FileStatus $status
     * @return $this
     */
    public function setStatus(FileStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return self|null
     */
    public function getParent(): ?self
    {
        return $this->parent;
    }

    /**
     * @param File|null $parent
     * @return $this
     */
    public function setParent(?self $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    /**
     * @param File $file
     * @return $this
     */
    public function addFile(self $file): self
    {
        if (!$this->files->contains($file)) {
            $this->files->add($file);
            $file->setParent($this);
        }

        return $this;
    }

    /**
     * @param File $file
     * @return $this
     */
    public function removeFile(self $file): self
    {
        if ($this->files->removeElement($file)) {
            if ($file->getParent() === $this) {
                $file->setParent(null);
            }
        }

        return $this;
    }

    /**
     * @return int|null
     */
    public function getVersion(): ?int
    {
        return $this->version;
    }

    /**
     * @param int $version
     * @return $this
     */
    public function setVersion(int $version): self
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array $metadata
     * @return $this
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * @return DateTime|null
     */
    #[Groups([File::GET_ITEM_FILE, File::GET_COLLECTION_FILE])]
    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return DateTime|null
     */
    #[Groups([File::GET_ITEM_FILE, File::GET_COLLECTION_FILE])]
    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
