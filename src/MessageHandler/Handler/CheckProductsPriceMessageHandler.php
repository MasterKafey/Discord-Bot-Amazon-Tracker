<?php

namespace App\MessageHandler\Handler;

use App\Business\ConfigBusiness;
use App\Entity\ExcludedCategory;
use App\Entity\OfferConfiguration;
use App\MessageHandler\Message\CheckProductsPriceMessage;
use App\MessageHandler\Message\SendChannelMessageMessage;
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
readonly class CheckProductsPriceMessageHandler
{
    public function __construct(
        private KeepaAPI               $keepaAPI,
        private ConfigBusiness         $configBusiness,
        private LoggerInterface        $logger,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface    $messageBus,
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
        $lastUpdate = null;
        foreach ($offerConfiguration->getLastOffersSent() as $offerSent) {
            if (is_iterable($offerSent) && (null === $lastUpdate || ($offerSent['last_update'] !== null && $offerSent['last_update'] < $lastUpdate))) {
                $lastUpdate = $offerSent['last_update'];
            }
        }

        if ($lastUpdate === null) {
            $lastUpdate = KeepaTime::unixInMillisToKeepaMinutes((new \DateTime())->sub(new \DateInterval('PT' . ConfigBusiness::get('minute_interval') . 'M'))->getTimestamp());
        }
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
                if ($lastUpdate === null || $deal->lastUpdate >= $lastUpdate) {
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

        $productsInfo = [];
        foreach (array_chunk($asins, 100) as $chunked_asins) {
            $asinsRequest = Request::getProductRequest($amazonDomain, 0, null, null, 0, true, $chunked_asins, ['rating' => 1]);
            $response = $this->keepaAPI->sendRequestWithRetry($asinsRequest);
            if ($response->status === ResponseStatus::OK) {
                foreach ($response->products as $product) {
                    $rating = !isset($product->csv[CSVType::RATING]) ? -1 : ProductAnalyzer::getLast($product->csv[CSVType::RATING], CSVTypeWrapper::getCSVTypeFromIndex(CSVType::RATING));
                    $reviews = !isset($product->csv[CSVType::COUNT_REVIEWS]) ? -1 : ProductAnalyzer::getLast($product->csv[CSVType::COUNT_REVIEWS], CSVTypeWrapper::getCSVTypeFromIndex(CSVType::COUNT_REVIEWS));
                    $newOfferCount = !isset($product->csv[CSVType::COUNT_NEW]) ? 0 : ProductAnalyzer::getLast($product->csv[CSVType::COUNT_NEW], CSVTypeWrapper::getCSVTypeFromIndex(CSVType::COUNT_NEW));
                    $salesCsv = $product->csv[CSVType::SALES] ?? [];
                    $buyBoxCsv = $product->csv[CSVType::BUY_BOX_SHIPPING] ?? [];
                    $average180Days = ProductAnalyzer::getValueAtTime($buyBoxCsv, KeepaTime::unixInMillisToKeepaMinutes((new \DateTime())->sub(new \DateInterval('P180D'))->getTimestamp()), CSVTypeWrapper::getCSVTypeFromIndex(CSVType::BUY_BOX_SHIPPING));
                    $currentBuyBoxPrice = ProductAnalyzer::getLast($buyBoxCsv, CSVTypeWrapper::getCSVTypeFromIndex(CSVType::BUY_BOX_SHIPPING));

                    $lastSales = ProductAnalyzer::getClosestValueAtTime($salesCsv, KeepaTime::unixInMillisToKeepaMinutes((new \DateTime())->getTimestamp()), CSVTypeWrapper::getCSVTypeFromIndex(CSVType::SALES));

                    $drops30Days = ProductAnalyzer::getClosestValueAtTime($salesCsv, KeepaTime::unixInMillisToKeepaMinutes((new \DateTime())->sub(new \DateInterval('P30D'))->getTimestamp()), CSVTypeWrapper::getCSVTypeFromIndex(CSVType::SALES));
                    $drops90Days = ProductAnalyzer::getClosestValueAtTime($salesCsv, KeepaTime::unixInMillisToKeepaMinutes((new \DateTime())->sub(new \DateInterval('P90D'))->getTimestamp()), CSVTypeWrapper::getCSVTypeFromIndex(CSVType::SALES));
                    $drops180Days = ProductAnalyzer::getClosestValueAtTime($salesCsv, KeepaTime::unixInMillisToKeepaMinutes((new \DateTime())->sub(new \DateInterval('P180D'))->getTimestamp()), CSVTypeWrapper::getCSVTypeFromIndex(CSVType::SALES));
                    if ($rating === null) {
                        $rating = 0;
                    }

                    if ($reviews === null || $reviews < 0) {
                        $reviews = 0;
                    }
                    if ($newOfferCount === null || $newOfferCount < 0) {
                        $newOfferCount = 0;
                    }

                    if ($lastSales === null || $lastSales < 0) {
                        $lastSales = 0;
                    }

                    $productsInfo[$product->asin] = [
                        'rating' => $rating,
                        'reviews' => $reviews,
                        'dimension' => [
                            'height' => $product->packageHeight,
                            'length' => $product->packageLength,
                            'width' => $product->packageWidth,
                            'weight' => $product->packageWeight,
                        ],
                        'fba_fees' => $product->fbaFees ?? 0,
                        'referral_fee_percentage' => $product->referralFeePercentage ?? 0,
                        'ean_list' => $product->eanList,
                        'new_offer_count' => $newOfferCount,
                        'last_sales' => $lastSales,
                        'drops' => [
                            '30_days' => $drops30Days,
                            '90_days' => $drops90Days,
                            '180_days' => $drops180Days,
                        ],
                        'average_buy_box' => [
                            '180_days' => $average180Days,
                        ],
                        'current_buy_box_price' => $currentBuyBoxPrice,
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
                'reviews' => $currentReviews,
                'last_sales' => $lastSales,
            ] = $productsInfo[$filteredDeal->asin];


            if ($currentReviews < $this->configBusiness->get('min_reviews')) {
                $offersRemoved++;
                continue;
            }

            if ($currentRating < $this->configBusiness->get('min_rating')) {
                $offersRemoved++;
                continue;
            }

//            if ($lastSales < $this->configBusiness->get('min_sales')) {
//                $offersRemoved++;
//                continue;
//            }

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
                    $offersRemoved++;
                    continue;
                }
            } else {
                $offersRemoved++;
                continue;
            }

            $googleSearchQuery = http_build_query([
                'q' => $filteredDeal->title,
                'tbm' => 'shop'
            ]);

            if ($currentRating <= 0 || $currentReviews <= 0) {
                $author = "⚠️ Attention : Cet article n'a aucun avis, faites vos propres recherches avant d'acheter";
            } else if ($offerConfiguration->isPremium()) {
                $author = "Un nouveau produit en erreur de prix a été trouvé";
            } else {
                $author = "Vous utilisez la version gratuite, devenez premium pour débloquer les fonctionnalités plus puissantes";
            }

            $payloads[$asin] = [
                'author' => $author,
                'title' => $filteredDeal->title,
                'url' => $url,
                'previousPrice' => $previousPrice,
                'weekAverage' => $weekAverage,
                'currentPrice' => $currentPrice,
                'currentRating' => $currentRating,
                'percentage' => $percentage,
                'flag' => $flag,
                'googleUrl' => "https://google.com/search?$googleSearchQuery",
                'aliexpressUrl' => "https://fr.aliexpress.com/w/wholesale-" . urlencode(str_replace(' ', '-', trim($filteredDeal->title))) . '.html',
                'asin' => $asin,
                'domain' => $domain,
                'reviews' => $currentReviews,
                'view' => $offerConfiguration->getView(),
                'info' => $productsInfo[$asin],
                'last_update' => $filteredDeal->lastUpdate ?? -1,
            ];

            if ($filteredDeal->image !== null) {
                $payloads[$asin]['thumbnail'] = 'https://images-na.ssl-images-amazon.com/images/I/' . implode('', array_map('chr', $filteredDeal->image));
            }

            if ($currentRating < $minRating || $currentReviews < $minReview) {
                $payloads[$asin]['footer'] = "📍 Vigilance : ce produit peut ne pas être fiable (mauvaise notes, faibles commentaires ...) - vérifiez avant d'acheter";
            }
        }

        $offers = array_keys($offerConfiguration->getLastOffersSent());
        $this->logger->info("Offer filtered because same price : $offersRemoved");
        $embedsToSend = [];

        foreach ($payloads as $asin => $embed) {
            if (!in_array($asin, $offers)) {
                $embedsToSend[$asin] = $embed;
            }
        }

        $this->logger->info(count($payloads) - count($embedsToSend) . " offer already send");

        $offerConfiguration->setLastOffersSent($payloads);
        $this->entityManager->persist($offerConfiguration);
        $this->entityManager->flush();

        if (!empty($embedsToSend)) {
            $this->messageBus->dispatch((new SendChannelMessageMessage())->setChannelId($offerConfiguration->getChannelId())->setPayload($embedsToSend));
        }
    }
}