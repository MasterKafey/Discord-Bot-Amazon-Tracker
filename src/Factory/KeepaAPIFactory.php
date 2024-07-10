<?php

namespace App\Factory;

use Keepa\KeepaAPI;

class KeepaAPIFactory
{
    public static function getKeepaAPI(string $keepaToken): KeepaAPI
    {
        return new KeepaAPI($keepaToken);
    }
}