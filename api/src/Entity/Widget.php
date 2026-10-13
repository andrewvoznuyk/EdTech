<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Repository\WidgetRepository;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Gedmo\Mapping\Annotation\SoftDeleteable;
use App\Entity\Trait\SoftDeleteableEntity;
use App\Entity\Trait\TimestampableEntity;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => [self::GET_COLLECTION_WIDGET, Asset::GET_COLLECTION_ASSET]],
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Get(
            normalizationContext: ['groups' => [self::GET_ITEM_WIDGET, Asset::GET_ITEM_ASSET]],
            security: "is_granted('ROLE_ADMIN') or (is_granted('ROLE_USER') and object.getUser() == user)"
        ),
        new Patch(
            normalizationContext: ['groups' => [self::GET_ITEM_WIDGET, Asset::GET_ITEM_ASSET]],
            denormalizationContext: ['groups' => [self::PATCH_ITEM_WIDGET]],
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN')"
        ),
    ]
)]
#[Entity(repositoryClass: WidgetRepository::class)]
#[SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class Widget extends Asset
{
    use TimestampableEntity;
    use SoftDeleteableEntity;

    public const string GET_COLLECTION_WIDGET = 'get:collection:widget';
    public const string GET_ITEM_WIDGET = 'get:item:widget';
    public const string PATCH_ITEM_WIDGET = 'patch:item:widget';

    /**
     * @var int|null
     */
    #[Column]
    #[Groups([Asset::GET_COLLECTION_ASSET, Asset::GET_ITEM_ASSET, Asset::GET_COLLECTION_ASSET_ADMIN, self::GET_COLLECTION_WIDGET, self::GET_ITEM_WIDGET, self::PATCH_ITEM_WIDGET])]
    private ?int $rate = null;

    /**
     * @var int|null
     */
    #[Column]
    #[Groups([Asset::GET_COLLECTION_ASSET, Asset::GET_ITEM_ASSET, Asset::GET_COLLECTION_ASSET_ADMIN, self::GET_COLLECTION_WIDGET, self::GET_ITEM_WIDGET, self::PATCH_ITEM_WIDGET])]
    private ?int $view = null;

    public function getAssetType(): string
    {
        return 'widget';
    }

    /**
     * @return int|null
     */
    public function getRate(): ?int
    {
        return $this->rate;
    }

    /**
     * @param int $rate
     * @return $this
     */
    public function setRate(int $rate): static
    {
        $this->rate = $rate;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getView(): ?int
    {
        return $this->view;
    }

    /**
     * @param int $view
     * @return $this
     */
    public function setView(int $view): static
    {
        $this->view = $view;

        return $this;
    }
}
