<?php

namespace AmeliaBooking\Application\Commands\PaymentGateway;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Infrastructure\Services\Payment\MollieService;
use AmeliaVendor\Psr\Container\ContainerExceptionInterface;
use Exception;

/**
 * Class MollieValidateKeyCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\PaymentGateway
 */
class MollieValidateKeyCommandHandler extends CommandHandler
{
    protected $mandatoryFields = [
        'apiKey',
        'testMode',
    ];

    /**
     * @param MollieValidateKeyCommand $command
     *
     * @return CommandResult
     * @throws Exception | ContainerExceptionInterface | AccessDeniedException
     */
    public function handle(MollieValidateKeyCommand $command): CommandResult
    {
        $result = new CommandResult();

        if (!$this->getContainer()->getPermissionsService()->currentUserCanRead(Entities::SETTINGS)) {
            throw new AccessDeniedException('You are not allowed to read settings.');
        }

        $this->checkMandatoryFields($command);

        /** @var MollieService $mollieService */
        $mollieService = $this->getContainer()->get('infrastructure.payment.mollie.service');

        $apiKey      = $command->getField('apiKey');
        $rawTestMode = $command->getField('testMode');
        $testMode    = filter_var($rawTestMode, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($testMode === null) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage('Invalid testMode value.');
            return $result;
        }

        $validation = $mollieService->validateKey($apiKey, $testMode);

        if ($validation['valid']) {
            $result->setResult(CommandResult::RESULT_SUCCESS);
        } else {
            $result->setResult(CommandResult::RESULT_ERROR);
        }

        $result->setMessage($validation['message']);
        $result->setData($validation);

        return $result;
    }
}
