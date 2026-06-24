<?php

namespace AmeliaBooking\Application\Controller\Mobile\Appointments;

use AmeliaBooking\Application\Commands\Booking\Appointment\GetAppointmentsCommand;
use AmeliaBooking\Application\Controller\Mobile\MobileV1Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

class GetAppointmentsMobileController extends MobileV1Controller
{
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new GetAppointmentsCommand($args);

        $params = (array)$request->getQueryParams();

        // Never trust a client-supplied provider filter — the backend scopes to
        // the token owner. Stripping it here is defence-in-depth on top of the
        // handler's own provider-scope injection.
        unset($params['source'], $params['providers']);

        $this->setArrayParams($params);

        if (!empty($params['services'])) {
            $params['services'] = array_map('intval', $params['services']);
        }

        $command->setField('params', $params);
        $command->setToken($request);
        $this->forceCabinetContext($command);

        return $command;
    }
}
