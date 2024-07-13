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

    public function getMinimumPrice(): ?int
    {
        return $this->minimumPrice;
    }

    public function setMinimumPrice(?int $minimumPrice): self
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
}