<?php

namespace App\MessageHandler\Handler;

use App\Business\ConfigBusiness;
use App\MessageHandler\Message\CheckProductsPriceMessage;
use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Embed\Embed;
use Discord\WebSockets\Intents;
use Keepa\API\DealRequest;
use Keepa\API\Request;
use Keepa\API\ResponseStatus;
use Keepa\helper\CSVType;
use Keepa\helper\KeepaTime;
use Keepa\KeepaAPI;
use Keepa\objects\AmazonLocale;
use Keepa\objects\Deal;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CheckProductsPriceMessageHandler
{
    public function __construct(
        private readonly KeepaAPI        $keepaAPI,
        private readonly string          $discordBotToken,
        private readonly ConfigBusiness  $configBusiness,
        private readonly LoggerInterface $logger
    )
    {

    }

    public function __invoke(CheckProductsPriceMessage $message): void
    {
        $currentTime = floor(time() / 60) * 60;
        $outputChannelId = $this->configBusiness->get('output_channel');
        $this->logger->info("Checking Prices for $currentTime current time");
        if (null === $outputChannelId) {
            $this->logger->warning("No output channel id found");
            return;
        }

        $domain = match ($message->getAmazonDomain()) {
            AmazonLocale::DE => 'de',
            AmazonLocale::FR => 'fr',
            AmazonLocale::ES => 'es',
            default => 'com',
        };

        $flag = match ($message->getAmazonDomain()) {
            AmazonLocale::DE => '🇩🇪',
            AmazonLocale::FR => '🇫🇷',
            AmazonLocale::ES => '🇪🇸',
            default => '🇺🇸',
        };
        $this->logger->info("Checking Prices for $domain");

        $page = 0;
        $filteredDeals = [];
        $percentage = $this->configBusiness->get('lowest_percentage');
        do {
            $this->logger->info("Request for page $page and $percentage%");
            $request = new DealRequest();
            $request->page = $page++;
            $request->domainId = $message->getAmazonDomain();
            $request->priceTypes = [CSVType::MARKET_NEW];
            $request->excludeCategories = [301061];
            $request->dateRange = 0;
            $request->deltaPercentRange = [$percentage, 100];
            $request->isLowest = true;
            $request->isLowestOffer = true;
            $request->isRangeEnabled = true;
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

        $discord->on('ready', function (Discord $discord) use ($filteredDeals, $outputChannelId, $domain, $flag, $message) {
            $channel = $discord->getChannel($outputChannelId);
            $embeds = [];
            $this->logger->info("Output channel id : $outputChannelId");

            if (null === $channel) {
                $this->logger->warning("Output channel not found");
            } else if (!$channel->getBotPermissions()->send_messages) {
                $this->logger->warning("Output channel permission denied");
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
                
                $asin = $filteredDeal->asin;
                $embeds[$asin] = (new Embed($discord))
                    ->setTitle($filteredDeal->title)
                    ->setURL("https://amazon.$domain/dp/$asin")
                    ->addFieldValues('Ancien prix', $previousPrice !== -2 ? number_format($previousPrice / 100, 2) . "€" : "-", true)
                    ->addFieldValues('Prix moyen de la semaine', $weekAverage !== -2 ? number_format($weekAverage / 100, 2) . "€" : "-", true)
                    ->addFieldValues('Prix actuel', number_format($currentPrice / 100, 2) . "€", true)
                    ->setThumbnail('https://images-na.ssl-images-amazon.com/images/I/' . implode('', array_map('chr', $filteredDeal->image)))
                    ->addFieldValues('Pays', $flag)
                    ->setImage("https://graph.keepa.com/pricehistory.png?" . http_build_query(['asin' => $asin, 'domain' => $domain]));
            }

            $offers = $this->configBusiness->get('offers');
            $this->logger->info("Offer filtered because same price : $offersRemoved");
            if (!isset($offers[$message->getAmazonDomain()])) {
                $offers[$message->getAmazonDomain()] = [];
            }
            $embedsToSend = [];

            foreach ($embeds as $asin => $embed) {
                if (!in_array($asin, $offers[$message->getAmazonDomain()])) {
                    $embedsToSend[] = $embed;
                }
            }
            $this->logger->info(count($embeds) - count($embedsToSend) . " offer already send");
            $offers[$message->getAmazonDomain()] = array_keys($embeds);

            $this->configBusiness->set('offers', $offers);

            if (empty($embedsToSend)) {
                $this->logger->info("Not offer to send");
                $discord->close();
            } else {
                $this->logger->info("Send " . count($embedsToSend) . " offers");
                $channel->sendMessage(MessageBuilder::new()->setEmbeds($embedsToSend))->always(function () use ($discord) {
                    $this->logger->info("Closing discord bot");
                    $discord->close();
                });
            }
        });

        $this->logger->info('Discord bot to send offer starting');
        $discord->run();

        $this->logger->info('Discord bot to send offer ending');
    }
}