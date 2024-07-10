<?php

namespace App\Discord\Command\System;

use App\Business\ConfigBusiness;
use App\Discord\Command\AbstractDiscordCommand;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use React\Promise\PromiseInterface;

class SetLowestPercentageCommand extends AbstractDiscordCommand
{
    public function __construct(
        private readonly ConfigBusiness $configBusiness
    )
    {
    }

    public function getName(): string
    {
        return 'set-percentage';
    }

    public function getOptions(): array
    {
        return [
            [
                'name' => 'percentage',
                'type' => Option::INTEGER,
                'description' => 'Lowest percentage to start notify',
                'required' => true,
            ]
        ];
    }

    public function getDescription(): string
    {
        return 'Configure lowest percentage to start notify';
    }

    public function execute(Interaction $interaction): ?PromiseInterface
    {
        $percentage = $interaction->data->options->get('name', 'percentage')->value;

        if ($percentage < 0 || $percentage > 100) {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent("Le pourcentage doit étre en entre 0 & 100"));
        }

        $this->configBusiness->set('lowest_percentage', $percentage);

        return $interaction->respondWithMessage(MessageBuilder::new()->setContent("Le pourcentage minimum est défini à $percentage"));
    }
}