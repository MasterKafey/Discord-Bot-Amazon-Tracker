<?php

namespace App\Discord\Command;

use Discord\Parts\Interactions\Command\Command;
use Discord\Parts\Interactions\Interaction;
use React\Promise\PromiseInterface;

abstract class AbstractDiscordCommand
{
    public function getOptions(): array
    {
        return [];
    }

    public function getAttributes(): array
    {
        return [
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'options' => $this->getOptions(),
        ];
    }

    public abstract function getName(): string;

    public function getDescription(): string
    {
        return 'Custom discord bot command';
    }

    public abstract function execute(Interaction $interaction): ?PromiseInterface;
}