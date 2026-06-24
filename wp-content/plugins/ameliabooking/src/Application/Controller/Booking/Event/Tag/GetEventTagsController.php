<?php

namespace AmeliaBooking\Application\Controller\Booking\Event\Tag;

use AmeliaBooking\Application\Commands\Booking\Event\Tag\GetEventTagsCommand;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Controller\Controller;
use AmeliaBooking\Domain\Events\DomainEventBus;
use RuntimeException;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Class GetEventTagsController
 *
 * @package AmeliaBooking\Application\Controller\Booking\Event\Tag
 */
class GetEventTagsController extends Controller
{
    /**
     * @param Request $request
     * @param         $args
     *
     * @return mixed
     * @throws RuntimeException
     */
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new GetEventTagsCommand($args);

        $this->setCommandFields($command, $request->getQueryParams());

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
        $eventBus->emit('event.tags.fetched', $result);
    }
}
