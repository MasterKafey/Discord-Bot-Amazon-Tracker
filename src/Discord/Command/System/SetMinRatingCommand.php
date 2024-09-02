<?php

namespace App\Discord\Command\System;

use App\Business\ConfigBusiness;
use App\Discord\Command\AbstractDiscordCommand;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use React\Promise\PromiseInterface;

class SetMinRatingCommand extends AbstractDiscordCommand
{
    public function __construct(
        private readonly ConfigBusiness $configBusiness
    )
    {

    }

    public function getName(): string
    {
        return 'set-min-rating';
    }

    public function getDescription(): string
    {
        return 'Défini la note minimal nécessaire pour être envoyé';
    }

    public function getOptions(): array
    {
        return [
            [
                'name' => 'min-rating',
                'type' => Option::NUMBER,
                'required' => true,
                'description' => "La note minimal",
            ],
        ];
    }

    public function execute(Interaction $interaction): ?PromiseInterface
    {
        $minRating = $interaction->data->options->get('name', 'min-rating')->value;

        if ($minRating < 0 || $minRating > 5) {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent("La note minimal doit être compris entre 0 et 5"));
        }

        $this->configBusiness->set('min_rating', round($minRating * 10));

        return $interaction->respondWithMessage(MessageBuilder::new()->setContent("La note minimal a correctement été mis à jour"));
    }
}