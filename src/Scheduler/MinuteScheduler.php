<?php

namespace App\Scheduler;

use App\MessageHandler\Message\CheckProductsPriceMessage;
use Keepa\objects\AmazonLocale;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule]
class MinuteScheduler implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(
            RecurringMessage::every(new \DateInterval('PT6M'), new CheckProductsPriceMessage(AmazonLocale::FR)),
            RecurringMessage::every(new \DateInterval('PT6M'), new CheckProductsPriceMessage(AmazonLocale::ES)),
            RecurringMessage::every(new \DateInterval('PT6M'), new CheckProductsPriceMessage(AmazonLocale::DE)),
        );
    }
}