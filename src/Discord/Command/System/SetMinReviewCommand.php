<?php

namespace App\Discord\Command\System;

use App\Business\ConfigBusiness;
use App\Discord\Command\AbstractDiscordCommand;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use React\Promise\PromiseInterface;

class SetMinReviewCommand extends AbstractDiscordCommand
{
    public function __construct(
        private readonly ConfigBusiness $configBusiness
    )
    {

    }

    public function getName(): string
    {
        return 'set-min-review';
    }

    public function getDescription(): string
    {
        return 'Défini le nombre de commentaire minimum pour envoyer l\'offre';
    }

    public function getOptions(): array
    {
        return [
            [
                'name' => 'min-review',
                'type' => Option::NUMBER,
                'required' => true,
                'description' => "Le nombre de commentaire minimum",
            ],
        ];
    }

    public function execute(Interaction $interaction): ?PromiseInterface
    {
        $minReviews = $interaction->data->options->get('name', 'min-review')->value;

        if ($minReviews < 0) {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent("Le nombre minimum de commentaire ne doit pas être inférieur à 0"));
        }

        $this->configBusiness->set('min_reviews', $minReviews);

        return $interaction->respondWithMessage(MessageBuilder::new()->setContent("Le nombre minimum de commentaire a correctement été mis à jour"));
    }
}