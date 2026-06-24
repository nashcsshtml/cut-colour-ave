<?php

namespace AmeliaBooking\Application\Commands\Google;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Domain\Factory\Google\GoogleCalendarFactory;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Repository\User\ProviderRepository;
use AmeliaBooking\Infrastructure\Services\Google\AbstractGoogleCalendarService;
use AmeliaBooking\Infrastructure\Repository\Google\GoogleCalendarRepository;

/**
 * Class FetchAccessTokenWithAuthCodeCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Google
 */
class FetchAccessTokenWithAuthCodeCommandHandler extends CommandHandler
{
    /** @var array */
    public $mandatoryFields = [
        'authCode',
        'userId'
    ];

    /**
     * @param FetchAccessTokenWithAuthCodeCommand $command
     *
     * @return CommandResult
     * @throws \AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException
     * @throws \AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException
     */
    public function handle(FetchAccessTokenWithAuthCodeCommand $command)
    {
        $result = new CommandResult();

        $this->checkMandatoryFields($command);

        /** @var GoogleCalendarRepository $googleCalendarRepository */
        $googleCalendarRepository = $this->container->get('domain.google.calendar.repository');

        /** @var AbstractGoogleCalendarService $googleCalService */
        $googleCalService = $this->container->get('infrastructure.google.calendar.service');

        $providerId = $command->getField('userId');

        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        $accessToken = null;
        try {
            $accessToken = $googleCalService->fetchAccessTokenWithAuthCode(
                $command->getField('authCode'),
                $command->getField('isBackend')
                    ? AMELIA_SITE_URL . '/wp-admin/admin.php?page=wpamelia-employees'
                    : $command->getField('redirectUri')
            );
        } catch (\Exception $e) {
            $providerRepository->updateErrorColumn($providerId, $e->getMessage());
        }

        $accessToken = apply_filters('amelia_before_google_calendar_added_filter', $accessToken, $command->getField('userId'));

        if (is_array($accessToken)) {
            $accessToken = json_encode($accessToken);
        }

        $hasExistingCalendar = false;
        try {
            $googleCalendarRepository->getByProviderId($providerId);
            $hasExistingCalendar = true;
        } catch (\Exception $e) {
            error_log('GoogleCalendar: No existing calendar found, this is the first account. ' . $e->getMessage());
        }

        $primaryCalendarId = null;
        if (!$hasExistingCalendar) {
            try {
                $provider = $providerRepository->getById($providerId);

                $tempGoogleCalendar = GoogleCalendarFactory::create([
                    'token' => $accessToken,
                    'calendarId' => null
                ]);
                $provider->setGoogleCalendar($tempGoogleCalendar);

                $calendarList = $googleCalService->listCalendarList($provider);
                foreach ($calendarList as $calendar) {
                    if (!empty($calendar['primary'])) {
                        $primaryCalendarId = $calendar['id'];
                        break;
                    }
                }
            } catch (\Exception $e) {
                error_log('GoogleCalendar: Unable to fetch primary calendar - ' . $e->getMessage());
            }
        }

        $googleCalendar = GoogleCalendarFactory::create([
            'token' => $accessToken,
            'calendarId' => $primaryCalendarId
        ]);

        $googleCalendarRepository->beginTransaction();

        do_action('amelia_before_google_calendar_added', $googleCalendar ? $googleCalendar->toArray() : null, $command->getField('userId'));

        $additionalSettings = null;
        if (!$hasExistingCalendar) {
            /** @var SettingsService $settingsService */
            $settingsService = $this->container->get('domain.settings.service');
            $globalGoogleSettings = $settingsService->getCategorySettings('googleCalendar');

            $additionalSettings = [
                'insertPendingAppointments' => $globalGoogleSettings['insertPendingAppointments'] ?? false,
                'includeBufferTime'         => $globalGoogleSettings['includeBufferTimeGoogleCalendar'] ?? false,
                'title'                     => $globalGoogleSettings['title'] ?? null,
                'description'               => $globalGoogleSettings['description'] ?? null,
            ];
        } else {
            $existingAccount = $providerRepository->getGoogleCalendarAccounts($providerId)[0] ?? null;

            if ($existingAccount) {
                $additionalSettings = [
                    'insertPendingAppointments' => $existingAccount['insertPendingAppointments'] ?? false,
                    'includeBufferTime'         => $existingAccount['includeBufferTime']         ?? false,
                    'title'                     => $existingAccount['title']                     ?? null,
                    'description'               => $existingAccount['description']               ?? null,
                ];
            }
        }

        if (!$googleCalendarRepository->add($googleCalendar, $providerId, $additionalSettings)) {
            $googleCalendarRepository->rollback();
        }

        $googleCalendarRepository->commit();

        do_action('amelia_after_google_calendar_added', $googleCalendar ? $googleCalendar->toArray() : null, $command->getField('userId'));

        if (!$hasExistingCalendar) {
            $providerRepository->updateFieldById($providerId, null, 'googleCalendarId');
        }

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully fetched access token');

        return $result;
    }
}
