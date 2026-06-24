<?php

namespace AmeliaBooking\Application\Controller\Import;

use AmeliaBooking\Application\Commands\Import\ImportCustomersCommand;
use AmeliaBooking\Application\Controller\Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Class ImportCustomersController
 *
 * @package AmeliaBooking\Application\Controller\Import
 */
class ImportCustomersController extends Controller
{
    public $allowedFields = [
        'data',
        'number',
        'overwrite'
    ];

    /**
     * Instantiates the Import Customers command to hand it over to the Command Handler
     *
     * @param Request $request
     * @param         $args
     *
     * @return ImportCustomersCommand
     * @throws \RuntimeException
     */
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new ImportCustomersCommand($args);

        $parsedBody = $request->getParsedBody();

        $command->setField('params', (array) array_merge(
            $request->getQueryParams(),
            is_array($parsedBody) ? $parsedBody : []
        ));

        $requestBody = $request->getParsedBody();

        $this->setCommandFields($command, $requestBody);

        return $command;
    }
}
