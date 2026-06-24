<?php

namespace AmeliaBooking\Application\Commands\User\Provider;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\User\ProviderApplicationService;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Entity\User\Provider;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Bookable\Service\ProviderServiceRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\AppointmentRepository;
use AmeliaBooking\Infrastructure\Repository\User\ProviderRepository;
use AmeliaBooking\Infrastructure\Services\Google\AbstractGoogleCalendarService;
use AmeliaBooking\Infrastructure\Services\Google\AbstractGoogleCalendarMiddlewareService;
use AmeliaBooking\Infrastructure\Services\Outlook\AbstractOutlookCalendarMiddlewareService;
use AmeliaBooking\Infrastructure\Services\Outlook\AbstractOutlookCalendarService;
use Slim\Exception\ContainerValueNotFoundException;

/**
 * Class GetProviderCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\User\Provider
 */
class GetProviderCommandHandler extends CommandHandler
{
    /**
     * @param GetProviderCommand $command
     *
     * @return CommandResult
     * @throws ContainerValueNotFoundException
     * @throws AccessDeniedException
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     */
    public function handle(GetProviderCommand $command)
    {
        /** @var int $providerId */
        $providerId = (int)$command->getField('id');

        /** @var AbstractUser $currentUser */
        $currentUser = $this->container->get('logged.in.user');

        if (
            !$command->getPermissionService()->currentUserCanRead(Entities::EMPLOYEES) ||
            (
                !$command->getPermissionService()->currentUserCanReadOthers(Entities::EMPLOYEES) &&
                $currentUser->getId()->getValue() !== $providerId
            )
        ) {
            throw new AccessDeniedException('You are not allowed to read employee.');
        }

        $result = new CommandResult();

        /** @var AppointmentRepository $appointmentRepository */
        $appointmentRepository = $this->container->get('domain.booking.appointment.repository');
        /** @var ProviderApplicationService $providerService */
        $providerService = $this->container->get('application.user.provider.service');
        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');
        /** @var AbstractGoogleCalendarService $googleCalService */
        $googleCalService = $this->container->get('infrastructure.google.calendar.service');
        /** @var AbstractOutlookCalendarService $outlookCalendarService */
        $outlookCalendarService = $this->container->get('infrastructure.outlook.calendar.service');
        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');
        /** @var ProviderServiceRepository $providerServiceRepository */
        $providerServiceRepository = $this->container->get('domain.bookable.service.providerService.repository');
        /** @var AbstractGoogleCalendarMiddlewareService $googleCalendarMiddlewareService */
        $googleCalendarMiddlewareService = $this->container->get(
            'infrastructure.google.calendar.middleware.service'
        );
        /** @var AbstractOutlookCalendarMiddlewareService $outlookCalendarMiddlewareService */
        $outlookCalendarMiddlewareService = $this->container->get(
            'infrastructure.outlook.calendar.middleware.service'
        );

        $companyDaysOff = $settingsService->getCategorySettings('daysOff');

        $companyDayOff = $providerService->checkIfTodayIsCompanyDayOff($companyDaysOff);

        /** @var Provider $provider */
        $provider = $providerService->getProviderWithServicesAndSchedule($providerId, true);

        $providerService->modifyPeriodsWithSingleLocationAfterFetch($provider->getWeekDayList());
        $providerService->modifyPeriodsWithSingleLocationAfterFetch($provider->getSpecialDayList());

        $futureAppointmentsServicesIds = $appointmentRepository->getFutureAppointmentsServicesIds(
            [$provider->getId()->getValue()],
            DateTimeService::getNowDateTime(),
            null
        );

        $providerArray = $providerService->manageProvidersActivity(
            [$provider->toArray()],
            $companyDayOff
        )[0];

        $successfulGoogleConnection = true;

        $successfulOutlookConnection = true;

        $providerArray['googleCalendar']['calendarList'] = [];
        $providerArray['googleCalendar']['calendarId'] = null;

        $providerArray['outlookCalendar']['calendarList'] = [];
        $providerArray['outlookCalendar']['calendarId'] = null;

        if ($settingsService->isFeatureEnabled('googleCalendar')) {
            $googleCalendarAccounts = $providerRepository->getGoogleCalendarAccounts($providerId);

            $providerArray['googleCalendar']['accounts'] = $googleCalendarAccounts;

            $googleCalendarIdFromAccounts = null;
            foreach ($googleCalendarAccounts as $account) {
                if (!empty($account['calendarId'])) {
                    $googleCalendarIdFromAccounts = $account['calendarId'];
                    break;
                }
            }

            $providerArray['googleCalendar']['insertPendingAppointments'] = $providerArray['googleCalendar']['insertPendingAppointments'] ?? false;
            $providerArray['googleCalendar']['includeBufferTime'] = $providerArray['googleCalendar']['includeBufferTime'] ?? false;

            $googleCalendarGlobalSettings = $settingsService->getCategorySettings('googleCalendar');
            $providerArray['googleCalendar']['title'] = $providerArray['googleCalendar']['title'] ??
                ($googleCalendarGlobalSettings['title'] ?? ['appointment' => '%service_name%', 'event' => '%event_name%']);
            $providerArray['googleCalendar']['description'] = $providerArray['googleCalendar']['description'] ??
                ($googleCalendarGlobalSettings['description'] ?? ['appointment' => '', 'event' => '']);

            $blockedCalendars = [];
            foreach ($googleCalendarAccounts as $account) {
                if (!empty($account['blockedCalendars'])) {
                    foreach ($account['blockedCalendars'] as $calendarId) {
                        if (!in_array($calendarId, $blockedCalendars)) {
                            $blockedCalendars[] = $calendarId;
                        }
                    }
                }
            }
            $providerArray['googleCalendar']['blockedCalendars'] = $blockedCalendars;
            try {
                $googleCalendar = $settingsService->getCategorySettings('googleCalendar');
                if (!$googleCalendar['accessToken']) {
                    $providerArray['googleCalendar']['calendarList'] = $googleCalService->listCalendarList($provider);
                    $providerArray['googleCalendar']['calendarId'] = $googleCalService->getProviderGoogleCalendarId($provider);

                    if (!empty($providerArray['googleCalendar']['accounts'])) {
                        $providerArray['googleCalendar']['accounts'] = $googleCalService->getCalendarListsForAccounts(
                            $providerArray['googleCalendar']['accounts'],
                            $provider
                        );
                    }
                } else {
                    $providerArray['googleCalendar']['calendarList'] = $googleCalendarMiddlewareService->getCalendarList($providerArray['googleCalendar']);
                    $providerArray['googleCalendar']['calendarId'] = $googleCalendarIdFromAccounts;

                    // Fetch calendar lists for all accounts
                    if (!empty($providerArray['googleCalendar']['accounts'])) {
                        $providerArray['googleCalendar']['accounts'] = $googleCalendarMiddlewareService->getCalendarListsForAccounts(
                            $providerArray['googleCalendar']['accounts']
                        );
                    }
                }
            } catch (\Exception $e) {
                $providerArray['googleCalendar']['calendarId'] = !empty($providerArray['googleCalendar']['calendarId'])
                    ? $providerArray['googleCalendar']['calendarId']
                    : null;

                $providerArray['googleCalendar']['calendarList'] = [];

                $providerRepository->updateErrorColumn($providerId, $e->getMessage());
                $successfulGoogleConnection = false;
            }

            if ($googleCalendarIdFromAccounts) {
                $providerArray['googleCalendar']['calendarId'] = $googleCalendarIdFromAccounts;
            }
        }

        if ($settingsService->isFeatureEnabled('outlookCalendar')) {
            $outlookCalendarAccounts = $providerRepository->getOutlookCalendarAccounts($providerId);

            $providerArray['outlookCalendar']['accounts'] = $outlookCalendarAccounts;

            $outlookCalendarIdFromAccounts = null;
            foreach ($outlookCalendarAccounts as $account) {
                if (!empty($account['calendarId'])) {
                    $outlookCalendarIdFromAccounts = $account['calendarId'];
                    break;
                }
            }

            $providerArray['outlookCalendar']['insertPendingAppointments'] = $providerArray['outlookCalendar']['insertPendingAppointments'] ?? false;
            $providerArray['outlookCalendar']['includeBufferTime'] = $providerArray['outlookCalendar']['includeBufferTime'] ?? false;

            $outlookCalendarGlobalSettings = $settingsService->getCategorySettings('outlookCalendar');
            $providerArray['outlookCalendar']['title'] = $providerArray['outlookCalendar']['title'] ??
                ($outlookCalendarGlobalSettings['title'] ?? ['appointment' => '%service_name%', 'event' => '%event_name%']);
            $providerArray['outlookCalendar']['description'] = $providerArray['outlookCalendar']['description'] ??
                ($outlookCalendarGlobalSettings['description'] ?? ['appointment' => '', 'event' => '']);

            $blockedCalendars = [];
            foreach ($outlookCalendarAccounts as $account) {
                if (!empty($account['blockedCalendars'])) {
                    foreach ($account['blockedCalendars'] as $calendarId) {
                        if (!in_array($calendarId, $blockedCalendars)) {
                            $blockedCalendars[] = $calendarId;
                        }
                    }
                }
            }
            $providerArray['outlookCalendar']['blockedCalendars'] = $blockedCalendars;
            try {
                $outlookCalendar = $settingsService->getCategorySettings('outlookCalendar');
                if (!$outlookCalendar['accessToken']) {
                    $providerArray['outlookCalendar']['calendarList'] = $outlookCalendarService->listCalendarList($provider);
                    $providerArray['outlookCalendar']['calendarId'] = $outlookCalendarService->getProviderOutlookCalendarId($provider);

                    if (!empty($providerArray['outlookCalendar']['accounts'])) {
                        $providerArray['outlookCalendar']['accounts'] = $outlookCalendarService->getCalendarListsForAccounts(
                            $providerArray['outlookCalendar']['accounts'],
                            $provider
                        );
                    }
                } else {
                    $providerArray['outlookCalendar']['calendarList'] = $outlookCalendarMiddlewareService->getCalendarList($providerArray['outlookCalendar']);
                    $providerArray['outlookCalendar']['calendarId'] = $outlookCalendarIdFromAccounts;

                    // Fetch calendar lists for all accounts
                    if (!empty($providerArray['outlookCalendar']['accounts'])) {
                        $providerArray['outlookCalendar']['accounts'] = $outlookCalendarMiddlewareService->getCalendarListsForAccounts(
                            $providerArray['outlookCalendar']['accounts']
                        );
                    }
                }
            } catch (\Exception $e) {
                $providerArray['outlookCalendar']['calendarId'] = !empty($providerArray['outlookCalendar']['calendarId'])
                    ? $providerArray['outlookCalendar']['calendarId']
                    : null;

                $providerArray['outlookCalendar']['calendarList'] = [];

                $providerRepository->updateErrorColumn($providerId, $e->getMessage());
                $successfulOutlookConnection = false;
            }

            if ($outlookCalendarIdFromAccounts) {
                $providerArray['outlookCalendar']['calendarId'] = $outlookCalendarIdFromAccounts;
            }
        }

        $providerArray['mandatoryServicesIds'] = $providerServiceRepository->getMandatoryServicesIdsForProvider($providerId);

        $providerArray['eventList'] = array_map(
            function ($event) {
                return [
                    'name' => $event['name'],
                    'id' => $event['id'],
                    'periods' => $event['periods'],
                    'color' => $event['color'],
                    'organizer' => ['id' => $event['organizerId']]
                ];
            },
            $providerArray['eventList']
        );

        $providerArray = apply_filters('amelia_get_provider_filter', $providerArray);

        do_action('amelia_get_provider', $providerArray);

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully retrieved user.');
        $result->setData(
            [
                Entities::USER                  => $providerArray,
                'successfulGoogleConnection'    => $successfulGoogleConnection,
                'successfulOutlookConnection'   => $successfulOutlookConnection,
                'futureAppointmentsServicesIds' => $futureAppointmentsServicesIds,
            ]
        );

        return $result;
    }
}
