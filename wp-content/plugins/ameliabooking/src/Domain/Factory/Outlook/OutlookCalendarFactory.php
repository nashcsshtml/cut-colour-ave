<?php

namespace AmeliaBooking\Domain\Factory\Outlook;

use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Outlook\OutlookCalendar;
use AmeliaBooking\Domain\ValueObjects\Number\Integer\Id;
use AmeliaBooking\Domain\ValueObjects\String\Label;
use AmeliaBooking\Domain\ValueObjects\String\Token;

/**
 * Class OutlookCalendarFactory
 *
 * @package AmeliaBooking\Domain\Factory\Outlook
 */
class OutlookCalendarFactory
{
    /**
     * @param $data
     *
     * @return OutlookCalendar
     * @throws InvalidArgumentException
     */
    public static function create($data)
    {
        $outlookCalendar = new OutlookCalendar(
            new Token($data['token']),
            new Label(empty($data['calendarId']) ? null : $data['calendarId'])
        );

        if (isset($data['id'])) {
            $outlookCalendar->setId(new Id($data['id']));
        }
        if (isset($data['insertPendingAppointments'])) {
            $outlookCalendar->setInsertPendingAppointments((bool)$data['insertPendingAppointments']);
        }

        if (isset($data['includeBufferTime'])) {
            $outlookCalendar->setIncludeBufferTime((bool)$data['includeBufferTime']);
        }

        if (isset($data['title'])) {
            $outlookCalendar->setTitle($data['title']);
        }

        if (isset($data['description'])) {
            $outlookCalendar->setDescription($data['description']);
        }

        return $outlookCalendar;
    }
}
