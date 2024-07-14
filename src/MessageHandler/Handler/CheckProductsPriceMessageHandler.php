<?php

namespace App\MessageHandler\Handler;

use App\Business\ConfigBusiness;
use App\Entity\ExcludedCategory;
use App\Entity\OfferConfiguration;
use App\MessageHandler\Message\CheckProductsPriceMessage;
use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Embed\Embed;
use Discord\WebSockets\Intents;
use Doctrine\ORM\EntityManagerInterface;
use Keepa\API\DealRequest;
use Keepa\API\Request;
use Keepa\API\ResponseStatus;
use Keepa\helper\CSVType;
use Keepa\helper\CSVTypeWrapper;
use Keepa\helper\KeepaTime;
use Keepa\helper\ProductAnalyzer;
use Keepa\KeepaAPI;
use Keepa\objects\AmazonLocale;
use Keepa\objects\Deal;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CheckProductsPriceMessageHandler
{
    public function __construct(
        private readonly KeepaAPI               $keepaAPI,
        private readonly string                 $discordBotToken,
        private readonly ConfigBusiness         $configBusiness,
        private readonly LoggerInterface        $logger,
        private readonly EntityManagerInterface $entityManager,
    )
    {

    }

    public function __invoke(CheckProductsPriceMessage $message): void
    {
        $offerConfigurations = $this->entityManager->getRepository(OfferConfiguration::class)->findAll();

        foreach ($offerConfigurations as $offerConfiguration) {
            $this->offerConfigurationCheck($offerConfiguration);
        }
    }

    public function offerConfigurationCheck(OfferConfiguration $offerConfiguration): void
    {
        $minRating = $this->configBusiness->get('rating_warning');
        $minReview = $this->configBusiness->get('review_warning');
        $amazonDomain = $offerConfiguration->getDomain();
        $excludedDomains = $this->entityManager->getRepository(ExcludedCategory::class)->findAll();
        $currentTime = floor(time() / 60) * 60;

        $this->logger->info("Checking Prices for $currentTime current time");

        $domain = match ($amazonDomain) {
            AmazonLocale::FR => 'fr',
            AmazonLocale::ES => 'es',
            AmazonLocale::IT => 'it',
            AmazonLocale::DE => 'de',
            AmazonLocale::BR => 'com.br',
            AmazonLocale::CA => 'ca',
            AmazonLocale::GB => 'co.uk',
            AmazonLocale::IN => 'in',
            AmazonLocale::JP => 'co.jp',
            AmazonLocale::MX => 'com.mx',
            default => 'com',
        };

        $flag = match ($amazonDomain) {
            AmazonLocale::FR => '🇫🇷',
            AmazonLocale::ES => '🇪🇸',
            AmazonLocale::IT => '🇮🇹',
            AmazonLocale::DE => '🇩🇪',
            AmazonLocale::BR => '🇧🇷',
            AmazonLocale::CA => '🇨🇦',
            AmazonLocale::GB => '🇬🇧',
            AmazonLocale::IN => '🇮🇳',
            AmazonLocale::JP => '🇯🇵',
            AmazonLocale::MX => '🇲🇽',
            default => '🇺🇸',
        };
        $this->logger->info("Checking Prices for $domain");

        $page = 0;
        $filteredDeals = [];
        do {
            $this->logger->info("Request for page $page for {$offerConfiguration->getId()} offer configuration id");
            $request = new DealRequest();
            $request->page = $page++;
            $request->domainId = $amazonDomain;
            $request->priceTypes = [CSVType::MARKET_NEW];
            $request->excludeCategories = array_map(function (ExcludedCategory $category) {
                return $category->getNode();
            }, $excludedDomains);
            $request->dateRange = 0;
            $request->deltaPercentRange = [$offerConfiguration->getMinPercentage(), $offerConfiguration->getMaxPercentage()];
            $request->isLowest = true;
            $request->isLowestOffer = true;
            $request->isRangeEnabled = true;
            $request->currentRange = [$offerConfiguration->getMinimumPrice() * 100, 99999999];
            $request->hasReviews = true;
            $request->sortType = 1;

            $r = Request::getDealsRequest($request);
            $response = $this->keepaAPI->sendRequestWithRetry($r);

            if ($response->status !== ResponseStatus::OK) {
                $this->logger->error("Request failed with response $response->status : " . $response->error?->message);
                return;
            }

            $this->logger->info("Current request deals number : " . count($response->deals->dr));
            foreach ($response->deals->dr as $deal) {
                if ($currentTime - (KeepaTime::keepaMinuteToUnixInMillis($deal->lastUpdate) / 1000) <= 360) {
                    $filteredDeals[] = $deal;
                } else {
                    break 2;
                }
            }
        } while (count($response?->deals?->dr ?? []) === 150);
        $this->logger->info("Deals number after first filter : " . count($filteredDeals));

        if (empty($filteredDeals)) {
            $this->logger->warning("No deals found");
            return;
        }


        $discord = new Discord([
            'token' => $this->discordBotToken,
            'intents' => Intents::getAllIntents(),
        ]);

        $discord->on('ready', function (Discord $discord) use ($filteredDeals, $offerConfiguration, $domain, $flag, $amazonDomain, $minRating, $minReview) {
            $channel = $discord->getChannel($offerConfiguration->getChannelId());
            $embeds = [];
            $this->logger->info("Output channel id : {$offerConfiguration->getChannelId()}");

            if (null === $channel) {
                $this->logger->warning("Output channel not found");
            } else if (!$channel->getBotPermissions()->send_messages) {
                $this->logger->warning("Output channel permission denied");
            }

            $asins = array_map(function(Deal $deal) {
                return $deal->asin;
            }, $filteredDeals);

            $asinsRequest = Request::getProductRequest($amazonDomain, 0, null, null, 0, true, $asins, ['rating' => 1]);
            $response = $this->keepaAPI->sendRequestWithRetry($asinsRequest);
            $ratings = [];
            if ($response->status === ResponseStatus::OK) {
                foreach ($response->products as $product) {
                    $rating = !isset($product->csv[CSVType::RATING]) ? -1 : ProductAnalyzer::getLast($product->csv[CSVType::RATING], CSVTypeWrapper::getCSVTypeFromIndex(CSVType::RATING));
                    $reviews = !isset($product->csv[CSVType::COUNT_REVIEWS]) ? -1 : ProductAnalyzer::getLast($product->csv[CSVType::COUNT_REVIEWS], CSVTypeWrapper::getCSVTypeFromIndex(CSVType::COUNT_REVIEWS));

                    if ($rating === null) {
                        $rating = -1;
                    }

                    if ($reviews === null) {
                        $reviews = -1;
                    }

                    $ratings[$product->asin] = [
                        'rating' => $rating,
                        'reviews' => $reviews,
                    ];
                }
            }

            $offersRemoved = 0;
            /** @var Deal $filteredDeal */
            foreach ($filteredDeals as $filteredDeal) {
                $currentPrice = $filteredDeal->current[CSVType::MARKET_NEW];
                $previousPrice = $currentPrice + (-1 * $filteredDeal->deltaLast[CSVType::MARKET_NEW]);
                $weekAverage = $filteredDeal->avg[2][CSVType::MARKET_NEW];

                if ($currentPrice === $previousPrice) {
                    $offersRemoved++;
                    continue;
                }

                [
                    'rating' => $currentRating,
                    'reviews' => $currentReviews
                ] = $ratings[$filteredDeal->asin];
                $asin = $filteredDeal->asin;
                $embed = (new Embed($discord))
                    ->setAuthor("Un nouveau produit en erreur de prix a été trouvé")
                    ->setTitle($filteredDeal->title)
                    ->setURL("https://amazon.$domain/dp/$asin?tag=duckamz-21")
                    ->addFieldValues('Ancien prix', $previousPrice !== -2 ? number_format($previousPrice / 100, 2) . "€" : "-", true)
                    ->addFieldValues('Prix moyen de la semaine', $weekAverage !== -2 ? number_format($weekAverage / 100, 2) . "€" : "-", true)
                    ->addFieldValues('Prix actuel', number_format($currentPrice / 100, 2) . "€", true)
                    ->addFieldValues('Note', $currentRating === -1 ? 'Aucune' : $currentRating / 10, true)
                    ->addFieldValues('Nb de commentaires', $currentReviews === -1 ? 0 : $currentReviews, true)
                    ->addFieldValues('Pays', $flag)
                    ->setImage("https://graph.keepa.com/pricehistory.png?" . http_build_query(['asin' => $asin, 'domain' => $domain]));

                if ($filteredDeal->image !== null) {
                    $embed->setThumbnail('https://images-na.ssl-images-amazon.com/images/I/' . implode('', array_map('chr', $filteredDeal->image)));
                }

                if ($currentRating < $minRating || $currentReviews < $minReview) {
                    $embed
                        ->setFooter("⚠️ Vigilance : ce produit peut ne pas être fiable (mauvaise notes, faibles commentaires ...) - vérifiez avant d'acheter");
                }

                $embeds[$asin] = $embed;
            }

            $offers = $this->configBusiness->get('offers');
            $this->logger->info("Offer filtered because same price : $offersRemoved");
            if (!isset($offers[$amazonDomain])) {
                $offers[$amazonDomain] = [];
            }
            $embedsToSend = [];

            foreach ($embeds as $asin => $embed) {
                if (!in_array($asin, $offers[$amazonDomain])) {
                    $embedsToSend[] = $embed;
                }
            }
            $this->logger->info(count($embeds) - count($embedsToSend) . " offer already send");
            $offers[$amazonDomain] = array_keys($embeds);

            $this->configBusiness->set('offers', $offers);

            if (empty($embedsToSend)) {
                $this->logger->info("No offer to send");
                $discord->close();
            } else {
                $this->logger->info("Send " . count($embedsToSend) . " offers");
                foreach (array_chunk($embedsToSend, 10) as $embeds) {
                    $channel->sendMessage(MessageBuilder::new()->setEmbeds($embeds))->always(function () use ($discord) {
                        $this->logger->info("Closing discord bot");
                        $discord->close();
                    });
                }
            }
        });

        $this->logger->info('Discord bot to send offer starting');
        $discord->run();

        $this->logger->info('Discord bot to send offer ending');
    }
}