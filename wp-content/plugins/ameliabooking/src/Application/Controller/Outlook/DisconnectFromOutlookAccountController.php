<?php

namespace AmeliaBooking\Application\Controller\Outlook;

use AmeliaBooking\Application\Commands\Outlook\DisconnectFromOutlookAccountCommand;
use AmeliaBooking\Application\Controller\Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Class DisconnectFromOutlookAccountController
 *
 * @package AmeliaBooking\Application\Controller\Outlook
 */
class DisconnectFromOutlookAccountController extends Controller
{
    protected $allowedFields = [
        'accountId'
    ];

    /**
     * @param Request $request
     * @param         $args
     *
     * @return DisconnectFromOutlookAccountCommand
     */
    protected function instantiateCommand(Request $request, $args)
    {
        $command     = new DisconnectFromOutlookAccountCommand($args);
        $requestBody = $request->getParsedBody();
        $this->setCommandFields($command, $requestBody);
        $command->setToken($request);

        return $command;
    }
}
