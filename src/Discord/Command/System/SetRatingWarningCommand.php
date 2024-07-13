<?php

namespace App\Discord\Command\System;

use App\Business\ConfigBusiness;
use App\Discord\Command\AbstractDiscordCommand;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use React\Promise\PromiseInterface;

class SetRatingWarningCommand extends AbstractDiscordCommand
{
    public function __construct(
        private readonly ConfigBusiness $configBusiness
    )
    {

    }

    public function getName(): string
    {
        return 'set-rating-warning';
    }

    public function getDescription(): string
    {
        return 'Défini la note minimum pour éviter le warning sur une offre';
    }

    public function getOptions(): array
    {
        return [
            [
                'name' => 'min-rating',
                'type' => Option::NUMBER,
                'required' => true,
                'description' => "La note minimum",
            ],
        ];
    }

    public function execute(Interaction $interaction): ?PromiseInterface
    {
        $minRating = $interaction->data->options->get('name', 'min-rating')->value;

        if ($minRating < 0 || $minRating > 5) {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent("La note minimum doit être compris entre 0 et 5"));
        }

        $this->configBusiness->set('rating_warning', round($minRating * 10));

        return $interaction->respondWithMessage(MessageBuilder::new()->setContent("La note minimum a correctement été mis à jour"));
    }
}