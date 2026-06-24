<?php

namespace AmeliaBooking\Application\Controller\Booking\Appointment;

use AmeliaBooking\Application\Commands\Booking\Appointment\CancelBookingRemotelyCommand;
use AmeliaBooking\Application\Commands\Booking\Appointment\UpdateBookingStatusCommand;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Controller\Controller;
use AmeliaBooking\Domain\Events\DomainEventBus;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Class CancelBookingRemotelyController
 *
 * @package AmeliaBooking\Application\Controller\Booking\Appointment
 */
class CancelBookingRemotelyController extends Controller
{
    /**
     * Fields for calendar service that can be received from front-end
     *
     * @var array
     */
    protected $allowedFields = [
        'token',
        'type',
        'fromForm'
    ];

    /**
     * Instantiates the Update Appointment command to hand it over to the Command Handler
     *
     * @param Request $request
     * @param         $args
     *
     * @return CancelBookingRemotelyCommand
     * @throws \RuntimeException
     */
    protected function instantiateCommand(Request $request, $args)
    {
        $command     = new CancelBookingRemotelyCommand($args);
        $requestBody = $request->getParsedBody();
        $command->setField('token', (string)self::getParam($request, 'token', ''));
        $command->setField('type', (string)self::getParam($request, 'type', ''));
        $command->setField('fromForm', self::getParam($request, 'fromForm', false));
        $this->setCommandFields($command, $requestBody);

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
        if ($result->getData() && empty($result->getData()['fromForm'])) {
            $eventBus->emit('BookingCanceled', $result);
        }
    }
}
