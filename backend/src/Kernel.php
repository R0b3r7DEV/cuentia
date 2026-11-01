<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * PHP runs in UTC, explicitly, whatever php.ini says: instants are stored in UTC and Doctrine writes
     * datetimes in the default zone. Spanish calendar dates come from App\Time\BusinessClock.
     * ES: PHP corre en UTC, de forma explícita, diga lo que diga php.ini: los instantes se guardan en UTC.
     * Las fechas de calendario de España salen de App\Time\BusinessClock.
     */
    public function boot(): void
    {
        date_default_timezone_set('UTC');
        parent::boot();
    }

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
