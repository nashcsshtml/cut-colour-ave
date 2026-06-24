<?php

namespace AmeliaBooking\Application\Commands\Outlook;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Outlook\OutlookCalendar;
use AmeliaBooking\Domain\Factory\Outlook\OutlookCalendarFactory;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\User\ProviderRepository;
use AmeliaBooking\Infrastructure\Services\Outlook\AbstractOutlookCalendarService;
use AmeliaBooking\Infrastructure\Repository\Outlook\OutlookCalendarRepository;

/**
 * Class FetchAccessTokenWithAuthCodeOutlookCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Outlook
 */
class FetchAccessTokenWithAuthCodeOutlookCommandHandler extends CommandHandler
{
    /** @var array */
    public $mandatoryFields = [
        'authCode',
        'userId'
    ];

    /**
     * @param FetchAccessTokenWithAuthCodeOutlookCommand $command
     *
     * @return CommandResult
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     */
    public function handle(FetchAccessTokenWithAuthCodeOutlookCommand $command)
    {
        $result = new CommandResult();

        $this->checkMandatoryFields($command);

        /** @var OutlookCalendarRepository $outlookCalendarRepository */
        $outlookCalendarRepository = $this->container->get('domain.outlook.calendar.repository');

        /** @var AbstractOutlookCalendarService $outlookCalendarService */
        $outlookCalendarService = $this->container->get('infrastructure.outlook.calendar.service');

        $providerId = $command->getField('userId');

        $token = null;
        try {
            $token = $outlookCalendarService->fetchAccessTokenWithAuthCode(
                $command->getField('authCode'),
                $command->getField('redirectUri'),
                $providerId
            );
        } catch (\Exception $e) {
            /** @var ProviderRepository $providerRepository */
            $providerRepository = $this->container->get('domain.users.providers.repository');

            $providerRepository->updateErrorColumn($providerId, $e->getMessage());

            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setData([]);
            $result->setMessage($e->getMessage());

            return $result;
        }

        if (!$token || !$token['outcome']) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setData($token);
            $result->setMessage($token['result']);

            return $result;
        }

        if (!$providerId) {
            /** @var SettingsService $settingsService */
            $settingsService = $this->getContainer()->get('domain.settings.service');

            $settings = $settingsService->getAllSettingsCategorized();

            $settings['outlookCalendar']['token'] = json_decode($token['result'], true);

            $settingsService->setAllSettings($settings);

            $result->setResult(CommandResult::RESULT_SUCCESS);
            $result->setMessage('Successfully fetched access token');

            return $result;
        }

        $token = apply_filters('amelia_before_outlook_calendar_added_filter', $token, $command->getField('userId'));

        $hasExistingCalendar = false;
        try {
            $outlookCalendarRepository->getByProviderId($providerId);
            $hasExistingCalendar = true;
        } catch (\Exception $e) {
            error_log('OutlookCalendar: No existing calendar found, this is the first account. ' . $e->getMessage());
        }

        $primaryCalendarId = null;
        if (!$hasExistingCalendar) {
            /** @var ProviderRepository $providerRepository */
            $providerRepository = $this->container->get('domain.users.providers.repository');

            try {
                $provider = $providerRepository->getById($providerId);

                $tempOutlookCalendar = OutlookCalendarFactory::create([
                    'token' => $token['result'],
                    'calendarId' => null
                ]);
                $provider->setOutlookCalendar($tempOutlookCalendar);

                $calendarList = $outlookCalendarService->listCalendarList($provider);
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

        /** @var OutlookCalendar $outlookCalendar */
        $outlookCalendar = OutlookCalendarFactory::create([
            'token' => $token['result'],
            'calendarId' => $primaryCalendarId
        ]);

        $outlookCalendarRepository->beginTransaction();

        do_action('amelia_before_outlook_calendar_added', $outlookCalendar ? $outlookCalendar->toArray() : null, $command->getField('userId'));

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
            /** @var ProviderRepository $existingProviderRepository */
            $existingProviderRepository = $this->container->get('domain.users.providers.repository');
            $existingAccount = $existingProviderRepository->getOutlookCalendarAccounts($providerId)[0] ?? null;

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
        }

        $outlookCalendarRepository->commit();

        do_action('amelia_after_outlook_calendar_added', $outlookCalendar ? $outlookCalendar->toArray() : null, $command->getField('userId'));

        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        if (!$hasExistingCalendar) {
            $providerRepository->updateFieldById($providerId, null, 'outlookCalendarId');
        }

        $outlookAccountData = null;
        try {
            $provider = $providerRepository->getById($providerId);
            $outlookAccountData = $outlookCalendarService->getUserInfo($provider);
        } catch (\Exception $e) {
            error_log('OutlookCalendar: Unable to fetch account data - ' . $e->getMessage());
        }

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully fetched access token');

        if ($outlookAccountData) {
            $result->setData([
                'outlookAccountData' => [
                    'name'    => $outlookAccountData['name'],
                    'email'   => $outlookAccountData['email'],
                    'picture' => $outlookAccountData['picture']
                ]
            ]);
        }

        return $result;
    }
}
