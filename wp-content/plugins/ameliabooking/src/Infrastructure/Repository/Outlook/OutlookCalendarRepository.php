<?php

namespace AmeliaBooking\Infrastructure\Repository\Outlook;

use AmeliaBooking\Domain\Entity\Outlook\OutlookCalendar;
use AmeliaBooking\Domain\Factory\Outlook\OutlookCalendarFactory;
use AmeliaBooking\Infrastructure\Common\Exceptions\NotFoundException;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\AbstractRepository;
use Exception;

/**
 * Class OutlookCalendarRepository
 *
 * @package AmeliaBooking\Infrastructure\Repository\Outlook
 */
class OutlookCalendarRepository extends AbstractRepository
{
    public const FACTORY = OutlookCalendarFactory::class;

    /**
     * @param OutlookCalendar $outlookCalendar
     * @param int            $userId
     * @param array|null     $additionalSettings
     *
     * @return int
     * @throws QueryExecutionException
     */
    public function add($outlookCalendar, $userId, $additionalSettings = null)
    {
        $data = $outlookCalendar->toArray();

        $params = [
            ':userId' => $userId,
            ':token'  => $data['token'],
            ':calendarId' => $data['calendarId']
        ];

        $fields = ['userId', 'token', 'calendarId'];
        $placeholders = [':userId', ':token', ':calendarId'];

        if ($additionalSettings !== null) {
            if (isset($additionalSettings['insertPendingAppointments'])) {
                $fields[] = 'insertPendingAppointments';
                $placeholders[] = ':insertPendingAppointments';
                $params[':insertPendingAppointments'] = (int)$additionalSettings['insertPendingAppointments'];
            }

            if (isset($additionalSettings['includeBufferTime'])) {
                $fields[] = 'includeBufferTime';
                $placeholders[] = ':includeBufferTime';
                $params[':includeBufferTime'] = (int)$additionalSettings['includeBufferTime'];
            }

            if (isset($additionalSettings['title'])) {
                $fields[] = 'title';
                $placeholders[] = ':title';
                $params[':title'] = is_array($additionalSettings['title'])
                    ? json_encode($additionalSettings['title'])
                    : $additionalSettings['title'];
            }

            if (isset($additionalSettings['description'])) {
                $fields[] = 'description';
                $placeholders[] = ':description';
                $params[':description'] = is_array($additionalSettings['description'])
                    ? json_encode($additionalSettings['description'])
                    : $additionalSettings['description'];
            }
        }

        try {
            $statement = $this->connection->prepare(
                "INSERT INTO {$this->table}
                (`" . implode('`, `', $fields) . "`)
                VALUES
                (" . implode(', ', $placeholders) . ")"
            );

            $statement->execute($params);
        } catch (Exception $e) {
            throw new QueryExecutionException('Unable to add data in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        return $this->connection->lastInsertId();
    }

    /**
     * @param OutlookCalendar $outlookCalendar
     * @param int            $id
     *
     * @return mixed
     * @throws QueryExecutionException
     */
    public function update($outlookCalendar, $id)
    {
        $data = $outlookCalendar->toArray();

        $params = [
            ':token'      => $data['token'],
            ':calendarId' => $data['calendarId'],
            ':id'         => $id
        ];

        try {
            $statement = $this->connection->prepare(
                "UPDATE {$this->table}
                SET `token` = :token, `calendarId` = :calendarId WHERE id = :id"
            );

            $statement->execute($params);

            return true;
        } catch (Exception $e) {
            throw new QueryExecutionException('Unable to save data in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param $userId
     *
     * @return mixed
     * @throws NotFoundException
     * @throws QueryExecutionException
     */
    public function getByProviderId($userId)
    {
        try {
            $statement = $this->connection->prepare($this->selectQuery() . " WHERE {$this->table}.userId = :userId");
            $statement->bindParam(':userId', $userId);
            $statement->execute();
            $row = $statement->fetch();
        } catch (Exception $e) {
            throw new QueryExecutionException('Unable to find by id in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        if (!$row) {
            throw new NotFoundException('Data not found in ' . __CLASS__);
        }

        return call_user_func([static::FACTORY, 'create'], $row);
    }
}
