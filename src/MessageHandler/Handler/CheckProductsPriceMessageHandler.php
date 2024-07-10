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
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CheckProductsPriceMessageHandler
{
    public function __construct(
        private readonly KeepaAPI       $keepaAPI,
        private readonly string         $discordBotToken,
        private readonly ConfigBusiness $configBusiness
    )
    {

    }

    public function __invoke(CheckProductsPriceMessage $message): void
    {
        $currentTime = floor(time() / 60) * 60;
        $outputChannelId = $this->configBusiness->get('output_channel');

        if (null === $outputChannelId) {
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

        $page = 0;
        $filteredDeals = [];
        do {
            $request = new DealRequest();
            $request->page = $page++;
            $request->domainId = $message->getAmazonDomain();
            $request->priceTypes = [CSVType::MARKET_NEW];
            $request->dateRange = 0;
            $request->deltaPercentRange = [$this->configBusiness->get('lowest_percentage'), 100];
            $request->isLowest = true;
            $request->isLowestOffer = true;
            $request->isRangeEnabled = true;
            $request->hasReviews = true;
            $request->sortType = 4;


            $r = Request::getDealsRequest($request);
            $response = $this->keepaAPI->sendRequestWithRetry($r);

            if ($response->status !== ResponseStatus::OK) {
                return;
            }

            foreach ($response->deals->dr as $deal) {
                if ($currentTime - (KeepaTime::keepaMinuteToUnixInMillis($deal->lastUpdate) / 1000) <= 360) {
                    $filteredDeals[] = $deal;
                }
            }
        } while (count($response?->deals?->dr ?? []) === 150);

        if (empty($filteredDeals)) {
            return;
        }

        $discord = new Discord([
            'token' => $this->discordBotToken,
            'intents' => Intents::getAllIntents(),
        ]);


        $discord->on('ready', function (Discord $discord) use ($filteredDeals, $outputChannelId, $domain, $flag, $message) {
            $channel = $discord->getChannel($outputChannelId);
            $embeds = [];
            $offers = $this->configBusiness->get('offers');
            /** @var Deal $filteredDeal */
            foreach ($filteredDeals as $filteredDeal) {
                $currentPrice = $filteredDeal->current[CSVType::MARKET_NEW];
                $previousPrice = $currentPrice + (-1 * $filteredDeal->deltaLast[CSVType::MARKET_NEW]);
                $weekAverage = $filteredDeal->avg[2][CSVType::MARKET_NEW];

                if ($currentPrice === $previousPrice) {
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
            if (!isset($offers[$message->getAmazonDomain()])) {
                $offers[$message->getAmazonDomain()] = [];
            }
            $embedsToSend = [];

            foreach ($embeds as $asin => $embed) {
                if (!in_array($asin, $offers[$message->getAmazonDomain()])) {
                    $embedsToSend[] = $embed;
                }
            }
            $offers[$message->getAmazonDomain()] = array_keys($embeds);
            $this->configBusiness->set('offers', $offers);

            if (empty($embedsToSend)) {
                $discord->close();
            } else {
                $channel->sendMessage(MessageBuilder::new()->setEmbeds($embedsToSend))->always(function () use ($discord) {
                    $discord->close();
                });
            }
        });

        $discord->run();
    }
}