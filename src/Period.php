<?php
/**
 * League.Period (http://period.thephpleague.com)
 *
 * @package   League.period
 * @author    Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @copyright 2014-2015 Ignace Nyamagana Butera
 * @license   https://github.com/thephpleague/period/blob/master/LICENSE (MIT License)
 * @version   4.0.0
 * @link      https://github.com/thephpleague/period/
 */
declare(strict_types=1);

namespace League\Period;

use DateInterval;
use DatePeriod;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Generator;
use JsonSerializable;

/**
 * A immutable value object class to manipulate Time Range.
 *
 * @package League.period
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since   1.0.0
 */
final class Period implements JsonSerializable
{
    /**
     * Period starting included date point.
     *
     * @var DateTimeImmutable
     */
    protected $startDate;

    /**
     * Period ending excluded date point.
     *
     * @var DateTimeImmutable
     */
    protected $endDate;

    /**
     * @inheritdoc
     */
    public static function __set_state(array $period): self
    {
        return new static($period['startDate'], $period['endDate']);
    }

    /**
     * Create a new instance.
     *
     * @param DateTimeInterface|string $startDate starting date point
     * @param DateTimeInterface|string $endDate   ending date point
     *
     * @throws Exception If $startDate is greater than $endDate
     */
    public function __construct($startDate, $endDate)
    {
        $startDate = static::filterDatePoint($startDate);
        $endDate = static::filterDatePoint($endDate);
        if ($startDate > $endDate) {
            throw new Exception('The ending datepoint must be greater or equal to the starting datepoint');
        }
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * Validate the DateTimeInterface.
     *
     * @param DateTimeInterface|string $datetime
     *
     * @return DateTimeImmutable
     */
    protected static function filterDatePoint($datetime): DateTimeImmutable
    {
        if ($datetime instanceof DateTimeImmutable) {
            return $datetime;
        }

        if ($datetime instanceof DateTime) {
            return DateTimeImmutable::createFromMutable($datetime);
        }

        return new DateTimeImmutable($datetime);
    }

    /**
     * Create a Period object from a starting point and an interval.
     *
     * The interval can be
     * <ul>
     * <li>a DateInterval object</li>
     * <li>an int interpreted as the duration expressed in seconds.</li>
     * <li>a string in a format supported by DateInterval::createFromDateString</li>
     * </ul>
     *
     * @param DateTimeInterface|string $startDate The start date point
     * @param mixed                    $interval  The interval
     *
     * @return static
     */
    public static function createFromDuration($startDate, $interval): self
    {
        $startDate = static::filterDatePoint($startDate);

        return new static($startDate, $startDate->add(static::filterDateInterval($interval)));
    }

    /**
     * Validate a DateInterval.
     *
     * The interval can be
     * <ul>
     * <li>a DateInterval object</li>
     * <li>an int interpreted as the duration expressed in seconds.</li>
     * <li>a string in a format supported by DateInterval::createFromDateString</li>
     * </ul>
     *
     * @param mixed $interval The interval
     *
     * @return DateInterval
     */
    protected static function filterDateInterval($interval): DateInterval
    {
        if ($interval instanceof DateInterval) {
            return $interval;
        }

        if (false !== ($res = filter_var($interval, FILTER_VALIDATE_INT))) {
            return new DateInterval('PT'.$res.'S');
        }

        return DateInterval::createFromDateString($interval);
    }

    /**
     * Create a Period object from a ending datepoint and an interval.
     *
     * The interval can be
     * <ul>
     * <li>a DateInterval object</li>
     * <li>an int interpreted as the duration expressed in seconds.</li>
     * <li>a string in a format supported by DateInterval::createFromDateString</li>
     * </ul>
     *
     * @param DateTimeInterface|string $endDate  The start date point
     * @param mixed                    $interval The interval
     *
     * @return static
     */
    public static function createFromDurationBeforeEnd($endDate, $interval): self
    {
        $endDate = static::filterDatePoint($endDate);

        return new static($endDate->sub(static::filterDateInterval($interval)), $endDate);
    }

    /**
     * Create a Period object for a specific Year
     *
     * @param DateTimeInterface|string|int $year
     *
     * @return static
     */
    public static function createFromYear($year): self
    {
        if (is_int($year)) {
            return self::createFromDuration($year.'-01-01', new DateInterval('P1Y'));
        }

        $date = self::approximateDate('Y-01-01 00:00:00', static::filterDatePoint($year));

        return self::createFromDuration($date, new DateInterval('P1Y'));
    }

    /**
     * Returns a DateTimeInterface object whose value are
     * approximated to the second
     *
     * @param string            $format
     * @param DateTimeInterface $date
     *
     * @return DateTimeInterface
     */
    protected static function approximateDate(string $format, DateTimeInterface $date): DateTimeInterface
    {
        return $date->createFromFormat('Y-m-d H:i:s', $date->format($format), $date->getTimeZone());
    }

    /**
     * Create a Period object for a specific semester
     *
     * @param DateTimeInterface|string|int $year
     * @param int                          $semester Semester Index from 1 to 2
     *
     * @return static
     */
    public static function createFromSemester($year, int $semester = null): self
    {
        if (1 == func_num_args()) {
            $date = self::filterDatePoint($year);
            $month = (intdiv((int) $date->format('m'), 6) * 6) + 1;
            $date = self::approximateDate('Y-m-01 00:00:00', $date->setDate((int) $date->format('Y'), $month, 1));

            return self::createFromDuration($date, new DateInterval('P6M'));
        }

        $month = ((static::validateRange($semester, 1, 2) - 1) * 6) + 1;
        $date = new DateTimeImmutable($year.'-'.$month.'-01 00:00:00');

        return self::createFromDuration($date, new DateInterval('P6M'));
    }

    /**
     * Validate a int according to a range.
     *
     * @param int $value the value to validate
     * @param int $min   the minimum value
     * @param int $max   the maximal value
     *
     * @throws Exception If the value is not in the range
     *
     * @return int
     */
    protected static function validateRange(int $value, int $min, int $max): int
    {
        $res = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        if (false === $res) {
            throw new Exception('the submitted value is not contained within the valid range');
        }

        return $res;
    }

    /**
     * Create a Period object for a specific quarter
     *
     * @param DateTimeInterface|string|int $year
     * @param int                          $quarter Quarter Index from 1 to 4
     *
     * @return static
     */
    public static function createFromQuarter($year, int $quarter = null): self
    {
        if (1 == func_num_args()) {
            $date = self::filterDatePoint($year);
            $month = (intdiv((int) $date->format('m'), 3) * 3) + 1;
            $date = self::approximateDate('Y-m-01 00:00:00', $date->setDate((int) $date->format('Y'), $month, 1));

            return self::createFromDuration($date, new DateInterval('P3M'));
        }

        $month = ((static::validateRange($quarter, 1, 4) - 1) * 3) + 1;
        $date = new DateTimeImmutable($year.'-'.$month.'-01 00:00:00');

        return self::createFromDuration($date, new DateInterval('P3M'));
    }

    /**
     * Create a Period object for a specific month
     *
     * @param DateTimeInterface|string|int $year
     * @param int                          $month Month index from 1 to 12
     *
     * @return static
     */
    public static function createFromMonth($year, int $month = null): self
    {
        if (1 == func_num_args()) {
            $date = self::approximateDate('Y-m-01 00:00:00', static::filterDatePoint($year));

            return self::createFromDuration($date, new DateInterval('P1M'));
        }

        $date = date_create()
            ->setDate($year, self::validateRange($month, 1, 12), 1)
            ->setTime(0, 0, 0);

        return self::createFromDuration($date, new DateInterval('P1M'));
    }

    /**
     * Create a Period object for a specific week
     *
     * @param DateTimeInterface|string|int $year
     * @param int                          $week index from 1 to 53
     *
     * @return static
     */
    public static function createFromWeek($year, int $week = null): self
    {
        if (1 == func_num_args()) {
            $date = static::filterDatePoint($year);
            $date = self::approximateDate('Y-m-d 00:00:00', $date->sub(new DateInterval('P'.($date->format('N') - 1).'D')));

            return self::createFromDuration($date, new DateInterval('P1W'));
        }

        $date = (new DateTimeImmutable())
            ->setISODate($year, self::validateRange($week, 1, 53))
            ->setTime(0, 0, 0)
        ;

        return self::createFromDuration($date, new DateInterval('P1W'));
    }

    /**
     * Create a Period object for a specific date
     *
     * The date is truncated so that the Time range starts at midnight according to the date timezone.
     * The duration is equivalent to one full day.
     *
     * @param DateTimeInterface|string $datepoint
     *
     * @return static
     */
    public static function createFromDay($datepoint): self
    {
        $date = self::approximateDate('Y-m-d 00:00:00', static::filterDatePoint($datepoint));

        return self::createFromDuration($date, new DateInterval('P1D'));
    }

    /**
     * Create a Period object for a specific date and time which last 1 hour
     *
     * The date is truncated so that the Time range starts at midnight according to the date timezone.
     * The duration is equivalent to one full day.
     *
     * @param DateTimeInterface|string $datepoint
     *
     * @return static
     */
    public static function createFromHour($datepoint): self
    {
        $date = self::approximateDate('Y-m-d H:00:00', static::filterDatePoint($datepoint));

        return self::createFromDuration($date, new DateInterval('PT1H'));
    }

    /**
     * Create a Period object for a specific date and time which last 1 minute
     *
     * The date is truncated so that the Time range starts at midnight according to the date timezone.
     * The duration is equivalent to one full day.
     *
     * @param DateTimeInterface|string $datepoint
     *
     * @return static
     */
    public static function createFromMinute($datepoint): self
    {
        $date = self::approximateDate('Y-m-d H:i:00', static::filterDatePoint($datepoint));

        return self::createFromDuration($date, new DateInterval('PT1M'));
    }

    /**
     * Create a Period object for a specific date and time which last 1 second
     *
     * The date is truncated so that the Time range starts at midnight according to the date timezone.
     * The duration is equivalent to one full day.
     *
     * @param DateTimeInterface|string $datepoint
     *
     * @return static
     */
    public static function createFromSecond($datepoint): self
    {
        $date = self::approximateDate('Y-m-d H:i:s', static::filterDatePoint($datepoint));

        return self::createFromDuration($date, new DateInterval('PT1S'));
    }

    /**
     * Returns the Period starting datepoint.
     *
     * The starting datepoint is included in the specified period.
     * The starting datepoint is always less than or equal to the ending datepoint.
     *
     * @return DateTimeImmutable
     */
    public function getStartDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    /**
     * Returns the Period ending datepoint.
     *
     * The ending datepoint is excluded from the specified period.
     * The ending datepoint is always greater than or equal to the starting datepoint.
     *
     * @return DateTimeImmutable
     */
    public function getEndDate(): DateTimeImmutable
    {
        return $this->endDate;
    }

    /**
     * Returns the Period duration as expressed in seconds
     *
     * @return float
     */
    public function getTimestampInterval(): float
    {
        return $this->endDate->getTimestamp() - $this->startDate->getTimestamp();
    }

    /**
     * Returns the Period duration as a DateInterval object.
     *
     * @return DateInterval
     */
    public function getDateInterval(): DateInterval
    {
        return $this->startDate->diff($this->endDate);
    }

    /**
     * Allows iteration over a set of dates and times,
     * recurring at regular intervals, over the Period object.
     *
     * The interval can be
     * <ul>
     * <li>a DateInterval object</li>
     * <li>an int interpreted as the duration expressed in seconds.</li>
     * <li>a string in a format supported by DateInterval::createFromDateString</li>
     * </ul>
     *
     * @param DateInterval|int|string $interval The interval
     *
     * @param int $option can be set to DatePeriod::EXCLUDE_START_DATE
     *                    to exclude the start date from the set of
     *                    recurring dates within the period.
     *
     * @return DatePeriod
     */
    public function getDatePeriod($interval, int $option = 0): DatePeriod
    {
        return new DatePeriod($this->startDate, static::filterDateInterval($interval), $this->endDate, $option);
    }

    /**
     * Allows splitting a Period in smaller Period object according
     * to a given interval.
     *
     * The returned iterable Period set is ordered so that:
     * <ul>
     * <li>The first returned Period MUST share the starting datepoint of the parent object.</li>
     * <li>The last returned Period MUST share the ending datepoint of the parent object.</li>
     * <li>The last returned Period MUST have a duration equal or lesser than the submitted interval.</li>
     * <li>All returned Period except for the first one MUST start immediately after the previously returned Period</li>
     * </ul>
     *
     * The interval can be
     * <ul>
     * <li>a DateInterval object</li>
     * <li>an int interpreted as the duration expressed in seconds.</li>
     * <li>a string in a format supported by DateInterval::createFromDateString</li>
     * </ul>
     *
     * @param DateInterval|int|string $interval The interval
     *
     * @return Generator
     */
    public function split($interval): Generator
    {
        $startDate = $this->startDate;
        $interval = static::filterDateInterval($interval);
        do {
            $endDate = $startDate->add($interval);
            if ($endDate > $this->endDate) {
                $endDate = $this->endDate;
            }
            yield new static($startDate, $endDate);

            $startDate = $endDate;
        } while ($startDate < $this->endDate);
    }

    /**
     * Allows splitting a Period in smaller Period object according
     * to a given interval.
     *
     * The returned iterable Period set is ordered so that:
     * <ul>
     * <li>The first returned Period MUST share the ending datepoint of the parent object.</li>
     * <li>The last returned Period MUST share the starting datepoint of the parent object.</li>
     * <li>The last returned Period MUST have a duration equal or lesser than the submitted interval.</li>
     * <li>All returned Period except for the first one MUST end immediately before the previously returned Period</li>
     * </ul>
     *
     * The interval can be
     * <ul>
     * <li>a DateInterval object</li>
     * <li>an int interpreted as the duration expressed in seconds.</li>
     * <li>a string in a format supported by DateInterval::createFromDateString</li>
     * </ul>
     *
     * @param DateInterval|int|string $interval The interval
     *
     * @return Generator
     */
    public function splitBackwards($interval): Generator
    {
        $endDate = $this->endDate;
        $interval = static::filterDateInterval($interval);
        do {
            $startDate = $endDate->sub($interval);
            if ($startDate < $this->startDate) {
                $startDate = $this->startDate;
            }
            yield new static($startDate, $endDate);

            $endDate = $startDate;
        } while ($endDate > $this->startDate);
    }

    /**
     * @inheritdoc
     */
    public function __toString(): string
    {
        $period = $this->jsonSerialize();

        return $period['startDate'].'/'.$period['endDate'];
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize(): array
    {
        static $iso8601_format = 'Y-m-d\TH:i:s.u\Z';
        static $utc;
        $utc = $utc ?? new DateTimeZone('UTC');

        return [
            'startDate' => $this->startDate->setTimeZone($utc)->format($iso8601_format),
            'endDate' => $this->endDate->setTimeZone($utc)->format($iso8601_format),
        ];
    }

    /**
     * Tells whether two Period share the same datepoints.
     *
     * @param Period $period
     *
     * @return bool
     */
    public function sameValueAs(Period $period): bool
    {
        return $this->startDate == $period->startDate
            && $this->endDate == $period->endDate;
    }

    /**
     * Tells whether two Period object abuts
     *
     * @param Period $period
     *
     * @return bool
     */
    public function abuts(Period $period): bool
    {
        return $this->startDate == $period->endDate
            || $this->endDate == $period->startDate;
    }

    /**
     * Tells whether two Period objects overlaps
     *
     * @param Period $period
     *
     * @return bool
     */
    public function overlaps(Period $period): bool
    {
        return !$this->abuts($period)
            && $this->startDate < $period->endDate
            && $this->endDate > $period->startDate;
    }

    /**
     * Tells whether a Period is entirely after the specified index
     *
     * The specified index can be
     * <ul>
     * <li>a Period</li>
     * <li>a DateTimeInterface</li>
     * <li>a string in a format supported by DateTimeImmutable::__construct</li>
     * </ul>
     *
     * @param Period|DateTimeInterface|string $index
     *
     * @return bool
     */
    public function isAfter($index): bool
    {
        if ($index instanceof Period) {
            return $this->startDate >= $index->endDate;
        }

        return $this->startDate > static::filterDatePoint($index);
    }

    /**
     * Tells whether a Period is entirely before the specified index
     *
     * The specified index can be
     * <ul>
     * <li>a Period</li>
     * <li>a DateTimeInterface</li>
     * <li>a string in a format supported by DateTimeImmutable::__construct</li>
     * </ul>
     *
     * @param Period|DateTimeInterface|string $index
     *
     * @return bool
     */
    public function isBefore($index): bool
    {
        if ($index instanceof Period) {
            return $this->endDate <= $index->startDate;
        }

        return $this->endDate <= static::filterDatePoint($index);
    }

    /**
     * Tells whether the specified index is fully contained within
     * the current Period object.
     *
     * The specified index can be
     * <ul>
     * <li>a Period</li>
     * <li>a DateTimeInterface</li>
     * <li>a string in a format supported by DateTimeImmutable::__construct</li>
     * </ul>
     *
     * @param Period|DateTimeInterface|string $index
     *
     * @return bool
     */
    public function contains($index): bool
    {
        if ($index instanceof Period) {
            return $this->containsPeriod($index);
        }

        return $this->containsDatePoint($index);
    }

    /**
     * Tells whether a Period object is fully contained within
     * the current Period object.
     *
     * @param Period $period
     *
     * @return bool
     */
    protected function containsPeriod(Period $period): bool
    {
        return $this->contains($period->startDate)
            && ($period->endDate >= $this->startDate && $period->endDate <= $this->endDate);
    }

    /**
     * Tells whether a datepoint is fully contained within
     * the current Period object.
     *
     * @param DateTimeInterface|string $datepoint
     *
     * @return bool
     */
    protected function containsDatePoint($datepoint): bool
    {
        $datetime = static::filterDatePoint($datepoint);

        return ($datetime >= $this->startDate && $datetime < $this->endDate)
            || ($datetime == $this->startDate && $datetime == $this->endDate);
    }

    /**
     * Compares two Period objects according to their duration.
     *
     * @param Period $period
     *
     * @return int
     */
    public function compareDuration(Period $period): int
    {
        return $this->endDate <=> $this->startDate->add($period->getDateInterval());
    }

    /**
     * Tells whether the current Period object duration
     * is greater than the submitted one.
     *
     * @param Period $period
     *
     * @return bool
     */
    public function durationGreaterThan(Period $period): bool
    {
        return 1 == $this->compareDuration($period);
    }

    /**
     * Tells whether the current Period object duration
     * is less than the submitted one.
     *
     * @param Period $period
     *
     * @return bool
     */
    public function durationLessThan(Period $period): bool
    {
        return -1 == $this->compareDuration($period);
    }

    /**
     * Tells whether the current Period object duration
     * is equal to the submitted one
     *
     * @param Period $period
     *
     * @return bool
     */
    public function sameDurationAs(Period $period): bool
    {
        return 0 == $this->compareDuration($period);
    }

    /**
     * Returns a new Period object with a new included starting date point.
     *
     * @param DateTimeInterface|string $startDate date point
     *
     * @return static
     */
    public function startingOn($startDate): self
    {
        return new static(static::filterDatePoint($startDate), $this->endDate);
    }

    /**
     * Returns a new Period object with a new ending date point.
     *
     * @param DateTimeInterface|string $endDate date point
     *
     * @return static
     */
    public function endingOn($endDate): self
    {
        return new static($this->startDate, static::filterDatePoint($endDate));
    }

    /**
     * Returns a new Period object with a new ending date point.
     *
     * The interval can be
     * <ul>
     * <li>a DateInterval object</li>
     * <li>an int interpreted as the duration expressed in seconds.</li>
     * <li>a string in a format supported by DateInterval::createFromDateString</li>
     * </ul>
     *
     * @param DateInterval|int|string $interval The interval
     *
     * @return static
     */
    public function withDuration($interval): self
    {
        return new static($this->startDate, $this->startDate->add(static::filterDateInterval($interval)));
    }

    /**
     * Returns a new Period object with a new starting date point.
     *
     * The interval can be
     * <ul>
     * <li>a DateInterval object</li>
     * <li>an int interpreted as the duration expressed in seconds.</li>
     * <li>a string in a format supported by DateInterval::createFromDateString</li>
     * </ul>
     *
     * @param DateInterval|int|string $interval The interval
     *
     * @return static
     */
    public function withDurationBeforeEnd($interval): self
    {
        return new static($this->endDate->sub(static::filterDateInterval($interval)), $this->endDate);
    }

    /**
     * Returns a new Period object with a new starting date point
     * moved forward or backward by the given interval
     *
     * The interval can be
     * <ul>
     * <li>a DateInterval object</li>
     * <li>an int interpreted as the duration expressed in seconds.</li>
     * <li>a string in a format supported by DateInterval::createFromDateString</li>
     * </ul>
     *
     * @param DateInterval|int|string $interval The interval
     *
     * @return static
     */
    public function moveStartDate($interval): self
    {
        return new static($this->startDate->add(static::filterDateInterval($interval)), $this->endDate);
    }

    /**
     * Returns a new Period object with a new ending date point
     * moved forward or backward by the given interval
     *
     * The interval can be
     * <ul>
     * <li>a DateInterval object</li>
     * <li>an int interpreted as the duration expressed in seconds.</li>
     * <li>a string in a format supported by DateInterval::createFromDateString</li>
     * </ul>
     *
     * @param DateInterval|int|string $interval The interval
     *
     * @return static
     */
    public function moveEndDate($interval): self
    {
        return new static($this->startDate, $this->endDate->add(static::filterDateInterval($interval)));
    }

    /**
     * Returns a new Period object where the datepoints
     * are moved forwards or backward simultaneously by the given DateInterval
     *
     * The interval can be
     * <ul>
     * <li>a DateInterval object</li>
     * <li>an int interpreted as the duration expressed in seconds.</li>
     * <li>a string in a format supported by DateInterval::createFromDateString</li>
     * </ul>
     *
     * @param DateInterval|int|string $interval The interval
     *
     * @return static
     */
    public function move($interval): self
    {
        $interval = static::filterDateInterval($interval);

        return new static($this->startDate->add($interval), $this->endDate->add($interval));
    }

    /**
     * Merges one or more Period objects to return a new Period object.
     *
     * The resultant object represents the largest duration possible.
     *
     * @param Period... $periods one or more Period objects
     *
     * @return static
     */
    public function merge(Period ...$periods): self
    {
        return array_reduce($periods, [$this, 'reducer'], $this);
    }

    /**
     * Returns a Period whose endpoints are the larget possible
     * between 2 instance of Period objects
     *
     * @param Period $carry
     * @param Period $period
     *
     * @return static
     */
    protected function reducer(Period $carry, Period $period): Period
    {
        if ($carry->startDate > $period->startDate) {
            $carry = $carry->startingOn($period->startDate);
        }

        if ($carry->endDate < $period->endDate) {
            $carry = $carry->endingOn($period->endDate);
        }

        return $carry;
    }

    /**
     * Computes the intersection between two Period objects.
     *
     * @param Period $period
     *
     * @throws Exception If Both objects do not overlaps
     *
     * @return static
     */
    public function intersect(Period $period): self
    {
        if (!$this->overlaps($period)) {
            throw new Exception('Both object should at least overlaps');
        }

        return new static(
            ($period->startDate > $this->startDate) ? $period->startDate : $this->startDate,
            ($period->endDate < $this->endDate) ? $period->endDate : $this->endDate
        );
    }

    /**
     * Computes the gap between two Period objects.
     *
     * @param Period $period
     *
     * @return static
     */
    public function gap(Period $period): self
    {
        if ($period->startDate > $this->startDate) {
            return new static($this->endDate, $period->startDate);
        }

        return new static($period->endDate, $this->startDate);
    }

    /**
     * Returns the difference between two Period objects expressed in seconds
     *
     * @param Period $period
     *
     * @return float
     */
    public function timestampIntervalDiff(Period $period): float
    {
        return $this->getTimestampInterval() - $period->getTimestampInterval();
    }

    /**
     * Returns the difference between two Period objects expressed in DateInterval
     *
     * @param Period $period
     *
     * @return DateInterval
     */
    public function dateIntervalDiff(Period $period): DateInterval
    {
        return $this->endDate->diff($this->withDuration($period->getDateInterval())->endDate);
    }

    /**
     * Computes the difference between two overlapsing Period objects
     *
     * Returns an array containing the difference expressed as Period objects
     * The array will:
     *
     * <ul>
     * <li>be empty if both objects have the same datepoints</li>
     * <li>contain one Period object if both objects share one datepoint</li>
     * <li>contain two Period objects if both objects share no datepoint</li>
     * </ul>
     *
     * @param Period $period
     *
     * @throws Exception if both object do not overlaps
     *
     * @return Period[]
     */
    public function diff(Period $period): array
    {
        if (!$this->overlaps($period)) {
            throw new Exception('Both Period objects should overlaps');
        }

        $res = [
            static::createFromDatepoints($this->startDate, $period->startDate),
            static::createFromDatepoints($this->endDate, $period->endDate),
        ];

        $filter = function (Period $period) {
            return $period->startDate != $period->endDate;
        };

        return array_values(array_filter($res, $filter));
    }

    /**
     * Create a new instance given two datepoints
     *
     * The datepoints will be used as to allow the creation of
     * a Period object
     *
     * @param DateTimeInterface|string $datePoint1 datepoint
     * @param DateTimeInterface|string $datePoint2 datepoint
     *
     * @return Period
     */
    protected static function createFromDatepoints($datePoint1, $datePoint2): self
    {
        $startDate = static::filterDatePoint($datePoint1);
        $endDate = static::filterDatePoint($datePoint2);
        if ($startDate > $endDate) {
            return new static($endDate, $startDate);
        }

        return new static($startDate, $endDate);
    }
}
