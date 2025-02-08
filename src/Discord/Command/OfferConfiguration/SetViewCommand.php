<?php

namespace App\Discord\Command\OfferConfiguration;

use App\Discord\Command\AbstractDiscordCommand;
use App\Discord\Command\AbstractServerDiscordCommand;
use App\Entity\OfferConfiguration;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Doctrine\ORM\EntityManagerInterface;
use React\Promise\PromiseInterface;

class SetViewCommand extends AbstractServerDiscordCommand
{
    const VIEWS = [OfferConfiguration::BUYER_VIEW, OfferConfiguration::SELLER_VIEW];

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    )
    {

    }

    public function getName(): string
    {
        return 'set-view';
    }

    public function getDescription(): string
    {
        return "Défini l'affichage des offres d'une configuration";
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
            [
                'name' => 'view',
                'type' => Option::STRING,
                'required' => true,
                'description' => "Le type de d'affichage voulu (" . implode(', ', self::VIEWS) . ')',
            ],
        ];
    }

    public function serverExecute(Interaction $interaction): ?PromiseInterface
    {
        $configurationId = $interaction->data->options->get('name', 'offer-configuration-id')->value;
        $configuration = $this->entityManager->getRepository(OfferConfiguration::class)->find($configurationId);
        $view = $interaction->data->options->get('name', 'view')->value;

        if (null === $configuration) {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent("La configuration d'offre avec l'id $configurationId n'existe pas\nUtilisez la commande /list-offer-configuration pour obtenir un id valide"));
        }

        if (!in_array(strtoupper($view), self::VIEWS, true)) {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent("La vue $view n'existe pas, valeurs valide : " . implode(', ', self::VIEWS)));
        }

        $configuration->setView(strtoupper($view));

        $this->entityManager->persist($configuration);
        $this->entityManager->flush();

        return $interaction->respondWithMessage(MessageBuilder::new()->setContent("La vue {$configuration->getView()} a été appliqué à la configuration de l'offre avec l'id $configurationId"));
    }
}