<?php

/**
 * League.Period (https://period.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Period;

use DateInterval;
use DateTime;
use DateTimeZone;
use League\Period\Duration;
use League\Period\Period;

class DurationTest extends TestCase
{
    public function testCreateFromDateString(): void
    {
        $duration = Duration::createFromDateString('+1 DAY');
        $altduration = Duration::createFromDateString('foobar');
        if (false !== $duration) {
            self::assertSame(1, $duration->d);
            self::assertFalse($duration->days);
        }

        if (false !== $altduration) {
            self::assertSame(0, $altduration->s);
        }
    }

    /**
     * @dataProvider getISO8601StringProvider
     *
     * @param mixed $input duration
     */
    public function testISO8601String($input, string $expected): void
    {
        self::assertSame($expected, (string) Duration::create($input));
    }

    public function getISO8601StringProvider(): array
    {
        return [
            'date only' => [
                'input' => new DateInterval('P1M'),
                'expected' => 'P1M',
            ],
            'time only' => [
                'input' => new DateInterval('PT1H'),
                'expected' => 'PT1H',
            ],
            'from a period object' => [
                'input' => Period::fromMonth(2018, 2),
                'expected' => 'P1M',
            ],
            'from a week' => [
                'input' => '1 WEEK',
                'expected' => 'P7D',
            ],
            'from an integer' => [
                'input' => 0,
                'expected' => 'PT0S',
            ],
            'microseconds' => [
                'input' => new Period('2012-02-06 08:25:32.000120', '2012-02-06 08:25:32.000130'),
                'expected' => 'PT0.00001S',
            ],
            'negative seconds' => [
                'input' => '-3 secondes 10 microseconds',
                'expected' => 'PT-3.00001S',
            ],
            'duration with microseconds' => [
                'input' => new Duration('PT0.0001S'),
                'expected' => 'PT0.0001S',
            ],
       ];
    }

    public function testIntervalWithFraction(): void
    {
        $duration = new Duration('PT3.1S');
        self::assertSame('PT3.1S', (string) $duration);

        $duration = new Duration('P0000-00-00T00:05:00.023658');
        self::assertSame('PT5M0.023658S', (string) $duration);
        self::assertSame(0.023658, $duration->f);
    }

    /**
     * @dataProvider fromChronoProvider
     */
    public function testCreateFromTimeString(string $chronometer, string $expected, int $revert): void
    {
        $duration = Duration::create($chronometer);
        self::assertSame($expected, (string) $duration);
        self::assertSame($revert, $duration->invert);
    }

    public function fromChronoProvider(): iterable
    {
        return [
            'seconds' => [
                'chronometer' => '1',
                'expected' => 'PT1S',
                'invert' => 0,
            ],
            'minute and seconds' => [
                'chronometer' => '1:2',
                'expected' => 'PT1M2S',
                'invert' => 0,
            ],
            'hour, minute, seconds' => [
                'chronometer' => '1:2:3',
                'expected' => 'PT1H2M3S',
                'invert' => 0,
            ],
            'handling 0 prefix' => [
                'chronometer' => '00001:00002:000003.0004',
                'expected' => 'PT1H2M3.0004S',
                'invert' => 0,
            ],
            'negative chrono' => [
                'chronometer' => '-12:28.5',
                'expected' => 'PT12M28.5S',
                'invert' => 1,
            ],
            'negative chrono with seconds' => [
                'chronometer' => '-28.5',
                'expected' => 'PT28.5S',
                'invert' => 1,
            ],
        ];
    }

    /**
     * @dataProvider withoutCarryOverDataProvider
     *
     * @param mixed $reference_date a valid datepoint
     */
    public function testWithoutCarryOver(string $input, $reference_date, string $expected): void
    {
        $duration = new Duration($input);
        self::assertSame($expected, (string) $duration->withoutCarryOver($reference_date));
    }

    public function withoutCarryOverDataProvider(): iterable
    {
        return [
            'nothing to carry over' => [
                'input' => 'PT3H',
                'reference_date' => 0,
                'expected' => 'PT3H',
            ],
            'hour transformed in days' => [
                'input' => 'PT24H',
                'reference_date' => 0,
                'expected' => 'P1D',
            ],
            'days transformed in months' => [
                'input' => 'P31D',
                'reference_date' => 0,
                'expected' => 'P1M',
            ],
            'months transformed in years' => [
                'input' => 'P12M',
                'reference_date' => 0,
                'expected' => 'P1Y',
            ],
            'leap year' => [
                'input' => 'P29D',
                'reference_date' => '2020-02-01',
                'expected' => 'P1M',
            ],
            'none leap year' => [
                'input' => 'P29D',
                'reference_date' => '2019-02-01',
                'expected' => 'P1M1D',
            ],
            'dst day' => [
                'input' => 'PT4H',
                'reference_date' => new DateTime('2019-03-31', new DateTimeZone('Europe/Brussels')),
                'expected' => 'PT3H',
            ],
            'non dst day' => [
                'input' => 'PT4H',
                'reference_date' => new DateTime('2019-04-01', new DateTimeZone('Europe/Brussels')),
                'expected' => 'PT4H',
            ],
        ];
    }

    public function testWithoutCarryOverReturnsSameInstance(): void
    {
        $interval = Period::fromMonth(2018, 2);
        $duration = Duration::create($interval);
        self::assertSame($duration, $duration->withoutCarryOver($interval->getStartDate()));
    }
}
