<?php

namespace AmeliaBooking\Application\Controller\Mobile\Events;

use AmeliaBooking\Application\Commands\Booking\Event\GetEventsCommand;
use AmeliaBooking\Application\Controller\Mobile\MobileV1Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

class GetEventsMobileController extends MobileV1Controller
{
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new GetEventsCommand($args);

        $params = (array)$request->getQueryParams();

        // Provider scoping comes from the token, not the client — strip any filter.
        unset($params['source'], $params['providers']);

        $this->setArrayParams($params);

        $command->setField('params', $params);
        $command->setToken($request);
        $this->forceCabinetContext($command);

        return $command;
    }
}
