<?php

namespace App\Scheduler;

use App\MessageHandler\Message\CheckProductsPriceMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule]
class MinuteScheduler implements ScheduleProviderInterface
{
    public function __construct(
        #[Autowire(env: 'REQUEST_INTERVAL')]
        private readonly string $interval
    )
    {

    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(
            RecurringMessage::every(new \DateInterval($this->interval), new CheckProductsPriceMessage()),
        );
    }
}