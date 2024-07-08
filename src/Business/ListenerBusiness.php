<?php

namespace App\Business;

use App\Discord\EventListener\AbstractDiscordListener;

class ListenerBusiness
{
    private array $listeners = [];

    public function addListener($listener): void
    {
        $this->listeners[] = $listener;
    }

    /** @return AbstractDiscordListener[] */
    public function getListeners(): array
    {
        return $this->listeners;
    }
}