<?php

namespace AmeliaBooking\Infrastructure\WP\MCP;

class AmeliaMcpServerRegistrar
{
    /**
     * Register the Amelia MCP server via the adapter.
     *
     * @param mixed $adapter The adapter instance (WP\MCP\Core\McpAdapter).
     */
    public static function init($adapter): void
    {
        $adapter->create_server(
            'amelia-mcp-server',
            'mcp',
            'amelia-mcp-server',
            'Amelia Booking System',
            'Amelia Booking System – appointments, events, services and customers.',
            '1.0.0',
            array( 'WP\\MCP\\Transport\\HttpTransport' ),
            null,
            null,
            array(
                'amelia/list-services',
                'amelia/list-employees',
                'amelia/list-customers',
                'amelia/list-events',
                'amelia/list-appointments',
                'amelia/add-service',
                'amelia/add-customer',
                'amelia/create-appointment',
                'amelia/create-event',
                'amelia/book-event',
                'amelia/cancel-booking',
                'amelia/check-availability',
            ),
            array(),
            array(),
            static function () {
                if (is_user_logged_in()) {
                    return true;
                }

                $username = sanitize_text_field($_SERVER['PHP_AUTH_USER'] ?? '');
                $password = sanitize_text_field($_SERVER['PHP_AUTH_PW'] ?? '');

                if (! $username || ! $password) {
                    return false;
                }

                $user = wp_authenticate_application_password(null, $username, $password);

                if ($user instanceof \WP_User) {
                    return true;
                }

                return false;
            }
        );
    }
}
