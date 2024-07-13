<?php

namespace App\Discord\Command\OfferConfiguration;

use App\Discord\Command\AbstractDiscordCommand;
use App\Entity\OfferConfiguration;
use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;
use Doctrine\ORM\EntityManagerInterface;
use React\Promise\PromiseInterface;

class ListOfferConfigurationCommand extends AbstractDiscordCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Discord                $discord
    )
    {

    }

    public function getName(): string
    {
        return 'list-offer-configuration';
    }

    public function getDescription(): string
    {
        return "Lister les configurations d'offres";
    }

    public function execute(Interaction $interaction): ?PromiseInterface
    {
        $offerConfigurations = $this->entityManager->getRepository(OfferConfiguration::class)->findAll();

        $ids = [];
        $domains = [];
        $minPercentages = [];
        $maxPercentages = [];
        $minPrices = [];
        $channels = [];

        foreach ($offerConfigurations as $offerConfiguration) {
            $domains[] = array_search($offerConfiguration->getDomain(), OfferConfiguration::DOMAINS);
            $ids[] = $offerConfiguration->getId();
            $minPercentages[] = $offerConfiguration->getMinPercentage();
            $maxPercentages[] = $offerConfiguration->getMaxPercentage();
            $minPrices[] = $offerConfiguration->getMinimumPrice();
            $channels[] = "<#{$offerConfiguration->getChannelId()}>";
        }

        $embeds = [];
        $embeds[] = (new Embed($this->discord))
            ->setTitle("Liste des configurations d'offres")
            ->addFieldValues('Id', implode("\n", $ids), true)
            ->addFieldValues('Domain', implode("\n", $domains), true)
            ->addFieldValues('Salon', implode("\n", $channels), true);

        $embeds[] = (new Embed($this->discord))
            ->setTitle("Liste des pourcentage de configuration d'offre")
            ->addFieldValues('Id', implode("\n", $ids), true)
            ->addFieldValues('% Min', implode("\n", $minPercentages), true)
            ->addFieldValues('% Max', implode("\n", $maxPercentages), true);

        $embeds[] = (new Embed($this->discord))
            ->setTitle('Liste des config')
            ->addFieldValues('Prix min', implode("\n", $minPrices), true);

        return $interaction->respondWithMessage(MessageBuilder::new()->setEmbeds($embeds));
    }
}