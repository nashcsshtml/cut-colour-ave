<?php

namespace AmeliaBooking\Application\Commands\Google;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\User\UserApplicationService;
use AmeliaBooking\Domain\Common\Exceptions\AuthorizationException;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Google\GoogleCalendarRepository;
use Interop\Container\Exception\ContainerException;

/**
 * Class DisconnectFromGoogleAccountCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Google
 */
class DisconnectFromGoogleAccountCommandHandler extends CommandHandler
{
    /**
     * @param DisconnectFromGoogleAccountCommand $command
     *
     * @return CommandResult
     * @throws AccessDeniedException
     * @throws QueryExecutionException
     * @throws ContainerException
     * @throws InvalidArgumentException
     */
    public function handle(DisconnectFromGoogleAccountCommand $command)
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

        /** @var GoogleCalendarRepository $googleCalendarRepository */
        $googleCalendarRepository = $this->container->get('domain.google.calendar.repository');

        $accountId = $command->getField('accountId');

        if ($accountId) {
            try {
                if ($googleCalendarRepository->delete($accountId)) {
                    do_action('amelia_after_google_calendar_deleted', ['accountId' => $accountId], $command->getArg('id'));

                    $result->setResult(CommandResult::RESULT_SUCCESS);
                    $result->setMessage('Google calendar account successfully disconnected.');
                } else {
                    $result->setResult(CommandResult::RESULT_ERROR);
                    $result->setMessage('Unable to delete google calendar account.');
                }
            } catch (\Exception $e) {
                $result->setResult(CommandResult::RESULT_ERROR);
                $result->setMessage('Unable to delete google calendar account.');
            }

            return $result;
        }

        $providerId = $command->getArg('id');

        try {
            $googleCalendars = $googleCalendarRepository->getByEntityId($providerId, 'userId');

            foreach ($googleCalendars->getItems() as $googleCalendar) {
                do_action('amelia_before_google_calendar_deleted', $googleCalendar->toArray(), $providerId);
            }

            if ($googleCalendarRepository->deleteByEntityId($providerId, 'userId')) {
                foreach ($googleCalendars->getItems() as $googleCalendar) {
                    do_action('amelia_after_google_calendar_deleted', $googleCalendar, $providerId);
                }

                $result->setResult(CommandResult::RESULT_SUCCESS);
                $result->setMessage('Google calendar successfully disconnected.');
            } else {
                $result->setResult(CommandResult::RESULT_ERROR);
                $result->setMessage('Unable to delete google calendar accounts.');
            }
        } catch (\Exception $e) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage('Unable to delete google calendar accounts.');
        }

        return $result;
    }
}
