<?php

namespace App\MessageHandler\Handler;

use App\Business\ConfigBusiness;
use App\Entity\ExcludedCategory;
use App\Entity\OfferConfiguration;
use App\Factory\DiscordFactory;
use App\MessageHandler\Message\CheckProductsPriceMessage;
use App\MessageHandler\Message\SendChannelMessageMessage;
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
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class CheckProductsPriceMessageHandler
{
    public function __construct(
        private readonly KeepaAPI               $keepaAPI,
        private readonly ConfigBusiness         $configBusiness,
        private readonly LoggerInterface        $logger,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface    $messageBus,
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
            if (!empty($offerConfiguration->getCategories())) {
                $request->includeCategories = array_map('intval', $offerConfiguration->getCategories());
            }
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
                if ($currentTime - (KeepaTime::keepaMinuteToUnixInMillis($deal->lastUpdate) / 1000) <= 3600) {
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

        $this->logger->info("Output channel id : {$offerConfiguration->getChannelId()}");

        $asins = array_map(function (Deal $deal) {
            return $deal->asin;
        }, $filteredDeals);

        $ratings = [];
        foreach (array_chunk($asins, 100) as $chunked_asins) {
            $asinsRequest = Request::getProductRequest($amazonDomain, 0, null, null, 0, true, $chunked_asins, ['rating' => 1]);
            $response = $this->keepaAPI->sendRequestWithRetry($asinsRequest);
            if ($response->status === ResponseStatus::OK) {
                foreach ($response->products as $product) {
                    $rating = !isset($product->csv[CSVType::RATING]) ? -1 : ProductAnalyzer::getLast($product->csv[CSVType::RATING], CSVTypeWrapper::getCSVTypeFromIndex(CSVType::RATING));
                    $reviews = !isset($product->csv[CSVType::COUNT_REVIEWS]) ? -1 : ProductAnalyzer::getLast($product->csv[CSVType::COUNT_REVIEWS], CSVTypeWrapper::getCSVTypeFromIndex(CSVType::COUNT_REVIEWS));

                    if ($rating === null) {
                        $rating = 0;
                    }

                    if ($reviews === null) {
                        $reviews = 0;
                    }

                    $ratings[$product->asin] = [
                        'rating' => $rating,
                        'reviews' => $reviews,
                    ];
                }
            }
        }

        $offersRemoved = 0;
        /** @var Deal $filteredDeal */
        $payloads = [];
        foreach ($filteredDeals as $filteredDeal) {
            $currentPrice = $filteredDeal->current[CSVType::MARKET_NEW];
            $previousPrice = $currentPrice + (-1 * $filteredDeal->deltaLast[CSVType::MARKET_NEW]);
            $weekAverage = $filteredDeal->avg[2][CSVType::MARKET_NEW];

            if ($currentPrice === $previousPrice) {
                $offersRemoved++;
                continue;
            }

            if ($offerConfiguration->isWeekAverage() && $previousPrice > 0 && $currentPrice > 0) {
                $weekPercentage = ($currentPrice - $weekAverage) / $weekAverage * -100;

                if ($weekPercentage < $offerConfiguration->getMinPercentage() || $weekPercentage > $offerConfiguration->getMaxPercentage()) {
                    $offersRemoved++;
                    continue;
                }
            }

            [
                'rating' => $currentRating,
                'reviews' => $currentReviews
            ] = $ratings[$filteredDeal->asin];


            if ($currentReviews < $this->configBusiness->get('min_reviews')) {
                continue;
            }

            if ($currentRating < $this->configBusiness->get('min_rating')) {
                continue;
            }

            $asin = $filteredDeal->asin;

            $url = "https://amazon.$domain/dp/$asin";
            $partnerId = $this->configBusiness->get('partner_id');

            if (null !== $partnerId) {
                $url .= "?tag=" . urlencode($partnerId);
            }

            if ($previousPrice > 0 && $currentPrice > 0) {
                $positivePercentage = round(($previousPrice - $currentPrice) / $previousPrice * 100);
                $percentage = $positivePercentage * -1;
                if ($positivePercentage < $offerConfiguration->getMinPercentage() || $positivePercentage > $offerConfiguration->getMaxPercentage()) {
                    continue;
                }
            } else {
                continue;
            }

            $googleSearchQuery = http_build_query([
                'q' => $filteredDeal->title,
                'tbm' => 'shop'
            ]);
            $payloads[$asin] = [
                'title' => $filteredDeal->title,
                'url' => $url,
                'previousPrice' => $previousPrice,
                'weekAverage' => $weekAverage,
                'currentPrice' => $currentPrice,
                'currentRating' => $currentRating,
                'percentage' => $percentage,
                'flag' => $flag,
                'googleSearchQuery' => $googleSearchQuery,
                'asin' => $asin,
                'domain' => $domain,
                'reviews' => $currentReviews,
            ];

            if ($filteredDeal->image !== null) {
                $payloads[$asin]['thumbnail'] = 'https://images-na.ssl-images-amazon.com/images/I/' . implode('', array_map('chr', $filteredDeal->image));
            }

            if ($currentRating < $minRating || $currentReviews < $minReview) {
                $payloads[$asin]['footer'] = "⚠️ Vigilance : ce produit peut ne pas être fiable (mauvaise notes, faibles commentaires ...) - vérifiez avant d'acheter";
            }
        }

        $offers = $offerConfiguration->getLastOffersSent();
        $this->logger->info("Offer filtered because same price : $offersRemoved");
        $embedsToSend = [];

        foreach ($payloads as $asin => $embed) {
            if (!in_array($asin, $offers)) {
                $embedsToSend[$asin] = $embed;
            }
        }

        $this->logger->info(count($payloads) - count($embedsToSend) . " offer already send");

        $offerConfiguration->setLastOffersSent(array_keys($payloads));
        $this->entityManager->persist($offerConfiguration);
        $this->entityManager->flush();

        if (!empty($embedsToSend)) {
            $this->messageBus->dispatch((new SendChannelMessageMessage())->setChannelId($offerConfiguration->getChannelId())->setPayload($embedsToSend));
        }
    }
}