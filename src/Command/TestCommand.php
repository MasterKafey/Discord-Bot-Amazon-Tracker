<?php

namespace App\Command;

use App\MessageHandler\Handler\CheckProductsPriceMessageHandler;
use App\MessageHandler\Message\CheckProductsPriceMessage;
use Keepa\objects\AmazonLocale;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:test')]
class TestCommand extends Command
{
    public function __construct(
        private readonly CheckProductsPriceMessageHandler $handler,
        private readonly LoggerInterface $logger,
    )
    {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Starting app:test command');
        $handler = $this->handler;
        $handler(new CheckProductsPriceMessage(AmazonLocale::FR));
        $this->logger->info('Ending app:test command');

        return Command::SUCCESS;
    }
}