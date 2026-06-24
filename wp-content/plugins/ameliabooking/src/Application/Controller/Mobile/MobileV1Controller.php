<?php

namespace AmeliaBooking\Application\Controller\Mobile;

use AmeliaBooking\Application\Controller\Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;
use AmeliaVendor\Psr\Http\Message\ResponseInterface as Response;

/**
 * Base controller for all /mobile/v1/ routes.
 *
 * Enforces two invariants so individual mobile controllers don't have to:
 *
 * 1. A Bearer token is required. If it is missing the response is a 409 JSON
 *    body with `data.reauthorize = true` — the same shape the mobile app
 *    already handles for expired sessions, so it drives the user back to
 *    the login screen rather than crashing.
 *
 * 2. The cabinet context (`source = cabinet-provider`) is forced by the route
 *    itself. Subclasses call `forceCabinetContext($command)` so the client
 *    never needs to send — or can fake — the source parameter.
 */
abstract class MobileV1Controller extends Controller
{
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
        $authHeader = $request->getHeaderLine('Authorization');
        $parts      = explode(' ', trim($authHeader));

        if (count($parts) !== 2 || $parts[0] !== 'Bearer' || empty($parts[1])) {
            $response = $response->withStatus(self::STATUS_CONFLICT);
            $response = $response->withHeader('Content-Type', 'application/json;charset=utf-8');
            $response->getBody()->write(
                json_encode(['message' => 'error', 'data' => ['reauthorize' => true]])
            );

            return $response;
        }

        return parent::__invoke($request, $response, $args, $validApiCall);
    }

    /**
     * Forces cabinet-provider context on the command regardless of what the
     * client sends. Subclasses call this inside instantiateCommand() instead
     * of reading the `source` query param.
     *
     * @param \AmeliaBooking\Application\Commands\Command $command
     */
    protected function forceCabinetContext($command)
    {
        $command->setPage('cabinet-provider');
    }
}
