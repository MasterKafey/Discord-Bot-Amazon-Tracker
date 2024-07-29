<?php

namespace App\Discord\Command\System;

use App\Business\ConfigBusiness;
use App\Discord\Command\AbstractDiscordCommand;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use React\Promise\PromiseInterface;

class SetIntervalCommand extends AbstractDiscordCommand
{
    public function __construct(
        private readonly ConfigBusiness $configBusiness
    )
    {

    }

    public function getName(): string
    {
        return 'set-interval';
    }

    public function getDescription(): string
    {
        return "Défini l'interval entre les requêtes";
    }

    public function getOptions(): array
    {
        return [
            [
                'name' => 'minutes',
                'type' => Option::INTEGER,
                'required' => true,
                'description' => 'Nombre de minute entre les requêtes',
            ]
        ];
    }

    public function execute(Interaction $interaction): ?PromiseInterface
    {
        $minutes = $interaction->data->options->get('name', 'minutes')->value;

        $this->configBusiness->set('minutes_interval', $minutes);

        return $interaction->respondWithMessage(MessageBuilder::new()->setContent("Les requêtes seront maintenant séparées de $minutes minute(s)"));
    }
}