<?php

namespace App\Discord\Command\OfferConfiguration;

use App\Discord\Command\AbstractDiscordCommand;
use App\Entity\OfferConfiguration;
use App\Repository\OfferConfigurationRepository;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Doctrine\ORM\EntityManagerInterface;
use Keepa\objects\AmazonLocale;
use React\Promise\PromiseInterface;

class CreateOfferConfigurationCommand extends AbstractDiscordCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    )
    {

    }

    public function getName(): string
    {
        return 'create-offer-configuration';
    }

    public function getDescription(): string
    {
        return 'Create an offer configuration to receive amazon deals';
    }

    public function getOptions(): array
    {

        return [
            [
                'name' => 'domain',
                'type' => Option::STRING,
                'required' => true,
                'description' => implode(' ', array_keys(OfferConfiguration::DOMAINS)),
            ],
            [
                'name' => 'min-percentage',
                'type' => Option::INTEGER,
                'required' => true,
                'description' => 'Minimum percentage',
            ],
            [
                'name' => 'max-percentage',
                'type' => Option::INTEGER,
                'required' => true,
                'description' => 'Maximum percentage',
            ],
        ];
    }

    public function execute(Interaction $interaction): ?PromiseInterface
    {
        $domain = $interaction->data->options->get('name', 'domain')->value;

        if (!isset($domain)) {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent("Domain '$domain' is not valid"));
        }

        $maxPercentage = $interaction->data->options->get('name', 'max-percentage')->value;
        $minPercentage = $interaction->data->options->get('name', 'min-percentage')->value;

        if ($minPercentage < 10 || $minPercentage > 100) {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent("Min % must be defined between 10 and 100"));
        }

        if ($maxPercentage < 10 || $maxPercentage > 100) {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent("Max % must be defined between 10 and 100"));
        }

        $offerConfiguration = (new OfferConfiguration())
            ->setDomain(OfferConfiguration::DOMAINS[$domain])
            ->setMaxPercentage($maxPercentage)
            ->setMinPercentage($minPercentage)
            ->setChannelId($interaction->channel_id)
        ;

        $this->entityManager->persist($offerConfiguration);
        $this->entityManager->flush();

        return $interaction->respondWithMessage(MessageBuilder::new()->setContent(
            'Offer configuration created for current channel'
        ));
    }
}