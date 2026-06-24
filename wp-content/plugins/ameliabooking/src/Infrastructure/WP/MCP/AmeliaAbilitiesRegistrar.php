<?php

namespace AmeliaBooking\Infrastructure\WP\MCP;

use AmeliaBooking\Application\Controller\Bookable\Service\AddServiceController;
use AmeliaBooking\Application\Controller\Bookable\Service\GetServicesController;
use AmeliaBooking\Application\Controller\Booking\Appointment\AddAppointmentController;
use AmeliaBooking\Application\Controller\Booking\Appointment\AddBookingController;
use AmeliaBooking\Application\Controller\Booking\Appointment\GetAppointmentsController;
use AmeliaBooking\Application\Controller\Booking\Appointment\GetTimeSlotsController;
use AmeliaBooking\Application\Controller\Booking\Appointment\UpdateBookingStatusController;
use AmeliaBooking\Application\Controller\Booking\Event\AddEventController;
use AmeliaBooking\Application\Controller\Booking\Event\GetEventsController;
use AmeliaBooking\Application\Controller\Booking\Event\UpdateEventBookingController;
use AmeliaBooking\Application\Controller\User\Customer\AddCustomerController;
use AmeliaBooking\Application\Controller\User\Customer\GetCustomersController;
use AmeliaBooking\Application\Controller\User\Provider\GetProvidersController;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;
use AmeliaVendor\Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use WP_Error;

class AmeliaAbilitiesRegistrar
{
    private const MCP_LIST_DEFAULT_LIMIT = 25;

    private const MCP_LIST_MAX_LIMIT = 500;

    private const MCP_DEFAULT_DATE_RANGE_DAYS = 90;

    /**
     * Register Amelia ability categories.
     */
    public static function registerCategories(): void
    {
        wp_register_ability_category('amelia-read', array(
            'label'       => __('Amelia – Read', 'wpamelia'),
            'description' => __(
                'Read-only abilities for the Amelia booking system. Canonical workflow: ' .
                '1) amelia/list-services — get service IDs and prices; ' .
                '2) amelia/list-employees — get provider IDs for that service; ' .
                '3) amelia/check-availability — find open timeslots; ' .
                '4) amelia/list-customers (or amelia/add-customer) — resolve the customer ID; ' .
                'then hand off to an amelia-write ability to complete the booking.',
                'wpamelia'
            ),
        ));

        wp_register_ability_category('amelia-write', array(
            'label'       => __('Amelia – Write', 'wpamelia'),
            'description' => __(
                'Write abilities for the Amelia booking system. Always gather required IDs from amelia-read abilities first, ' .
                'then confirm all details with the user before calling a write ability. ' .
                'Available actions: amelia/create-appointment (one-to-one service booking), ' .
                'amelia/book-event (group event registration), ' .
                'amelia/add-service, amelia/add-customer, amelia/create-event, amelia/cancel-booking.',
                'wpamelia'
            ),
        ));
    }

    /**
     * Register all Amelia abilities.
     */
    public static function registerAbilities(): void
    {
        static::registerListServicesAbility();
        static::registerListEmployeesAbility();
        static::registerListCustomersAbility();
        static::registerListEventsAbility();
        static::registerListAppointmentsAbility();
        static::registerCheckAvailabilityAbility();
        static::registerAddServiceAbility();
        static::registerAddCustomerAbility();
        static::registerCreateAppointmentAbility();
        static::registerCreateEventAbility();
        static::registerBookEventAbility();
        static::registerCancelBookingAbility();
    }

    /**
     * Build a bootstrapped container for command dispatch.
     *
     * @return \AmeliaBooking\Infrastructure\Common\Container
     */
    protected static function getContainer()
    {
        return require AMELIA_PATH . '/src/Infrastructure/ContainerConfig/container.php';
    }

    /**
     * Route a request through the real Slim controller so that emitSuccessEvent()
     * fires naturally — triggering notifications and all post-booking integrations.
     *
     * @param string $controllerClass Fully-qualified controller class name.
     * @param array  $params          Request params: body for POST, query string for GET.
     * @param array  $args            Route arguments (e.g. ['id' => 123] for path parameters).
     * @param string $method          HTTP method ('POST' or 'GET'). Defaults to 'POST'.
     * @return mixed|\WP_Error The 'data' payload from the JSON response, or WP_Error on failure.
     */
    protected static function invokeApplication(string $controllerClass, array $params, array $args = [], string $method = 'POST')
    {
        $container = static::getContainer();

        $serverRequestFactory = new ServerRequestFactory();
        $uri = 'http://127.0.0.1/';
        if ($method === 'GET' && $params !== []) {
            $uri .= '?' . http_build_query($params);
        }

        $request = $serverRequestFactory->createServerRequest($method, $uri)
            ->withHeader('Content-Type', 'application/json');

        if ($method !== 'GET') {
            $request = $request->withParsedBody($params);
        }

        $response = (new ResponseFactory())->createResponse();

        $controller = new $controllerClass($container);

        /** @var Response $result */
        $result = $controller(
            $request,
            $response,
            $args,
            true  // $validApiCall = true → skips nonce validation
        );

        $statusCode = $result->getStatusCode();
        $decoded    = json_decode((string) $result->getBody(), true);

        if ($statusCode >= 400) {
            $message = isset($decoded['message']) ? $decoded['message'] : __('Command failed.', 'wpamelia');
            $code    = $statusCode === 403 ? 'amelia_access_denied' : 'amelia_command_error';
            return new WP_Error($code, $message, array('status' => $statusCode));
        }

        return isset($decoded['data']) ? $decoded['data'] : $decoded;
    }

    // ---------------------------------------------------------------------------
    // READ abilities
    // ---------------------------------------------------------------------------

    protected static function registerListServicesAbility(): void
    {
        wp_register_ability('amelia/list-services', array(
        'label'       => __('List Services', 'wpamelia'),
        'description' => __(
            'Use when the user asks what services are available, what can be booked, appointment types,' .
            ' or pricing. Returns bookable services with IDs, durations, and prices.' .
            ' Results are paginated (use page and limit; default 25 per page).' .
            ' Call this first before amelia/check-availability or amelia/create-appointment.',
            'wpamelia'
        ),
        'category'    => 'amelia-read',
        'input_schema' => array(
            'type'       => 'object',
            'default'    => array(),
            'properties' => array(
                'limit' => array(
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 500,
                    'description' => 'Number of services per page (default 25, max 500). Use with page for pagination.',
               ),
                'page' => array(
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'description' => 'Page number for pagination (default 1)',
               ),
                'search' => array(
                    'type'        => 'string',
                    'description' => 'Search services by name',
               ),
           ),
            'additionalProperties' => false,
        ),
        'output_schema' => array(
            'type'       => 'object',
            'properties' => array(
                'services' => array(
                    'type'  => 'array',
                    'items' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'id'          => array('type' => 'integer', 'description' => 'Service ID'),
                            'name'        => array('type' => 'string'),
                            'description' => array('type' => array('string', 'null')),
                            'color'       => array('type' => 'string', 'description' => 'Hex color, e.g. "#1788FB"'),
                            'price'       => array('type' => 'number'),
                            'duration'    => array('type' => 'integer', 'description' => 'Duration in seconds'),
                            'minCapacity' => array('type' => 'integer'),
                            'maxCapacity' => array('type' => 'integer'),
                            'categoryId'  => array('type' => 'integer'),
                            'status'      => array('type' => 'string', 'description' => '"visible" or "hidden"'),
                            'show'        => array('type' => 'boolean'),
                            'extras'      => array('type' => 'array', 'items' => array('type' => 'object')),
                       ),
                   ),
               ),
                'countFiltered'       => array('type' => 'integer'),
                'countTotalByCategory' => array('type' => 'integer'),
                'countTotal'          => array('type' => 'integer'),
           ),
        ),
        'execute_callback' => function ($input) {
            $input  = is_array($input) ? $input : array();
            $params = array_merge(
                array('sort' => 'idAsc'),
                AmeliaAbilitiesRegistrar::getMcpListPaginationParams($input)
            );

            if (!empty($input['search'])) {
                $params['search'] = sanitize_text_field($input['search']);
            }

            return AmeliaAbilitiesRegistrar::invokeApplication(GetServicesController::class, $params, [], 'GET');
        },
        'permission_callback' => function () {
            return AmeliaAbilitiesRegistrar::canListServices();
        },
        'meta' => array(
            'annotations' => array(
                'readonly'    => true,
                'destructive' => false,
           ),
            'show_in_rest' => true,
            'mcp'          => array('public' => true),
        ),
        ));
    }

    protected static function registerListEmployeesAbility(): void
    {
        wp_register_ability('amelia/list-employees', array(
        'label'       => __('List Employees', 'wpamelia'),
        'description' => __(
            'Use when the user asks about staff, employees, therapists, or who performs a service.' .
            ' Returns provider IDs required by amelia/create-appointment and amelia/check-availability.' .
            ' Results are paginated (use page and limit; default 25 per page).',
            'wpamelia'
        ),
        'category'    => 'amelia-read',
        'input_schema' => array(
            'type'       => 'object',
            'default'    => array(),
            'properties' => array(
                'limit' => array(
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 500,
                    'description' => 'Number of employees per page (default 25, max 500). Use with page for pagination.',
               ),
                'page' => array(
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'description' => 'Page number for pagination (default 1)',
               ),
                'search' => array(
                    'type'        => 'string',
                    'description' => 'Search employees by name',
               ),
           ),
            'additionalProperties' => false,
        ),
        'output_schema' => array(
            'type'       => 'object',
            'properties' => array(
                'users' => array(
                    'type'  => 'array',
                    'items' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'id'             => array('type' => 'integer', 'description' => 'Provider/employee ID'),
                            'firstName'      => array('type' => 'string'),
                            'lastName'       => array('type' => 'string'),
                            'email'          => array('type' => array('string', 'null')),
                            'phone'          => array('type' => array('string', 'null')),
                            'type'           => array('type' => 'string', 'description' => 'Always "provider"'),
                            'status'         => array('type' => 'string', 'description' => '"visible" or "hidden"'),
                            'locationId'     => array('type' => array('integer', 'null')),
                            'timeZone'       => array('type' => array('string', 'null')),
                            'serviceList'    => array(
                                'type'        => 'array',
                                'items'       => array('type' => 'object'),
                                'description' => 'Services this provider delivers',
                            ),
                            'pictureFullPath' => array('type' => array('string', 'null')),
                       ),
                   ),
               ),
                'countFiltered' => array('type' => 'integer'),
                'countTotal'    => array('type' => 'integer'),
           ),
        ),
        'execute_callback' => function ($input) {
            $input  = is_array($input) ? $input : array();
            $params = AmeliaAbilitiesRegistrar::getMcpListPaginationParams($input);

            if (!empty($input['search'])) {
                $params['search'] = sanitize_text_field($input['search']);
            }

            return AmeliaAbilitiesRegistrar::invokeApplication(GetProvidersController::class, $params, [], 'GET');
        },
        'permission_callback' => function () {
            return current_user_can('amelia_read_employees');
        },
        'meta' => array(
            'annotations' => array(
                'readonly'    => true,
                'destructive' => false,
           ),
            'show_in_rest' => true,
            'mcp'          => array('public' => true),
        ),
        ));
    }

    protected static function registerListCustomersAbility(): void
    {
        wp_register_ability('amelia/list-customers', array(
        'label'       => __('List Customers', 'wpamelia'),
        'description' => __(
            'Use when the user asks to look up a customer, find a client, or before booking on behalf' .
            ' of an existing person. Returns customer IDs required by amelia/create-appointment and amelia/book-event.' .
            ' Results are paginated (use page and limit; default 25 per page).',
            'wpamelia'
        ),
        'category'    => 'amelia-read',
        'input_schema' => array(
            'type'       => 'object',
            'default'    => array(),
            'properties' => array(
                'limit'  => array(
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 500,
                    'description' => 'Number of customers per page (default 25, max 500). Use with page for pagination.',
               ),
                'search' => array(
                    'type'        => 'string',
                    'description' => 'Search customers by name or email',
               ),
                'page'   => array(
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'description' => 'Page number for pagination (default 1)',
               ),
           ),
            'additionalProperties' => false,
        ),
        'output_schema' => array(
            'type'       => 'object',
            'properties' => array(
                'users' => array(
                    'type'  => 'array',
                    'items' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'id'        => array('type' => 'integer', 'description' => 'Customer ID'),
                            'firstName' => array('type' => 'string'),
                            'lastName'  => array('type' => 'string'),
                            'email'     => array('type' => array('string', 'null')),
                            'phone'     => array('type' => array('string', 'null')),
                            'type'      => array('type' => 'string', 'description' => 'Always "customer"'),
                            'status'    => array('type' => 'string'),
                            'gender'    => array('type' => array('string', 'null')),
                            'note'      => array('type' => array('string', 'null')),
                       ),
                   ),
               ),
                'filteredCount' => array('type' => 'integer'),
                'totalCount'    => array('type' => 'integer'),
           ),
        ),
        'execute_callback' => function ($input) {
            $input  = is_array($input) ? $input : array();
            $params = AmeliaAbilitiesRegistrar::getMcpListPaginationParams($input);

            if (!empty($input['search'])) {
                $params['search'] = sanitize_text_field($input['search']);
            }

            return AmeliaAbilitiesRegistrar::invokeApplication(GetCustomersController::class, $params, [], 'GET');
        },
        'permission_callback' => function () {
            return AmeliaAbilitiesRegistrar::canListCustomers();
        },
        'meta' => array(
            'annotations' => array(
                'readonly'    => true,
                'destructive' => false,
           ),
            'show_in_rest' => true,
            'mcp'          => array('public' => true),
        ),
        ));
    }

    protected static function registerListEventsAbility(): void
    {
        wp_register_ability('amelia/list-events', array(
        'label'       => __('List Events', 'wpamelia'),
        'description' => __(
            'Use when the user asks about events, classes, workshops, or group sessions.' .
            ' Returns event IDs, periods, capacity, and availability. Call this before amelia/book-event.' .
            ' Results are paginated (use page and limit). When dates are omitted, only events from today through the next 90 days are returned.',
            'wpamelia'
        ),
        'category'    => 'amelia-read',
        'input_schema' => array(
            'type'       => 'object',
            'default'    => array(),
            'properties' => array(
                'limit'  => array(
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 500,
                    'description' => 'Number of events per page (default 25, max 500). Use with page for pagination.',
               ),
                'search' => array(
                    'type'        => 'string',
                    'description' => 'Search events by name',
               ),
                'page'   => array(
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'description' => 'Page number for pagination (default 1)',
               ),
                'dates'  => array(
                    'type'        => 'array',
                    'items'       => array('type' => 'string'),
                    'maxItems'    => 2,
                    'description' => 'Optional date range as [startDate, endDate] in YYYY-MM-DD format. Defaults to today through 90 days ahead.',
               ),
           ),
            'additionalProperties' => false,
        ),
        'output_schema' => array(
            'type'       => 'object',
            'properties' => array(
                'events' => array(
                    'type'  => 'array',
                    'items' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'id'          => array('type' => 'integer', 'description' => 'Event ID'),
                            'name'        => array('type' => 'string'),
                            'description' => array('type' => array('string', 'null')),
                            'color'       => array('type' => array('string', 'null')),
                            'price'       => array('type' => 'number'),
                            'maxCapacity' => array('type' => 'integer'),
                            'status'      => array(
                                'type'        => 'string',
                                'description' => 'Computed display status: "open", "closed", "full", "upcoming", "canceled"',
                            ),
                            'show'        => array('type' => 'boolean'),
                            'locationId'  => array('type' => array('integer', 'null')),
                            'bookedSpots' => array('type' => 'integer', 'description' => 'Number of confirmed bookings'),
                            'places'      => array('type' => 'integer', 'description' => 'Remaining capacity (maxCapacity - bookedSpots)'),
                            'full'        => array('type' => 'boolean'),
                            'periods'     => array(
                                'type'  => 'array',
                                'items' => array(
                                    'type'       => 'object',
                                    'properties' => array(
                                        'periodStart' => array('type' => 'string', 'description' => 'YYYY-MM-DD HH:mm'),
                                        'periodEnd'   => array('type' => 'string', 'description' => 'YYYY-MM-DD HH:mm'),
                                   ),
                               ),
                           ),
                            'tags'     => array('type' => 'array', 'items' => array('type' => 'object')),
                       ),
                   ),
               ),
                'count'      => array('type' => array('integer', 'null'), 'description' => 'Events matching filters on the current page query'),
                'countTotal' => array('type' => array('integer', 'null'), 'description' => 'Total events in the system'),
           ),
        ),
        'execute_callback' => function ($input) {
            $input  = is_array($input) ? $input : array();
            $params = array_merge(
                AmeliaAbilitiesRegistrar::getMcpListPaginationParams($input),
                array('bookings' => false)
            );

            if (!empty($input['search'])) {
                $params['search'] = sanitize_text_field($input['search']);
            }
            if (!empty($input['dates']) && is_array($input['dates'])) {
                $params['dates'] = array_map('sanitize_text_field', $input['dates']);
            } else {
                $params['dates'] = AmeliaAbilitiesRegistrar::getDefaultMcpDateRange();
            }

            return AmeliaAbilitiesRegistrar::invokeApplication(GetEventsController::class, $params, [], 'GET');
        },
        'permission_callback' => function () {
            return current_user_can('amelia_read_events');
        },
        'meta' => array(
            'annotations' => array(
                'readonly'    => true,
                'destructive' => false,
           ),
            'show_in_rest' => true,
            'mcp'          => array('public' => true),
        ),
        ));
    }

    protected static function registerListAppointmentsAbility(): void
    {
        wp_register_ability('amelia/list-appointments', array(
            'label'       => __('List Appointments', 'wpamelia'),
            'description' => __(
                'Use when the user asks about existing appointments, upcoming bookings, or wants to find' .
                ' a specific appointment to reschedule or cancel. Returns appointments with IDs, dates,' .
                ' service, provider, customer, and status. Supports filtering by date range, provider,' .
                ' service, customer, and status. Results are paginated (use page and limit; default 25 per page).' .
                ' When dates are omitted, only appointments from today through the next 90 days are returned.',
                'wpamelia'
            ),
            'category'    => 'amelia-read',
            'input_schema' => array(
                'type'       => 'object',
                'default'    => array(),
                'properties' => array(
                    'dates' => array(
                        'type'        => 'array',
                        'items'       => array('type' => 'string'),
                        'maxItems'    => 2,
                        'description' => 'Optional date range as [startDate, endDate] in YYYY-MM-DD format. Defaults to today through 90 days ahead.',
                    ),
                    'limit' => array(
                        'type'        => 'integer',
                        'minimum'     => 1,
                        'maximum'     => 500,
                        'description' => 'Number of appointments per page (default 25, max 500). Use with page for pagination.',
                    ),
                    'providers' => array(
                        'type'        => 'array',
                        'items'       => array('type' => 'integer'),
                        'description' => 'Filter by provider/employee IDs. Use amelia/list-employees to find IDs.',
                    ),
                    'services' => array(
                        'type'        => 'array',
                        'items'       => array('type' => 'integer'),
                        'description' => 'Filter by service IDs. Use amelia/list-services to find IDs.',
                    ),
                    'customers' => array(
                        'type'        => 'array',
                        'items'       => array('type' => 'integer'),
                        'description' => 'Filter by customer IDs. Use amelia/list-customers to find IDs.',
                    ),
                    'status' => array(
                        'type'        => 'string',
                        'enum'        => array('approved', 'pending', 'canceled', 'rejected', 'no-show'),
                        'description' => 'Filter by appointment status.',
                    ),
                    'search' => array(
                        'type'        => 'string',
                        'description' => 'Free-text search across service, provider, and customer names.',
                    ),
                    'page' => array(
                        'type'        => 'integer',
                        'minimum'     => 1,
                        'description' => 'Page number for pagination (default 1).',
                    ),
                ),
                'additionalProperties' => false,
            ),
            'output_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'appointments' => array(
                        'type'        => 'object',
                        'description' => 'Appointments grouped by date (YYYY-MM-DD). Each key contains { date, appointments[] }.',
                        'additionalProperties' => array(
                            'type'       => 'object',
                            'properties' => array(
                                'date'         => array('type' => 'string', 'description' => 'YYYY-MM-DD'),
                                'appointments' => array(
                                    'type'  => 'array',
                                    'items' => array(
                                        'type'       => 'object',
                                        'properties' => array(
                                            'id'            => array('type' => 'integer', 'description' => 'Appointment ID'),
                                            'serviceId'     => array('type' => 'integer'),
                                            'providerId'    => array('type' => 'integer'),
                                            'locationId'    => array('type' => array('integer', 'null')),
                                            'bookingStart'  => array('type' => 'string', 'description' => 'YYYY-MM-DD HH:mm:ss'),
                                            'bookingEnd'    => array('type' => 'string', 'description' => 'YYYY-MM-DD HH:mm:ss'),
                                            'status'        => array(
                                                'type'        => 'string',
                                                'description' => '"approved", "pending", "canceled", "rejected", "no-show"',
                                            ),
                                            'internalNotes' => array('type' => array('string', 'null')),
                                            'cancelable'    => array('type' => 'boolean'),
                                            'reschedulable' => array('type' => 'boolean'),
                                            'bookings'      => array(
                                                'type'  => 'array',
                                                'items' => array(
                                                    'type'       => 'object',
                                                    'properties' => array(
                                                        'id'         => array(
                                                            'type'        => 'integer',
                                                            'description' => 'CustomerBooking ID — use as bookingId for amelia/cancel-booking',
                                                        ),
                                                        'customerId' => array('type' => 'integer'),
                                                        'persons'    => array('type' => 'integer'),
                                                        'status'     => array('type' => 'string'),
                                                        'price'      => array('type' => 'number'),
                                                    ),
                                                ),
                                            ),
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                    'filteredCount' => array('type' => 'integer', 'description' => 'Appointments matching the current filters'),
                    'totalCount'    => array('type' => 'integer'),
                    'totalApproved' => array('type' => 'integer'),
                    'totalPending'  => array('type' => 'integer'),
                ),
            ),
            'execute_callback' => function ($input) {
                $input  = is_array($input) ? $input : array();
                $params = array_merge(
                    array('asArray' => false),
                    AmeliaAbilitiesRegistrar::getMcpListPaginationParams($input)
                );

                if (!empty($input['dates']) && is_array($input['dates'])) {
                    $params['dates'] = array_map('sanitize_text_field', $input['dates']);
                } else {
                    $params['dates'] = AmeliaAbilitiesRegistrar::getDefaultMcpDateRange();
                }
                if (!empty($input['providers'])) {
                    $params['providers'] = array_map('intval', (array) $input['providers']);
                }
                if (!empty($input['services'])) {
                    $params['services'] = array_map('intval', (array) $input['services']);
                }
                if (!empty($input['customers'])) {
                    $params['customers'] = array_map('intval', (array) $input['customers']);
                }
                if (!empty($input['status'])) {
                    $params['status'] = sanitize_text_field($input['status']);
                }
                if (!empty($input['search'])) {
                    $params['search'] = sanitize_text_field($input['search']);
                }

                return AmeliaAbilitiesRegistrar::invokeApplication(GetAppointmentsController::class, $params, [], 'GET');
            },
            'permission_callback' => function () {
                return current_user_can('amelia_read_appointments');
            },
            'meta' => array(
                'annotations' => array(
                    'readonly'    => true,
                    'destructive' => false,
                ),
                'show_in_rest' => true,
                'mcp'          => array('public' => true),
            ),
        ));
    }

    protected static function registerCheckAvailabilityAbility(): void
    {
        wp_register_ability('amelia/check-availability', array(
        'label'       => __('Check Availability', 'wpamelia'),
        'description' => __(
            'Use when the user asks when they can book, what slots are free, or what times are available' .
            ' for a service. Returns available datetimes grouped by date. Requires serviceId from amelia/list-services.',
            'wpamelia'
        ),
        'category'    => 'amelia-read',
        'input_schema' => array(
            'type'       => 'object',
            'properties' => array(
                'serviceId' => array(
                    'type'        => 'integer',
                    'description' => 'Service ID (required). Use amelia/list-services to find IDs.',
               ),
                'persons' => array(
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'description' => 'Number of persons (required). Default: 1',
               ),
                'startDateTime' => array(
                    'type'        => 'string',
                    'description' => 'From which date/time to retrieve slots, in YYYY-MM-DD HH:mm format. Defaults to now.',
               ),
                'endDateTime' => array(
                    'type'        => 'string',
                    'description' => 'Up until which date/time to retrieve slots, in YYYY-MM-DD HH:mm format.',
               ),
                'providerIds' => array(
                    'type'        => 'array',
                    'items'       => array('type' => 'integer'),
                    'description' => 'Filter by specific employee IDs. Use amelia/list-employees to find IDs.',
               ),
                'locationId' => array(
                    'type'        => 'integer',
                    'description' => 'Filter by location ID.',
               ),
                'serviceDuration' => array(
                    'type'        => 'integer',
                    'description' => 'Override the service duration in seconds.',
               ),
                'excludeAppointmentId' => array(
                    'type'        => 'integer',
                    'description' => 'Exclude this appointment ID from calculations (used when rescheduling).',
               ),
                'extras' => array(
                    'type'        => 'array',
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'id'       => array('type' => 'integer'),
                            'quantity' => array('type' => 'integer', 'minimum' => 1),
                       ),
                        'required' => array('id', 'quantity'),
                   ),
                    'description' => 'Extras to include in slot calculation.',
               ),
           ),
            'required'             => array('serviceId', 'persons'),
            'additionalProperties' => false,
        ),
        'output_schema' => array(
            'type'       => 'object',
            'properties' => array(
                'minimum'  => array('type' => 'string', 'description' => 'Earliest bookable datetime in YYYY-MM-DD HH:mm format'),
                'maximum'  => array('type' => 'string', 'description' => 'Latest bookable datetime in YYYY-MM-DD HH:mm format'),
                'duration' => array('type' => 'integer', 'description' => 'Computed service duration in seconds'),
                'slots'    => array(
                    'type'        => 'object',
                    'description' => 'Available slots keyed by date (YYYY-MM-DD), then by time (HH:mm),' .
                        ' each value is an array of provider ID arrays, e.g. { "2025-06-01": { "09:00": [[3]] } }',
               ),
                'occupied' => array(
                    'type'        => 'object',
                    'description' => 'Same structure as slots but for unavailable times',
               ),
                'busyness' => array(
                    'type'        => 'object',
                    'description' => 'Per-day occupancy percentage (0-100) keyed by date (YYYY-MM-DD)',
               ),
           ),
        ),
        'execute_callback' => function ($input) {
            $input  = is_array($input) ? $input : array();
            $params = array(
                'serviceId' => (int) $input['serviceId'],
                'persons'   => isset($input['persons']) ? max(1, (int) $input['persons']) : 1,
            );

            if (!empty($input['startDateTime'])) {
                $params['startDateTime'] = sanitize_text_field($input['startDateTime']);
            }
            if (!empty($input['endDateTime'])) {
                $params['endDateTime'] = sanitize_text_field($input['endDateTime']);
            }
            if (!empty($input['providerIds'])) {
                $params['providerIds'] = array_map('intval', (array) $input['providerIds']);
            }
            if (!empty($input['locationId'])) {
                $params['locationId'] = (int) $input['locationId'];
            }
            if (!empty($input['serviceDuration'])) {
                $params['serviceDuration'] = (int) $input['serviceDuration'];
            }
            if (!empty($input['excludeAppointmentId'])) {
                $params['excludeAppointmentId'] = (int) $input['excludeAppointmentId'];
            }
            if (!empty($input['extras'])) {
                $params['extras'] = json_encode($input['extras']);
            }

            return AmeliaAbilitiesRegistrar::invokeApplication(GetTimeSlotsController::class, $params, [], 'GET');
        },
        'permission_callback' => function () {
            return AmeliaAbilitiesRegistrar::canListServices();
        },
        'meta' => array(
            'annotations' => array(
                'readonly'    => true,
                'destructive' => false,
           ),
            'show_in_rest' => true,
            'mcp'          => array('public' => true),
        ),
        ));
    }

    // ---------------------------------------------------------------------------
    // WRITE abilities
    // ---------------------------------------------------------------------------

    protected static function registerAddServiceAbility(): void
    {
        wp_register_ability('amelia/add-service', array(
        'label'       => __('Add Service', 'wpamelia'),
        'description' => __(
            'Use when the user wants to add, create, or set up a new bookable service.' .
            ' ALWAYS confirm the service name, duration, price, and assigned employees with the user before calling this ability.',
            'wpamelia'
        ),
        'category'    => 'amelia-write',
        'input_schema' => array(
            'type'       => 'object',
            'properties' => array(
                'name'       => array(
                    'type'        => 'string',
                    'description' => 'Service name (required). Ask the user to confirm this value before calling the ability.',
               ),
                'categoryId' => array('type' => 'integer', 'description' => 'Category ID (required)'),
                'duration'   => array('type' => 'integer', 'description' => 'Duration in seconds (required). Example: 3600 for 1 hour'),
                'price'      => array('type' => 'number', 'description' => 'Service price (required)'),
                'providers'  => array(
                    'type'        => 'array',
                    'items'       => array('type' => 'integer'),
                    'description' => 'Array of provider IDs (required). Use amelia/list-employees to find IDs.',
               ),
                'show'  => array('type' => 'boolean', 'description' => 'Whether to show the service on the website. Default: true'),
                'color' => array('type' => 'string', 'description' => 'Service color as a hex value (e.g. "#1788FB"). Default: #1788FB'),
           ),
            'required'             => array('name', 'categoryId', 'duration', 'price', 'providers'),
            'additionalProperties' => false,
        ),
        'output_schema' => array(
            'type'       => 'object',
            'properties' => array(
                'service' => array(
                    'type'       => 'object',
                    'description' => 'The newly created service',
                    'properties' => array(
                        'id'          => array('type' => 'integer', 'description' => 'Assigned service ID'),
                        'name'        => array('type' => 'string'),
                        'color'       => array('type' => 'string'),
                        'price'       => array('type' => 'number'),
                        'duration'    => array('type' => 'integer', 'description' => 'Duration in seconds'),
                        'minCapacity' => array('type' => 'integer'),
                        'maxCapacity' => array('type' => 'integer'),
                        'categoryId'  => array('type' => 'integer'),
                        'status'      => array('type' => 'string'),
                        'show'        => array('type' => 'boolean'),
                   ),
               ),
           ),
        ),
        'execute_callback' => function ($input) {
            return AmeliaAbilitiesRegistrar::invokeApplication(
                AddServiceController::class,
                array(
                    'name'             => sanitize_text_field($input['name']),
                    'categoryId'       => (int) $input['categoryId'],
                    'duration'         => (int) $input['duration'],
                    'price'            => (float) $input['price'],
                    'minCapacity'      => isset($input['minCapacity']) ? (int) $input['minCapacity'] : 1,
                    'maxCapacity'      => isset($input['maxCapacity']) ? (int) $input['maxCapacity'] : 1,
                    'providers'        => array_map('intval', (array) $input['providers']),
                    'color'            => !empty($input['color']) ? sanitize_text_field($input['color']) : '#1788FB',
                    'status'           => 'visible',
                    'show'             => isset($input['show']) ? (bool) $input['show'] : true,
                    'description'      => '',
                    'depositPayment'   => 'disabled',
                    'recurringCycle'   => 'disabled',
                    'recurringSub'     => 'future',
                    'recurringPayment' => 0,
                    'deposit'          => 0,
                )
            );
        },
        // note: 'status' is in AddServiceController::allowedFields so it passes through.
        'permission_callback' => function () {
            return current_user_can('amelia_write_services');
        },
        'meta' => array(
            'annotations' => array(
                'readonly'    => false,
                'destructive' => true,
                'idempotent'  => false,
           ),
            'show_in_rest' => true,
            'mcp'          => array('public' => true),
        ),
        ));
    }

    protected static function registerAddCustomerAbility(): void
    {
        wp_register_ability('amelia/add-customer', array(
        'label'       => __('Add Customer', 'wpamelia'),
        'description' => __(
            'Use when the user wants to register, add, or create a new customer/client.' .
            ' ALWAYS confirm first name, last name, and email with the user before calling this ability.',
            'wpamelia'
        ),
        'category'    => 'amelia-write',
        'input_schema' => array(
            'type'       => 'object',
            'properties' => array(
                'firstName' => array(
                    'type'        => 'string',
                    'description' => 'Customer first name (required). Confirm with the user before submitting.',
               ),
                'lastName'  => array(
                    'type'        => 'string',
                    'description' => 'Customer last name (required). Confirm with the user before submitting.',
               ),
                'email'     => array(
                    'type'        => 'string',
                    'format'      => 'email',
                    'description' => 'Customer email address (required). Confirm with the user before submitting.',
               ),
                'phone'     => array('type' => 'string', 'description' => 'Customer phone number (optional)'),
           ),
            'required'             => array('firstName', 'lastName', 'email'),
            'additionalProperties' => false,
        ),
        'output_schema' => array(
            'type'       => 'object',
            'properties' => array(
                'user' => array(
                    'type'        => 'object',
                    'description' => 'The newly created customer',
                    'properties'  => array(
                        'id'        => array('type' => 'integer', 'description' => 'Assigned customer ID'),
                        'firstName' => array('type' => 'string'),
                        'lastName'  => array('type' => 'string'),
                        'email'     => array('type' => 'string'),
                        'phone'     => array('type' => array('string', 'null')),
                        'type'      => array('type' => 'string', 'description' => 'Always "customer"'),
                        'status'    => array('type' => 'string'),
                   ),
               ),
           ),
        ),
        'execute_callback' => function ($input) {
            $body = array(
                'firstName' => sanitize_text_field($input['firstName']),
                'lastName'  => sanitize_text_field($input['lastName']),
                'email'     => sanitize_email($input['email']),
                'type'      => 'customer',
                'status'    => 'visible',
            );

            if (!empty($input['phone'])) {
                $body['phone'] = sanitize_text_field($input['phone']);
            }

            return AmeliaAbilitiesRegistrar::invokeApplication(
                AddCustomerController::class,
                $body
            );
        },
        'permission_callback' => function () {
            return current_user_can('amelia_write_customers');
        },
        'meta' => array(
            'annotations' => array(
                'readonly'    => false,
                'destructive' => true,
                'idempotent'  => false,
           ),
            'show_in_rest' => true,
            'mcp'          => array('public' => true),
        ),
        ));
    }

    protected static function registerCreateAppointmentAbility(): void
    {
        wp_register_ability('amelia/create-appointment', array(
        'label'       => __('Create Appointment', 'wpamelia'),
        'description' => __(
            'Use when the user wants to book, schedule, or reserve an appointment for a customer.' .
            ' ALWAYS confirm service, employee, customer, and date/time with the user before calling.' .
            ' Use amelia/list-services, amelia/list-employees, amelia/list-customers, and amelia/check-availability first.',
            'wpamelia'
        ),
        'category'    => 'amelia-write',
        'input_schema' => array(
            'type'       => 'object',
            'properties' => array(
                'serviceId'    => array(
                    'type'        => 'integer',
                    'description' => 'Service ID. Use amelia/list-services to find IDs. Confirm with the user before submitting.',
               ),
                'providerId'   => array(
                    'type'        => 'integer',
                    'description' => 'Provider/employee ID. Use amelia/list-employees to find IDs. Confirm with the user before submitting.',
               ),
                'customerId'   => array(
                    'type'        => 'integer',
                    'description' => 'Customer ID. Use amelia/list-customers to find IDs. Confirm with the user before submitting.',
               ),
                'bookingStart' => array(
                    'type'        => 'string',
                    'pattern'     => '^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$',
                    'description' => 'Appointment start date and time in YYYY-MM-DD HH:mm format' .
                        ' (e.g. "2025-12-25 14:00"). Confirm with the user before submitting.',
               ),
                'internalNotes' => array('type' => 'string', 'description' => 'Internal notes for the appointment (optional)'),
           ),
            'required'             => array('serviceId', 'providerId', 'customerId', 'bookingStart'),
            'additionalProperties' => false,
        ),
        'output_schema' => array(
            'type'       => 'object',
            'properties' => array(
                'appointment' => array(
                    'type'        => 'object',
                    'description' => 'The newly created appointment',
                    'properties'  => array(
                        'id'           => array('type' => 'integer', 'description' => 'Appointment ID'),
                        'serviceId'    => array('type' => 'integer'),
                        'providerId'   => array('type' => 'integer'),
                        'locationId'   => array('type' => array('integer', 'null')),
                        'bookingStart' => array('type' => 'string', 'description' => 'YYYY-MM-DD HH:mm:ss'),
                        'bookingEnd'   => array('type' => 'string', 'description' => 'YYYY-MM-DD HH:mm:ss'),
                        'status'       => array('type' => 'string', 'description' => '"approved", "pending", "canceled", "rejected", "waiting"'),
                        'internalNotes' => array('type' => 'string'),
                        'bookings'     => array(
                            'type'  => 'array',
                            'items' => array(
                                'type'       => 'object',
                                'properties' => array(
                                    'id'         => array('type' => 'integer', 'description' => 'CustomerBooking ID'),
                                    'customerId' => array('type' => 'integer'),
                                    'persons'    => array('type' => 'integer'),
                                    'status'     => array('type' => 'string'),
                                    'price'      => array('type' => 'number'),
                               ),
                           ),
                       ),
                   ),
               ),
                'recurring'                => array(
                    'type'        => 'array',
                    'items'       => array('type' => 'object'),
                    'description' => 'Additional appointments created for recurring bookings',
                ),
                'timeSlotUnavailable'      => array('type' => 'boolean', 'description' => 'True when the slot was already taken'),
                'customerAlreadyBooked'    => array('type' => 'boolean', 'description' => 'True when the customer has an existing booking for this slot'),
           ),
        ),
        'execute_callback' => function ($input) {
            return AmeliaAbilitiesRegistrar::invokeApplication(
                AddAppointmentController::class,
                array(
                    'serviceId'          => (int) $input['serviceId'],
                    'providerId'         => (int) $input['providerId'],
                    'bookingStart'       => sanitize_text_field($input['bookingStart']),
                    'notifyParticipants' => 1,
                    'internalNotes'      => !empty($input['internalNotes']) ? sanitize_textarea_field($input['internalNotes']) : '',
                    'locationId'         => null,
                    'recurring'          => array(),
                    'bookings'           => array(
                        array(
                            'customerId' => (int) $input['customerId'],
                            'persons'    => 1,
                            'status'     => 'approved',
                       ),
                   ),
                )
            );
        },
        'permission_callback' => function () {
            return current_user_can('amelia_write_appointments');
        },
        'meta' => array(
            'annotations' => array(
                'readonly'    => false,
                'destructive' => true,
                'idempotent'  => false,
           ),
            'show_in_rest' => true,
            'mcp'          => array('public' => true),
        ),
        ));
    }

    protected static function registerCreateEventAbility(): void
    {
        wp_register_ability('amelia/create-event', array(
        'label'       => __('Create Event', 'wpamelia'),
        'description' => __(
            'Use when the user wants to create, add, or set up a new event, class, or group session.' .
            ' ALWAYS confirm name, start/end date-time, price, and capacity with the user before calling this ability.',
            'wpamelia'
        ),
        'category'    => 'amelia-write',
        'input_schema' => array(
            'type'       => 'object',
            'properties' => array(
                'name'           => array(
                    'type'        => 'string',
                    'description' => 'The name of the event (required). Confirm with the user before submitting.',
               ),
                'periodStart'    => array(
                    'type'        => 'string',
                    'description' => 'Event start date/time in YYYY-MM-DD HH:mm format (required). Confirm with the user before submitting.',
               ),
                'periodEnd'      => array(
                    'type'        => 'string',
                    'description' => 'Event end date/time in YYYY-MM-DD HH:mm format (required). Confirm with the user before submitting.',
               ),
                'price'          => array('type' => 'number', 'description' => 'Price of the event. Default: 0'),
                'maxCapacity'    => array('type' => 'integer', 'minimum' => 1, 'description' => 'Maximum capacity. Default: 10'),
                'color'          => array('type' => 'string', 'description' => 'Event color as a hex value (e.g. "#1a84ee"). Default: #1a84ee'),
                'show'           => array('type' => 'boolean', 'description' => 'Whether to show the event on the website. Default: true'),
                'depositPayment' => array(
                    'type'        => 'string',
                    'enum'        => array('disabled', 'fixed', 'percentage'),
                    'description' => 'Deposit payment type. Default: disabled',
               ),
                'deposit'        => array(
                    'type'        => 'number',
                    'description' => 'Deposit amount (used when depositPayment is fixed or percentage). Default: 0',
               ),
           ),
            'required'             => array('name', 'periodStart', 'periodEnd'),
            'additionalProperties' => false,
        ),
        'output_schema' => array(
            'type'       => 'object',
            'properties' => array(
                'events' => array(
                    'type'        => 'array',
                    'description' => 'The created event(s). Multiple items when a recurring series is created.',
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'id'          => array('type' => 'integer', 'description' => 'Event ID'),
                            'name'        => array('type' => 'string'),
                            'price'       => array('type' => 'number'),
                            'maxCapacity' => array('type' => 'integer'),
                            'color'       => array('type' => 'string'),
                            'show'        => array('type' => 'boolean'),
                            'status'      => array('type' => 'string'),
                            'periods'     => array(
                                'type'  => 'array',
                                'items' => array(
                                    'type'       => 'object',
                                    'properties' => array(
                                        'periodStart' => array('type' => 'string', 'description' => 'YYYY-MM-DD HH:mm:ss'),
                                        'periodEnd'   => array('type' => 'string', 'description' => 'YYYY-MM-DD HH:mm:ss'),
                                   ),
                               ),
                           ),
                       ),
                   ),
               ),
           ),
        ),
        'execute_callback' => function ($input) {
            return AmeliaAbilitiesRegistrar::invokeApplication(
                AddEventController::class,
                array(
                    'name'           => sanitize_text_field($input['name']),
                    'price'          => isset($input['price']) ? (float) $input['price'] : 0,
                    'maxCapacity'    => isset($input['maxCapacity']) ? (int) $input['maxCapacity'] : 10,
                    'color'          => !empty($input['color']) ? sanitize_text_field($input['color']) : '#1a84ee',
                    'show'           => isset($input['show']) ? (bool) $input['show'] : true,
                    'depositPayment' => !empty($input['depositPayment']) ? sanitize_text_field($input['depositPayment']) : 'disabled',
                    'deposit'        => isset($input['deposit']) ? (float) $input['deposit'] : 0,
                    'periods'        => array(
                        array(
                            'periodStart' => sanitize_text_field($input['periodStart']),
                            'periodEnd'   => sanitize_text_field($input['periodEnd']),
                       ),
                   ),
                )
            );
        },
        'permission_callback' => function () {
            return current_user_can('amelia_write_events');
        },
        'meta' => array(
            'annotations' => array(
                'readonly'    => false,
                'destructive' => true,
                'idempotent'  => false,
           ),
            'show_in_rest' => true,
            'mcp'          => array('public' => true),
        ),
        ));
    }

    protected static function registerBookEventAbility(): void
    {
        wp_register_ability('amelia/book-event', array(
        'label'       => __('Book Event', 'wpamelia'),
        'description' => __(
            'Use when the user wants to register or enroll a customer in an event, class, or group session.' .
            ' ALWAYS confirm the event and customer with the user before calling.' .
            ' Use amelia/list-events for eventId and amelia/list-customers or amelia/add-customer for customerId.',
            'wpamelia'
        ),
        'category'    => 'amelia-write',
        'input_schema' => array(
            'type'       => 'object',
            'properties' => array(
                'eventId'    => array(
                    'type'        => 'integer',
                    'description' => 'Event ID. Use amelia/list-events to find IDs. Confirm with the user before submitting.',
               ),
                'customerId' => array(
                    'type'        => 'integer',
                    'description' => 'Customer ID. Use amelia/list-customers to find IDs. Confirm with the user before submitting.',
               ),
                'persons'    => array('type' => 'integer', 'minimum' => 1, 'description' => 'Number of persons attending. Default: 1'),
           ),
            'required'             => array('eventId', 'customerId'),
            'additionalProperties' => false,
        ),
        'output_schema' => array(
            'type'       => 'object',
            'properties' => array(
                'type'    => array('type' => 'string', 'description' => 'Always "event" for this ability'),
                'event'   => array(
                    'type'        => 'object',
                    'description' => 'The event that was booked',
                    'properties'  => array(
                        'id'          => array('type' => 'integer'),
                        'name'        => array('type' => 'string'),
                        'maxCapacity' => array('type' => 'integer'),
                        'bookedSpots' => array('type' => 'integer'),
                   ),
               ),
                'booking' => array(
                    'type'        => 'object',
                    'description' => 'The CustomerBooking record created for this customer',
                    'properties'  => array(
                        'id'         => array('type' => 'integer', 'description' => 'CustomerBooking ID'),
                        'customerId' => array('type' => 'integer'),
                        'persons'    => array('type' => 'integer'),
                        'status'     => array('type' => 'string'),
                        'price'      => array('type' => 'number'),
                   ),
               ),
                'customer' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'id'        => array('type' => 'integer'),
                        'firstName' => array('type' => 'string'),
                        'lastName'  => array('type' => 'string'),
                        'email'     => array('type' => array('string', 'null')),
                   ),
               ),
                'paymentId'          => array('type' => array('integer', 'null')),
                'customerCabinetUrl' => array('type' => 'string', 'description' => 'Customer self-service URL'),
           ),
        ),
        'execute_callback' => function ($input) {
            return AmeliaAbilitiesRegistrar::invokeApplication(
                AddBookingController::class,
                array(
                    'type'                        => 'event',
                    'eventId'                     => (int) $input['eventId'],
                    'notifyParticipants'          => 1,
                    'runInstantPostBookingActions' => true,
                    'isBackendOrCabinet'          => true,
                    'payment'                     => array('gateway' => 'onSite'),
                    'bookings'                    => array(
                        array(
                            'eventId'    => (int) $input['eventId'],
                            'customerId' => (int) $input['customerId'],
                            'persons'    => isset($input['persons']) ? (int) $input['persons'] : 1,
                            'status'     => 'approved',
                            'customer'   => array('id' => (int) $input['customerId']),
                       ),
                   ),
                )
            );
        },
        'permission_callback' => function () {
            return current_user_can('amelia_write_events');
        },
        'meta' => array(
            'annotations' => array(
                'readonly'    => false,
                'destructive' => true,
                'idempotent'  => false,
           ),
            'show_in_rest' => true,
            'mcp'          => array('public' => true),
        ),
        ));
    }

    protected static function registerCancelBookingAbility(): void
    {
        wp_register_ability('amelia/cancel-booking', array(
        'label'       => __('Cancel Booking', 'wpamelia'),
        'description' => __(
            'Use when the user wants to cancel, remove, or undo a booking.' .
            ' ALWAYS ask the user explicitly to confirm before calling:' .
            ' "Are you sure you want to cancel booking ID {bookingId}? This cannot be undone."',
            'wpamelia'
        ),
        'category'    => 'amelia-write',
        'input_schema' => array(
            'type'       => 'object',
            'properties' => array(
                'bookingId' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the customer booking to cancel. Confirm this ID with the user before submitting.',
               ),
                'type' => array(
                    'type'        => 'string',
                    'enum'        => array('appointment', 'event'),
                    'description' => 'Type of the booking to cancel: "appointment" or "event". Default: "appointment".',
               ),
           ),
            'required'             => array('bookingId'),
            'additionalProperties' => false,
        ),
        'output_schema' => array(
            'type'       => 'object',
            'properties' => array(
                'type'    => array('type' => 'string', 'description' => '"appointment" or "event"'),
                'status'  => array('type' => 'string', 'description' => 'The new booking status, e.g. "canceled"'),
                'message' => array('type' => 'string', 'description' => 'Human-readable confirmation message'),
                'appointment' => array(
                    'type'        => 'object',
                    'description' => 'Updated appointment (present when type is "appointment")',
                    'properties'  => array(
                        'id'           => array('type' => 'integer'),
                        'serviceId'    => array('type' => 'integer'),
                        'providerId'   => array('type' => 'integer'),
                        'bookingStart' => array('type' => 'string'),
                        'bookingEnd'   => array('type' => 'string'),
                        'status'       => array('type' => 'string'),
                   ),
               ),
                'event' => array(
                    'type'        => 'object',
                    'description' => 'Updated event (present when type is "event")',
                    'properties'  => array(
                        'id'     => array('type' => 'integer'),
                        'name'   => array('type' => array('string', 'null')),
                        'status' => array(
                            'type'        => array('string', 'null'),
                            'description' => 'Raw event status; see newEventStatus for computed display status',
                        ),
                   ),
               ),
                'newEventStatus' => array(
                    'type'        => 'string',
                    'description' => 'Computed event display status after cancellation (event bookings only)',
                ),
                'bookingStatusChanged' => array('type' => 'boolean'),
                'booking' => array(
                    'type'        => 'object',
                    'description' => 'The canceled CustomerBooking record',
                    'properties'  => array(
                        'id'         => array('type' => 'integer'),
                        'customerId' => array('type' => 'integer'),
                        'status'     => array('type' => 'string'),
                   ),
               ),
                'appointmentStatusChanged'   => array('type' => 'boolean'),
                'updateBookingUnavailable'   => array(
                    'type'        => 'boolean',
                    'description' => 'True when cancellation is not allowed (outside window or capacity constraint)',
                ),
           ),
        ),
        'execute_callback' => function ($input) {
            $bookingId = (int) $input['bookingId'];
            $type      = !empty($input['type']) ? sanitize_text_field($input['type']) : 'appointment';

            if ($type === Entities::EVENT) {
                return AmeliaAbilitiesRegistrar::invokeApplication(
                    UpdateEventBookingController::class,
                    array(
                        'bookings' => array(
                            array(
                                'id'     => $bookingId,
                                'status' => 'canceled',
                            ),
                        ),
                    ),
                    array('id' => $bookingId)
                );
            }

            return AmeliaAbilitiesRegistrar::invokeApplication(
                UpdateBookingStatusController::class,
                array(
                    'status' => 'canceled',
                    'type'   => $type,
                ),
                array('id' => $bookingId)
            );
        },
        'permission_callback' => function () {
            return current_user_can('amelia_write_appointments') || current_user_can('amelia_write_events');
        },
        'meta' => array(
            'annotations' => array(
                'readonly'    => false,
                'destructive' => true,
                'idempotent'  => false,
           ),
            'show_in_rest' => true,
            'mcp'          => array('public' => true),
        ),
        ));
    }

    /**
     * Default page/limit query params for MCP list abilities.
     *
     * @param array $input
     * @return array<string, int>
     */
    protected static function getMcpListPaginationParams(array $input): array
    {
        return array(
            'page'  => !empty($input['page']) ? max(1, (int) $input['page']) : 1,
            'limit' => !empty($input['limit'])
                ? min(self::MCP_LIST_MAX_LIMIT, max(1, (int) $input['limit']))
                : self::MCP_LIST_DEFAULT_LIMIT,
        );
    }

    /**
     * Default date range for MCP list abilities: today through N days ahead (site timezone).
     *
     * @return array{0: string, 1: string}
     */
    protected static function getDefaultMcpDateRange(): array
    {
        $start = current_time('Y-m-d');
        $end   = wp_date(
            'Y-m-d',
            strtotime('+' . self::MCP_DEFAULT_DATE_RANGE_DAYS . ' days', current_time('timestamp'))
        );

        return array($start, $end);
    }

    protected static function canListCustomers(): bool
    {
        $container = static::getContainer();
        $currentUser = $container->get('logged.in.user');

        return $container->getPermissionsService()->currentUserCanRead(Entities::CUSTOMERS)
            || ($currentUser && $currentUser->getType() === AbstractUser::USER_ROLE_PROVIDER);
    }

    protected static function canListServices(): bool
    {
        $container = static::getContainer();
        $currentUser = $container->get('logged.in.user');

        return $container->getPermissionsService()->currentUserCanRead(Entities::SERVICES)
            || ($currentUser && $currentUser->getType() === AbstractUser::USER_ROLE_PROVIDER);
    }
}
