<?php

namespace AmeliaBooking\Application\Commands\Outlook;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Factory\Outlook\OutlookCalendarFactory;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Outlook\OutlookCalendarRepository;
use AmeliaBooking\Infrastructure\Repository\User\ProviderRepository;
use AmeliaBooking\Infrastructure\Services\Outlook\AbstractOutlookCalendarMiddlewareService;

class FetchOutlookMiddlewareAccessTokenCommandHandler extends CommandHandler
{
    /**
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     */
    public function handle(FetchOutlookMiddlewareAccessTokenCommand $command): CommandResult
    {
        $result = new CommandResult();

        $accessToken = $this->normalizeAccessToken($command->getField('params')['access_token'] ?? null);

        $providerId = $command->getField('params')['providerId'] ?? null;

        $returnUrl = $command->getField('params')['returnUrl'] ?? null;

        if (!$accessToken) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage('Access token is required');
            return $result;
        }

        if ($providerId) {
            /** @var ProviderRepository $providerRepository */
            $providerRepository = $this->container->get('domain.users.providers.repository');

            /** @var OutlookCalendarRepository $outlookCalendarRepository */
            $outlookCalendarRepository = $this->container->get('domain.outlook.calendar.repository');

            /** @var AbstractOutlookCalendarMiddlewareService $outlookCalendarMiddlewareService */
            $outlookCalendarMiddlewareService = $this->container->get('infrastructure.outlook.calendar.middleware.service');

            $hasExistingCalendar = false;
            try {
                $outlookCalendarRepository->getByProviderId($providerId);
                $hasExistingCalendar = true;
            } catch (\Exception $e) {
                error_log('OutlookCalendar: No existing calendar found, this is the first account. ' . $e->getMessage());
            }

            $primaryCalendarId = null;
            if (!$hasExistingCalendar) {
                try {
                    $calendarList = $outlookCalendarMiddlewareService->getCalendarList(['token' => $accessToken]);
                    foreach ($calendarList as $calendar) {
                        if (!empty($calendar['owner'])) {
                            $primaryCalendarId = $calendar['id'];
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    error_log('OutlookCalendar: Unable to fetch primary calendar - ' . $e->getMessage());
                }
            }

            $outlookCalendar = OutlookCalendarFactory::create([
                'token' => $accessToken,
                'calendarId' => $primaryCalendarId
            ]);

            $outlookCalendarRepository->beginTransaction();

            $additionalSettings = null;
            if (!$hasExistingCalendar) {
                /** @var SettingsService $settingsService */
                $settingsService = $this->container->get('domain.settings.service');
                $globalOutlookSettings = $settingsService->getCategorySettings('outlookCalendar');

                $additionalSettings = [
                    'insertPendingAppointments' => $globalOutlookSettings['insertPendingAppointments'] ?? false,
                    'includeBufferTime'         => $globalOutlookSettings['includeBufferTimeOutlookCalendar'] ?? false,
                    'title'                     => $globalOutlookSettings['title'] ?? null,
                    'description'               => $globalOutlookSettings['description'] ?? null,
                ];
            } else {
                $existingAccount = $providerRepository->getOutlookCalendarAccounts($providerId)[0] ?? null;

                if ($existingAccount) {
                    $additionalSettings = [
                        'insertPendingAppointments' => $existingAccount['insertPendingAppointments'] ?? false,
                        'includeBufferTime'         => $existingAccount['includeBufferTime']         ?? false,
                        'title'                     => $existingAccount['title']                     ?? null,
                        'description'               => $existingAccount['description']               ?? null,
                    ];
                }
            }

            if (!$outlookCalendarRepository->add($outlookCalendar, $providerId, $additionalSettings)) {
                $outlookCalendarRepository->rollback();
                $result->setResult(CommandResult::RESULT_ERROR);
                $result->setMessage('Failed to add Outlook Calendar');
                return $result;
            }

            $outlookCalendarRepository->commit();

            do_action('amelia_after_outlook_calendar_added', $outlookCalendar ? $outlookCalendar->toArray() : null, $providerId);

            if (!$hasExistingCalendar) {
                $providerRepository->updateFieldById($providerId, null, 'outlookCalendarId');
            }

            $result->setResult(CommandResult::RESULT_SUCCESS);
            $result->setMessage('Successfully fetched access token');

            $result->setUrl(AMELIA_SITE_URL . '/wp-admin/admin.php?page=wpamelia-employees#/manage/' . $providerId . '/integrations/outlook-calendar');

            if ($returnUrl) {
                $result->setUrl($returnUrl);
            }

            return $result;
        }

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        $outlookSettings = $settingsService->getCategorySettings('outlookCalendar');
        $outlookSettings['accessToken'] = $accessToken;
        /** @var AbstractOutlookCalendarMiddlewareService  $outlookCalendarMiddlewareService */
        $outlookCalendarMiddlewareService = $this->container->get('infrastructure.outlook.calendar.middleware.service');
        $outlookAccountData = $outlookCalendarMiddlewareService->getUserInfo($accessToken);
        $outlookSettings['mailEnabled'] = true;

        $outlookSettings['outlookAccountData'] = [
            'name'    => $outlookAccountData['name'],
            'email'   => $outlookAccountData['email'],
            'picture' => $outlookAccountData['picture']
        ];

        $settingsService->setCategorySettings('outlookCalendar', $outlookSettings);

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully fetched access token');
        $result->setData($outlookAccountData);

        $result->setUrl(
            AMELIA_SITE_URL . '/wp-admin/admin.php?page=wpamelia-features-integrations#/integrations/microsoft/general'
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
