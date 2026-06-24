<?php

namespace AmeliaBooking\Application\Controller\Mobile;

use AmeliaBooking\Application\Controller\Controller;
use AmeliaBooking\Infrastructure\Licence\Licence;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;
use AmeliaVendor\Psr\Http\Message\ResponseInterface as Response;

/**
 * Version-negotiation handshake for the Amelia Staff mobile app.
 *
 * The mobile app talks to the versioned /mobile/v1/* routes, but different sites
 * run different plugin builds. This endpoint lets the app detect version skew
 * BEFORE it makes (or fails) a real call, so it can show a precise "update the
 * plugin" vs "update the app" dialog instead of a cryptic 404.
 *
 * Contract (must stay stable FOREVER — it is the thing used to detect breaking
 * changes, so it can never itself have one):
 *
 *   GET /mobile/info  ->  200 {
 *     "message": "success",
 *     "data": {
 *       "pluginVersion": "9.5",            // AMELIA_VERSION, informational
 *       "mobileApi": { "min": 1, "max": 1}, // inclusive range of mobile-API
 *                                           // contract versions this build serves
 *       "licenseOk": true                  // false when the installed license does
 *                                           // not include the mobile-app feature (Pro+)
 *     }
 *   }
 *
 * Deliberately:
 *   - UNVERSIONED — lives outside /mobile/v1/ so it survives any contract change.
 *   - UNAUTHENTICATED — extends the base Controller (NOT MobileV1Controller, which
 *     forces a Bearer token) and returns its payload directly, bypassing the
 *     command/handler pipeline. No token, nonce, API key or DB access. The app
 *     calls it before login, so it must work with no credentials.
 *
 * The app declares the single contract version it was built against and compares:
 *   appVersion > max  -> plugin too old  -> "update the plugin"
 *   appVersion < min  -> contract dropped -> "update the app"  (force-update case)
 *   otherwise         -> compatible
 *
 * Release invariant: this endpoint MUST ship in the same release as the
 * /mobile/v1/* routes (see MobileV1.php), otherwise the very release that
 * introduces mobile support would fail its own compatibility check.
 *
 * Bump protocol for the constants below:
 *   - Raise MOBILE_API_MAX when adding a new, additive mobile-API contract version.
 *   - Raise MOBILE_API_MIN only when DELIBERATELY dropping support for an older app
 *     contract — this forces those app builds to update.
 */
class GetMobileInfoController extends Controller
{
    /** Oldest mobile-API contract version this plugin build still serves. */
    public const MOBILE_API_MIN = 1;

    /** Newest mobile-API contract version this plugin build serves. */
    public const MOBILE_API_MAX = 1;

    /**
     * @param Request  $request
     * @param Response $response
     * @param          $args
     * @param bool     $validApiCall
     *
     * @return Response
     */
    public function __invoke(Request $request, Response $response, $args, $validApiCall = false)
    {
        $response = $response->withStatus(self::STATUS_OK);
        $response = $response->withHeader('Content-Type', 'application/json;charset=utf-8');
        $response->getBody()->write(
            json_encode(
                [
                    'message' => 'success',
                    'data'    => [
                        'pluginVersion' => defined('AMELIA_VERSION') ? AMELIA_VERSION : null,
                        'mobileApi'     => [
                            'min' => self::MOBILE_API_MIN,
                            'max' => self::MOBILE_API_MAX,
                        ],
                        'licenseOk'     => Licence::hasFeatureAccess('mobileApp'),
                    ],
                ]
            )
        );

        return $response;
    }

    /**
     * Required by the base Controller, but never reached: __invoke returns the
     * static handshake payload directly and never enters the command pipeline.
     *
     * @param Request $request
     * @param         $args
     *
     * @return null
     */
    protected function instantiateCommand(Request $request, $args)
    {
        return null;
    }
}
