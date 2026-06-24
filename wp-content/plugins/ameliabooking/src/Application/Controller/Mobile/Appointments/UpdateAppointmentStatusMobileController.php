<?php

namespace AmeliaBooking\Application\Controller\Mobile\Appointments;

use AmeliaBooking\Application\Commands\Booking\Appointment\UpdateAppointmentStatusCommand;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Controller\Mobile\MobileV1Controller;
use AmeliaBooking\Domain\Events\DomainEventBus;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

class UpdateAppointmentStatusMobileController extends MobileV1Controller
{
    public $allowedFields = ['status'];

    protected function instantiateCommand(Request $request, $args)
    {
        $command = new UpdateAppointmentStatusCommand($args);

        $requestBody = $request->getParsedBody();
        $this->setCommandFields($command, $requestBody);
        $command->setToken($request);
        $this->forceCabinetContext($command);

        return $command;
    }

    protected function emitSuccessEvent(DomainEventBus $eventBus, CommandResult $result)
    {
        $eventBus->emit('AppointmentStatusUpdated', $result);
    }
}
