<?php

namespace App\Discord\Command\Category;

use App\Discord\Command\AbstractDiscordCommand;
use App\Entity\ExcludedCategory;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Keepa\objects\Category;
use React\Promise\PromiseInterface;

class DeleteExcludedCategoryCommand extends AbstractDiscordCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    )
    {

    }

    public function getName(): string
    {
        return 'include-category';
    }

    public function getDescription(): string
    {
        return 'Inclure de nouveau une catégorie précédement exclue';
    }

    public function getOptions(): array
    {
        return [
            [
                'name' => 'node-category',
                'type' => Option::STRING,
                'required' => true,
                'description' => "Le numéro de catégorie à inclure de nouveau"
            ]
        ];
    }

    public function execute(Interaction $interaction): ?PromiseInterface
    {
        $nodeCategory = $interaction->data->options->get('name', 'node-category')->value;
        $category = $this->entityManager->getRepository(ExcludedCategory::class)->findOneBy(['node' => $nodeCategory]);

        if (null === $category) {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent("La catégorie avec le numéro '$nodeCategory' n'est pas exclue"));
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();

        return $interaction->respondWithMessage(MessageBuilder::new()->setContent("La catégorie avec le numéro '$nodeCategory' est maintenant réintégré aux requête Keepa"));
    }
}