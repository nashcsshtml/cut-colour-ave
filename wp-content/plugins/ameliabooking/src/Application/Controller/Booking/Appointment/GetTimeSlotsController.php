<?php

namespace AmeliaBooking\Application\Controller\Booking\Appointment;

use AmeliaBooking\Application\Commands\Booking\Appointment\GetTimeSlotsCommand;
use AmeliaBooking\Application\Controller\Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Class GetTimeSlotsController
 *
 * @package AmeliaBooking\Application\Controller\Booking\Appointment
 */
class GetTimeSlotsController extends Controller
{
    /**
     * Fields for calendar service that can be received from front-end
     *
     * @var array
     */
    protected $allowedFields = [
        'serviceId',
        'serviceDuration',
        'weekDays',
        'startDateTime',
        'providerIds',
        'extras',
        'excludeAppointmentId',
        'persons',
        'group',
        'page',
        'monthsLoad',
        'queryTimeZone',
        'timeZone',
        'allowAdminBookAtAnyTime',
        'allowBookingIfPending',
        'allowBookingIfNotMin',
        'timeSlotLength',
        'serviceDurationAsSlot',
        'bufferTimeInSlot',
        'structured',
    ];

    /**
     * Instantiates the Get Time Slots command to hand it over to the Command Handler
     *
     * @param Request $request
     * @param         $args
     *
     * @return mixed
     * @throws \RuntimeException
     */
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new GetTimeSlotsCommand($args);

        $params = (array)$request->getQueryParams();

        if (!empty($params['extras'])) {
            if (($arrayExtras = json_decode($params['extras'], true)) !== null) {
                $params['extras'] = $arrayExtras;
            } else {
                $arrayExtras = [];

                foreach (explode(',', $params['extras']) as $item) {
                    $extrasData = explode('-', $item);

                    $arrayExtras[] = ['id' => $extrasData[0], 'quantity' => $extrasData[1]];
                }

                $params['extras'] = $arrayExtras;
            }
        }

        $this->setArrayParams($params);

        $command->setField('serviceId', (int)self::getParam($request, 'serviceId', 0));
        $command->setField('locationId', (int)self::getParam($request, 'locationId', 0));
        $command->setField('locationIds', !empty($params['locationIds']) ? $params['locationIds'] : []);
        $command->setField('serviceDuration', (int)self::getParam($request, 'serviceDuration', 0));
        $command->setField('weekDays', (array)self::getParam($request, 'weekDays', [1, 2, 3, 4, 5, 6, 7]));
        $command->setField('startDateTime', (string)self::getParam($request, 'startDateTime', ''));
        $command->setField('endDateTime', (string)self::getParam($request, 'endDateTime', ''));
        $command->setField('providerIds', !empty($params['providerIds']) ? $params['providerIds'] : []);
        $command->setField('extras', !empty($params['extras']) ? $params['extras'] : []);
        $command->setField('excludeAppointmentId', (int)self::getParam($request, 'excludeAppointmentId', []));
        $command->setField('persons', (int)self::getParam($request, 'persons', 1));
        $command->setField('group', (int)self::getParam($request, 'group', 0));
        $command->setField('page', (string)self::getParam($request, 'page', ''));
        $command->setField('monthsLoad', (int)self::getParam($request, 'monthsLoad', 0));
        $command->setField('queryTimeZone', (string)self::getParam($request, 'queryTimeZone', ''));
        $command->setField('timeZone', (string)self::getParam($request, 'timeZone', ''));

        $command->setField('allowAdminBookAtAnyTime', self::getParam($request, 'allowAdminBookAtAnyTime'));
        $command->setField('allowBookingIfPending', self::getParam($request, 'allowBookingIfPending'));
        $command->setField('allowBookingIfNotMin', self::getParam($request, 'allowBookingIfNotMin'));
        $command->setField('timeSlotLength', self::getParam($request, 'timeSlotLength'));
        $command->setField('serviceDurationAsSlot', self::getParam($request, 'serviceDurationAsSlot'));
        $command->setField('bufferTimeInSlot', self::getParam($request, 'bufferTimeInSlot'));
        $command->setField('structured', self::getParam($request, 'structured'));

        $requestBody = $request->getParsedBody();
        $this->setCommandFields($command, $requestBody);

        return $command;
    }
}
