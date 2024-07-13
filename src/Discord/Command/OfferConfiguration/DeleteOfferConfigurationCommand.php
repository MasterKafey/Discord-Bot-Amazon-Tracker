<?php

namespace App\Discord\Command\OfferConfiguration;

use App\Discord\Command\AbstractDiscordCommand;
use App\Entity\OfferConfiguration;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Doctrine\ORM\EntityManagerInterface;
use React\Promise\PromiseInterface;

class DeleteOfferConfigurationCommand extends AbstractDiscordCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    )
    {

    }

    public function getName(): string
    {
        return 'delete-offer-configuration';
    }

    public function getDescription(): string
    {
        return "Supprimer une configuration d'offre";
    }

    public function getOptions(): array
    {
        return [
            [
                'name' => 'id',
                'type' => Option::INTEGER,
                'description' => "Id de le la configuration d'offre à supprimer",
                'required' => true,
            ],
        ];
    }

    public function execute(Interaction $interaction): ?PromiseInterface
    {
        $offerConfigurationId = $interaction->data->options->get('name', 'id')->value;
        $offerConfiguration = $this->entityManager->getRepository(OfferConfiguration::class)->find($offerConfigurationId);

        if (null === $offerConfiguration) {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent("La configuration d'offre avec l'id $offerConfigurationId n'existe pas"));
        }

        $this->entityManager->remove($offerConfiguration);
        $this->entityManager->flush();

        return $interaction->respondWithMessage(MessageBuilder::new()->setContent("Succés de la suppression de l'offre avec l'id $offerConfigurationId"));
    }
}