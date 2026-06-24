<?php

namespace AmeliaBooking\Infrastructure\WP\Translations;

use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\WP\SettingsService\SettingsStorage;

/**
 * @phpcs:disable
 */
class LiteFrontendStrings
{
    private static ?SettingsService $settings = null;

    /**
     * @return array|mixed
     */
    public static function getLabelsFromSettings()
    {
        if (!self::$settings) {
            self::$settings = new SettingsService(new SettingsStorage());
        }

        if (self::$settings->getSetting('labels', 'enabled') === true) {
            $labels = self::$settings->getCategorySettings('labels');
            unset($labels['enabled']);

            return $labels;
        }

        return [];
    }

    /**
     * Return all strings for frontend
     */
    public static function getAllStrings(): array
    {
        return array_merge(
            self::getCommonStrings(),
            self::getBookingStrings(),
            self::getBookableStrings(),
            self::getCatalogStrings(),
            self::getSearchStrings(),
            self::getLabelsFromSettings(),
            self::getEventStrings(),
            self::getCabinetStrings()
        );
    }

    /**
     * Returns the array for the bookable strings
     */
    public static function getBookableStrings(): array
    {
        return [];
    }

    /**
     * Returns the array of the common frontend strings
     */
    public static function getCommonStrings(): array
    {
        return [
            'add_to_calendar'              => __('Add to Calendar', 'wpamelia'),
            'amount'                       => __('Amount', 'wpamelia'),
            'and'                          => __('and', 'wpamelia'),
            'all_services'                 => __('All Services', 'wpamelia'),
            'all_locations'                => __('All Locations', 'wpamelia'),
            'no_services_employees'        => __('It seems like there are no employees or services created, or no  employees are assigned to the service, at this moment.'),
            'add_services_employees'       => __('If you are the admin of this page, see how to'),
            'add_services_url'             => __('Add services'),
            'add_employees_url'            => __('employees.'),
            'back'                         => __('Back', 'wpamelia'),
            'base_price_colon'             => __('Base Price:', 'wpamelia'),
            'booking_canceled'             => __('Your booking has been canceled.', 'wpamelia'),
            'booking_completed_approved'   => __('Thank you! Your booking is completed.', 'wpamelia'),
            'bookings_limit_reached'       => __('Maximum bookings reached', 'wpamelia'),
            'cancel'                       => __('Cancel', 'wpamelia'),
            'canceled'                     => __('Canceled', 'wpamelia'),
            'capacity_colon'               => __('Capacity:', 'wpamelia'),
            'closed'                       => __('Closed', 'wpamelia'),
            'content_mode_tooltip'         => __('Don\'t use Text mode option if you already have HTML code in the description, since once this option is enabled the existing HTML tags could be lost.', 'wpamelia'),
            'created_on'                   => __('Created on', 'wpamelia'),
            'enable_google_meet'           => __('Enable Google Meet', 'wpamelia'),
            'enable_microsoft_teams'       => __('Enable Microsoft Teams', 'wpamelia'),
            'full'                         => __('Full', 'wpamelia'),
            'upcoming'                     => __('Upcoming', 'wpamelia'),
            'confirm'                      => __('Confirm', 'wpamelia'),
            'congratulations'              => __('Congratulations', 'wpamelia'),
            'customer_already_booked_app'  => __('You have already booked this appointment', 'wpamelia'),
            'customer_already_booked_ev'   => __('You have already booked this event', 'wpamelia'),
            'date_colon'                   => __('Date:', 'wpamelia'),
            'duration_colon'               => __('Duration:', 'wpamelia'),
            'email_colon'                  => __('Email:', 'wpamelia'),
            'email_exist_error'            => __('Email already exists with different name. Please check your name.', 'wpamelia'),
            'email_required'               => __('Email field is required', 'wpamelia'),
            'employee_limit_reached'       => __('Employee daily appointment limit has been reached. Please choose another date or employee.', 'wpamelia'),
            'enter_email_warning'          => __('Please enter email', 'wpamelia'),
            'enter_first_name_warning'     => __('Please enter first name', 'wpamelia'),
            'enter_last_name_warning'      => __('Please enter last name', 'wpamelia'),
            'enter_phone_warning'          => __('Please enter phone number', 'wpamelia'),
            'enter_valid_email_warning'    => __('Please enter a valid email address', 'wpamelia'),
            'enter_valid_phone_warning'    => __('Please enter a valid phone number.', 'wpamelia'), // Used in Redesign | Updated
            'event_info'                   => __('Event Info', 'wpamelia'),
            'finish_appointment'           => __('Finish', 'wpamelia'),
            'first_name_colon'             => __('First Name:', 'wpamelia'),
            'h'                            => __('h', 'wpamelia'),
            'last_name_colon'              => __('Last Name:', 'wpamelia'),
            'licence_start_description'    => __('Available from Starter license', 'wpamelia'),
            'licence_basic_description'    => __('Available from Standard license', 'wpamelia'),
            'licence_pro_description'      => __('Available from Pro license', 'wpamelia'),
            'licence_dev_description'      => __('Available in Elite licence', 'wpamelia'),
            'licence_button_text'          => __('Upgrade', 'wpamelia'),
            'min'                          => __('min', 'wpamelia'),
            'no_results_found'             => __('No results found...', 'wpamelia'),
            'on_site'                      => __('On-site', 'wpamelia'),
            'payment_btn_on_site'          => __('On-Site', 'wpamelia'),
            'oops'                         => __('Oops...'),
            'payment_btn_square'           => __('Square', 'wpamelia'),
            'open'                         => __('Open', 'wpamelia'),
            'opens_in_new_tab'             => __('opens in new tab', 'wpamelia'),
            'on_line'                      => __('Online', 'wpamelia'),
            'pay_pal'                      => __('PayPal', 'wpamelia'),
            'previous_step'                => __('Previous step', 'wpamelia'),
            'loading'                      => __('Loading', 'wpamelia'),
            'loading_header'               => __('Loading header', 'wpamelia'),
            'phone_colon'                  => __('Phone:', 'wpamelia'),
            'phone_exist_error'            => __('Phone already exists with different name. Please check your name.', 'wpamelia'),
            'price_colon'                  => __('Price:', 'wpamelia'),
            'service'                      => __('service', 'wpamelia'),
            'show'                         => __('Show', 'wpamelia'),
            'select_calendar'              => __('Select Calendar', 'wpamelia'),
            'services_lower'               => __('services', 'wpamelia'),
            'square'                       => __('Square', 'wpamelia'),
            'time_colon'                   => __('Local Time:', 'wpamelia'),
            'time_slot_unavailable'        => __('Time slot is unavailable', 'wpamelia'),
            'total_cost_colon'             => __('Total Cost:', 'wpamelia'),
            'total_number_of_persons'      => __('Total Number of People:', 'wpamelia'),
            'view'                         => __('View', 'wpamelia'),
            'select'                       => __('Select', 'wpamelia'),
            'free'                         => __('Free', 'wpamelia'),
            'error_use_diff_email_or_phone' => __('Please use a different email or phone number to complete the booking.', 'wpamelia'),
            'image'                        => __('Image', 'wpamelia'),
            'image_navigation'             => __('Image navigation', 'wpamelia'),
            'next_image'                   => __('Next image', 'wpamelia'),
            'previous_image'               => __('Previous image', 'wpamelia'),
            'of'                           => __('of', 'wpamelia'),
            'people_waiting'               => __('people waiting', 'wpamelia'),
            'person_waiting'               => __('person waiting', 'wpamelia'),
            'minimum'                      => __('Minimum', 'wpamelia'),
            'maximum'                      => __('Maximum', 'wpamelia'),
        ];
    }

    /**
     * Returns the array of the frontend strings for the search shortcode
     */
    public static function getSearchStrings(): array
    {
        return [];
    }

    /**
     * Returns the array of the frontend strings for the booking shortcode
     */
    public static function getBookingStrings(): array
    {
        return [
            'continue'                     => __('Continue', 'wpamelia'),
            'email_address_colon'          => __('Email Address', 'wpamelia'),
            'get_in_touch'                 => __('Get in Touch', 'wpamelia'),
            'ivyforms_validation_failed'   => __('IvyForms validation failed.', 'wpamelia'),
            'collapse_menu'                => __('Collapse menu', 'wpamelia'),
            'payment_onsite_sentence'      => __('The payment will be done on-site.', 'wpamelia'),
            'payment_or_pay_with_card'     => __('Or pay with card', 'wpamelia'),
            'phone_number_colon'           => __('Phone Number', 'wpamelia'),
            'pick_date_and_time_colon'     => __('Pick date & time:', 'wpamelia'),
            'please_select'                => __('Please select', 'wpamelia'),
            'summary'                      => __('Summary', 'wpamelia'),
            'total_amount_colon'           => __('Total Amount:', 'wpamelia'),
            'your_name_colon'              => __('Your Name', 'wpamelia'),

            'service_selection'            => __('Service Selection', 'wpamelia'),
            'employee_selection'           => __('Employee Selection', 'wpamelia'),
            'location_selection'           => __('Location Selection', 'wpamelia'),
            'service_colon'                => __('Service', 'wpamelia'),
            'please_select_service'        => __('Please select service', 'wpamelia'),
            'dropdown_category_heading'    => __('Category', 'wpamelia'),
            'dropdown_items_heading'       => __('Service', 'wpamelia'),
            'date_time'                    => __('Date & Time', 'wpamelia'),
            'info_step'                    => __('Your Information', 'wpamelia'),
            'enter_first_name'             => __('Enter first name', 'wpamelia'),
            'enter_last_name'              => __('Enter last name', 'wpamelia'),
            'enter_email'                  => __('Enter email', 'wpamelia'),
            'enter_phone'                  => __('Enter phone', 'wpamelia'),
            'payment_step'                 => __('Payments', 'wpamelia'),
            'summary_services'             => __('Services', 'wpamelia'),
            'summary_person'               => __('person', 'wpamelia'),
            'summary_persons'              => __('people', 'wpamelia'),
            'summary_event'                => __('Event', 'wpamelia'),
            'appointment_id'               => __('Appointment ID', 'wpamelia'),
            'event_id'                     => __('Event ID', 'wpamelia'),
            'congrats_payment'             => __('Payment', 'wpamelia'),
            'congrats_date'                => __('Date', 'wpamelia'),
            'congrats_time'                => __('Local Time', 'wpamelia'),
            'congrats_service'             => __('Service', 'wpamelia'),
            'congrats_employee'            => __('Employee', 'wpamelia'),
            'show_more'                    => __('Show more', 'wpamelia'),
            'show_less'                    => __('Show less', 'wpamelia'),
            'disable_popup_blocker'        => __('Popup Blocker is enabled! To add your appointment to your calendar, please allow popups and add this site to your exception list.', 'wpamelia'),
            'full_amount_consent'          => __('I want to pay full amount', 'wpamelia'),
            'learn_more'                   => __('Learn More', 'wpamelia'),
            'view_in_package'              => __('View in Package', 'wpamelia'),
            'service_information'          => __('Service information', 'wpamelia'),
            'payment_wc_mollie_sentence'   => __('You will be redirected to the payment checkout.', 'wpamelia'),
            'payment_button'               => __('payment button', 'wpamelia'),
            'total_tax_colon'              => __('VAT', 'wpamelia'),
            'incl_tax'                     => __('Incl. VAT', 'wpamelia'),
            'recurring_unavailable_slots'  => __('Unavailable Time Slots', 'wpamelia'),
            'recurring_chose_date'         => __('Choose Date and Time', 'wpamelia'),
            'recurring_delete'             => __('Delete', 'wpamelia'),
            'recurring_slots_selected'     => __('All slots are selected', 'wpamelia'),
        ];
    }

    /**
     * Returns the array of the frontend strings for the event shortcode
     */
    public static function getEventStrings(): array
    {
        return [
            'event_book_event'          => __('Book event', 'wpamelia'),
            'event_book'                => __('Book this event', 'wpamelia'),
            'event_capacity'            => __('Capacity:', 'wpamelia'),
            'event_filters'             => __('Filters', 'wpamelia'),
            'event_start'               => __('Event Starts', 'wpamelia'),
            'event_end'                 => __('Event Ends', 'wpamelia'),
            'event_at'                  => __('at', 'wpamelia'),
            'event_close'               => __('Close', 'wpamelia'),
            'event_congrats'            => __('Congratulations', 'wpamelia'),
            'event_payment'             => __('Payment', 'wpamelia'),
            'event_customer_info'       => __('Your Information', 'wpamelia'),
            'event_about_list'          => __('About Event', 'wpamelia'),
            'events_available'          => __('Events Available', 'wpamelia'),
            'event_available'           => __('Event Available', 'wpamelia'),
            'event_search'              => __('Search for Events', 'wpamelia'),
            'event_slot_left'           => __('slot left', 'wpamelia'),
            'event_slots_left'          => __('slots left', 'wpamelia'),
            'event_learn_more'          => __('Learn more', 'wpamelia'),
            'event_read_more'           => __('Read more', 'wpamelia'),
            'event_timetable'           => __('Timetable', 'wpamelia'),
            'event_type'                => __('Event type', 'wpamelia'),
            'event_page'                => __('Page', 'wpamelia'),
            'gallery'                   => __('Gallery', 'wpamelia'),
            'event_bringing'            => __('How many attendees do you want to book event for?', 'wpamelia'),
            'bringing_anyone'           => __('Bringing anyone with you?', 'wpamelia'),
            'loading_event_information' => __('Loading event information', 'wpamelia'),
            'events'                    => __('Events', 'wpamelia'),
            'events_pagination'         => __('Events pagination', 'wpamelia'),
            'event_booking'             => __('Event booking', 'wpamelia'),
            'event_image_gallery'       => __('Event image gallery', 'wpamelia'),
            'event_tickets_context'     => __('Select the number of tickets that you want to book for each ticket type', 'wpamelia'),
            'event_tickets_left'        => __('tickets left', 'wpamelia'),
            'event_ticket_left'         => __('ticket left', 'wpamelia'),
            'event_show_less'           => __('Show less', 'wpamelia'),
            'event_show_more'           => __('Show more', 'wpamelia'),
            'event_location'            => __('Event Location', 'wpamelia'),
            'no_events'                 => __('No results found...', 'wpamelia'),
            'date_picker_placeholder'   => __('Date Picker', 'wpamelia'),
        ];
    }

    /**
     * Returns the array of the frontend strings for the catalog shortcode
     */
    public static function getCatalogStrings(): array
    {
        return [
            'categories'                         => __('Categories', 'wpamelia'),
            'category_colon'                     => __('Category:', 'wpamelia'),
            'description'                        => __('Description', 'wpamelia'),
            'info'                               => __('Info', 'wpamelia'),
            'view_more'                          => __('View More', 'wpamelia'),
            'view_all'                           => __('View All', 'wpamelia'),
            'filter_input'                       => __('Search', 'wpamelia'),
            'book_now'                           => __('Book Now', 'wpamelia'),
            'about_service'                      => __('About Service', 'wpamelia'),
            'view_all_photos'                    => __('View all photos', 'wpamelia'),
            'back_btn'                           => __('Go Back', 'wpamelia'),
            'heading_service'                    => __('Service', 'wpamelia'),
            'heading_services'                   => __('Services', 'wpamelia'),
            'no_search_data'                     => __('No results', 'wpamelia'),
            'filter_all'                         => __('All', 'wpamelia'),
        ];
    }

    /**
     * Returns the array of the frontend strings for the event shortcode
     */
    public static function getCabinetStrings(): array
    {
        return [
            'available'                              => __('Available', 'wpamelia'),
            'booking_cancel_exception'               => __('Booking can\'t be canceled', 'wpamelia'),
            'generate_payment_links'                 => __('Generate payment links', 'wpamelia'),
            'generate_payment_links_tooltip'         => __('Check this box to generate a payment link.<br> To include it in the notification, add the payment link placeholder.', 'wpamelia'),
            'no_results'                             => __('There are no results...', 'wpamelia'),
            'select_customer'                        => __('Select Customer', 'wpamelia'),
            'select_service'                         => __('Select Service', 'wpamelia'),
            'subtotal'                               => __('Subtotal', 'wpamelia'),
        ];
    }
}
