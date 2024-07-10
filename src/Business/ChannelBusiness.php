<?php

namespace App\Business;

use Discord\Discord;
use Discord\Parts\Channel\Channel;

readonly class ChannelBusiness
{
    public function __construct(
        private ConfigBusiness         $configBusiness,
        private Discord                $discord
    )
    {

    }

    public function getChannel(string $channelId): ?Channel
    {
        return $this->discord->getChannel($channelId);
    }

    public function setOutputChannel(Channel $channel): void
    {
        $this->configBusiness->set('output_channel', $channel->id);
    }

    public function getOutputChannel(): ?Channel
    {
        $channelId = $this->configBusiness->get('output_channel');

        if (null === $channelId) {
            return null;
        }

        return $this->getChannel($this->configBusiness->get('output_channel'));
    }
}