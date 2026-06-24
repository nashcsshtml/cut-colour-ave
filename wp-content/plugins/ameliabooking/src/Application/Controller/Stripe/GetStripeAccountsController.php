<?php

namespace AmeliaBooking\Application\Controller\Stripe;

use AmeliaBooking\Application\Controller\Controller;
use AmeliaBooking\Application\Commands\Stripe\GetStripeAccountsCommand;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

class GetStripeAccountsController extends Controller
{
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new GetStripeAccountsCommand($args);

        $requestBody = $request->getParsedBody();
        $this->setCommandFields($command, $requestBody);

        $command->setToken($request);

        return $command;
    }
}
