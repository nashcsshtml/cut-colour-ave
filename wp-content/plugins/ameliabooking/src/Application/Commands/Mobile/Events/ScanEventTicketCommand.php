<?php

namespace AmeliaBooking\Application\Commands\Mobile\Events;

use AmeliaBooking\Application\Commands\QrCode\ScanQrCodeCommand;

class ScanEventTicketCommand extends ScanQrCodeCommand
{
    public function __construct($args)
    {
        parent::__construct($args);
    }
}
