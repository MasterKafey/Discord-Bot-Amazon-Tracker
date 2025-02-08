<?php

namespace App\Discord\Command\Category;

use App\Discord\Command\AbstractDiscordCommand;
use App\Discord\Command\AbstractServerDiscordCommand;
use App\Entity\ExcludedCategory;
use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;
use Doctrine\ORM\EntityManagerInterface;
use React\Promise\PromiseInterface;

class ListExcludedCategoryCommand extends AbstractServerDiscordCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Discord $discord
    )
    {

    }

    public function getName(): string
    {
        return 'list-excluded-categories';
    }

    public function getDescription(): string
    {
        return 'Lister les numéro de catégories exclues';
    }

    public function serverExecute(Interaction $interaction): ?PromiseInterface
    {
        $categories = $this->entityManager->getRepository(ExcludedCategory::class)->findAll();

        $ids = [];
        $nodes = [];
        foreach ($categories as $category) {
            $ids[] = $category->getId();
            $nodes[] = $category->getNode();
        }

        $embed = (new Embed($this->discord))
            ->setTitle('Catégorie exclue')
            ->addFieldValues('Id', implode("\n", $ids), true)
            ->addFieldValues('Node', implode("\n", $nodes), true)
        ;

        return $interaction->respondWithMessage(MessageBuilder::new()->setEmbeds([$embed]));
    }
}