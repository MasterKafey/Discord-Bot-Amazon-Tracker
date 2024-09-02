<?php

namespace App\Doctrine\EventListener\Listener;

use App\Entity\Token;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::prePersist, entity: Token::class)]
class TokenListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {

    }

    public function prePersist(Token $token): void
    {
        $this->entityManager->getRepository(Token::class)->deleteTokens($token->getType(), $token->getUser());
    }
}