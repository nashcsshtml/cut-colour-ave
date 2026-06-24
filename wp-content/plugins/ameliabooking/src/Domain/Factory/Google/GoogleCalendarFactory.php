<?php

namespace AmeliaBooking\Domain\Factory\Google;

use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Google\GoogleCalendar;
use AmeliaBooking\Domain\ValueObjects\Number\Integer\Id;
use AmeliaBooking\Domain\ValueObjects\String\Name;
use AmeliaBooking\Domain\ValueObjects\String\Token;

/**
 * Class GoogleCalendarFactory
 *
 * @package AmeliaBooking\Domain\Factory\Google
 */
class GoogleCalendarFactory
{
    /**
     * @param $data
     *
     * @return GoogleCalendar
     * @throws InvalidArgumentException
     */
    public static function create($data)
    {
        $googleCalendar = new GoogleCalendar(
            new Token($data['token']),
            new Name(empty($data['calendarId']) ? null : $data['calendarId'])
        );

        if (isset($data['id'])) {
            $googleCalendar->setId(new Id($data['id']));
        }

        if (isset($data['insertPendingAppointments'])) {
            $googleCalendar->setInsertPendingAppointments((bool)$data['insertPendingAppointments']);
        }

        if (isset($data['includeBufferTime'])) {
            $googleCalendar->setIncludeBufferTime((bool)$data['includeBufferTime']);
        }

        if (isset($data['title'])) {
            $googleCalendar->setTitle($data['title']);
        }

        if (isset($data['description'])) {
            $googleCalendar->setDescription($data['description']);
        }

        return $googleCalendar;
    }
}
