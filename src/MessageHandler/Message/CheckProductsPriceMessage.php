<?php

namespace App\MessageHandler\Message;

readonly class CheckProductsPriceMessage
{
    public function __construct(
        private int $amazonDomain
    )
    {

    }

    public function getAmazonDomain(): int
    {
        return $this->amazonDomain;
    }
}