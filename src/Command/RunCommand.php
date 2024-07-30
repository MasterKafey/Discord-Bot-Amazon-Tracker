<?php

namespace App\Command;

use App\Business\ListenerBusiness;
use App\MessageHandler\Message\SendChannelMessageMessage;
use Discord\Builders\MessageBuilder;
use Discord\Discord;
use App\Business\CommandBusiness;
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

#[AsCommand(name: 'app:run', description: 'Start discord bot')]
class RunCommand extends Command
{
    const COMMAND_TO_DELETE = [
        'add-product',
        'detail-product',
        'excluded-category',
        'list-products',
        'set-percentage',
        'set-output-channel'
    ];

    private array $processingQueue = [];
    private int $batchSize = 10;

    public function __construct(
        private readonly CommandBusiness   $commandBusiness,
        private readonly ListenerBusiness  $listenerBusiness,
        private readonly Discord           $discord,
        private readonly LoggerInterface   $logger,
        private readonly ReceiverInterface $receiver,
        #[Autowire(env: 'GOOGLE_EMOJI_ID')]
        private readonly string            $googleEmojiId,
    )
    {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Starting app:run command');

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
                    $this->discord->application->commands->save($command['discord']);
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
                        try {$this->sendMessage($message);} catch (\Throwable) {}
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
            $weekAverage = $payload['weekAverage'];
            $currentPrice = $payload['currentPrice'];
            $currentRating = $payload['currentRating'];
            $percentage = $payload['percentage'];
            $flag = $payload['flag'];
            $thumbnail = $payload['thumbnail'] ?? null;
            $footer = $payload['footer'] ?? null;
            $googleSearchQuery = $payload['googleSearchQuery'];
            $asin = $payload['asin'];
            $domain = $payload['domain'];
            $reviews = $payload['reviews'];

            $embed = (new Embed($this->discord))
                ->setAuthor("Un nouveau produit en erreur de prix a été trouvé")
                ->setTitle($flag . ' ' . $payload['title'])
                ->setURL($payload['url'])
                ->addFieldValues('Prix moyen', $weekAverage !== -2 ? number_format($weekAverage / 100, 2) . "€" : "-", true)
                ->addFieldValues('Nouveau prix', number_format($currentPrice / 100, 2) . "€", true)
                ->addFieldValues('Réduction', "$percentage%", true)
                ->addFieldValues('Note', $currentRating === 0 ? 'Aucune' : $currentRating / 10, true)
                ->addFieldValues('Avis', $reviews === 0 ? 'Aucun' : $reviews, true)
                ->addFieldValues('Google', "[" . (empty($this->googleEmojiId) ? '' : "<:google:$this->googleEmojiId>") . " Lien](https://google.com/search?$googleSearchQuery)", true)
                ->setImage("https://graph.keepa.com/pricehistory.png?" . http_build_query(['asin' => $asin, 'domain' => $domain]));

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