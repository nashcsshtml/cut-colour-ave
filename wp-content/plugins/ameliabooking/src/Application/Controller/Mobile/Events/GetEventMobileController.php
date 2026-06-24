<?php

namespace AmeliaBooking\Application\Controller\Mobile\Events;

use AmeliaBooking\Application\Commands\Booking\Event\GetEventCommand;
use AmeliaBooking\Application\Controller\Mobile\MobileV1Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

class GetEventMobileController extends MobileV1Controller
{
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new GetEventCommand($args);

        $params = (array)$request->getQueryParams();
        unset($params['source']);

        $command->setField('params', $params);
        $command->setToken($request);
        $this->forceCabinetContext($command);

        return $command;
    }
}
