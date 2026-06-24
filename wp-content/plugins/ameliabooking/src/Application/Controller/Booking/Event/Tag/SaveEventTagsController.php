<?php

namespace AmeliaBooking\Application\Controller\Booking\Event\Tag;

use AmeliaBooking\Application\Commands\Booking\Event\Tag\SaveEventTagsCommand;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Controller\Controller;
use AmeliaBooking\Domain\Events\DomainEventBus;
use RuntimeException;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Class SaveEventTagsController
 *
 * @package AmeliaBooking\Application\Controller\Booking\Event\Tag
 */
class SaveEventTagsController extends Controller
{
    /**
     * Fields allowed from the request body.
     *
     * @var array
     */
    protected $allowedFields = ['tags'];

    /**
     * @param Request $request
     * @param         $args
     *
     * @return mixed
     * @throws RuntimeException
     */
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new SaveEventTagsCommand($args);

        $this->setCommandFields($command, $request->getParsedBody());

        $command->setToken($request);

        $params = (array)$request->getQueryParams();

        if (isset($params['source'])) {
            $command->setPage($params['source']);
        }

        return $command;
    }

    /**
     * @param DomainEventBus $eventBus
     * @param CommandResult  $result
     *
     * @return void
     */
    protected function emitSuccessEvent(DomainEventBus $eventBus, CommandResult $result)
    {
        $eventBus->emit('event.tags.saved', $result);
    }
}
