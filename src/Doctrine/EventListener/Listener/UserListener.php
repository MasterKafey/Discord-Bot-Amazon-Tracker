<?php

namespace App\Doctrine\EventListener\Listener;

use App\Business\UserBusiness;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::prePersist, entity: User::class)]
class UserListener
{
    public function __construct(
        private readonly UserBusiness $userBusiness
    )
    {
    }

    public function prePersist(User $user): void
    {
        $this->userBusiness->hashPassword($user);
    }

    public function preUpdate(User $user): void
    {
        $this->userBusiness->hashPassword($user);
    }
}