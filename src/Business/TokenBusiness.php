<?php

namespace App\Business;

use App\Entity\Token;
use App\Entity\TokenType;
use App\Entity\User;

class TokenBusiness
{
    public function createNewToken(TokenType $type, User $user): Token
    {
        return (new Token())
            ->setType($type)
            ->setValue($this->generateTokenValue())
            ->setExpireAt((new \DateTime())->add($this->getExpirationDateTime($type)))
            ->setUser($user);
    }

    public function generateTokenValue(int $length = 16): string
    {
        return bin2hex(openssl_random_pseudo_bytes(round($length / 2)));
    }

    public function getExpirationDateTime(TokenType $type): \DateInterval
    {
        return match ($type) {
            TokenType::ForgotPassword => new \DateInterval('PT15M'),
        };
    }
}