<?php

namespace App\Tests\Time;

use App\Time\BusinessClock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class BusinessClockTest extends TestCase
{
    private function at(string $utc): BusinessClock
    {
        return new BusinessClock(new MockClock(new \DateTimeImmutable($utc, new \DateTimeZone('UTC'))));
    }

    public function testNewYearArrivesInMadridBeforeUtc(): void
    {
        // 23:30 UTC on 31 December is 00:30 on 1 January in Madrid (winter, UTC+1)
        $clock = $this->at('2026-12-31 23:30:00');

        self::assertSame('2027-01-01', $clock->today()->format('Y-m-d'));
        self::assertSame('2027-01-01T00:30:00+01:00', $clock->localNow()->format('c'));
        self::assertSame('2026-12-31T23:30:00+00:00', $clock->now()->format('c'), 'the stored instant stays UTC');
    }

    /** Spring forward, Sunday 29 March 2026 at 01:00 UTC: Madrid goes from UTC+1 to UTC+2. */
    public function testSpringDaylightSavingChange(): void
    {
        $before = $this->at('2026-03-28 23:30:00'); // 00:30 CET on the 29th
        self::assertSame('2026-03-29', $before->today()->format('Y-m-d'));
        self::assertSame('+01:00', $before->localNow()->format('P'));

        $after = $this->at('2026-03-29 22:30:00'); // 00:30 CEST on the 30th
        self::assertSame('2026-03-30', $after->today()->format('Y-m-d'));
        self::assertSame('+02:00', $after->localNow()->format('P'));
    }

    /** Fall back, Sunday 25 October 2026 at 01:00 UTC: Madrid goes from UTC+2 to UTC+1. */
    public function testAutumnDaylightSavingChange(): void
    {
        $before = $this->at('2026-10-24 22:30:00'); // 00:30 CEST on the 25th
        self::assertSame('2026-10-25', $before->today()->format('Y-m-d'));
        self::assertSame('+02:00', $before->localNow()->format('P'));

        $after = $this->at('2026-10-25 22:30:00'); // 23:30 CET, still the 25th
        self::assertSame('2026-10-25', $after->today()->format('Y-m-d'));
        self::assertSame('+01:00', $after->localNow()->format('P'));
    }

    public function testQuarterEndIsDecidedInSpain(): void
    {
        // 22:30 UTC on 31 March is already 1 April in Madrid (summer, UTC+2): the second quarter
        self::assertSame('2026-04-01', $this->at('2026-03-31 22:30:00')->today()->format('Y-m-d'));
        // and 22:30 UTC on 30 June is already 1 July: the third quarter
        self::assertSame('2026-07-01', $this->at('2026-06-30 22:30:00')->today()->format('Y-m-d'));
    }

    public function testATypedTimeIsSpanishTimeStoredAsUtc(): void
    {
        $clock = new BusinessClock();
        // summer: UTC+2; winter: UTC+1
        self::assertSame('2026-07-01 08:30', $clock->parseLocal('2026-07-01T10:30')->format('Y-m-d H:i'));
        self::assertSame('2026-12-01 09:30', $clock->parseLocal('2026-12-01T10:30')->format('Y-m-d H:i'));
        self::assertSame('UTC', $clock->parseLocal('2026-12-01T10:30')->getTimezone()->getName());
        // a value that carries its own offset keeps it
        self::assertSame('2026-12-01 10:30', $clock->parseLocal('2026-12-01T10:30:00+00:00')->format('Y-m-d H:i'));
    }

    public function testStoredInstantsAreShownInSpanishTime(): void
    {
        $clock = new BusinessClock();
        $utc = new \DateTimeZone('UTC');
        self::assertSame('01/07/2026 10:30', $clock->format(new \DateTimeImmutable('2026-07-01 08:30', $utc), 'd/m/Y H:i'));
        // a signature at 23:30 UTC on 31 December belongs to 1 January in Spain
        self::assertSame('01/01/2027 00:30', $clock->format(new \DateTimeImmutable('2026-12-31 23:30', $utc), 'd/m/Y H:i'));
        self::assertNull($clock->format(null, 'd/m/Y'));
    }
}
