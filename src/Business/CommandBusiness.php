<?php

namespace App\Business;

use App\Discord\Command\AbstractDiscordCommand;
use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Interactions\Command\Command;
use Discord\Parts\Interactions\Interaction;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;

class CommandBusiness
{
    /** @var AbstractDiscordCommand[] */
    private array $commands = [];

    public function addCommand(AbstractDiscordCommand $command): void
    {
        $this->commands[] = $command;
    }

    public function __construct(
        private readonly Discord         $discord,
        private readonly LoggerInterface $logger,
    )
    {
    }

    public function getCommands(): array
    {
        $commands = [];
        foreach ($this->commands as $command) {
            $commands[] = [
                'discord' => new Command($this->discord, $command->getAttributes()),
                'callback' => function (Interaction $interaction) use ($command) {
                    try {
                        $this->logger->info($command->getName() . ' executed by ' . $interaction->user->username);
                        $promise = $command->execute($interaction);
                        if ($promise !== null) {
                            $promise->then(onRejected: function (\Throwable $throwable) use ($interaction) {
                                $this->error($throwable, $interaction);
                            });
                        }
                    } catch (\Throwable $throwable) {
                        return $this->error($throwable, $interaction);
                    }
                    return $promise;
                },
                'command' => $command,
            ];
        }
        return $commands;
    }

    private function error(\Throwable $throwable, Interaction $interaction): PromiseInterface
    {
        $lines = [
            "Impossible d'éxecuter la commande : {$throwable->getMessage()}",
        ];

        $index = 10;
        /** @var array $value */
        foreach (array_merge([['file' => $throwable->getFile(), 'line' => $throwable->getLine()]], $throwable->getTrace()) as ['file' => $file, 'line' => $line]) {
            --$index;
            $lines[] = "**$line**:\t$file";
            if ($index <= 0) {
                break;
            }
        }

        $this->logger->error(implode("\n", $lines));

        return $interaction->respondWithMessage(MessageBuilder::new()->setContent(implode("\n", $lines)));
    }
}