<?php

namespace App\Factory;

use Discord\Discord;
use Discord\WebSockets\Intents;

class DiscordFactory
{
    public static function getDiscord(string $discordBotToken): Discord
    {
        return new Discord([
            'token' => $discordBotToken,
            'intents' => Intents::getAllIntents(),
        ]);
    }
}