<?php

namespace AmeliaBooking\Application\Controller\Mobile\Events;

use AmeliaBooking\Application\Commands\Mobile\Events\ScanEventTicketCommand;
use AmeliaBooking\Application\Controller\Mobile\MobileV1Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /mobile/v1/events/scan
 *
 * Validates and checks in an event ticket by its QR manual code.
 * Requires: Authorization: Bearer <provider-jwt>
 * Body:     { "ticketManualCode": string }
 *
 * scannedAt is generated server-side; the client does not send it.
 */
class ScanEventTicketMobileController extends MobileV1Controller
{
    public $allowedFields = ['ticketManualCode'];

    protected function instantiateCommand(Request $request, $args)
    {
        $command = new ScanEventTicketCommand($args);

        $requestBody = $request->getParsedBody();
        $this->setCommandFields($command, $requestBody);
        $command->setToken($request);
        $this->forceCabinetContext($command);

        return $command;
    }
}
