<?php

namespace App\Factory;

use Discord\Discord;
use Discord\WebSockets\Intents;
use Psr\Log\LoggerInterface;

class DiscordFactory
{
    public static function getDiscord(string $discordBotToken, LoggerInterface $logger): Discord
    {
        return new Discord([
            'token' => $discordBotToken,
            'intents' => Intents::getAllIntents(),
            'logger' => $logger
        ]);
    }
}