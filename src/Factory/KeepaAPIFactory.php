<?php

namespace App\Factory;

use App\Business\ConfigBusiness;
use Keepa\KeepaAPI;

class KeepaAPIFactory
{
    public static function getKeepaAPI(): KeepaAPI
    {
        return new KeepaAPI(ConfigBusiness::get('keepa_token'));
    }
}