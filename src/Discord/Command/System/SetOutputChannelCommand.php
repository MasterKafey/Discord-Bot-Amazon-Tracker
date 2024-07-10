<?php

namespace App\Discord\Command\System;

use App\Business\ChannelBusiness;
use App\Discord\Command\AbstractDiscordCommand;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use React\Promise\PromiseInterface;

class SetOutputChannelCommand extends AbstractDiscordCommand
{
    public function __construct(
        private readonly ChannelBusiness $channelBusiness,
    )
    {

    }

    public function getName(): string
    {
        return 'set-output-channel';
    }

    public function getDescription(): string
    {
        return 'Definie le salon qui recevera les notificaitons du Bot';
    }

    public function getOptions(): array
    {
        return [
            [
                'name' => 'channel',
                'required' => false,
                'type' => Option::CHANNEL,
                'description' => 'Le salon ciblé'
            ]
        ];
    }

    public function execute(Interaction $interaction): ?PromiseInterface
    {
        if (!$interaction->member->permissions->administrator) {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent("Vous n'avez pas la permission d'éxecuter cette commande"));
        }

        $channelId = $interaction->data->options->get('name', 'channel')->value ?? $interaction->channel_id;
        $channel = $this->channelBusiness->getChannel($channelId);

        if ($channel->is_private) {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent("Cette commande ne fonctionne pas pour un salon privé"));
        }

        if ($channel->type !== Channel::TYPE_TEXT) {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent('Le salon doit être textuel'));
        }

        $this->channelBusiness->setOutputChannel($channel);
        return $interaction->respondWithMessage(MessageBuilder::new()->setContent('Les informations de fonctionnement seront envoyés dans ce channel'));
    }
}