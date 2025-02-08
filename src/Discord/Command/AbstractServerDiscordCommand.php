<?php

namespace App\Discord\Command;

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Interactions\Interaction;
use React\Promise\PromiseInterface;

abstract class AbstractServerDiscordCommand extends AbstractDiscordCommand
{
    public function execute(Interaction $interaction): ?PromiseInterface
    {
        return $interaction->user->getPrivateChannel()->then(function (?Channel $channel) use ($interaction) {
            if (null !== $channel && $channel->is_private && $channel->id === $interaction->channel->id) {
                return $interaction->respondWithMessage(MessageBuilder::new()->setContent("Cette commande n'est pas utilisable en privé"));
            }

            return $this->serverExecute($interaction);
        });
    }

    public abstract function serverExecute(Interaction $interaction): ?PromiseInterface;
}
