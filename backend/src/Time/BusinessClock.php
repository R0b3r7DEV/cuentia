<?php

namespace App\Time;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;

/**
 * The business calendar: instants are stored in UTC, but every CALENDAR fact is decided in Europe/Madrid.
 *
 * An invoice issued at 00:30 on 1 January in Madrid is still 23:30 on 31 December in UTC. Deriving the
 * issue date from UTC would date it the previous day and — because the series is the year — number it in
 * the previous year's series. The issue date is mandatory (RD 1619/2012, art. 6) and sets the VAT period,
 * so it has to be the date in Spain. The same applies to "today" for tax quarters and deadlines, and to the
 * date and time a work order shows or is signed at.
 *
 * Rules: PHP runs in UTC (set explicitly in Kernel), Doctrine stores UTC, and this class is the only place
 * that turns an instant into a Spanish calendar date or wall-clock time — and back. It wraps Symfony's
 * clock, so tests can freeze time at any instant (MockClock).
 *
 * ES: El calendario del negocio: los instantes se guardan en UTC, pero todo dato de CALENDARIO se decide en
 * Europe/Madrid. Una factura emitida a las 00:30 del 1 de enero en Madrid sigue siendo las 23:30 del 31 de
 * diciembre en UTC; derivar la fecha en UTC la fecharía el día anterior y, como la serie es el año, la
 * numeraría en la serie del año anterior. La fecha de expedición es obligatoria (RD 1619/2012, art. 6) y fija
 * el periodo de IVA, así que tiene que ser la fecha en España. Igual con el "hoy" de trimestres y plazos, y
 * con la fecha y hora de un parte o de su firma. PHP corre en UTC (fijado en Kernel), Doctrine guarda en UTC
 * y esta clase es el único sitio que convierte un instante en fecha u hora de España, y al revés.
 */
final class BusinessClock
{
    public const ZONE = 'Europe/Madrid';

    private readonly \DateTimeZone $zone;
    private readonly \DateTimeZone $utc;

    public function __construct(private readonly ClockInterface $clock = new NativeClock())
    {
        $this->zone = new \DateTimeZone(self::ZONE);
        $this->utc = new \DateTimeZone('UTC');
    }

    /** The current instant, in UTC (what gets stored). / El instante actual, en UTC (lo que se guarda). */
    public function now(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone($this->utc);
    }

    /** The current wall-clock time in Spain. / La hora actual en España. */
    public function localNow(): \DateTimeImmutable
    {
        return $this->now()->setTimezone($this->zone);
    }

    /** Today's date in Spain, at 00:00 Madrid time. / La fecha de hoy en España. */
    public function today(): \DateTimeImmutable
    {
        return $this->localNow()->setTime(0, 0);
    }

    /** A stored instant shown in Spain. / Un instante guardado, visto en España. */
    public function toLocal(?\DateTimeInterface $instant): ?\DateTimeImmutable
    {
        return $instant === null ? null : \DateTimeImmutable::createFromInterface($instant)->setTimezone($this->zone);
    }

    /** Format a stored instant as Spanish wall-clock time. / Formatea un instante guardado en hora de España. */
    public function format(?\DateTimeInterface $instant, string $format): ?string
    {
        return $this->toLocal($instant)?->format($format);
    }

    /**
     * A time a person typed ("2026-11-01T10:30", from <input type="datetime-local">) means Spanish time;
     * convert it to the UTC instant to store. A string that carries its own offset keeps it.
     * ES: Una hora escrita por una persona significa hora de España; se convierte al instante UTC a guardar.
     */
    public function parseLocal(string $value): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($value, $this->zone))->setTimezone($this->utc);
    }

    /** Today's date in Spain without a clock service, for entity defaults. / Para valores por defecto. */
    public static function localToday(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today', new \DateTimeZone(self::ZONE));
    }
}
