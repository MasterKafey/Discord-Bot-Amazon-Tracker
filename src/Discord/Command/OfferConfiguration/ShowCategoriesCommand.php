<?php

namespace App\Discord\Command\OfferConfiguration;

use App\Discord\Command\AbstractDiscordCommand;
use App\Discord\Command\AbstractServerDiscordCommand;
use App\Entity\OfferConfiguration;
use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Doctrine\ORM\EntityManagerInterface;
use React\Promise\PromiseInterface;

class ShowCategoriesCommand extends AbstractServerDiscordCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Discord                $discord,
    )
    {

    }

    public function getName(): string
    {
        return 'show-categories';
    }

    public function getOptions(): array
    {
        return [
            [
                'name' => 'offer-configuration-id',
                'type' => Option::INTEGER,
                'required' => true,
                'description' => "L'id de la configuration de l'offre que vous souhaitez modifier"
            ],
        ];
    }

    public function getDescription(): string
    {
        return "Ajoute une categorie à la configuration d'offre";
    }

    public function serverExecute(Interaction $interaction): ?PromiseInterface
    {
        $configurationId = $interaction->data->options->get('name', 'offer-configuration-id')->value;
        $configuration = $this->entityManager->getRepository(OfferConfiguration::class)->find($configurationId);

        if (null === $configuration) {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent("La configuration d'offre avec l'id $configurationId n'existe pas\nUtilisez la commande /list-offer-configuration pour obtenir un id valide"));
        }

        $id = $configuration->getId();
        $domain = array_search($configuration->getDomain(), OfferConfiguration::DOMAINS);
        $channel = "<#{$configuration->getChannelId()}>";
        $minPercentage = $configuration->getMinPercentage();
        $maxPercentage = $configuration->getMaxPercentage();
        $minPrice = $configuration->getMinimumPrice();

        $embed = (new Embed($this->discord))
            ->setTitle("Configuration d'offre")
            ->addFieldValues('Id', $id, true)
            ->addFieldValues('Domain', $domain, true)
            ->addFieldValues('Salon', $channel, true)
            ->addFieldValues('% Min', $minPercentage, true)
            ->addFieldValues('% Max', $maxPercentage, true)
            ->addFieldValues('Prix min', $minPrice, true)
            ->addFieldValues('Categories', implode(", ", $configuration->getCategories()), true)
        ;

        return $interaction->respondWithMessage(MessageBuilder::new()->setEmbeds([$embed]));
    }
}