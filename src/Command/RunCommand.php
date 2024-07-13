<?php

namespace App\Command;

use App\Business\ListenerBusiness;
use Discord\Discord;
use App\Business\CommandBusiness;
use Discord\Parts\Interactions\Interaction;
use Discord\WebSockets\Event;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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

    public function __construct(
        private readonly CommandBusiness  $commandBusiness,
        private readonly ListenerBusiness $listenerBusiness,
        private readonly Discord          $discord,
        private readonly LoggerInterface  $logger
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
                    $this->discord->on($listener->getDiscordEvent(), function(...$args) use ($listener) {
                        $listener(...$args);
                    });
                }
                $this->logger->info('Discord setup finished');
            });
        });

        $this->logger->info('Discord bot starting');
        $this->discord->run();
        $this->logger->info('Ending app:run command');

        return Command::SUCCESS;
    }
}