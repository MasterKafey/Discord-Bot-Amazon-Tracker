<?php

namespace App\Mailer;

class Mail
{
    private ?string $from = null;

    private ?string $to = null;

    private ?string $twigTemplate = null;

    private array $context = [];

    public function getFrom(): ?string
    {
        return $this->from;
    }

    public function setFrom(?string $from): self
    {
        $this->from = $from;
        return $this;
    }

    public function getTo(): ?string
    {
        return $this->to;
    }

    public function setTo(?string $to): self
    {
        $this->to = $to;
        return $this;
    }

    public function getTwigTemplate(): ?string
    {
        return $this->twigTemplate;
    }

    public function setTwigTemplate(?string $twigTemplate): self
    {
        $this->twigTemplate = $twigTemplate;
        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }
}