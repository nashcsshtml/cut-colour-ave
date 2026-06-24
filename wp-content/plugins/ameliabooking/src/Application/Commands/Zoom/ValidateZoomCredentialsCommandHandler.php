<?php

namespace AmeliaBooking\Application\Commands\Zoom;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Infrastructure\Services\Zoom\AbstractZoomService;
use Exception;

/**
 * Class ValidateZoomCredentialsCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Zoom
 */
class ValidateZoomCredentialsCommandHandler extends CommandHandler
{
    /** @var array */
    protected $mandatoryFields = [
        'accountId',
        'clientId',
        'clientSecret',
    ];

    /**
     * @param ValidateZoomCredentialsCommand $command
     *
     * @return CommandResult
     * @throws AccessDeniedException|InvalidArgumentException
     */
    public function handle(ValidateZoomCredentialsCommand $command): CommandResult
    {
        if (!$this->getContainer()->getPermissionsService()->currentUserCanRead(Entities::SETTINGS)) {
            throw new AccessDeniedException('You are not allowed to read settings.');
        }

        $result = new CommandResult();

        $this->checkMandatoryFields($command);

        $accountId    = trim((string)$command->getField('accountId'));
        $clientId     = trim((string)$command->getField('clientId'));
        $clientSecret = trim((string)$command->getField('clientSecret'));

        try {
            /** @var AbstractZoomService $zoomService */
            $zoomService = $this->container->get('infrastructure.zoom.service');

            $zoomResult = $zoomService->validateCredentials(
                [
                    'accountId'    => $accountId,
                    'clientId'     => $clientId,
                    'clientSecret' => $clientSecret,
                ]
            );

            if (!isset($zoomResult['users']) || $zoomResult['users'] === null) {
                $result->setResult(CommandResult::RESULT_ERROR);
                $result->setMessage('Make sure you are using the correct Zoom credentials.');
                $result->setData(['valid' => false]);

                return $result;
            }

            $result->setResult(CommandResult::RESULT_SUCCESS);
            $result->setMessage('Zoom credentials are valid.');
            $result->setData(['valid' => true]);

            return $result;
        } catch (Exception $e) {
            $result->setResult(CommandResult::RESULT_ERROR);
            error_log('Zoom validation failed: ' . $e->getMessage());
            $result->setMessage('Zoom credentials validation failed.');
            $result->setData(['valid' => false]);

            return $result;
        }
    }
}
