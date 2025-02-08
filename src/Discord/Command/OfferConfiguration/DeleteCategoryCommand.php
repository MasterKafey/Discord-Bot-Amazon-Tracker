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

class DeleteCategoryCommand extends AbstractServerDiscordCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    )
    {

    }

    public function getName(): string
    {
        return 'delete-category';
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
                'name' => 'node-category',
                'type' => Option::STRING,
                'required' => true,
                'description' => 'Noeud de la catégorie que vous voulez inclure',
            ],
        ];
    }

    public function getDescription(): string
    {
        return "Retire une catégorie d'une configuration d'offre";
    }

    public function serverExecute(Interaction $interaction): ?PromiseInterface
    {
        $configurationId = $interaction->data->options->get('name', 'offer-configuration-id')->value;
        $configuration = $this->entityManager->getRepository(OfferConfiguration::class)->find($configurationId);

        if (null === $configuration) {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent("La configuration d'offre avec l'id $configuration n'existe pas\nUtilisez la commande /list-offer-configuration pour obtenir un id valide"));
        }

        $configuration->removeCategory($categoryNode = $interaction->data->options->get('name', 'node-category')->value);
        $this->entityManager->flush();

        return $interaction->respondWithMessage(MessageBuilder::new()->setContent("La categorie $categoryNode a été retiré de la configuration de l'offre"));
    }
}