<?php

namespace AmeliaBooking\Application\Commands\Mobile\Events;

use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Commands\QrCode\ScanQrCodeCommand;
use AmeliaBooking\Application\Commands\QrCode\ScanQrCodeCommandHandler;

/**
 * Mobile-API wrapper around ScanQrCodeCommandHandler.
 *
 * Key differences from the web scanner:
 *  - scannedAt is generated server-side (current date); the client does not send it.
 *  - "Already scanned" cases are returned as HTTP 200 with alreadyScanned:true
 *    instead of HTTP 409, so the mobile app can show a distinct UI state.
 */
class ScanEventTicketCommandHandler extends ScanQrCodeCommandHandler
{
    public $mandatoryFields = ['ticketManualCode'];

    private const ALREADY_SCANNED_CODES = [
        'ticket_already_scanned',
        'group_ticket_already_scanned',
        'all_already_scanned',
    ];

    public function handle(ScanQrCodeCommand $command)
    {
        // Generate the scan date server-side so the client cannot forge it.
        $command->setField('scannedAt', date('Y-m-d'));

        $result = parent::handle($command);

        if ($result->getResult() === CommandResult::RESULT_ERROR) {
            $data    = $result->getData();
            $message = isset($data['message']) ? (string) $data['message'] : '';

            if (in_array($message, self::ALREADY_SCANNED_CODES, true)) {
                $result->setResult(CommandResult::RESULT_SUCCESS);
                $result->setData(['alreadyScanned' => true]);
            }

            return $result;
        }

        $result->setData(['alreadyScanned' => false]);

        return $result;
    }
}
