<?php

namespace AmeliaBooking\Infrastructure\Services\Outlook;

use AmeliaBooking\Application\Commands\CommandResult;
use WP_Error;

/**
 * Outlook calendar client ID / secret checks
 */
final class OutlookCredentialsValidatorService
{
    /**
     * Validates credentials
     *
     * @param string $clientId
     * @param string $clientSecret
     *
     * @return CommandResult|null
     */
    public static function validateOrError($clientId, $clientSecret)
    {
        $id     = trim((string)$clientId);
        $secret = trim((string)$clientSecret);

        if (!$id && !$secret) {
            return null;
        }

        if (!self::clientIdIsValidGuid($id)) {
            return self::invalidCredentialsResult();
        }

        if (!self::clientSecretMeetsMinimumLength($secret)) {
            return self::invalidCredentialsResult();
        }

        if (!self::validate($id, $secret)) {
            return self::invalidCredentialsResult();
        }

        return null;
    }

    /**
     * Validates credentials when they change
     *
     * @param array $outlookSettings
     * @param array $savedOutlookSettings
     *
     * @return array|CommandResult
     */
    public static function validateCredentials(array $outlookSettings, array $savedOutlookSettings)
    {
        unset($outlookSettings['token']);

        $clientIdProvided     = array_key_exists('clientID', $outlookSettings);
        $clientSecretProvided = array_key_exists('clientSecret', $outlookSettings);

        if ($clientIdProvided || $clientSecretProvided) {
            $clientId = $clientIdProvided
                ? trim((string)($outlookSettings['clientID'] ?? ''))
                : trim((string)($savedOutlookSettings['clientID'] ?? ''));

            $clientSecret = $clientSecretProvided
                ? trim((string)($outlookSettings['clientSecret'] ?? ''))
                : trim((string)($savedOutlookSettings['clientSecret'] ?? ''));

            $savedClientId     = trim((string)($savedOutlookSettings['clientID'] ?? ''));
            $savedClientSecret = trim((string)($savedOutlookSettings['clientSecret'] ?? ''));

            $credentialsChanged =
                ($clientId !== $savedClientId) ||
                ($clientSecret !== $savedClientSecret);

            if ($credentialsChanged) {
                $invalid = self::validateOrError($clientId, $clientSecret);
                if ($invalid !== null) {
                    return $invalid;
                }
            }
        }

        foreach ($outlookSettings as $key => $value) {
            $savedOutlookSettings[$key] = $value;
        }

        return $savedOutlookSettings;
    }

    /**
     * Calls Microsoft token endpoint to verify application (client) ID and secret.
     *
     * @param string $clientId
     * @param string $clientSecret
     */
    public static function validate($clientId, $clientSecret): bool
    {
        $clientId     = trim((string)$clientId);
        $clientSecret = trim((string)$clientSecret);

        if ($clientId === '' || $clientSecret === '') {
            return false;
        }

        $response = wp_remote_post(
            'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            [
                'timeout' => 15,
                'body'    => [
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type'    => 'client_credentials',
                    'scope'         => 'https://graph.microsoft.com/.default',
                ],
            ]
        );

        if ($response instanceof WP_Error) {
            return false;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $rawBody    = wp_remote_retrieve_body($response);
        $body       = json_decode($rawBody, true);

        if (!is_array($body)) {
            $body = [];
        }

        if ($statusCode === 200) {
            return true;
        }

        if (self::isInvalidApplicationCredentialResponse($body)) {
            return false;
        }

        if ($statusCode >= 500) {
            return false;
        }

        $error = (string)($body['error'] ?? '');
        if ($error === 'unauthorized_client') {
            return true;
        }

        if ($error === 'invalid_grant') {
            return true;
        }

        return false;
    }

    /**
     * @param string $clientId
     */
    private static function clientIdIsValidGuid($clientId): bool
    {
        return (bool)preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $clientId
        );
    }

    /**
     * @param string $clientSecret
     */
    private static function clientSecretMeetsMinimumLength($clientSecret): bool
    {
        return strlen($clientSecret) >= 8;
    }

    /**
     * @param string $message
     */
    private static function formatValidationResult($message): CommandResult
    {
        $result = new CommandResult();
        $result->setDataInResponse(true);
        $result->setResult(CommandResult::RESULT_ERROR);
        $result->setMessage($message);
        $result->setData(['valid' => false]);

        return $result;
    }

    private static function invalidCredentialsResult(): CommandResult
    {
        $result = new CommandResult();
        $result->setDataInResponse(true);
        $result->setResult(CommandResult::RESULT_ERROR);
        $result->setMessage(__('Invalid Outlook credentials.', 'wpamelia'));
        $result->setData(['valid' => false]);

        return $result;
    }

    /**
     * @param array $body
     */
    private static function isInvalidApplicationCredentialResponse(array $body): bool
    {
        if (($body['error'] ?? '') === 'invalid_client') {
            return true;
        }

        $errorCodes = $body['error_codes'] ?? [];
        if (is_array($errorCodes)) {
            $invalidCredentialCodes = [7000215, 700016, 7000222];
            foreach ($errorCodes as $code) {
                if (in_array((int)$code, $invalidCredentialCodes, true)) {
                    return true;
                }
            }
        }

        $desc = isset($body['error_description']) ? (string)$body['error_description'] : '';
        if ($desc !== '' && preg_match('/AADSTS7000(215|16|222)/i', $desc)) {
            return true;
        }
        if (stripos($desc, 'Invalid client secret is provided') !== false) {
            return true;
        }
        if (
            stripos($desc, 'Application with identifier') !== false &&
            stripos($desc, 'was not found') !== false
        ) {
            return true;
        }

        return false;
    }
}
