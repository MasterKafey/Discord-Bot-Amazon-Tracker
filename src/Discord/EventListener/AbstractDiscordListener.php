<?php

namespace App\Discord\EventListener;

abstract class AbstractDiscordListener
{
    public abstract function getDiscordEvent(): string;

    abstract public function __invoke(...$args);
}