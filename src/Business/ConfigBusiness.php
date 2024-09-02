<?php

namespace App\Business;

use Symfony\Component\Yaml\Yaml;

class ConfigBusiness
{
    private static string $configFilePath = __DIR__ . '/../../data/configuration.yaml';

    public static function get($key): mixed
    {
        return array_merge(self::getDefaults(), self::getFileContent())[$key] ?? null;
    }

    public static function set(string $key, mixed $value): void
    {
        if (!array_key_exists($key, self::getDefaults())) {
            throw new \Exception("Invalid configuration key '$key'");
        }

        self::setFileContent(array_merge(self::getFileContent(), [$key => $value]));
    }

    private static function getFileContent(): array
    {
        if (!file_exists(self::$configFilePath)) {
            if (!is_dir(dirname(self::$configFilePath))) {
                mkdir(dirname(self::$configFilePath), 775, true);
            }
            self::setFileContent([]);
        }

        return Yaml::parseFile(self::$configFilePath);
    }

    private static function setFileContent(array $data): void
    {
        file_put_contents(self::$configFilePath, Yaml::dump($data));
    }

    public static function getDefaults(): array
    {
        return [
            'review_warning' => 50,
            'rating_warning' => 30,
            'partner_id' => null,
            'min_sales' => 0,
            'min_reviews' => 0,
            'min_rating' => 0,
            'minute_interval' => 5,
            'discord_token' => null,
            'keepa_token' => null,
            'google_emoji_id' => null,
            'aliexpress_emoji_id' => null,
            'amazon_emoji_id' => null,
        ];
    }
}