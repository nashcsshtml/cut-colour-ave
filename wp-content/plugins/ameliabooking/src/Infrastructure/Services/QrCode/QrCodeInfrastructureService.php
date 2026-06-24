<?php

namespace AmeliaBooking\Infrastructure\Services\QrCode;

use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Common\Exceptions\NotFoundException;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaVendor\Dompdf\Dompdf;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Class QrCodeInfrastructureService
 *
 * @package AmeliaBooking\Infrastructure\Services\QrCode
 */
class QrCodeInfrastructureService extends AbstractQrCodeInfrastructureService
{
    /**
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws QueryExecutionException
     */
    public function generateQrCode(array $qrData): array
    {
        $eventStart = strtotime($qrData['eventStartDateTime']);

        if (!$eventStart) {
            throw new InvalidArgumentException('Invalid eventStartDateTime provided.');
        }

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        $dateFormat = $settingsService->getSetting('wordpress', 'dateFormat');
        $timeFormat = $settingsService->getSetting('wordpress', 'timeFormat');

        $renderer = new ImageRenderer(
            new RendererStyle(300, 2),
            new SvgImageBackEnd()
        );

        $qrDataItem = [
            'type'               => $qrData['type'],
            'data'               => base64_encode((new Writer($renderer))->writeString($qrData['qrCodeData'])),
            'mimeType'           => 'image/svg+xml',
            'ticketManualCode'   => $qrData['ticketManualCode'],
            'bookingId'          => $qrData['bookingId'],
            'eventName'          => $qrData['eventName'],
            'eventStartDateTime' => date_i18n(
                $dateFormat . ' ' . $timeFormat,
                $eventStart
            ),
        ];

        if (array_key_exists('eventLocation', $qrData)) {
            $qrDataItem['eventLocation'] = $qrData['eventLocation'];
        }

        if (array_key_exists('eventTicketName', $qrData)) {
            $qrDataItem['eventTicketName'] = $qrData['eventTicketName'];
        }

        ob_start();
        include AMELIA_PATH . '/templates/qrCode/qrCode.inc';
        $html = ob_get_clean();

        $dompdf = new Dompdf();

        $dompdf->setPaper('A4');

        $dompdf->loadHtml($html);

        $dompdf->render();

        $ticketPdfName = ($qrDataItem['type'] === 'ticket'
                ? 'Single Ticket' : 'Group Ticket') . (array_key_exists('eventTicketName', $qrDataItem) ? '-' . $qrDataItem['eventTicketName']
                : '') . ' - ' . $qrDataItem['eventName'] . '.pdf';

        return [
            'name'    => $ticketPdfName,
            'type'    => 'application/pdf',
            'content' => $dompdf->output(),
        ];
    }
}
