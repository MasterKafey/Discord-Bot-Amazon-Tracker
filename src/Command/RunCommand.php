<?php

namespace App\Command;

use App\Business\ListenerBusiness;
use Discord\Discord;
use App\Business\CommandBusiness;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function React\Promise\all;

#[AsCommand(name: 'app:run', description: 'Start discord bot')]
class RunCommand extends Command
{

    public function __construct(
        private readonly CommandBusiness  $commandBusiness,
        private readonly ListenerBusiness $listenerBusiness,
        private readonly Discord          $discord
    )
    {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->discord->on('ready', function () {
            $promises = [];
            foreach ($this->discord->guilds as $guild) {
                foreach ($guild->commands as $command) {
                    /** @var \Discord\Parts\Interactions\Command\Command $command */
                    $promises[] = $guild->commands->delete($command);
                }
            }

            all($promises)->then(function () {
                $commands = $this->commandBusiness->getCommands();
                foreach ($commands as $command) {
                    $this->discord->application->commands->save($command['discord']);
                    $this->discord->listenCommand($command['discord']->name, $command['callback']);
                }
            })->then(function () {
                $listeners = $this->listenerBusiness->getListeners();
                foreach ($listeners as $listener) {
                    $this->discord->on($listener->getDiscordEvent(), $listener);
                }
            });
        });

        $this->discord->run();

        return Command::SUCCESS;
    }
}