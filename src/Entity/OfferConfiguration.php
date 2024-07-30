<?php

namespace App\Entity;

use App\Repository\OfferConfigurationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Keepa\objects\AmazonLocale;

#[ORM\Entity(repositoryClass: OfferConfigurationRepository::class)]
class OfferConfiguration
{
    const DOMAINS = [
        'FR' => AmazonLocale::FR,
        'ES' => AmazonLocale::ES,
        'IT' => AmazonLocale::IT,
        'DE' => AmazonLocale::DE,
        'BR' => AmazonLocale::BR,
        'CA' => AmazonLocale::CA,
        'GB' => AmazonLocale::GB,
        'IN' => AmazonLocale::IN,
        'JP' => AmazonLocale::JP,
        'MX' => AmazonLocale::MX,
        'US' => AmazonLocale::US,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $domain = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $minPercentage = 10;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $maxPercentage = 100;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $minimumPrice = 10;

    #[ORM\Column(type: Types::STRING)]
    private ?string $channelId = null;

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private array $categories = [];

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => 0])]
    private bool $weekAverage = false;

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private array $lastOffersSent = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDomain(): ?int
    {
        return $this->domain;
    }

    public function setDomain(int $domain): self
    {
        $this->domain = $domain;
        return $this;
    }

    public function getMinPercentage(): ?int
    {
        return $this->minPercentage;
    }

    public function setMinPercentage(?int $minPercentage): self
    {
        $this->minPercentage = $minPercentage;
        return $this;
    }

    public function getMaxPercentage(): ?int
    {
        return $this->maxPercentage;
    }

    public function setMaxPercentage(?int $maxPercentage): self
    {
        $this->maxPercentage = $maxPercentage;
        return $this;
    }

    public function getMinimumPrice(): ?float
    {
        return $this->minimumPrice;
    }

    public function setMinimumPrice(?float $minimumPrice): self
    {
        $this->minimumPrice = $minimumPrice;
        return $this;
    }

    public function getChannelId(): ?string
    {
        return $this->channelId;
    }

    public function setChannelId(string $channelId): self
    {
        $this->channelId = $channelId;
        return $this;
    }

    public function getCategories(): array
    {
        return $this->categories;
    }

    public function setCategories(array $categories): self
    {
        $this->categories = $categories;
        return $this;
    }

    public function addCategory(string $category): self
    {
        $this->categories[] = $category;
        return $this;
    }

    public function removeCategory(string $category): self
    {
        $key = array_search($category, $this->categories);

        if (false !== $key) {
            unset($this->categories[$key]);
        }

        return $this;
    }

    public function isWeekAverage(): bool
    {
        return $this->weekAverage;
    }

    public function setWeekAverage(bool $weekAverage): self
    {
        $this->weekAverage = $weekAverage;
        return $this;
    }

    public function getLastOffersSent(): array
    {
        return $this->lastOffersSent;
    }

    public function setLastOffersSent(array $lastOffersSent): self
    {
        $this->lastOffersSent = $lastOffersSent;
        return $this;
    }
}