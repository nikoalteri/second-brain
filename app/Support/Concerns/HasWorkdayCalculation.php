<?php

namespace App\Support\Concerns;

use Carbon\Carbon;

trait HasWorkdayCalculation
{
    protected function adjustToWorkday(Carbon $date, bool $skipWeekends = true): Carbon
    {
        if (! $skipWeekends) {
            return $date;
        }

        while ($this->isWeekend($date) || $this->isItalianHoliday($date)) {
            $date->addDay();
        }

        return $date;
    }

    protected function isWeekend(Carbon $date): bool
    {
        return $date->isSaturday() || $date->isSunday();
    }

    protected function isItalianHoliday(Carbon $date): bool
    {
        $fixedHolidays = [
            '01-01',
            '01-06',
            '04-25',
            '05-01',
            '06-02',
            '08-15',
            '11-01',
            '12-08',
            '12-25',
            '12-26',
        ];

        if (in_array($date->format('m-d'), $fixedHolidays, true)) {
            return true;
        }

        $easter = Carbon::createFromTimestamp(easter_date($date->year))->startOfDay();
        $easterMonday = $easter->copy()->addDay();

        return $date->isSameDay($easter) || $date->isSameDay($easterMonday);
    }
}
