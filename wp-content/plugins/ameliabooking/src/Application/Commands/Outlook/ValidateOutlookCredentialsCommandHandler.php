<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See COPYING.md for license details.
 */

namespace AmeliaBooking\Application\Commands\Outlook;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Infrastructure\Services\Outlook\OutlookCredentialsValidatorService;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Entities;

/**
 * Class ValidateOutlookCredentialsCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Outlook
 */
class ValidateOutlookCredentialsCommandHandler extends CommandHandler
{
    /** @var array */
    protected $mandatoryFields = [
        'clientID',
        'clientSecret',
    ];

    /**
     * @param ValidateOutlookCredentialsCommand $command
     *
     * @return CommandResult
     * @throws AccessDeniedException
     * @throws InvalidArgumentException
     */
    public function handle(ValidateOutlookCredentialsCommand $command): CommandResult
    {
        if (!$this->getContainer()->getPermissionsService()->currentUserCanRead(Entities::SETTINGS)) {
            throw new AccessDeniedException('You are not allowed to read settings.');
        }

        $result = new CommandResult();

        $this->checkMandatoryFields($command);

        $clientId     = trim((string)$command->getField('clientID'));
        $clientSecret = trim((string)$command->getField('clientSecret'));

        if ($clientId === '' && $clientSecret === '') {
            $result->setResult(CommandResult::RESULT_SUCCESS);
            $result->setMessage(__('Outlook credentials are valid.', 'wpamelia'));
            $result->setData(['valid' => true]);

            return $result;
        }

        $invalid = OutlookCredentialsValidatorService::validateOrError($clientId, $clientSecret);
        if ($invalid !== null) {
            return $invalid;
        }

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage(__('Outlook credentials are valid.', 'wpamelia'));
        $result->setData(['valid' => true]);

        return $result;
    }
}
