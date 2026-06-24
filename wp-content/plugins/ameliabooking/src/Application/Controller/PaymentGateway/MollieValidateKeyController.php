<?php

namespace AmeliaBooking\Application\Controller\PaymentGateway;

use AmeliaBooking\Application\Commands\PaymentGateway\MollieValidateKeyCommand;
use AmeliaBooking\Application\Controller\Controller;
use RuntimeException;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Class MollieValidateKeyController
 *
 * @package AmeliaBooking\Application\Controller\PaymentGateway
 */
class MollieValidateKeyController extends Controller
{
    protected $allowedFields = [
        'apiKey',
        'testMode',
    ];

    /**
     * @param Request $request
     * @param         $args
     *
     * @return MollieValidateKeyCommand
     * @throws RuntimeException
     */
    protected function instantiateCommand(Request $request, $args): MollieValidateKeyCommand
    {
        $command = new MollieValidateKeyCommand($args);
        $requestBody = $request->getParsedBody();
        $this->setCommandFields($command, $requestBody);

        return $command;
    }
}
