<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Appointment;

use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Services\Booking\BookingApplicationService;
use AmeliaBooking\Application\Services\Integration\ApplicationIntegrationService;
use AmeliaBooking\Application\Services\Notification\ApplicationNotificationService;
use AmeliaBooking\Application\Services\WebHook\AbstractWebHookApplicationService;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Booking\Appointment\Appointment;
use AmeliaBooking\Domain\Entity\Booking\Event\Event;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Factory\Booking\Appointment\AppointmentFactory;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Common\Exceptions\NotFoundException;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use Exception;
use Slim\Exception\ContainerValueNotFoundException;

/**
 * Class AppointmentTimeUpdatedEventHandler
 *
 * @package AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Appointment
 */
class AppointmentTimeUpdatedEventHandler
{
    /** @var string */
    public const TIME_UPDATED = 'bookingTimeUpdated';

    /**
     * @param CommandResult $commandResult
     * @param Container     $container
     *
     * @throws ContainerValueNotFoundException
     * @throws NotFoundException
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public static function handle($commandResult, $container)
    {
        /** @var ApplicationNotificationService $applicationNotificationService */
        $applicationNotificationService = $container->get('application.notification.service');

        /** @var ApplicationIntegrationService $applicationIntegrationService */
        $applicationIntegrationService = $container->get('application.integration.service');

        /** @var AbstractWebHookApplicationService $webHookService */
        $webHookService = $container->get('application.webHook.service');

        /** @var BookingApplicationService $bookingApplicationService */
        $bookingApplicationService = $container->get('application.booking.booking.service');

        /** @var Appointment|Event $reservationObject */
        $reservationObject = AppointmentFactory::create($commandResult->getData()[Entities::APPOINTMENT]);

        $bookingApplicationService->setAppointmentEntities($reservationObject);

        $appointment = $reservationObject->toArray();

        $applicationIntegrationService->handleAppointment(
            $reservationObject,
            $appointment,
            ApplicationIntegrationService::TIME_UPDATED
        );

        $applicationNotificationService->sendAppointmentRescheduleNotifications(
            $reservationObject
        );

        $webHookService->process(self::TIME_UPDATED, $appointment, []);
    }
}
