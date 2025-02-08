<?php

namespace App\Discord\Command\System;

use App\Discord\Command\AbstractDiscordCommand;
use App\Discord\Command\AbstractServerDiscordCommand;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use React\Promise\PromiseInterface;

class StatusCommand extends AbstractServerDiscordCommand
{
    public function getName(): string
    {
        return 'status';
    }

    public function getDescription(): string
    {
        return 'Obtenir le statut du bot';
    }

    public function serverExecute(Interaction $interaction): ?PromiseInterface
    {
        return $interaction->respondWithMessage(MessageBuilder::new()->setContent("Je suis actuellement en ligne"));
    }
}