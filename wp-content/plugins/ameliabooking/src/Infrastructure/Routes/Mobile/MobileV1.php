<?php

namespace AmeliaBooking\Infrastructure\Routes\Mobile;

use AmeliaBooking\Application\Controller\Mobile\Appointments\GetAppointmentMobileController;
use AmeliaBooking\Application\Controller\Mobile\Appointments\GetAppointmentsMobileController;
use AmeliaBooking\Application\Controller\Mobile\Appointments\UpdateAppointmentStatusMobileController;
use AmeliaBooking\Application\Controller\Mobile\Events\GetEventMobileController;
use AmeliaBooking\Application\Controller\Mobile\Events\GetEventsMobileController;
use AmeliaBooking\Application\Controller\Mobile\Events\ScanEventTicketMobileController;
use AmeliaBooking\Application\Controller\Mobile\GetMobileInfoController;
use Slim\App;

/**
 * Dedicated routes for the Amelia Staff mobile app.
 *
 * These routes always run in cabinet-provider context — the `source` param is
 * never read from the client. A Bearer JWT is required; requests without one
 * receive a 409 `{data: {reauthorize: true}}` response, the same shape as an
 * expired-session error, so the app drives the user back to login. Both are
 * enforced by MobileV1Controller, which every controller below extends.
 *
 * Versioned under /mobile/v1/ so the contract can evolve independently of the
 * shared web-cabinet routes (/appointments, /events) without breaking either.
 */
class MobileV1
{
    /**
     * @param App $app
     *
     * @throws \InvalidArgumentException
     */
    public static function routes(App $app)
    {
        // Version-negotiation handshake. Intentionally UNVERSIONED and
        // UNAUTHENTICATED (no Bearer token) — it is the contract the app uses to
        // detect version skew, so it must never change shape or require auth.
        // Must ship in the same release as the /mobile/v1/* routes below.
        $app->get('/mobile/info', GetMobileInfoController::class);

        $app->get('/mobile/v1/appointments', GetAppointmentsMobileController::class);

        $app->get('/mobile/v1/appointments/{id:[0-9]+}', GetAppointmentMobileController::class);

        $app->post('/mobile/v1/appointments/status/{id:[0-9]+}', UpdateAppointmentStatusMobileController::class);

        $app->get('/mobile/v1/events', GetEventsMobileController::class);

        $app->get('/mobile/v1/events/{id:[0-9]+}', GetEventMobileController::class);

        $app->post('/mobile/v1/events/scan', ScanEventTicketMobileController::class);
    }
}
