<?php

namespace App\Scheduler;

use App\Business\ConfigBusiness;
use App\MessageHandler\Message\CheckProductsPriceMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule]
class MinuteScheduler implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        $minutes = ConfigBusiness::get('minute_interval');
        return (new Schedule())->add(
            RecurringMessage::every(new \DateInterval("PT" . $minutes . "M"), new CheckProductsPriceMessage()),
        );
    }
}