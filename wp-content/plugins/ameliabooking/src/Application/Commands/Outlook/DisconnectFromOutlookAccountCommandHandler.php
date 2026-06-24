<?php

namespace AmeliaBooking\Application\Commands\Outlook;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\User\UserApplicationService;
use AmeliaBooking\Domain\Common\Exceptions\AuthorizationException;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Outlook\OutlookCalendarRepository;
use Interop\Container\Exception\ContainerException;

/**
 * Class DisconnectFromOutlookAccountCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Outlook
 */
class DisconnectFromOutlookAccountCommandHandler extends CommandHandler
{
    /**
     * @param DisconnectFromOutlookAccountCommand $command
     *
     * @return CommandResult
     * @throws AccessDeniedException
     * @throws QueryExecutionException
     * @throws ContainerException
     * @throws InvalidArgumentException
     */
    public function handle(DisconnectFromOutlookAccountCommand $command)
    {

        /** @var UserApplicationService $userAS */
        $userAS = $this->getContainer()->get('application.user.service');

        if (!$command->getPermissionService()->currentUserCanRead(Entities::EMPLOYEES)) {
            try {
                /** @var AbstractUser $user */
                $user = $userAS->authorization(
                    $command->getToken(),
                    Entities::PROVIDER
                );
            } catch (AuthorizationException $e) {
                $result = new CommandResult();
                $result->setResult(CommandResult::RESULT_ERROR);
                $result->setData(
                    [
                        'reauthorize' => true
                    ]
                );

                return $result;
            }

            if ($userAS->isCustomer($user)) {
                throw new AccessDeniedException('You are not allowed');
            }
        }

        $result = new CommandResult();

        /** @var OutlookCalendarRepository $outlookCalendarRepository */
        $outlookCalendarRepository = $this->container->get('domain.outlook.calendar.repository');

        $accountId = $command->getField('accountId');

        if ($accountId) {
            try {
                if ($outlookCalendarRepository->delete($accountId)) {
                    do_action('amelia_after_outlook_calendar_deleted', ['accountId' => $accountId], $command->getArg('id'));

                    $result->setResult(CommandResult::RESULT_SUCCESS);
                    $result->setMessage('Outlook calendar account successfully disconnected.');
                } else {
                    $result->setResult(CommandResult::RESULT_ERROR);
                    $result->setMessage('Unable to delete outlook calendar account.');
                }
            } catch (\Exception $e) {
                $result->setResult(CommandResult::RESULT_ERROR);
                $result->setMessage('Unable to delete outlook calendar account.');
            }

            return $result;
        }

        if (!$command->getArg('id')) {
            /** @var SettingsService $settingsService */
            $settingsService = $this->getContainer()->get('domain.settings.service');

            $settings = $settingsService->getAllSettingsCategorized();

            $settings['outlookCalendar']['token'] = null;

            $settingsService->setAllSettings($settings);

            $result->setResult(CommandResult::RESULT_SUCCESS);
            $result->setMessage('Outlook calendar successfully deleted.');

            return $result;
        }

        $providerId = $command->getArg('id');

        try {
            $outlookCalendars = $outlookCalendarRepository->getByEntityId($providerId, 'userId');

            foreach ($outlookCalendars->getItems() as $outlookCalendar) {
                do_action('amelia_before_outlook_calendar_deleted', $outlookCalendar->toArray(), $providerId);
            }

            if ($outlookCalendarRepository->deleteByEntityId($providerId, 'userId')) {
                foreach ($outlookCalendars->getItems() as $outlookCalendar) {
                    do_action('amelia_after_outlook_calendar_deleted', $outlookCalendar->toArray(), $providerId);
                }

                $result->setResult(CommandResult::RESULT_SUCCESS);
                $result->setMessage('Outlook calendar successfully disconnected.');
            } else {
                $result->setResult(CommandResult::RESULT_ERROR);
                $result->setMessage('Unable to delete outlook calendar accounts.');
            }
        } catch (\Exception $e) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage('Unable to delete outlook calendar accounts.');
        }

        return $result;
    }
}
