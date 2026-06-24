<?php

namespace AmeliaBooking\Application\Commands\Google;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Factory\Google\GoogleCalendarFactory;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Google\GoogleCalendarRepository;
use AmeliaBooking\Infrastructure\Repository\User\ProviderRepository;
use AmeliaBooking\Infrastructure\Services\Google\AbstractGoogleCalendarMiddlewareService;

class FetchGoogleMiddlewareAccessTokenCommandHandler extends CommandHandler
{
    /**
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     */
    public function handle(FetchGoogleMiddlewareAccessTokenCommand $command)
    {
        $result = new CommandResult();

        $accessToken = $this->normalizeAccessToken($command->getField('params')['access_token'] ?? null);

        $providerId = $command->getField('params')['providerId'] ?? null;

        $returnUrl = $command->getField('params')['returnUrl'] ?? null;

        $isBackend = $command->getField('params')['isBackend'] ?? null;

        if (!$accessToken) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage('Access token is required');
            return $result;
        }

        if ($providerId) {
            /** @var ProviderRepository $providerRepository */
            $providerRepository = $this->container->get('domain.users.providers.repository');

            /** @var GoogleCalendarRepository $googleCalendarRepository */
            $googleCalendarRepository = $this->container->get('domain.google.calendar.repository');

            /** @var AbstractGoogleCalendarMiddlewareService $googleCalendarMiddlewareService */
            $googleCalendarMiddlewareService = $this->container->get('infrastructure.google.calendar.middleware.service');

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
                    $calendarList = $googleCalendarMiddlewareService->getCalendarList(['token' => $accessToken]);
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
                $result->setResult(CommandResult::RESULT_ERROR);
                $result->setMessage('Failed to add Google Calendar');
                return $result;
            }

            $googleCalendarRepository->commit();

            do_action('amelia_after_google_calendar_added', $googleCalendar ? $googleCalendar->toArray() : null, $providerId);

            if (!$hasExistingCalendar) {
                $providerRepository->updateFieldById($providerId, null, 'googleCalendarId');
            }

            $result->setResult(CommandResult::RESULT_SUCCESS);
            $result->setMessage('Successfully fetched access token');

            if ($returnUrl) {
                $result->setUrl($returnUrl);
            } else {
                $result->setUrl(AMELIA_SITE_URL . '/wp-admin/admin.php?page=wpamelia-employees#/manage/' . $providerId . '/integrations/google-calendar');
            }

            return $result;
        }

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        /** @var AbstractGoogleCalendarMiddlewareService  $googleCalendarMiddlewareService */
        $googleCalendarMiddlewareService = $this->container->get('infrastructure.google.calendar.middleware.service');

        $googleSettings = $settingsService->getCategorySettings('googleCalendar');
        $googleAccountData = $googleCalendarMiddlewareService->getUserInfo($accessToken);
        $googleSettings['googleAccountData'] = [
            'name'    => $googleAccountData['name'],
            'email'   => $googleAccountData['email'],
            'picture' => $googleAccountData['picture']
        ];
        $googleSettings['accessToken'] = $accessToken;
        $settingsService->setCategorySettings('googleCalendar', $googleSettings);

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully fetched access token');
        $result->setData($googleAccountData);

        $result->setUrl(
            AMELIA_SITE_URL . '/wp-admin/admin.php?page=wpamelia-features-integrations#/integrations/google/general'
        );

        return $result;
    }

    /**
     * Middleware can return the token JSON as a slashed query-string value.
     *
     * @param string|null $accessToken
     *
     * @return string|null
     */
    private function normalizeAccessToken(?string $accessToken): ?string
    {
        if (!$accessToken) {
            return $accessToken;
        }

        $decoded = json_decode($accessToken, true);

        if (!is_array($decoded)) {
            $decoded = json_decode(stripslashes($accessToken), true);
        }

        $encoded = is_array($decoded) ? json_encode($decoded) : false;

        return $encoded ?: $accessToken;
    }
}
