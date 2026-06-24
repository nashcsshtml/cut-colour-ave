<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Commands\Payment;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\Booking\BookingFallbackService;
use AmeliaBooking\Application\Services\Payment\PaymentApplicationService;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Bookable\Service\PackageCustomer;
use AmeliaBooking\Domain\Entity\Booking\Appointment\CustomerBooking;
use AmeliaBooking\Domain\Entity\Payment\Payment;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Services\Reservation\ReservationServiceInterface;
use AmeliaBooking\Domain\ValueObjects\String\PaymentStatus;
use AmeliaBooking\Domain\ValueObjects\String\BookingStatus;
use AmeliaBooking\Infrastructure\Common\Exceptions\NotFoundException;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Payment\PaymentRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\CustomerBookingRepository;
use AmeliaBooking\Infrastructure\Repository\Bookable\Service\PackageCustomerRepository;

/**
 * Class GetPaymentLinkCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Payment
 */
class GetPaymentLinkCommandHandler extends CommandHandler
{
    /**
     * @var array
     */
    public $mandatoryFields = [
        'paymentMethod',
        'token',
    ];

    /**
     * @param GetPaymentLinkCommand $command
     *
     * @return CommandResult
     * @throws QueryExecutionException
     * @throws NotFoundException
     * @throws InvalidArgumentException
     * @throws AccessDeniedException
     */
    public function handle(GetPaymentLinkCommand $command)
    {
        $this->checkMandatoryFields($command);

        $result = new CommandResult();

        /** @var PaymentApplicationService $paymentAS */
        $paymentAS = $this->container->get('application.payment.service');

        /** @var PaymentRepository $paymentRepository */
        $paymentRepository = $this->container->get('domain.payment.repository');

        /** @var CustomerBookingRepository $bookingRepository */
        $bookingRepository = $this->container->get('domain.booking.customerBooking.repository');

        /** @var PackageCustomerRepository $packageCustomerRepository */
        $packageCustomerRepository = $this->container->get('domain.bookable.packageCustomer.repository');

        /** @var Payment $payment */
        $payment = $paymentRepository->getById($command->getArg('id'));

        if ($payment->getStatus()->getValue() === PaymentStatus::PAID) {
            return $result->setHtml(BookingFallbackService::getFallbackHtml('payment_done'));
        }

        /** @var CustomerBooking|PackageCustomer $booking */
        $booking = $payment->getEntity()->getValue() === Entities::PACKAGE
            ? $packageCustomerRepository->getById($payment->getPackageCustomerId()->getValue())
            : $bookingRepository->getById($payment->getCustomerBookingId()->getValue());

        if (
            !$booking ||
            $booking->getToken()->getValue() !== $command->getField('token') ||
            $booking->getStatus()->getValue() === BookingStatus::CANCELED ||
            $booking->getStatus()->getValue() === BookingStatus::REJECTED ||
            $booking->getStatus()->getValue() === BookingStatus::NO_SHOW
        ) {
            return $result->setHtml(BookingFallbackService::getFallbackHtml('payment_failed'));
        }

        /** @var ReservationServiceInterface $reservationService */
        $reservationService = $this->container->get('application.reservation.service')->get(
            $payment->getEntity()->getValue()
        );

        $reservation = $reservationService->getReservationByPayment($payment)->getData();

        $data = [
            'type'      => $payment->getEntity()->getValue(),
            'paymentId' => $payment->getId()->getValue(),
            'customer'  => $reservation['customer'],
            'booking'   => $reservation['booking'],
            'bookable'  => $reservation['bookable'],
        ];

        switch ($payment->getEntity()->getValue()) {
            case Entities::APPOINTMENT:
                $data['appointment'] = $reservation['appointment'];

                break;
            case Entities::PACKAGE:
                $data['package'] = $reservation['bookable'];
                $data['packageCustomerId'] = $payment->getPackageCustomerId()->getValue();
                $data['packageCustomer'] = $reservation['packageCustomer'];

                $data['packageReservations'] = $reservation['booking'] === null
                    ? []
                    : array_merge([$reservation['appointment']], array_column($reservation['recurring'], 'appointment'));

                break;
            case Entities::EVENT:
                $data['event'] = $reservation['event'];

                break;
        }

        $data = apply_filters('amelia_before_payment_from_link_created_filter', $data);

        do_action('amelia_before_payment_from_link_created', $data);

        $paymentLinks = $paymentAS->createPaymentLink(
            $data,
            0,
            null,
            [$command->getField('paymentMethod') => true],
            null,
            true
        );

        $paymentLinks = apply_filters('amelia_after_payment_from_link_created_filter', $paymentLinks, $data);

        do_action('amelia_after_payment_from_link_created', $data, $paymentLinks);

        if ($paymentLinks === [] || !empty($paymentLinks['payment_link_error_message'])) {
            return $result->setHtml(BookingFallbackService::getFallbackHtml('payment_failed'));
        }

        if ($paymentLinks === null) {
            return $result->setHtml(BookingFallbackService::getFallbackHtml('payment_done'));
        }

        $result->setUrl(array_values($paymentLinks)[0]);

        return $result;
    }
}
