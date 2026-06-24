<?php

namespace AmeliaBooking\Application\Controller\Zoom;

use AmeliaBooking\Application\Commands\Zoom\ValidateZoomCredentialsCommand;
use AmeliaBooking\Application\Controller\Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Class ValidateZoomCredentialsController
 *
 * @package AmeliaBooking\Application\Controller\Zoom
 */
class ValidateZoomCredentialsController extends Controller
{
    /**
     * @var array
     */
    protected $allowedFields = [
        'accountId',
        'clientId',
        'clientSecret',
    ];

    /**
     * @param Request $request
     * @param         $args
     *
     * @return ValidateZoomCredentialsCommand
     */
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new ValidateZoomCredentialsCommand($args);

        $requestBody = $request->getParsedBody();
        $this->setCommandFields($command, $requestBody);

        return $command;
    }
}
