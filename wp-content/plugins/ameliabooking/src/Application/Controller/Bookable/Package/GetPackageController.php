<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Controller\Bookable\Package;

use AmeliaBooking\Application\Commands\Bookable\Package\GetPackageCommand;
use AmeliaBooking\Application\Controller\Controller;
use RuntimeException;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Class GetPackageController
 *
 * @package AmeliaBooking\Application\Controller\Bookable\Package
 */
class GetPackageController extends Controller
{
    /**
     * Instantiates the Get Package command to hand it over to the Command Handler
     *
     * @param Request $request
     * @param         $args
     *
     * @return GetPackageCommand
     * @throws RuntimeException
     */
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new GetPackageCommand($args);

        $requestBody = $request->getParsedBody();

        $this->setCommandFields($command, $requestBody);

        return $command;
    }
}
