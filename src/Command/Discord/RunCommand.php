<?php

namespace App\Command\Discord;

use App\Business\CommandBusiness;
use App\Business\ConfigBusiness;
use App\Business\ListenerBusiness;
use App\Entity\OfferConfiguration;
use App\MessageHandler\Message\SendChannelMessageMessage;
use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Embed\Embed;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use function React\Promise\all;

#[AsCommand(name: 'app:discord:run', description: 'Start discord bot')]
class RunCommand extends Command
{
    const COMMAND_TO_DELETE = [
        'add-product',
        'detail-product',
        'excluded-category',
        'list-products',
        'set-percentage',
        'set-output-channel',
        'set-interval'
    ];

    private array $processingQueue = [];
    private int $batchSize = 10;

    public function __construct(
        private readonly CommandBusiness   $commandBusiness,
        private readonly ListenerBusiness  $listenerBusiness,
        private readonly Discord           $discord,
        private readonly LoggerInterface   $logger,
        private readonly ReceiverInterface $receiver,
        private readonly ConfigBusiness $configBusiness,
    )
    {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Starting app:discord:run command');

        $this->discord->on('ready', function () {
            $this->logger->info('Discord bot started');
            $promises = [];
            $this->logger->info('Discord bot delete commands');
            foreach ($this->discord->guilds as $guild) {
                foreach ($guild->commands as $command) {
                    /** @var \Discord\Parts\Interactions\Command\Command $command */
                    $promises[] = $guild->commands->delete($command);
                }
            }

            $promises[] = $this->discord->application->commands->freshen()->then(function ($commands) {
                $promises = [];
                foreach ($commands as $command) {
                    if (in_array($command->name, self::COMMAND_TO_DELETE)) {
                        $promises[] = $this->discord->application->commands->delete($command);
                    }
                }

                return all($promises);
            });

            all($promises)->then(function () {
                $this->logger->info('Discord bot saving commands');
                $commands = $this->commandBusiness->getCommands();
                foreach ($commands as $command) {
                    $this->discord->application->commands->save($command['discord'])->then(onRejected: fn() => $this->logger->error(
                        "Can't register {$command['discord']->name} command"
                    ));
                    $this->discord->listenCommand($command['discord']->name, $command['callback']);
                }
            })->then(function () {
                $this->logger->info('Discord bot register listener');
                $listeners = $this->listenerBusiness->getListeners();
                foreach ($listeners as $listener) {
                    $this->discord->on($listener->getDiscordEvent(), function (...$args) use ($listener) {
                        $listener(...$args);
                    });
                }
                $this->logger->info('Discord setup finished');
            })->then(function () {
                $this->discord->getLoop()->addPeriodicTimer(1, function () {
                    if (count($this->processingQueue) >= $this->batchSize) {
                        return;
                    }

                    $envelopes = $this->receiver->get();

                    if (empty($envelopes)) {
                        return;
                    }

                    $this->processingQueue = array_chunk($envelopes, $this->batchSize)[0];
                    $i = 0;
                    while (!empty($this->processingQueue)) {
                        $key = array_key_first($this->processingQueue);
                        $envelope = $this->processingQueue[$key];
                        unset($this->processingQueue[$key]);
                        $message = $envelope->getMessage();
                        if ($i >= $this->batchSize || !($message instanceof SendChannelMessageMessage)) {
                            return;
                        }
                        ++$i;
                        try {
                            $this->sendMessage($message);
                        } catch (\Throwable $exception) {
                            $this->logger->error($exception->getMessage());
                            $this->logger->error($exception->getLine() . " " . $exception->getFile());
                        }
                        $this->receiver->ack($envelope);
                    }
                });
            });
        });

        $this->logger->info('Discord bot starting');
        $this->discord->run();
        $this->logger->info('Ending app:run command');

        return Command::SUCCESS;
    }

    private function sendMessage(SendChannelMessageMessage $message): PromiseInterface
    {
        $payloads = $message->getPayload();
        $embeds = [];
        foreach ($payloads as $payload) {
            $author = $payload['author'];
            $weekAverage = $payload['weekAverage'];
            $currentPrice = $payload['currentPrice'];
            $currentRating = $payload['currentRating'];
            $percentage = $payload['percentage'];
            $flag = $payload['flag'];
            $thumbnail = $payload['thumbnail'] ?? null;
            $footer = $payload['footer'] ?? null;
            $googleUrl = $payload['googleUrl'];
            $aliexpressURL = $payload['aliexpressUrl'];
            $asin = $payload['asin'];
            $domain = $payload['domain'];
            $reviews = $payload['reviews'];
            $view = $payload['view'] ?? OfferConfiguration::BUYER_VIEW;
            $infos = $payload['info'];

            $amazonEmojiId = $this->configBusiness->get('amazon_emoji_id');
            $googleEmojiId = $this->configBusiness->get('google_emoji_id');
            $aliexpressEmojiId = $this->configBusiness->get('aliexpress_emoji_id');
            $embed = (new Embed($this->discord))
                ->setAuthor($author)
                ->setTitle((null === $amazonEmojiId ? "<:amazon:$amazonEmojiId> " : '') . $flag . ' ' . $payload['title'])
                ->setURL($payload['url'])
                ->setImage("https://graph.keepa.com/pricehistory.png?" . http_build_query(['asin' => $asin, 'domain' => $domain]));

            if ($view === OfferConfiguration::BUYER_VIEW) {
                $embed
                    ->addFieldValues('Prix moyen', $weekAverage !== -2 ? number_format($weekAverage / 100, 2) . "€" : "-", true)
                    ->addFieldValues('Nouveau prix', number_format($currentPrice / 100, 2) . "€", true)
                    ->addFieldValues('Réduction', "$percentage%", true)
                    ->addFieldValues('Note', $currentRating === 0 ? 'Aucune' : $currentRating / 10, true)
                    ->addFieldValues('Avis', $reviews === 0 ? 'Aucun' : $reviews, true)
                    ->addFieldValues('Comparer sur', "[" . (null === $googleEmojiId ? '' : "<:google:$googleEmojiId>") . " Google]($googleUrl) [" . (null === $aliexpressEmojiId ? '' : "<:aliexpress:$aliexpressEmojiId>") . " Aliexpress]($aliexpressURL)");
            } else if ($view === OfferConfiguration::SELLER_VIEW) {
                $dimension = $infos['dimension'] ?? null;
                $embed->addFieldValues('ASIN', $asin, true);
                $eanList = $infos['ean_list'];
                $drops = $infos['drops'];
                $referralFeePercentage = $infos['referral_fee_percentage'];
                $fbaFees = $infos['fba_fees']?->pickAndPackFee ?? 0;
                $currentBuyBoxPrice = $infos['current_buy_box_price'];

                if (!empty($eanList)) {
                    $embed->addFieldValues('EAN', implode(', ', $eanList));
                }

                if (null !== $dimension) {
                    $height = ($dimension['height'] ?? 0) / 10;
                    $width = ($dimension['width'] ?? 0) / 10;
                    $length = ($dimension['length'] ?? 0) / 10;

                    if (!in_array(0, [$height, $width, $length])) {
                        $embed
                            ->addFieldValues('Dimension', "$height * $width * $length cm", true);
                    }
                    $weight = $dimension['weight'] ?? 0;
                    if ($weight !== 0) {
                        $embed->addFieldValues('Poids', "$weight g", true);
                    }
                }
                $newOfferCount = $infos['new_offer_count'] ?? 0;
                if ($newOfferCount !== 0) {
                    $embed->addFieldValues("Nombre d'offres", $newOfferCount, true);
                }

                $day30 = $drops['30_days'];
                $day90 = $drops['90_days'];
                $day180 = $drops['180_days'];

                if ((array_count_values([$day30, $day90, $day180])[0] ?? 0) !== 3) {
                    $embed->addFieldValues('Classement ventes', '');
                    $first = true;
                    if (0 !== $day30) {
                        $embed->addFieldValues('Drop 30 jours', $day30, true);
                        $first = false;
                    }

                    if (0 !== $day90) {
                        $embed->addFieldValues('Drop 90 jours', $day90, $first);
                        $first = false;
                    }

                    if (0 !== $day180) {
                        $embed->addFieldValues('Drop 180 jours', $day180, $first);
                    }
                }

                if ((0 !== $averageBuyBox180Days = $infos['average_buy_box']['180_days']) || -1 !== $averageBuyBox180Days) {
                    $embed->addFieldValues('Buy Box - 180 jours', $averageBuyBox180Days);
                }

                if (0 !== $fbaFees) {
                    $embed->addFieldValues("FBA Fees", number_format($fbaFees / 100, 2), true);
                }

                if (0 !== $referralFeePercentage) {
                    $embed->addFieldValues('Pourcentage Fee', $referralFeePercentage . '%', true);
                }

                if (0 !== $currentBuyBoxPrice && 0 !== $referralFeePercentage) {
                    $embed->addFieldValues('', number_format($currentBuyBoxPrice * $referralFeePercentage / 10000, 2), true);
                }
            }

            if ($thumbnail !== null) {
                $embed->setThumbnail($thumbnail);
            }

            if (null !== $footer) {
                $embed->setFooter($footer);
            }

            $embeds[] = $embed;
        }

        $promises = [];
        foreach (array_chunk($embeds, 10) as $chunkEmbed) {
            $promises[] = $this->discord->getChannel($message->getChannelId())->sendMessage(MessageBuilder::new()->setEmbeds($chunkEmbed));
        }

        return all($promises);
    }
}