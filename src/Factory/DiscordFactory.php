<?php

namespace App\Factory;

use App\Business\ConfigBusiness;
use Discord\Discord;
use Discord\WebSockets\Intents;
use Psr\Log\LoggerInterface;

class DiscordFactory
{
    public static function getDiscord(LoggerInterface $logger): Discord
    {
        return new Discord([
            'token' => ConfigBusiness::get('discord_token'),
            'intents' => Intents::getAllIntents(),
            'logger' => $logger
        ]);
    }
}