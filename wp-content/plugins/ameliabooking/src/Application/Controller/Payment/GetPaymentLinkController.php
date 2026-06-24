<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Controller\Payment;

use AmeliaBooking\Application\Commands\Payment\GetPaymentLinkCommand;
use AmeliaBooking\Application\Controller\Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Class GetPaymentLinkController
 *
 * @package AmeliaBooking\Application\Controller\Payment
 */
class GetPaymentLinkController extends Controller
{
    /**
     * Fields for Get Payment Link that can be received from API
     *
     * @var array
     */
    protected $allowedFields = [
        'paymentMethod',
        'token',
    ];

    /**
     * Instantiates the Get Payment Link command to hand it over to the Command Handler
     *
     * @param Request $request
     * @param         $args
     *
     * @return GetPaymentLinkCommand
     * @throws \RuntimeException
     */
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new GetPaymentLinkCommand($args);

        $this->setCommandFields($command, $request->getQueryParams());

        return $command;
    }
}
