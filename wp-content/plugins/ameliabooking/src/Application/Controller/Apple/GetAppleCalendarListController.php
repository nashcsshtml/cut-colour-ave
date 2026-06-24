<?php

namespace AmeliaBooking\Application\Controller\Apple;

use AmeliaBooking\Application\Commands\Apple\GetAppleCalendarListCommand;
use AmeliaBooking\Application\Controller\Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Class GetAppleCalendarListController
 *
 * @package AmeliaBooking\Application\Controller\Apple
 */
class GetAppleCalendarListController extends Controller
{
    /**
     * Instantiates the Get Apple Calendar List command to hand it over to the Command Handler
     *
     * @param Request $request
     * @param         $args
     *
     * @return GetAppleCalendarListCommand
     */
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new GetAppleCalendarListCommand($args);

        $requestBody = $request->getParsedBody();

        $this->setCommandFields($command, $requestBody);

        $command->setToken($request);

        return $command;
    }
}
