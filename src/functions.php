<?php

/**
 * League.Period (https://period.thephpleague.com).
 *
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license https://github.com/thephpleague/period/blob/master/LICENSE (MIT License)
 * @version 4.0.0
 * @link    https://github.com/thephpleague/period
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Period;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use TypeError;
use const FILTER_VALIDATE_INT;
use function filter_var;
use function get_class;
use function gettype;
use function intdiv;
use function is_object;
use function is_string;
use function sprintf;

/**
 * Returns a DateTimeImmutable object.
 */
function datepoint($datepoint): DateTimeImmutable
{
    if ($datepoint instanceof DateTimeImmutable) {
        return $datepoint;
    }

    if ($datepoint instanceof DateTime) {
        return DateTimeImmutable::createFromMutable($datepoint);
    }

    if (false !== ($res = filter_var($datepoint, FILTER_VALIDATE_INT))) {
        return new DateTimeImmutable('@'.$res);
    }

    if (is_string($datepoint)) {
        return new DateTimeImmutable($datepoint);
    }

    throw new TypeError(sprintf(
        'The datepoint must be expressed using an integer, a string or a DateTimeInterface object %s given',
        is_object($datepoint) ? get_class($datepoint) : gettype($datepoint)
    ));
}

/**
 * Returns a DateInval object.
 *
 * The duration can be
 * <ul>
 * <li>a DateInterval object</li>
 * <li>an Interval object</li>
 * <li>an int interpreted as the duration expressed in seconds.</li>
 * <li>a string in a format supported by DateInterval::createFromDateString</li>
 * </ul>
 */
function duration($duration): DateInterval
{
    if ($duration instanceof Period) {
        return $duration->getDateInterval();
    }

    if ($duration instanceof DateInterval) {
        return $duration;
    }

    if (false !== ($res = filter_var($duration, FILTER_VALIDATE_INT))) {
        return new DateInterval('PT'.$res.'S');
    }

    if (is_string($duration)) {
        return DateInterval::createFromDateString($duration);
    }

    throw new TypeError(sprintf(
        'The duration must be expressed using an integer, a string, a DateInterval or a Period object %s given',
        is_object($duration) ? get_class($duration) : gettype($duration)
    ));
}

/**
 * Creates new instance from a starting point and an interval.
 */
function interval_after($datepoint, $duration): Period
{
    $datepoint = datepoint($datepoint);

    return new Period($datepoint, $datepoint->add(duration($duration)));
}

/**
 * Creates new instance from a ending excluded datepoint and an interval.
 */
function interval_before($datepoint, $duration): Period
{
    $datepoint = datepoint($datepoint);

    return new Period($datepoint->sub(duration($duration)), $datepoint);
}

/**
 * Creates new instance where the given duration is simultaneously
 * substracted from and added to the datepoint.
 */
function interval_around($datepoint, $duration): Period
{
    $datepoint = datepoint($datepoint);
    $duration = duration($duration);

    return new Period($datepoint->sub($duration), $datepoint->add($duration));
}

/**
 * Creates new instance for a specific year.
 *
 * @param mixed $int_or_datepoint a year as an int or a datepoint
 */
function year($int_or_datepoint): Period
{
    if (is_int($int_or_datepoint)) {
        $startDate = (new DateTimeImmutable())->setTime(0, 0)->setDate($int_or_datepoint, 1, 1);

        return new Period($startDate, $startDate->add(new DateInterval('P1Y')));
    }

    $datepoint = datepoint($int_or_datepoint);
    $startDate = $datepoint->setTime(0, 0)->setDate((int) $datepoint->format('Y'), 1, 1);

    return new Period($startDate, $startDate->add(new DateInterval('P1Y')));
}

/**
 * Creates new instance for a specific ISO year.
 *
 * @param mixed $int_or_datepoint a year as an int or a datepoint
 */
function iso_year($int_or_datepoint): Period
{
    if (is_int($int_or_datepoint)) {
        $datepoint = (new DateTimeImmutable())->setTime(0, 0);

        return new Period(
            $datepoint->setISODate($int_or_datepoint, 1),
            $datepoint->setISODate(++$int_or_datepoint, 1)
        );
    }

    $datepoint = datepoint($int_or_datepoint)->setTime(0, 0);
    $int_or_datepoint = (int) $datepoint->format('o');

    return new Period(
        $datepoint->setISODate($int_or_datepoint, 1),
        $datepoint->setISODate(++$int_or_datepoint, 1)
    );
}

/**
 * Creates new instance for a specific semester in a given year.
 *
 * @param mixed $int_or_datepoint a year as an int or a datepoint
 * @param int   $index            a semester index from 1 to 2 included
 *
 * @throws Exception If the semester index is out of bounds
 */
function semester($int_or_datepoint, int $index = 1): Period
{
    if (!is_int($int_or_datepoint)) {
        $datepoint = datepoint($int_or_datepoint);
        $startDate = $datepoint->setTime(0, 0)->setDate(
            (int) $datepoint->format('Y'),
            (intdiv((int) $datepoint->format('n'), 6) * 6) + 1,
            1
        );

        return new Period($startDate, $startDate->add(new DateInterval('P6M')));
    }

    if (0 < $index && 2 >= $index) {
        $startDate = (new DateTimeImmutable())->setTime(0, 0)
            ->setDate($int_or_datepoint, (($index - 1) * 6) + 1, 1);

        return new Period($startDate, $startDate->add(new DateInterval('P6M')));
    }

    throw new Exception('The semester index is not contained within the valid range.');
}

/**
 * Creates new instance for a specific quarter in a given year.
 *
 * @param mixed $int_or_datepoint a year as an int or a datepoint
 * @param int   $index            quarter index from 1 to 4 included
 *
 * @throws Exception If the quarter index is out of bounds
 */
function quarter($int_or_datepoint, int $index = 1): Period
{
    if (!is_int($int_or_datepoint)) {
        $datepoint = datepoint($int_or_datepoint)->setTime(0, 0);
        $startDate = $datepoint->setDate(
            (int) $datepoint->format('Y'),
            (intdiv((int) $datepoint->format('n'), 3) * 3) + 1,
            1
        );

        return new Period($startDate, $startDate->add(new DateInterval('P3M')));
    }

    if (0 < $index && 4 >= $index) {
        $startDate = (new DateTimeImmutable())->setTime(0, 0)
            ->setDate($int_or_datepoint, (($index - 1) * 3) + 1, 1);

        return new Period($startDate, $startDate->add(new DateInterval('P3M')));
    }

    throw new Exception('The quarter index is not contained within the valid range.');
}

/**
 * Creates new instance for a specific year and month.
 *
 * @param mixed $int_or_datepoint a year as an int or a datepoint
 * @param int   $index            month index from 1 to 12 included
 *
 * @throws Exception If the month index is out of bounds
 */
function month($int_or_datepoint, int $index = 1): Period
{
    if (!is_int($int_or_datepoint)) {
        $datepoint = datepoint($int_or_datepoint)->setTime(0, 0);
        $startDate = $datepoint->setDate((int) $datepoint->format('Y'), (int) $datepoint->format('n'), 1);

        return new Period($startDate, $startDate->add(new DateInterval('P1M')));
    }

    if (0 < $index && 12 >= $index) {
        $startDate = (new DateTimeImmutable())->setTime(0, 0)->setDate($int_or_datepoint, $index, 1);

        return new Period($startDate, $startDate->add(new DateInterval('P1M')));
    }

    throw new Exception('The month index is not contained within the valid range.');
}

/**
 * Creates new instance for a specific ISO8601 week.
 *
 * @param mixed $int_or_datepoint a year as an int or a datepoint
 * @param int   $index            index from 1 to 53 included
 *
 * @throws Exception If the week index for a given year is out of bounds
 */
function iso_week($int_or_datepoint, int $index = 1): Period
{
    if (!is_int($int_or_datepoint)) {
        $datepoint = datepoint($int_or_datepoint)->setTime(0, 0);
        $startDate = $datepoint->setISODate((int) $datepoint->format('o'), (int) $datepoint->format('W'), 1);

        return new Period($startDate, $startDate->add(new DateInterval('P7D')));
    }

    $datepoint = (new DateTimeImmutable())->setTime(0, 0)->setDate($int_or_datepoint, 12, 28);
    if (0 < $index && (int) $datepoint->format('W') >= $index) {
        $startDate = $datepoint->setISODate($int_or_datepoint, $index, 1);

        return new Period($startDate, $startDate->add(new DateInterval('P7D')));
    }

    throw new Exception('The week index is not contained within the valid range.');
}

/**
 * Creates new instance for a specific date.
 *
 * The date is truncated so that the time range starts at midnight
 * according to the date timezone and last a full day.
 *
 * @param mixed $int_or_datepoint a year as an int or a datepoint
 */
function day($int_or_datepoint, int $month = 1, int $day = 1): Period
{
    if (!is_int($int_or_datepoint)) {
        $startDate = datepoint($int_or_datepoint)->setTime(0, 0);

        return new Period($startDate, $startDate->add(new DateInterval('P1D')));
    }

    if (1 > $month || 12 < $month) {
        throw new Exception('The month index is not contained within the valid range.');
    }

    $datepoint = (new DateTimeImmutable())->setTime(0, 0)->setDate($int_or_datepoint, $month, 1);
    if (0 < $day && (int) $datepoint->format('t') >= $day) {
        $startDate = $datepoint->setDate($int_or_datepoint, $month, $day);

        return new Period($startDate, $startDate->add(new DateInterval('P1D')));
    }

    throw new Exception('The day index is not contained within the valid range.');
}

/**
 * Creates new instance for a specific date and hour.
 *
 * The starting datepoint represents the beginning of the hour
 * The interval is equal to 1 hour
 */
function hour($datepoint): Period
{
    $datepoint = datepoint($datepoint);
    $startDate = $datepoint->setTime((int) $datepoint->format('H'), 0);

    return new Period($startDate, $startDate->add(new DateInterval('PT1H')));
}

/**
 * Creates new instance for a specific date, hour and minute.
 *
 * The starting datepoint represents the beginning of the minute
 * The interval is equal to 1 minute
 */
function minute($datepoint): Period
{
    $datepoint = datepoint($datepoint);
    $startDate = $datepoint->setTime((int) $datepoint->format('H'), (int) $datepoint->format('i'));

    return new Period($startDate, $startDate->add(new DateInterval('PT1M')));
}

/**
 * Creates new instance for a specific date, hour, minute and second.
 *
 * The starting datepoint represents the beginning of the second
 * The interval is equal to 1 second
 */
function second($datepoint): Period
{
    $datepoint = datepoint($datepoint);
    $startDate = $datepoint->setTime(
        (int) $datepoint->format('H'),
        (int) $datepoint->format('i'),
        (int) $datepoint->format('s')
    );

    return new Period($startDate, $startDate->add(new DateInterval('PT1S')));
}

/**
 * Creates new instance for a specific datepoint.
 */
function instant($datepoint): Period
{
    $datepoint = datepoint($datepoint);

    return new Period($datepoint, $datepoint);
}
