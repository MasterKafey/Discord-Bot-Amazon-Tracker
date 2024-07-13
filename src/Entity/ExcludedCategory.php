<?php

namespace App\Entity;

use App\Repository\ExcludedCategoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExcludedCategoryRepository::class)]
class ExcludedCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER, unique: true)]
    private ?int $node;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNode(): ?int
    {
        return $this->node;
    }

    public function setNode(?int $node): self
    {
        $this->node = $node;
        return $this;
    }
}