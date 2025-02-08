<?php

namespace App\Discord\Command\System;

use App\Business\ConfigBusiness;
use App\Discord\Command\AbstractDiscordCommand;
use App\Discord\Command\AbstractServerDiscordCommand;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use React\Promise\PromiseInterface;

class SetAmazonPartnerIdCommand extends AbstractServerDiscordCommand
{
    public function __construct(
        private readonly ConfigBusiness $configBusiness
    )
    {

    }

    public function getName(): string
    {
        return 'set-amazon-partner-id';
    }

    public function getDescription(): string
    {
        return "Défini l'id d'affiliation pour les liens amazons";
    }

    public function getOptions(): array
    {
        return [
            [
                'name' => 'partner-id',
                'type' => Option::STRING,
                'required' => true,
                'description' => 'Id du partenaire',
            ]
        ];
    }

    public function serverExecute(Interaction $interaction): ?PromiseInterface
    {
        $partnerId = $interaction->data->options->get('name', 'partner-id')->value;

        $this->configBusiness->set('partner_id', $partnerId);

        return $interaction->respondWithMessage(MessageBuilder::new()->setContent("Les liens d'affiliations d'amazon sont configurés avec le nouvel id partenaire"));
    }
}