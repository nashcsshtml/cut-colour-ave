<?php

namespace AmeliaBooking\Infrastructure\WP\InstallActions\DB\Booking;

use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\ValueObjects\String\Name;
use AmeliaBooking\Infrastructure\WP\InstallActions\DB\AbstractDatabaseTable;

/**
 * Class EventsTagsTable
 *
 * @package AmeliaBooking\Infrastructure\WP\InstallActions\DB\Booking
 */
class EventsTagsTable extends AbstractDatabaseTable
{
    public const TABLE = 'events_tags';

    /**
     * @return string
     * @throws InvalidArgumentException
     */
    public static function buildTable()
    {
        $table = self::getTableName();

        $charsetCollate = self::getCharsetCollate();

        $name = Name::MAX_LENGTH;

        return "CREATE TABLE {$table} (
                   `id` INT(11) NOT NULL AUTO_INCREMENT,
                   `eventId` bigint(20) NULL,
                   `name` varchar({$name}) NOT NULL,
                    PRIMARY KEY (`id`)
                ) {$charsetCollate};";
    }

    /**
     * @return array
     * @throws InvalidArgumentException
     */
    public static function alterTable()
    {
        $table = self::getTableName();

        return [
            "ALTER TABLE {$table} MODIFY eventId bigint(20) NULL",
            "INSERT INTO `{$table}` (`eventId`, `name`)
                SELECT NULL, t.name
                FROM (SELECT DISTINCT `name` FROM `{$table}` WHERE `eventId` IS NOT NULL) AS t
                WHERE EXISTS (SELECT 1 FROM `{$table}` WHERE `eventId` IS NOT NULL)
                AND NOT EXISTS (SELECT 1 FROM `{$table}` WHERE `eventId` IS NULL)",
        ];
    }

    /**
     * @return bool
     * @throws InvalidArgumentException
     */
    public static function hasTags()
    {
        global $wpdb;
        $table = self::getTableName();
        return (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$table}`") > 0;
    }
}
