<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\WidgetRepository;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Gedmo\Mapping\Annotation\SoftDeleteable;
use App\Entity\Trait\SoftDeleteableEntity;
use App\Entity\Trait\TimestampableEntity;

#[ApiResource]
#[Entity(repositoryClass: WidgetRepository::class)]
#[SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class Widget extends Asset
{
    use TimestampableEntity;
    use SoftDeleteableEntity;

    /**
     * @var int|null
     */
    #[Column]
    private ?int $rate = null;

    /**
     * @var int|null
     */
    #[Column]
    private ?int $view = null;

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
