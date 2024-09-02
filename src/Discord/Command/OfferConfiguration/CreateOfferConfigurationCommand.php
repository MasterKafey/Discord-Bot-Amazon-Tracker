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
        return "Créer une configuration d'offre pour le salon en cours";
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
                'description' => 'Pourcentage minimum',
            ],
            [
                'name' => 'max-percentage',
                'type' => Option::INTEGER,
                'required' => true,
                'description' => "Pourcentage maximum",
            ],
            [
                'name' => 'week-average',
                'type' => Option::BOOLEAN,
                'required' => true,
                'description' => 'Filtrer les prix par moyen de la semaine'
            ],
            [
                'name' => 'premium',
                'type' => Option::BOOLEAN,
                'required' => true,
                'description' => 'Afficher le texte premium (True) ou freemium (False)',
                'default' => false,
            ],
            [
                'name' => 'view',
                'type' => Option::STRING,
                'required' => false,
                'description' => "Définir l'affichage des offres (" . implode(', ', SetViewCommand::VIEWS) . ')',
            ]
        ];
    }

    public function execute(Interaction $interaction): ?PromiseInterface
    {
        $domain = strtoupper($interaction->data->options->get('name', 'domain')->value);

        if (!isset(OfferConfiguration::DOMAINS[$domain])) {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent("Domain '$domain' is not valid"));
        }

        $maxPercentage = $interaction->data->options->get('name', 'max-percentage')->value;
        $minPercentage = $interaction->data->options->get('name', 'min-percentage')->value;
        $view = $interaction->data->options->get('name', 'view')->value ?? OfferConfiguration::BUYER_VIEW;

        if ($minPercentage < 10 || $minPercentage > 100) {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent("Min % doit être défini entre 10 et 100"));
        }

        if ($maxPercentage < 10 || $maxPercentage > 100) {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent("Max % doit être défini entre 10 et 100"));
        }

        if ($maxPercentage < $minPercentage) {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent("Min % doit être inférieur au Max %"));
        }

        if (!in_array(strtoupper($view), SetViewCommand::VIEWS, true)) {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent("La vue $view n'existe pas, valeurs valide : " . implode(', ', SetViewCommand::VIEWS)));
        }

        $offerConfiguration = (new OfferConfiguration())
            ->setDomain(OfferConfiguration::DOMAINS[$domain])
            ->setView(strtoupper($view))
            ->setMaxPercentage($maxPercentage)
            ->setMinPercentage($minPercentage)
            ->setChannelId($interaction->channel_id)
            ->setWeekAverage($interaction->data->options->get('name', 'week-average')->value)
            ->setIsPremium($interaction->data->options->get('name', 'premium')->value)
        ;

        $this->entityManager->persist($offerConfiguration);
        $this->entityManager->flush();

        return $interaction->respondWithMessage(MessageBuilder::new()->setContent(
            "La configuration d'offre a été défini"
        ));
    }
}