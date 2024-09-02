<?php

namespace App\Business;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserBusiness
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function hashPassword(User $user): void
    {
        if (null === $user->getPlainPassword()) {
            return;
        }

        $user->setPassword($this->hasher->hashPassword($user, $user->getPlainPassword()));
    }
}