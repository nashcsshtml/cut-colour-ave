<?php

namespace AmeliaBooking\Infrastructure\Services\Outlook;

use AmeliaBooking\Application\Services\CustomField\AbstractCustomFieldApplicationService;
use AmeliaBooking\Application\Services\Placeholder\PlaceholderService;
use AmeliaBooking\Application\Services\User\ProviderApplicationService;
use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Booking\Appointment\Appointment;
use AmeliaBooking\Domain\Entity\Booking\Appointment\CustomerBooking;
use AmeliaBooking\Domain\Entity\Booking\Event\EventPeriod;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\Provider;
use AmeliaBooking\Domain\Factory\Booking\Appointment\AppointmentFactory;
use AmeliaBooking\Domain\Factory\Outlook\OutlookCalendarFactory;
use AmeliaBooking\Domain\Factory\User\ProviderFactory;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Domain\ValueObjects\String\Label;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Common\Exceptions\NotFoundException;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\AppointmentRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Event\EventPeriodsRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Event\EventRepository;
use AmeliaBooking\Infrastructure\Repository\Location\LocationRepository;
use AmeliaBooking\Infrastructure\Repository\User\CustomerRepository;
use AmeliaBooking\Infrastructure\Repository\User\ProviderRepository;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Appointment\AppointmentAddedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Appointment\AppointmentDeletedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Appointment\AppointmentEditedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Appointment\AppointmentStatusUpdatedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Appointment\AppointmentTimeUpdatedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Appointment\BookingAddedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Appointment\BookingApprovedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Appointment\BookingCanceledEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Appointment\BookingRejectedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Event\EventAddedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Event\EventEditedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Event\EventStatusUpdatedEventHandler;
use Exception;
use Interop\Container\Exception\ContainerException;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\Attendee;
use Microsoft\Graph\Model\BodyType;
use Microsoft\Graph\Model\Calendar;
use Microsoft\Graph\Model\DateTimeTimeZone;
use Microsoft\Graph\Model\Event;
use Microsoft\Graph\Model\FileAttachment;
use Microsoft\Graph\Model\FreeBusyStatus;
use Microsoft\Graph\Model\Message;
use Microsoft\Graph\Model\ItemBody;
use Microsoft\Graph\Model\Location;
use Microsoft\Graph\Model\OnlineMeetingProviderType;
use Microsoft\Graph\Model\OutlookGeoCoordinates;
use Microsoft\Graph\Model\PhysicalAddress;
use Microsoft\Graph\Model\SingleValueLegacyExtendedProperty;
use Microsoft\Graph\Model\User;
use WP_Error;

/**
 * Class OutlookCalendarService
 *
 * @package AmeliaBooking\Infrastructure\Services\Outlook
 */
class OutlookCalendarService extends AbstractOutlookCalendarService
{
    /** @var Graph */
    private $graph;

    /** @var mixed */
    private $outlookCalendarSettings;

    public const GUID = '{66f5a359-4659-4830-9070-00049ec6ac6e}';

    /** @var SettingsService */
    private $settings;

    /**
     * OutlookCalendarService constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->settings = $this->container->get('domain.settings.service');
        $this->outlookCalendarSettings = $this->settings->getCategorySettings('outlookCalendar');

        $this->graph = new Graph();
    }

    /**
     * Create a URL to obtain user authorization.
     *
     * @param $providerId
     *
     * @return string
     *
     * @throws ContainerException
     */
    public function createAuthUrl($providerId)
    {
        $params = [
            'client_id'     => $this->outlookCalendarSettings['clientID'],
            'response_type' => 'code',
            'redirect_uri'  => !AMELIA_DEV
                ? str_replace('http://', 'https://', $this->outlookCalendarSettings['redirectURI'])
                : $this->outlookCalendarSettings['redirectURI'],
            'scope'         => $providerId
                ? 'offline_access calendars.readwrite'
                : 'offline_access calendars.readwrite mail.send',
            'response_mode' => 'query',
            'state'         => 'amelia-outlook-calendar-auth-' . $providerId,
            'prompt'        => 'select_account',
        ];

        return add_query_arg(
            urlencode_deep($params),
            'https://login.microsoftonline.com/common/oauth2/v2.0/authorize'
        );
    }

    /**
     * @return void
     */
    public static function handleCallback()
    {
        if (isset($_REQUEST['code'], $_REQUEST['state']) && !isset($_REQUEST['scope']) && !isset($_REQUEST['type']) && !isset($_REQUEST['response_type'])) {
            $id = (int)substr($_REQUEST['state'], strrpos($_REQUEST['state'], '-') + 1);

            wp_redirect(
                add_query_arg(
                    urlencode_deep(
                        [
                        'code'  => esc_attr($_REQUEST['code']),
                        'state' => esc_attr($_REQUEST['state']),
                        'type'  => 'outlook'
                        ]
                    ),
                    admin_url($id ? 'admin.php?page=wpamelia-employees#/manage/' . $id . '/integrations/outlook-calendar' : 'admin.php?page=wpamelia-settings')
                )
            );
        }
    }

    /**
     * @param $authCode
     * @param $redirectUri
     * @param $providerId
     *
     * @return array|bool
     */
    public function fetchAccessTokenWithAuthCode($authCode, $redirectUri, $providerId)
    {
        $redirectUrl = empty($redirectUri) ? $this->outlookCalendarSettings['redirectURI'] : explode('?', $redirectUri)[0];

        $response = wp_remote_post(
            'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            [
                'timeout' => 25,
                'body'    => [
                    'client_id'     => $this->outlookCalendarSettings['clientID'],
                    'client_secret' => $this->outlookCalendarSettings['clientSecret'],
                    'grant_type'    => 'authorization_code',
                    'code'          => $authCode,
                    'redirect_uri'  => !AMELIA_DEV
                        ? str_replace('http://', 'https://', $redirectUrl)
                        : $redirectUrl,
                    'scope'         => $providerId
                        ? 'offline_access calendars.readwrite'
                        : 'offline_access calendars.readwrite mail.send',
                ]
            ]
        );

        if ($response instanceof WP_Error) {
            return false;
        }

        if ($response['response']['code'] !== 200) {
            $error = json_decode($response['body'], true);
            return [
                'outcome' => false,
                'result'  => $error['error_description']
            ];
        }

        $decodedToken = json_decode($response['body'], true);

        $decodedToken['created'] = time();

        return ['outcome' => true, 'result' => json_encode($decodedToken)];
    }

    private function isAccessTokenSet()
    {
        return (
            array_key_exists('accessToken', $this->outlookCalendarSettings) &&
            !empty($this->outlookCalendarSettings['accessToken']) &&
            $this->settings->isFeatureEnabled('outlookCalendar'));
    }

    /**
     * @param string $token
     *
     * @return string
     * @throws ContainerException
     * @throws Exception
     */
    private function authorize($token)
    {
        try {
            if (empty($token)) {
                error_log('OutlookCalendar: Empty token provided to authorize()');
                return '';
            }

            if ($expiredToken = $this->isAccessTokenExpired($token)) {
                $token = $this->refreshToken($token);

                if (empty($token)) {
                    error_log('OutlookCalendar: Failed to refresh expired token');
                    return '';
                }
            }

            $tokenArray = json_decode($token, true);

            if (!$tokenArray || !isset($tokenArray['access_token'])) {
                error_log('OutlookCalendar: Invalid token format - missing access_token');
                return '';
            }

            $this->graph->setAccessToken($tokenArray['access_token']);

            return $expiredToken ? $token : '';
        } catch (\Exception $e) {
            error_log('OutlookCalendar: Error in authorize() - ' . $e->getMessage());
            return '';
        }
    }

    /**
     * @return void
     * @throws ContainerException
     * @throws Exception
     */
    private function authorizeAdmin()
    {
        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        /** @var array $outlookSettings */
        $outlookSettings = $settingsService->getCategorySettings('outlookCalendar');

        if ($token = $this->authorize(json_encode($outlookSettings['token']))) {
            $settings = $settingsService->getAllSettingsCategorized();

            $settings['outlookCalendar']['token'] = json_decode($token, true);

            $settingsService->setAllSettings($settings);
        }
    }

    /**
     * @param Provider $provider
     *
     * @return bool
     * @throws ContainerException
     * @throws Exception
     */
    private function authorizeProvider(Provider $provider): bool
    {
        if ($this->isAccessTokenSet()) {
            /** @var OutlookCalendarMiddlewareService $outlookCalendarMiddlewareService */
            $outlookCalendarMiddlewareService = $this->container->get(
                'infrastructure.outlook.calendar.middleware.service'
            );

            // Pass provider's calendar data - middleware will use provider's token if available
            $providerOutlookCalendar = $provider->getOutlookCalendar() ? $provider->getOutlookCalendar()->toArray() : null;
            $graph = $outlookCalendarMiddlewareService->getGraph($providerOutlookCalendar);

            if ($graph === null) {
                return false;
            }

            $this->graph = $graph;
            return true;
        }

        if ($this->isCalendarEnabled() && $provider->getOutlookCalendar()) {
            try {
                $token = $provider->getOutlookCalendar()->getToken()->getValue();

                if (empty($token)) {
                    error_log('OutlookCalendar: Empty token for provider ' . $provider->getId()->getValue());
                    return false;
                }

                if ($authorizedToken = $this->authorize($token)) {
                    /** @var ProviderApplicationService $providerApplicationService */
                    $providerApplicationService = $this->container->get('application.user.provider.service');

                    $outlookCalendarData = $provider->getOutlookCalendar()->toArray();
                    $outlookCalendarData['token'] = $authorizedToken;

                    $provider->setOutlookCalendar(
                        OutlookCalendarFactory::create($outlookCalendarData)
                    );

                    $providerApplicationService->updateProviderOutlookCalendar($provider);
                }
                return true;
            } catch (\Exception $e) {
                error_log('OutlookCalendar: Failed to authorize provider ' . $provider->getId()->getValue() . ': ' . $e->getMessage());
                return false;
            }
        }

        return false;
    }

    /**
     * Get insertPendingAppointments
     *
     * @param Provider $provider
     * @return bool
     */
    private function getInsertPendingAppointments($provider)
    {
        if ($provider && $provider->getOutlookCalendar()) {
            return (bool)$provider->getOutlookCalendar()->getInsertPendingAppointments();
        }

        return (bool)($this->outlookCalendarSettings['insertPendingAppointments'] ?? false);
    }

    /**
     * Get includeBufferTime setting
     *
     * @param Provider $provider
     * @return bool
     */
    private function getIncludeBufferTime($provider)
    {
        if ($provider && $provider->getOutlookCalendar()) {
            return (bool)$provider->getOutlookCalendar()->getIncludeBufferTime();
        }

        return (bool)($this->outlookCalendarSettings['includeBufferTimeOutlookCalendar'] ?? false);
    }

    /**
     * @param Provider $provider
     *
     * @return array
     * @throws ContainerException
     * @throws GraphException
     * @throws QueryExecutionException
     */
    public function listCalendarList($provider): array
    {
        $calendars = [];

        if ($provider && $provider->getOutlookCalendar() && ($this->isCalendarEnabled() || $this->isAccessTokenSet())) {
            if (!$this->authorizeProvider($provider)) {
                return $calendars;
            }

            $outlookCalendars = $this->graph
                ->createCollectionRequest('GET', '/me/calendars')
                ->setReturnType(Calendar::class)
                ->setPageSize(100)
                ->getPage();

            /** @var Calendar $outlookCalendar */
            foreach ($outlookCalendars as $outlookCalendar) {
                if ($outlookCalendar->getCanEdit()) {
                    $calendars[] = [
                        'id'   => $outlookCalendar->getId(),
                        'name' => $outlookCalendar->getName(),
                        'owner' => $outlookCalendar->getOwner()->getName(),
                    ];
                }
            }
        }

        return $calendars;
    }

    /**
     * Get Outlook account user info
     *
     * @param Provider $provider
     *
     * @return array|null
     * @throws GraphException
     */
    public function getUserInfo($provider)
    {
        if (!$provider || !$provider->getOutlookCalendar()) {
            return null;
        }

        if (!$this->authorizeProvider($provider)) {
            return null;
        }

        try {
            $user = $this->graph->createRequest('GET', '/me')
                ->setReturnType(User::class)
                ->execute();

            return [
                'name'    => $user->getDisplayName(),
                'email'   => $user->getMail() ?? $user->getUserPrincipalName(),
            ];
        } catch (GraphException $e) {
            error_log('OutlookCalendar: Failed to fetch user info - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get Provider's Outlook Calendar ID.
     *
     * @param Provider $provider
     *
     * @return null|string
     * @throws GraphException|ContainerException|QueryExecutionException
     * @throws InvalidArgumentException
     */
    public function getProviderOutlookCalendarId($provider)
    {
        if (!$this->isCalendarEnabled()) {
            return null;
        }

        // If Outlook Calendar ID is not set, take the primary calendar and save it as Provider's Outlook Calendar ID
        if ($provider && $provider->getOutlookCalendar() && $provider->getOutlookCalendar()->getCalendarId()->getValue() === null) {
            $calendarList = $this->listCalendarList($provider);

            /** @var ProviderApplicationService $providerApplicationService */
            $providerApplicationService = $this->container->get('application.user.provider.service');

            $provider->getOutlookCalendar()->setCalendarId(new Label($calendarList[0]['id']));

            $providerApplicationService->updateProviderOutlookCalendar($provider);

            return $provider->getOutlookCalendar()->getCalendarId()->getValue();
        }

        // If Outlook Calendar is set, return it
        if ($provider && $provider->getOutlookCalendar() && $provider->getOutlookCalendar()->getCalendarId()->getValue() !== null) {
            return $provider->getOutlookCalendar()->getCalendarId()->getValue();
        }

        return null;
    }


    /**
     * Handle Google Calendar Event's.
     *
     * @param Appointment $appointment
     * @param string      $commandSlug
     *
     * @return void
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    public function handleEvent($appointment, $commandSlug, $oldStatus = null)
    {
        if (!$this->isCalendarEnabled() && !$this->isAccessTokenSet()) {
            return;
        }

        try {
            $this->handleEventAction($appointment, $commandSlug);
        } catch (Exception $e) {
            /** @var AppointmentRepository $appointmentRepository */
            $appointmentRepository = $this->container->get('domain.booking.appointment.repository');

            $appointmentRepository->updateErrorColumn($appointment->getId()->getValue(), $e->getMessage());
        }
    }


    /**
     * Handle Google Calendar Events.
     *
     * @param \AmeliaBooking\Domain\Entity\Booking\Event\Event $event
     * @param string $commandSlug
     * @param Collection $periods
     * @param array $newProviders
     * @param array $removeProviders
     *
     * @return void
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    public function handleEventPeriod($event, $commandSlug, $periods, $newProviders = null, $removeProviders = null)
    {
        if (!$this->isCalendarEnabled() && !$this->isAccessTokenSet()) {
            return;
        }

        try {
            $this->handleEventPeriodAction($event, $commandSlug, $periods, $newProviders, $removeProviders);
        } catch (Exception $e) {
            /** @var EventRepository $eventRepository */
            $eventRepository = $this->container->get('domain.booking.event.repository');

            $eventRepository->updateErrorColumn($event->getId()->getValue(), $e->getMessage());
        }
    }

    /**
     * @param Appointment $appointment
     * @param string      $commandSlug
     * @param null|string $oldStatus
     *
     * @return void
     * @throws ContainerException
     * @throws GraphException
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     */
    private function handleEventAction($appointment, $commandSlug, $oldStatus = null)
    {
        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        $appointmentStatus = $appointment->getStatus()->getValue();

        $provider = $providerRepository->getById($appointment->getProviderId()->getValue());

        if (
            $provider && (
                ($provider->getOutlookCalendar() && $provider->getOutlookCalendar()->getCalendarId()->getValue()) ||
                ($provider->getOutlookCalendarId() && $provider->getOutlookCalendarId()->getValue())
            )
        ) {
            if (!$this->authorizeProvider($provider)) {
                return;
            }

            switch ($commandSlug) {
                case AppointmentAddedEventHandler::APPOINTMENT_ADDED:
                case BookingAddedEventHandler::BOOKING_ADDED:
                    if ($appointmentStatus === 'pending' && $this->getInsertPendingAppointments($provider) === false) {
                        break;
                    }

                    // Add new appointment or update existing one
                    if (!$appointment->getOutlookCalendarEventId()) {
                        $this->insertEvent($appointment, $provider);
                    } else {
                        $this->updateEvent($appointment, $provider);
                    }

                    break;
                case AppointmentEditedEventHandler::APPOINTMENT_EDITED:
                case AppointmentTimeUpdatedEventHandler::TIME_UPDATED:
                case AppointmentStatusUpdatedEventHandler::APPOINTMENT_STATUS_UPDATED:
                case BookingCanceledEventHandler::BOOKING_CANCELED:
                case BookingApprovedEventHandler::BOOKING_APPROVED:
                case BookingRejectedEventHandler::BOOKING_REJECTED:
                    if (
                        $appointmentStatus === 'canceled' || $appointmentStatus === 'rejected' ||
                        ($appointmentStatus === 'pending' && $this->getInsertPendingAppointments($provider) === false)
                    ) {
                        $this->deleteEvent($appointment, $provider);
                        break;
                    }

                    if (
                        $appointmentStatus === 'approved' && $oldStatus && $oldStatus !== 'approved' &&
                        $this->getInsertPendingAppointments($provider) === false
                    ) {
                        $this->insertEvent($appointment, $provider);
                        break;
                    }

                    if (!$appointment->getOutlookCalendarEventId()) {
                        $this->insertEvent($appointment, $provider);
                        break;
                    }

                    $this->updateEvent($appointment, $provider);
                    break;
                case AppointmentDeletedEventHandler::APPOINTMENT_DELETED:
                    $this->deleteEvent($appointment, $provider);
                    break;
            }
        }
    }

    /**
     * @param \AmeliaBooking\Domain\Entity\Booking\Event\Event $event
     * @param string $commandSlug
     * @param Collection $periods
     *
     * @return void
     * @throws ContainerException
     * @throws GraphException
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     */
    private function handleEventPeriodAction($event, $commandSlug, $periods, $newProviders = null, $removeProviders = null)
    {
        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        if ($event->getOrganizerId()) {
            $provider = $providerRepository->getById($event->getOrganizerId()->getValue());

            if (
                $provider && (
                    ($provider->getOutlookCalendar() && $provider->getOutlookCalendar()->getCalendarId()->getValue()) ||
                    ($provider->getOutlookCalendarId() && $provider->getOutlookCalendarId()->getValue())
                )
            ) {
                if (!$this->authorizeProvider($provider)) {
                    return;
                }

                /** @var EventPeriod $period */
                foreach ($periods->getItems() as $period) {
                    switch ($commandSlug) {
                        case EventAddedEventHandler::EVENT_ADDED:
                        case EventEditedEventHandler::TIME_UPDATED:
                        case EventEditedEventHandler::PROVIDER_CHANGED:
                            if (!$period->getOutlookCalendarEventId()) {
                                $this->insertEvent($event, $provider, $period);
                                break;
                            }

                            $this->updateEvent($event, $provider, $period, $newProviders, $removeProviders);
                            break;
                        case EventEditedEventHandler::EVENT_PERIOD_DELETED:
                            $this->deleteEvent($period, $provider);
                            break;
                        case BookingAddedEventHandler::BOOKING_ADDED:
                        case BookingCanceledEventHandler::BOOKING_CANCELED:
                            if (!$period->getOutlookCalendarEventId()) {
                                $this->insertEvent($event, $provider, $period);
                            } else {
                                $this->updateEvent($event, $provider, $period);
                            }
                            break;
                        case EventStatusUpdatedEventHandler::EVENT_STATUS_UPDATED:
                            if ($event->getStatus()->getValue() === 'rejected') {
                                $this->deleteEvent($period, $provider);
                            } elseif ($event->getStatus()->getValue() === 'approved') {
                                $this->insertEvent($event, $provider, $period);
                            }
                            break;
                        case EventEditedEventHandler::EVENT_PERIOD_ADDED:
                            $this->insertEvent($event, $provider, $period);
                            break;
                    }
                }
            }
        }
    }

    /**
     * Get providers events within date range
     *
     * @param array $providerArr
     * @param string $dateStart
     * @param string $dateStartEnd
     * @param string $dateEnd
     * @param array $eventIds
     *
     * @return array
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws ContainerException
     * @throws GraphException
     * @throws Exception
     */
    public function getEvents($providerArr, $dateStart, $dateStartEnd, $dateEnd, $eventIds)
    {
        if (!$this->isCalendarEnabled() && !$this->isAccessTokenSet()) {
            return [];
        }

        $finalEvents = [];
        $provider    = ProviderFactory::create($providerArr);
        if (
            $provider && (
                ($provider->getOutlookCalendar() && $provider->getOutlookCalendar()->getCalendarId()->getValue()) ||
                ($provider->getOutlookCalendarId() && $provider->getOutlookCalendarId()->getValue())
            )
        ) {
            if (!$this->authorizeProvider($provider)) {
                return $finalEvents;
            }
            $startDate    = DateTimeService::getCustomDateTimeObject($dateStart);
            $startDateEnd = DateTimeService::getCustomDateTimeObject($dateStartEnd);
            $endDate      = DateTimeService::getCustomDateTimeObject($dateEnd);

            $outlookCalendarId = $provider->getOutlookCalendar() ?
                $provider->getOutlookCalendar()->getCalendarId()->getValue() :
                $provider->getOutlookCalendarId()->getValue();

            $request = $this->graph->createCollectionRequest(
                'GET',
                sprintf(
                    '/me/calendars/%s/calendarView?startDateTime=%s&endDateTime=%s&$expand=%s&$orderby=%s',
                    $outlookCalendarId,
                    rawurlencode($startDate->format('c')),
                    rawurlencode($endDate->format('c')),
                    rawurlencode(
                        'singleValueExtendedProperties($filter=id eq \'Integer ' .
                        self::GUID . ' Name appointmentId\')'
                    ),
                    rawurlencode('start/dateTime')
                )
            )
                ->setReturnType(Event::class)
                ->setPageSize($this->outlookCalendarSettings['maximumNumberOfEventsReturned']);

            $events = $request->getPage();

            /** @var Event $event */
            foreach ($events as $event) {
                if ($event->getShowAs()->value() === 'free') {
                    continue;
                }
                $extendedProperties = $event->getSingleValueExtendedProperties();
                if ($extendedProperties !== null && !$this->outlookCalendarSettings['ignoreAmeliaEvents']) {
                    foreach ($extendedProperties as $extendedProperty) {
                        if (
                            $extendedProperty['id'] === 'Integer ' . self::GUID . ' Name appointmentId' &&
                            in_array((int)$extendedProperty['value'], $eventIds)
                        ) {
                            continue 2;
                        }
                    }
                }
                $eventStart = DateTimeService::getCustomDateTimeObject($event->getStart()->getDateTime());
                $eventEnd   = DateTimeService::getCustomDateTimeObject($event->getEnd()->getDateTime());

                $eventDateStart = DateTimeService::getCustomDateTimeObject($eventStart->format('Y-m-d') . ' ' . $startDate->format('H:i:s'));
                $eventDateEnd   = DateTimeService::getCustomDateTimeObject($eventEnd->format('Y-m-d') . ' ' . $startDateEnd->format('H:i:s'));

                if ($eventDateEnd <= $eventStart || $eventDateStart >= $eventEnd) {
                    continue;
                }
                $finalEvents[] = $event;
            }
        }

        return $finalEvents;
    }


    /**
     * Create fake appointments in provider's list so that these slots will not be available for booking
     *
     * @param Collection $providers
     * @param int        $excludeAppointmentId
     * @param \DateTime  $startDateTime
     * @param \DateTime  $endDateTime
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ContainerException
     */
    public function removeSlotsFromOutlookCalendar(
        $providers,
        $excludeAppointmentId,
        $startDateTime,
        $endDateTime
    ) {
        if (!$this->isCalendarEnabled() && !$this->isAccessTokenSet()) {
            return;
        }

        if ($this->outlookCalendarSettings['removeOutlookCalendarBusySlots'] === true) {
            /** @var ProviderRepository $providerRepository */
            $providerRepository = $this->container->get('domain.users.providers.repository');

            foreach ($providers->keys() as $providerKey) {
                /** @var Provider $provider */
                $provider = $providers->getItem($providerKey);

                if ($provider && ($provider->getOutlookCalendar() || $provider->getOutlookCalendarId())) {
                    $startDateTimeCopy = clone $startDateTime;
                    $startDateTimeCopy->modify('-1 days');

                    $endDateTimeCopy = clone $endDateTime;
                    $endDateTimeCopy->modify('+1 days');

                    // Process main calendar events
                    if (!array_key_exists($provider->getId()->getValue(), self::$providersOutlookEvents)) {
                        if (!$this->authorizeProvider($provider)) {
                            continue;
                        }

                        $outlookCalendarId = $provider->getOutlookCalendar() ?
                            $provider->getOutlookCalendar()->getCalendarId()->getValue() :
                            $provider->getOutlookCalendarId()->getValue();

                        try {
                            $request = $this->graph->createCollectionRequest(
                                'GET',
                                sprintf(
                                    '/me/calendars/%s/calendarView?startDateTime=%s&endDateTime=%s&$expand=%s&$orderby=%s',
                                    $outlookCalendarId,
                                    rawurlencode($startDateTimeCopy->format('c')),
                                    rawurlencode($endDateTimeCopy->format('c')),
                                    rawurlencode(
                                        'singleValueExtendedProperties($filter=id eq \'Integer ' .
                                        self::GUID . ' Name appointmentId\')'
                                    ),
                                    rawurlencode('start/dateTime')
                                )
                            )
                                ->setReturnType(Event::class)
                                ->setPageSize($this->outlookCalendarSettings['maximumNumberOfEventsReturned']);

                            $events = $request->getPage();
                            self::$providersOutlookEvents[$provider->getId()->getValue()] = $events;
                        } catch (\Exception $e) {
                            $errorMessage = $e->getMessage();
                            $isNotFound = strpos($errorMessage, '404') !== false ||
                                          strpos($errorMessage, 'ErrorItemNotFound') !== false;

                            if ($isNotFound) {
                                error_log('Outlook Calendar: Calendar not found (ID: ' . $outlookCalendarId .
                                    ') for provider ' . $provider->getId()->getValue() .
                                    '. The calendar may have been deleted or the connection needs to be re-established.');
                            } else {
                                error_log('Outlook Calendar: Failed to fetch events for calendar ' . $outlookCalendarId . ' - ' . $errorMessage);
                            }

                            self::$providersOutlookEvents[$provider->getId()->getValue()] = [];
                            continue;
                        }
                    } else {
                        $events = self::$providersOutlookEvents[$provider->getId()->getValue()];
                    }

                    $this->processOutlookCalendarEvents($events, $provider, $excludeAppointmentId);

                    // Process blocked calendars events
                    $this->processBlockedCalendarsEvents(
                        $provider,
                        $providerRepository,
                        $startDateTimeCopy,
                        $endDateTimeCopy,
                        $excludeAppointmentId
                    );
                }
            }
        }
    }

    /**
     * Process events from blocked calendars and add them as fake appointments
     *
     * @param Provider           $provider
     * @param ProviderRepository $providerRepository
     * @param \DateTime          $startDateTime
     * @param \DateTime          $endDateTime
     * @param int|null           $excludeAppointmentId
     *
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     */
    private function processBlockedCalendarsEvents(
        $provider,
        $providerRepository,
        $startDateTime,
        $endDateTime,
        $excludeAppointmentId
    ) {
        $providerId = $provider->getId()->getValue();
        $cacheKey = $providerId . '_blocked';

        if (array_key_exists($cacheKey, self::$providersOutlookEvents)) {
            return;
        }

        $accounts = $providerRepository->getOutlookCalendarAccounts($providerId);

        $this->fetchBlockedCalendarEventsFromAccounts(
            $accounts,
            $startDateTime,
            $endDateTime,
            $provider,
            $excludeAppointmentId
        );

        self::$providersOutlookEvents[$cacheKey] = true;
    }

    /**
     * Fetch events from blocked calendars for each account
     *
     * @param array     $accounts
     * @param \DateTime $startDateTime
     * @param \DateTime $endDateTime
     * @param Provider  $provider
     * @param int|null  $excludeAppointmentId
     *
     * @throws InvalidArgumentException
     */
    private function fetchBlockedCalendarEventsFromAccounts(
        $accounts,
        $startDateTime,
        $endDateTime,
        $provider,
        $excludeAppointmentId
    ) {
        foreach ($accounts as $account) {
            if (empty($account['blockedCalendars']) || empty($account['token'])) {
                continue;
            }

            $graph = $this->createGraphForAccount($account);

            if ($graph === null) {
                continue;
            }

            foreach ($account['blockedCalendars'] as $calendarId) {
                try {
                    $request = $graph->createCollectionRequest(
                        'GET',
                        sprintf(
                            '/me/calendars/%s/calendarView?startDateTime=%s&endDateTime=%s&$expand=%s&$orderby=%s',
                            $calendarId,
                            rawurlencode($startDateTime->format('c')),
                            rawurlencode($endDateTime->format('c')),
                            rawurlencode(
                                'singleValueExtendedProperties($filter=id eq \'Integer ' .
                                self::GUID . ' Name appointmentId\')'
                            ),
                            rawurlencode('start/dateTime')
                        )
                    )
                        ->setReturnType(Event::class)
                        ->setPageSize($this->outlookCalendarSettings['maximumNumberOfEventsReturned']);

                    $events = $request->getPage();
                    $this->processOutlookCalendarEvents($events, $provider, $excludeAppointmentId);
                } catch (\Exception $e) {
                    error_log('OutlookCalendar: Error fetching events from blocked calendar ' . $calendarId . ': ' . $e->getMessage());
                    continue;
                }
            }
        }
    }

    /**
     * Create a Graph instance for a given account
     *
     * @param array $account
     *
     * @return Graph|null
     */
    private function createGraphForAccount($account)
    {
        try {
            $token = $account['token'];

            if ($this->isAccessTokenExpired($token)) {
                $token = $this->refreshTokenForAccount($token, $account['id']);

                if ($token === null) {
                    error_log('OutlookCalendar: Failed to refresh token for account ID ' . $account['id']);
                    return null;
                }
            }

            $tokenArray = json_decode($token, true);

            $graph = new Graph();
            $graph->setAccessToken($tokenArray['access_token']);

            return $graph;
        } catch (\Exception $e) {
            error_log('OutlookCalendar: Error creating graph for account - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Refresh token for a specific account and update it in the database
     *
     * @param string $token
     * @param int    $accountId
     *
     * @return string|null
     */
    private function refreshTokenForAccount($token, $accountId)
    {
        try {
            if ($this->isAccessTokenSet()) {
                /** @var OutlookCalendarMiddlewareService $outlookCalendarMiddlewareService */
                $outlookCalendarMiddlewareService = $this->container->get(
                    'infrastructure.outlook.calendar.middleware.service'
                );

                $decodedToken = json_decode($token, true);

                if (!isset($decodedToken['refresh_token'])) {
                    return null;
                }

                $newToken = $outlookCalendarMiddlewareService->refreshAccessToken($decodedToken['refresh_token']);

                if ($newToken === null) {
                    return null;
                }

                $newTokenJson = json_encode($newToken);
            } else {
                $newTokenJson = $this->refreshToken($token);
            }

            /** @var ProviderRepository $providerRepository */
            $providerRepository = $this->container->get('domain.users.providers.repository');
            $providerRepository->updateOutlookCalendarAccountToken($accountId, $newTokenJson);

            return $newTokenJson;
        } catch (\Exception $e) {
            error_log('OutlookCalendar: Error refreshing token for account - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Process Outlook Calendar events and add them as fake appointments to block slots
     *
     * @param array    $events
     * @param Provider $provider
     * @param int|null $excludeAppointmentId
     *
     * @throws InvalidArgumentException
     */
    private function processOutlookCalendarEvents($events, $provider, $excludeAppointmentId)
    {
        /** @var Event $event */
        foreach ($events as $event) {
            if ($event->getShowAs() !== null && $event->getShowAs()->is(FreeBusyStatus::FREE)) {
                continue;
            }

            $extendedProperties = $event->getSingleValueExtendedProperties();
            if ($extendedProperties !== null && !$this->outlookCalendarSettings['ignoreAmeliaEvents']) {
                foreach ($extendedProperties as $extendedProperty) {
                    if (
                        $extendedProperty['id'] === 'Integer ' . self::GUID . ' Name appointmentId' &&
                        $excludeAppointmentId && (int)$extendedProperty['value'] === $excludeAppointmentId
                    ) {
                        continue 2;
                    }
                }
            }

            $eventStartString = DateTimeService::getCustomDateTimeFromUtc($event->getStart()->getDateTime());

            $eventEndString = DateTimeService::getCustomDateTimeFromUtc($event->getEnd()->getDateTime());

            /** @var Appointment $appointment */
            $appointment = AppointmentFactory::create(
                [
                    'bookingStart'       => $eventStartString,
                    'bookingEnd'         => $eventEndString,
                    'notifyParticipants' => false,
                    'serviceId'          => 0,
                    'providerId'         => $provider->getId()->getValue(),
                ]
            );

            $provider->getAppointmentList()->addItem($appointment);
        }
    }

    /**
     * @param Appointment|\AmeliaBooking\Domain\Entity\Booking\Event\Event $appointment
     * @param Provider $provider
     * @param EventPeriod $period
     *
     * @return bool
     *
     * @throws ContainerException
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    private function insertEvent($appointment, $provider, $period = null, $newProviders = null, $removeProviders = null)
    {
        $event = $this->getEventPreview($appointment, $provider, $period);

        if (!$event) {
            return false;
        }

        $location = $event->getLocation();
        if ($location && !$location->getDisplayName()) {
            $newLocation = [
                'displayName' => $location->getDisplayName(),
                'locationType' => 'default',
                'uniqueIdType' => 'private'
            ];
            $event->setLocation($newLocation);
        }

        $type = $period ? Entities::EVENT : Entities::APPOINTMENT;
        /** @var PlaceholderService $placeholderService */
        $placeholderService         = $this->container->get("application.placeholder.{$type}.service");
        $appointmentArray           = $appointment->toArray();
        $appointmentArray['sendCF'] = true;

        $placeholderData = $placeholderService->getPlaceholdersData($appointmentArray);

        $eventId = $event->getId();

        $outlookAttendees = new Attendee($this->getAttendees($appointment, $provider, $newProviders, $removeProviders));
        $event->setAttendees($outlookAttendees);

        $eventType = $period ? 'event' : 'appointment';

        if ($provider->getOutlookCalendar()) {
            $providerDescription = $provider->getOutlookCalendar()->getDescription();

            if (
                is_array($providerDescription) &&
                array_key_exists($eventType, $providerDescription) &&
                $providerDescription[$eventType] !== '' &&
                $providerDescription[$eventType] !== null
            ) {
                $descriptionSettings = $providerDescription;
            } else {
                $descriptionSettings = [
                    'appointment' => '',
                    'event' => ''
                ];
            }
        } else {
            $descriptionSettings = $this->outlookCalendarSettings['description'];
        }

        $body = $event->getBody();

        $joinUrl = $event->getOnlineMeeting() ? $event->getOnlineMeeting()->getJoinUrl() : null;

        $body->setContentType(new BodyType(BodyType::HTML));
        $body->setContent($this->getDescriptionForInsert($placeholderService, $placeholderData, $period, $joinUrl, $descriptionSettings));

        $event = apply_filters('amelia_before_outlook_calendar_event_added_filter', $event, $appointment->toArray(), $provider->toArray());
        $event->setBody($body);

        do_action('amelia_before_outlook_calendar_event_added', $event, $appointment->toArray(), $provider->toArray());

        $outlookCalendarId = $provider->getOutlookCalendar() ?
            $provider->getOutlookCalendar()->getCalendarId()->getValue() :
            $provider->getOutlookCalendarId()->getValue();

        try {
            $event = $this->graph->createRequest(
                'PATCH',
                sprintf(
                    '/me/calendars/%s/events/%s',
                    $outlookCalendarId,
                    $eventId
                )
            )->attachBody($event)->setReturnType(Event::class)->execute();
        } catch (GraphException $e) {
            return false;
        }

        if ($period) {
            /** @var EventPeriodsRepository $eventPeriodsRepository */
            $eventPeriodsRepository = $this->container->get('domain.booking.event.period.repository');
            $period->setOutlookCalendarEventId(new Label($event->getId()));
            $period->setMicrosoftTeamsUrl($event->getOnlineMeeting() ? $event->getOnlineMeeting()->getJoinUrl() : null);
            $eventPeriodsRepository->updateFieldById($period->getId()->getValue(), $period->getOutlookCalendarEventId()->getValue(), 'outlookCalendarEventId');
            $eventPeriodsRepository->updateFieldById($period->getId()->getValue(), $period->getMicrosoftTeamsUrl(), 'microsoftTeamsUrl');
        } else {
            /** @var AppointmentRepository $appointmentRepository */
            $appointmentRepository = $this->container->get('domain.booking.appointment.repository');
            $appointment->setOutlookCalendarEventId(new Label($event->getId()));
            $appointment->setMicrosoftTeamsUrl($event->getOnlineMeeting() ? $event->getOnlineMeeting()->getJoinUrl() : null);
            $appointmentRepository->update($appointment->getId()->getValue(), $appointment);
        }

        do_action('amelia_after_outlook_calendar_event_added', $event, $appointment->toArray(), $provider->toArray());

        return true;
    }

    /**
     * Update an Event in Outlook Calendar.
     *
     * @param Appointment|\AmeliaBooking\Domain\Entity\Booking\Event\Event $appointment
     * @param Provider    $provider
     * @param EventPeriod $period
     * @param array $newProviders
     * @param array $removeProviders
     *
     * @return bool
     * @throws ContainerException
     * @throws QueryExecutionException
     */
    private function updateEvent($appointment, $provider, $period = null, $newProviders = null, $removeProviders = null)
    {
        $entity = $period ?: $appointment;
        if ($entity->getOutlookCalendarEventId()) {
            $event = $this->createEvent($appointment, $provider, $period);

            $eventId = $entity->getOutlookCalendarEventId()->getValue();

            $outlookAttendees = new Attendee($this->getAttendees($appointment, $provider, $newProviders, $removeProviders));
            $event->setAttendees($outlookAttendees);

            $type = $period ? Entities::EVENT : Entities::APPOINTMENT;
            /** @var PlaceholderService $placeholderService */
            $placeholderService         = $this->container->get("application.placeholder.{$type}.service");
            $appointmentArray           = $appointment->toArray();
            $appointmentArray['sendCF'] = true;

            $placeholderData = $placeholderService->getPlaceholdersData($appointmentArray);

            $eventType = $period ? 'event' : 'appointment';

            if ($provider->getOutlookCalendar()) {
                $providerDescription = $provider->getOutlookCalendar()->getDescription();

                if (
                    is_array($providerDescription) &&
                    array_key_exists($eventType, $providerDescription) &&
                    $providerDescription[$eventType] !== '' &&
                    $providerDescription[$eventType] !== null
                ) {
                    $descriptionSettings = $providerDescription;
                } else {
                    $descriptionSettings = [
                        'appointment' => '',
                        'event' => ''
                    ];
                }
            } else {
                $descriptionSettings = $this->outlookCalendarSettings['description'];
            }

            $description = $this->getDescription($placeholderService, $placeholderData, $type, $descriptionSettings);

            $body = $this->getBodyForInsert($eventId, $description);

            $event->setBody($body);

            $event = apply_filters('amelia_before_outlook_calendar_event_updated_filter', $event, $appointment->toArray(), $provider->toArray());

            do_action('amelia_before_outlook_calendar_event_updated', $event, $appointment->toArray(), $provider->toArray());

            $outlookCalendarId = $provider->getOutlookCalendar() ?
                $provider->getOutlookCalendar()->getCalendarId()->getValue() :
                $provider->getOutlookCalendarId()->getValue();

            try {
                $this->graph->createRequest(
                    'PATCH',
                    sprintf(
                        '/me/calendars/%s/events/%s',
                        $outlookCalendarId,
                        $eventId
                    )
                )->attachBody($event)->setReturnType(get_class($event))->execute();

                do_action('amelia_after_outlook_calendar_event_updated', $event, $appointment->toArray(), $provider->toArray());
            } catch (GraphException $e) {
                return false;
            }
        }

        return true;
    }

    /**
     * Delete an Event from Outlook Calendar.
     *
     * @param Appointment|EventPeriod $appointment
     * @param Provider    $provider
     *
     * @throws GraphException
     * @throws QueryExecutionException
     */
    private function deleteEvent($appointment, $provider)
    {
        if ($appointment->getOutlookCalendarEventId()) {
            do_action('amelia_before_outlook_calendar_event_deleted', $appointment->toArray(), $provider->toArray());

            $outlookCalendarId = $provider->getOutlookCalendar() ?
                $provider->getOutlookCalendar()->getCalendarId()->getValue() :
                $provider->getOutlookCalendarId()->getValue();

            $this->graph->createRequest(
                'DELETE',
                sprintf(
                    '/me/calendars/%s/events/%s',
                    $outlookCalendarId,
                    $appointment->getOutlookCalendarEventId()->getValue()
                )
            )->execute();

            $appointment->setOutlookCalendarEventId(null);
            $appointment->setMicrosoftTeamsUrl(null);

            /** @var AppointmentRepository $repository */
            $repository = $this->container->get('domain.booking.appointment.repository');

            if (is_a($appointment, EventPeriod::class)) {
                /** @var EventPeriodsRepository $repository */
                $repository = $this->container->get('domain.booking.event.period.repository');
            }
            $repository->updateFieldById($appointment->getId()->getValue(), null, 'outlookCalendarEventId');
            $repository->updateFieldById($appointment->getId()->getValue(), null, 'microsoftTeamsUrl');

            do_action('amelia_after_outlook_calendar_event_deleted', $appointment->toArray(), $provider->toArray());
        }
    }

    /**
     * @throws ContainerException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws QueryExecutionException
     */
    private function getEventPreview($appointment, $provider, $period = null)
    {
        $event = $this->createEvent($appointment, $provider, $period);

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        $enabledForEntity = $settingsService
            ->getEntitySettings($period ? $appointment->getSettings() : $appointment->getService()->getSettings())
            ->getMicrosoftTeamsSettings()
            ->getEnabled();

        $type = $period ? Entities::EVENT : Entities::APPOINTMENT;
        /** @var PlaceholderService $placeholderService */
        $placeholderService         = $this->container->get("application.placeholder.{$type}.service");
        $appointmentArray           = $appointment->toArray();
        $appointmentArray['sendCF'] = true;

        $placeholderData = $placeholderService->getPlaceholdersData($appointmentArray);

        $body = new ItemBody();
        $body->setContentType(new BodyType(BodyType::HTML));

        if ($enabledForEntity) {
            $body->setContent('');
            $event->setAttendees(new Attendee([]));
            $event->setIsOnlineMeeting(true);
            $event->setOnlineMeetingProvider(new OnlineMeetingProviderType('teamsForBusiness'));
        }
        if (!$enabledForEntity) {
            $body->setContent($this->getDescriptionForInsert($placeholderService, $placeholderData, $period, null));
        }
        $event->setBody($body);

        $outlookCalendarId = $provider->getOutlookCalendar() ?
            $provider->getOutlookCalendar()->getCalendarId()->getValue() :
            $provider->getOutlookCalendarId()->getValue();

        try {
            $event = $this->graph->createRequest(
                'POST',
                sprintf(
                    '/me/calendars/%s/events',
                    $outlookCalendarId
                )
            )->attachBody($event)->setReturnType(get_class($event))->execute();
            return $event;
        } catch (GraphException $e) {
            return null;
        }
    }

    /**
     * Create and return Outlook Calendar Event Object filled with appointments data.
     *
     * @param Appointment|\AmeliaBooking\Domain\Entity\Booking\Event\Event $appointment
     * @param Provider    $provider
     * @param EventPeriod $period
     *
     * @return Event
     *
     * @throws QueryExecutionException
     * @throws ContainerException
     * @throws Exception
     */
    private function createEvent($appointment, $provider, $period = null)
    {
        /** @var LocationRepository $locationRepository */
        $locationRepository = $this->container->get('domain.locations.repository');

        /** @var AbstractCustomFieldApplicationService $customFieldService */
        $customFieldService = $this->container->get('application.customField.service');

        $type = $period ? Entities::EVENT : Entities::APPOINTMENT;
        /** @var PlaceholderService $placeholderService */
        $placeholderService = $this->container->get("application.placeholder.{$type}.service");

        $appointmentLocationId = $appointment->getLocationId() ? $appointment->getLocationId()->getValue() : null;
        $providerLocationId    = $provider->getLocationId() ? $provider->getLocationId()->getValue() : null;

        $locationId = $appointmentLocationId ?: $providerLocationId;

        /** @var \AmeliaBooking\Domain\Entity\Location\Location $location */
        $location = $locationId ? $locationRepository->getById($locationId) : null;

        $address = $customFieldService->getCalendarEventLocation($appointment);

        $appointmentArray           = $appointment->toArray();
        $appointmentArray['sendCF'] = true;

        $placeholderData = $placeholderService->getPlaceholdersData($appointmentArray);

        $start = $period ?  clone $period->getPeriodStart()->getValue() : clone $appointment->getBookingStart()->getValue();

        if ($period) {
            $time = (int)$period->getPeriodEnd()->getValue()->format('H') * 60 + (int)$period->getPeriodEnd()->getValue()->format('i');
            $end  = DateTimeService::getCustomDateTimeObject(
                $start->format('Y-m-d')
            )->add(new \DateInterval('PT' . $time . 'M'));
        } else {
            $end = clone $appointment->getBookingEnd()->getValue();
        }

        if ($this->getIncludeBufferTime($provider) === true && $type === Entities::APPOINTMENT) {
            $timeBefore = $appointment->getService()->getTimeBefore() ?
                $appointment->getService()->getTimeBefore()->getValue() : 0;
            $timeAfter  = $appointment->getService()->getTimeAfter() ?
                $appointment->getService()->getTimeAfter()->getValue() : 0;
            $start->modify('-' . $timeBefore . ' second');
            $end->modify('+' . $timeAfter . ' second');
        }

        $eventType = $period ? 'event' : 'appointment';

        if ($provider->getOutlookCalendar()) {
            $providerTitle = $provider->getOutlookCalendar()->getTitle();

            if (
                is_array($providerTitle) &&
                array_key_exists($eventType, $providerTitle) &&
                $providerTitle[$eventType] !== '' &&
                $providerTitle[$eventType] !== null
            ) {
                $titleSettings = $providerTitle;
            } else {
                $titleSettings = [
                    'appointment' => '%service_name%',
                    'event' => '%event_name%'
                ];
            }
        } else {
            $titleSettings = $this->outlookCalendarSettings['title'];
        }

        $startDateTime = new DateTimeTimeZone();
        $startDateTime->setDateTime($start)->setTimeZone('UTC');
        $endDateTime = new DateTimeTimeZone();
        $endDateTime->setDateTime($end)->setTimeZone('UTC');

        $event = new Event();

        $event->setStart($startDateTime);
        $event->setEnd($endDateTime);

        $event->setSubject(
            $placeholderService->applyPlaceholders(
                $period ? $titleSettings['event'] : $titleSettings['appointment'],
                $placeholderData
            )
        );

        if ($location || $address) {
            $outlookLocation = new Location();
            $outlookLocation->setDisplayName($address ?: $location->getName()->getValue());

            if ($location && $location->getCoordinates()) {
                $outlookCoordinates = new OutlookGeoCoordinates();
                $outlookCoordinates->setLatitude($location->getCoordinates()->getLatitude());
                $outlookCoordinates->setLongitude($location->getCoordinates()->getLongitude());
                $outlookLocation->setCoordinates($outlookCoordinates);
            }

            if ($location) {
                $outlookAddress = new PhysicalAddress();
                $outlookAddress->setStreet($location->getAddress() ? $location->getAddress()->getValue() : null);
                $outlookLocation->setAddress($outlookAddress);
            }

            $event->setLocation($outlookLocation);
        }

        $property = new SingleValueLegacyExtendedProperty();
        $property->setId('Integer ' . self::GUID . ' Name appointmentId');
        $property->setValue((string)$appointment->getId()->getValue());

        $event->setSingleValueExtendedProperties([$property]);

        if ($period && $period->getPeriodStart()->getValue()->diff($period->getPeriodEnd()->getValue())->format('%a') !== '0') {
            $recData = [
                "pattern" => [
                    "type" => "daily",
                    "interval" => 1
                ],
                "range" => [
                    "type" => "endDate",
                    "startDate" => $period->getPeriodStart()->getValue()->format('Y-m-d'),
                    "endDate" => $period->getPeriodEnd()->getValue()->format('Y-m-d'),
                    "recurrenceTimeZone" => $period->getPeriodStart()->getValue()->getTimezone()->getName()
                ]
            ];
            $event->setRecurrence($recData);
        }

        return $event;
    }

    private function getDescription($placeholderService, $placeholderData, $type, $descriptionSettings = null)
    {
        if ($descriptionSettings === null) {
            $descriptionSettings = $this->outlookCalendarSettings['description'];
        }

        $description = $placeholderService->applyPlaceholders(
            $type === 'event' ? $descriptionSettings['event'] : $descriptionSettings['appointment'],
            $placeholderData
        );
        return str_replace("\n", '<br>', $description);
    }

    private function getDescriptionForInsert(
        $placeholderService,
        $placeholderData,
        $period,
        $joinUrl,
        $descriptionSettings = null
    ) {
        $type        = $period ? Entities::EVENT : Entities::APPOINTMENT;
        $description = $this->getDescription($placeholderService, $placeholderData, $type, $descriptionSettings);

        // include the joinUrl in the body content to ensure the Join button remains visible
        return $joinUrl
            ? '<a href="' . $joinUrl . '">Join Meeting</a><br><br>' . $description . '<br><br>'
            : $description;
    }

    private function getBodyForInsert($eventId, $description)
    {
        $event = $this->getEvent($eventId);
        $body  = $event->getBody();

        $joinUrl     = $event->getOnlineMeeting() ? $event->getOnlineMeeting()->getJoinUrl() : null;
        $bodyContent = ($joinUrl)
            ? '<a href="' . $joinUrl . '">Join Meeting</a><br><br>' .  $description . '<br><br>'
            : $description;

        $body->setContentType(new BodyType(BodyType::HTML));
        $body->setContent($bodyContent);

        return $body;
    }

    private function getEvent($eventId)
    {
        try {
            $event = $this->graph->createRequest(
                'GET',
                sprintf(
                    '/me/events/%s/',
                    $eventId
                )
            )->setReturnType(Event::class)->execute();
            return $event;
        } catch (GraphException $e) {
            return null;
        }
    }

    /**
     * Get All Attendees that need to be added in Outlook Calendar Event based on "addAttendees" Settings.
     *
     * @param Appointment|\AmeliaBooking\Domain\Entity\Booking\Event\Event $appointment
     * @param Provider $provider
     *
     * @return array
     *
     * @throws NotFoundException
     * @throws QueryExecutionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function getAttendees($appointment, $provider, $newProviders = null, $removeProviders = null)
    {
        $attendees = [];

        if ($this->outlookCalendarSettings['addAttendees'] === true) {
            /** @var ProviderRepository $providerRepository */
            $providerRepository = $this->container->get('domain.users.providers.repository');

            $providers = is_a($appointment, Appointment::class) ?
                [$providerRepository->getById($appointment->getProviderId()->getValue())] :
                $appointment->getProviders()->getItems();

            if ($newProviders) {
                $providers = array_merge($providers, $newProviders);
            }
            if ($removeProviders) {
                $providersRemoveIds = array_map(
                    function ($value) {
                        return $value->getId()->getValue();
                    },
                    $removeProviders
                );
            }

            foreach ($providers as $provider) {
                if (empty($providersRemoveIds) || !in_array($provider->getId()->getValue(), $providersRemoveIds)) {
                    $attendees[] = [
                        'emailAddress'    => [
                            'name'    => $provider->getFirstName()->getValue() . ' ' . $provider->getLastName()->getValue(),
                            'address' => $provider->getEmail()->getValue(),
                        ],
                        'type'            => 'required',
                        'status'  => [
                            'response' => 'accepted',
                            'time' => (new \DateTime('now'))->format(DATE_ATOM)
                        ]
                    ];
                }
            }

            /** @var CustomerRepository $customerRepository */
            $customerRepository = $this->container->get('domain.users.customers.repository');

            $bookings = $appointment->getBookings()->getItems();

            /** @var CustomerBooking $booking */
            foreach ($bookings as $booking) {
                $bookingStatus = $booking->getStatus()->getValue();

                if (
                    $bookingStatus === 'approved' ||
                    ($bookingStatus === 'pending' && $this->getInsertPendingAppointments($provider) === true)
                ) {
                    $customer = $customerRepository->getById($booking->getCustomerId()->getValue());

                    if ($customer->getEmail()->getValue()) {
                        $attendees[] = [
                            'emailAddress' => [
                                'name'    =>
                                    $customer->getFirstName()->getValue() . ' ' . $customer->getLastName()->getValue(),
                                'address' => $customer->getEmail()->getValue(),
                            ],
                            'type'         => 'required',
                            'status'  => [
                                'response' => 'accepted',
                                'time' => (new \DateTime('now'))->format(DATE_ATOM)
                            ]
                        ];
                    }
                }
            }
        }

        return $attendees;
    }

    /**
     * Refresh Token if it is expired
     *
     * @param String $token
     *
     * @return string
     *
     * @throws ContainerException
     * @throws Exception
     */
    private function refreshToken($token)
    {
        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        /** @var array $outlookSettings */
        $outlookSettings = $settingsService->getCategorySettings('outlookCalendar');

        $decodedToken = json_decode($token, true);

        $response = wp_remote_post(
            'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            array(
            'timeout' => 25,
            'body'    => array(
                'client_id'     => $outlookSettings['clientID'],
                'client_secret' => $outlookSettings['clientSecret'],
                'grant_type'    => 'refresh_token',
                'refresh_token' => $decodedToken['refresh_token'],
                'redirect_uri'  => !AMELIA_DEV
                    ? str_replace('http://', 'https://', $outlookSettings['redirectURI'])
                    : $outlookSettings['redirectURI'],
                'scope'         => 'offline_access calendars.readwrite',
            )
            )
        );

        if ($response instanceof WP_Error) {
            throw new \Exception($response->get_error_message());
        }

        if ($response['response']['code'] !== 200) {
            $responseBody = json_decode($response['body'], true);

            throw new \Exception($responseBody['error_description']);
        }

        $decodedToken            = json_decode($response['body'], true);
        $decodedToken['created'] = time();

        return json_encode($decodedToken);
    }

    /**
     * @param $token
     *
     * @return bool
     */
    private function isAccessTokenExpired($token)
    {
        $decodedToken = json_decode($token, true);

        if (!isset($decodedToken['created'])) {
            return true;
        }

        return ($decodedToken['created'] + ($decodedToken['expires_in'] - 30)) < time();
    }

    /**
     * @param DateTimeTimeZone $eventStart
     * @param DateTimeTimeZone $eventEnd
     *
     * @return array
     *
     * @throws Exception
     */
    private function removeTimeBasedEvents($eventStart, $eventEnd)
    {
        $timesToRemove = [];

        $daysBetweenStartAndEnd = (int)DateTimeService::getCustomDateTimeObjectFromUtc($eventEnd->getDateTime())
            ->diff(DateTimeService::getCustomDateTimeObjectFromUtc($eventStart->getDateTime()))->format('%a');

        // If event is in the same day, or not
        if ($daysBetweenStartAndEnd === 0) {
            $timesToRemove[] = [
                'eventStartDateTime' => DateTimeService::getCustomDateTimeFromUtc($eventStart->getDateTime()),
                'eventEndDateTime'   => DateTimeService::getCustomDateTimeFromUtc($eventEnd->getDateTime())
            ];
        } else {
            for ($i = 0; $i <= $daysBetweenStartAndEnd; $i++) {
                $startDateTime = DateTimeService::getCustomDateTimeObjectFromUtc(
                    $eventStart->getDateTime()
                )->modify('+' . $i . ' days');

                $timesToRemove[] = [
                    'eventStartDateTime' => $i === 0 ?
                        $startDateTime->format('Y-m-d H:i:s') :
                        $startDateTime->format('Y-m-d') . ' 00:00:01',
                    'eventEndDateTime'   => $i === $daysBetweenStartAndEnd ?
                        DateTimeService::getCustomDateTimeFromUtc($eventEnd->getDateTime()) :
                        $startDateTime->format('Y-m-d') . ' 23:59:59'
                ];
            }
        }

        return $timesToRemove;
    }

    /**
     * @return bool
     */
    private function isCalendarEnabled()
    {
        return $this->settings->isFeatureEnabled('outlookCalendar') &&
            $this->outlookCalendarSettings['clientID'] &&
            $this->outlookCalendarSettings['clientSecret'];
    }

    /** @noinspection MoreThanThreeArgumentsInspection */
    /**
     * @param string $from
     * @param string $fromName
     * @param string $replyTo
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param array  $bccEmails
     * @param array  $attachments
     *
     * @return void
     *
     * @throws ContainerException
     * @throws GraphException
     */
    public function sendEmail(
        $from,
        $fromName,
        $replyTo,
        $to,
        $subject,
        $body,
        $bccEmails = [],
        $attachments = []
    ) {
        $this->authorizeAdmin();

        $attachmentList = [];

        foreach ($attachments as $attachment) {
            $attachmentObject = new FileAttachment();

            $attachmentObject->setODataType("#microsoft.graph.fileAttachment");
            $attachmentObject->setName($attachment['fileName']);
            $attachmentObject->setContentType(mime_content_type($attachment['filePath']));
            $attachmentObject->setContentBytes(base64_encode(file_get_contents($attachment['filePath'])));
            $attachmentObject->setIsInline(false);

            $attachmentList[] = $attachmentObject;
        }

        $bccList = [];

        foreach ($bccEmails as $bcc) {
            $bccList[] = [
                'emailAddress' => [
                    'address' => trim($bcc),
                ]
            ];
        }

        $message = new Message(
            [
                'from'          => [
                    'emailAddress' => [
                        'name' => mb_convert_encoding($fromName, 'UTF-8'),
                    ]
                ],
                'subject'       => mb_convert_encoding($subject, 'UTF-8'),
                'body'          => [
                    'contentType' => 'HTML',
                    'content'     => mb_convert_encoding($body, 'UTF-8'),
                ],
                'toRecipients'  => [
                    [
                        'emailAddress' => [
                            'address' => trim($to),
                        ],
                    ]
                ],
                'replyTo'       => trim($replyTo) ? [
                    [
                        'emailAddress' => [
                            'address' => trim($replyTo),
                        ],
                    ]
                ] : [],
                'bccRecipients' => $bccList,
                'attachments'   => $attachmentList,
            ]
        );

        try {
            $this->graph
                ->createRequest('POST', '/me/sendMail')
                ->attachBody(
                    [
                        'message'         => $message,
                        'saveToSentItems' => true
                    ]
                )
                ->execute();
        } catch (\Exception $e) {
        }
    }

    public function getCalendarListsForAccounts(array $accounts, $provider): array
    {
        foreach ($accounts as &$account) {
            if (isset($account['token']) && $account['token']) {
                try {
                    $graph = $this->createGraphForAccount($account);

                    if (!$graph) {
                        $account['calendarList'] = [];
                        continue;
                    }

                    $outlookCalendars = $graph
                        ->createCollectionRequest('GET', '/me/calendars')
                        ->setReturnType(Calendar::class)
                        ->setPageSize(100)
                        ->getPage();

                    $calendarList = [];
                    /** @var Calendar $outlookCalendar */
                    foreach ($outlookCalendars as $outlookCalendar) {
                        if ($outlookCalendar->getCanEdit()) {
                            $calendarList[] = [
                                'id'    => $outlookCalendar->getId(),
                                'name'  => $outlookCalendar->getName(),
                                'owner' => $outlookCalendar->getOwner() ? $outlookCalendar->getOwner()->getName() : '',
                            ];
                        }
                    }

                    $account['calendarList'] = $calendarList;
                } catch (\Exception $e) {
                    error_log('OutlookCalendar: Error fetching calendar list for account ' . $account['id'] . ': ' . $e->getMessage());
                    $account['calendarList'] = [];
                }
            } else {
                $account['calendarList'] = [];
            }
        }

        return $accounts;
    }
}
