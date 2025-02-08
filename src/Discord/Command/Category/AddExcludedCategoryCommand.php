<?php

namespace App\Discord\Command\Category;

use App\Discord\Command\AbstractDiscordCommand;
use App\Discord\Command\AbstractServerDiscordCommand;
use App\Entity\ExcludedCategory;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;

class AddExcludedCategoryCommand extends AbstractServerDiscordCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    )
    {

    }

    public function getName() : string
    {
        return 'exclude-category';
    }

    public function getDescription(): string
    {
        return 'Exclure une categorie des requête Keepa';
    }

    public function getOptions(): array
    {
        return [
            [
                'name' => 'category-node',
                'type' => Option::STRING,
                'required' => true,
                'description' => 'Le numéro de la category à exclure',
            ]
        ];
    }

    public function serverExecute(Interaction $interaction): ?PromiseInterface
    {
        $nodeCategory = $interaction->data->options->get('name', 'category-node')->value;

        if (null !== $this->entityManager->getRepository(ExcludedCategory::class)->findOneBy(['node' => $nodeCategory])) {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent("La categorie avec le numéro '$nodeCategory' est déjà exclue"));
        }

        $excludedCategory = (new ExcludedCategory())
            ->setNode($nodeCategory)
        ;

        $this->entityManager->persist($excludedCategory);
        $this->entityManager->flush();

        return $interaction->respondWithMessage(MessageBuilder::new()->setContent("La catégorie '$nodeCategory' sera désormais exclue"));
    }
}