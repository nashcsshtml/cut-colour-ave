<?php

namespace AmeliaBooking\Application\Controller\User;

use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Commands\User\LoginCabinetCommand;
use AmeliaBooking\Application\Controller\Controller;
use AmeliaBooking\Domain\Events\DomainEventBus;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Class LoginCabinetController
 *
 * @package AmeliaBooking\Application\Controller\User
 */
class LoginCabinetController extends Controller
{
    /**
     * Fields for login that can be received from front-end
     *
     * @var array
     */
    protected $allowedFields = [
        'email',
        'password',
        'token',
        'checkIfWpUser',
        'cabinetType',
        'changePass',
        'recaptcha',
    ];

    /**
     * Instantiates the Login Cabinet command to hand it over to the Command Handler
     *
     * @param Request $request
     * @param         $args
     *
     * @return LoginCabinetCommand
     * @throws \RuntimeException
     */
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new LoginCabinetCommand($args);

        $requestBody = $request->getParsedBody();

        $this->setCommandFields($command, $requestBody);
        $command->setToken($request);

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
        $data = $result->getData();

        if (
            $result->getResult() !== CommandResult::RESULT_SUCCESS ||
            !is_array($data) ||
            empty($data['token'])
        ) {
            return;
        }

        $expires = $this->getTokenExpiration($data['token']);

        $this->setCabinetCookie('ameliaToken', $data['token'], $expires);

        if (!empty($data['user']['email'])) {
            $this->setCabinetCookie('ameliaUserEmail', $data['user']['email'], $expires);
        }
    }

    /**
     * @param string $name
     * @param string $value
     * @param int    $expires
     *
     * @return void
     */
    private function setCabinetCookie($name, $value, $expires)
    {
        if (headers_sent()) {
            return;
        }

        setcookie(
            $name,
            $value,
            [
                'expires'  => $expires,
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly' => false,
                'samesite' => 'Lax',
            ]
        );
    }

    /**
     * @param string $token
     *
     * @return int
     */
    private function getTokenExpiration($token)
    {
        $parts = explode('.', $token);

        if (count($parts) < 2) {
            return 0;
        }

        $payload = json_decode(
            base64_decode(strtr($parts[1], '-_', '+/')),
            true
        );

        return !empty($payload['exp']) ? (int)$payload['exp'] : 0;
    }
}
