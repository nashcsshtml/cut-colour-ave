<?php

namespace AmeliaBooking\Application\Controller\Outlook;

use AmeliaBooking\Application\Commands\Outlook\ValidateOutlookCredentialsCommand;
use AmeliaBooking\Application\Controller\Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Class ValidateOutlookCredentialsController
 *
 * @package AmeliaBooking\Application\Controller\Outlook
 */
class ValidateOutlookCredentialsController extends Controller
{
    /**
     * @var array
     */
    protected $allowedFields = [
        'clientID',
        'clientSecret',
    ];

    /**
     * @param Request $request
     * @param mixed   $args
     *
     * @return ValidateOutlookCredentialsCommand
     */
    protected function instantiateCommand(Request $request, $args)
    {
        $command     = new ValidateOutlookCredentialsCommand($args);
        $requestBody = $request->getParsedBody();
        $this->setCommandFields($command, $requestBody);

        return $command;
    }
}
