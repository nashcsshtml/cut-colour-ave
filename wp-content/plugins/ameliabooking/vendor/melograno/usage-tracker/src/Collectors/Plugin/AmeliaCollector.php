<?php

declare(strict_types=1);

namespace AmeliaVendor\Melograno\UsageTracker\Collectors\Plugin;

use AmeliaVendor\Melograno\UsageTracker\Collectors\BaseCollector;
use AmeliaVendor\Melograno\UsageTracker\Collectors\ConsentNoticeCollectorInterface;

class AmeliaCollector extends BaseCollector implements ConsentNoticeCollectorInterface
{
    /** @var array<string, string> */
    private const LICENSE_TIER_MAP = [
        'lite' => 'lite',
        'starter' => 'starter',
        'basic' => 'standard',
        'pro' => 'pro',
        'developer' => 'elite',
    ];

    public function getPluginSlug(): string
    {
        return 'ameliabooking';
    }

    public function getConsentOptionName(): string
    {
        return 'amelia_usage_tracking_consent';
    }

    public function getNoticeOptionName(): string
    {
        return 'amelia_show_usage_tracking_notice';
    }

    public function getOptInMigrationVersion(): ?string
    {
        return '2.4.2';
    }

    public function shouldEnableConsentByDefault(): bool
    {
        return \AmeliaBooking\Infrastructure\Licence\Licence::isPremium();
    }

    public function shouldShowAdminNotice(): bool
    {
        return true;
    }

    public function shouldMigrateConsentOnUpgrade(): bool
    {
        $licence = \AmeliaBooking\Infrastructure\Licence\Licence::getLicence();

        return is_string($licence)
            && strcasecmp($licence, \AmeliaBooking\Infrastructure\Licence\LicenceConstants::LITE) === 0;
    }

    public function getConsentNoticeAjaxPrefix(): string
    {
        return 'amelia';
    }

    public function renderConsentAdminNotice(): void
    {
        if (!$this->isOnAmeliaAdminPage() || $this->isOnWelcomePage()) {
            return;
        }

        $resourcesUrl = AMELIA_URL . 'vendor/melograno/usage-tracker/resources';

        wp_enqueue_style(
            'amelia-usage-tracking-notice-css',
            $resourcesUrl . '/usage-tracking-notice.css',
            [],
            AMELIA_VERSION
        );

        $usageTrackingCollector = $this;
        $usageTrackingResourcesUrl = $resourcesUrl;
        include dirname(dirname(dirname(__DIR__))) . '/resources/usage-tracking-notice.php';
    }

    /**
     * Maps Amelia activation.licence values to telemetry license slugs.
     */
    public static function normalizeLicenseTier(?string $ameliaLicence): ?string
    {
        if ($ameliaLicence === null) {
            return null;
        }

        $trimmed = trim($ameliaLicence);
        if ($trimmed === '') {
            return 'elite';
        }

        $key = strtolower($trimmed);

        return self::LICENSE_TIER_MAP[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function pluginPayload(): array
    {
        $container = require AMELIA_PATH . '/src/Infrastructure/ContainerConfig/container.php';
        $customerBookingRepository = $container->get('domain.booking.customerBooking.repository');
        $appointmentRepository = $container->get('domain.booking.appointment.repository');

        $data = [
            'plugin_version' => AMELIA_VERSION,
        ];

        $rawLicence = $this->resolveAmeliaLicence();
        $licenseTier = self::normalizeLicenseTier($rawLicence);
        if ($licenseTier !== null) {
            $data['license'] = $licenseTier;
        }

        $minCreated = $customerBookingRepository->getEarliestCreatedAt();
        if ($minCreated !== null && $minCreated !== '') {
            $timestamp = strtotime($minCreated);
            if ($timestamp !== false) {
                $data['first_booking_created_at'] = gmdate('c', $timestamp);
            }
        }

        $data['appointment_bookings_count'] = $customerBookingRepository->getAppointmentBookingsCount();
        $data['appointments_count'] = $appointmentRepository->getAppointmentsCount();
        $data['event_bookings_count'] = $customerBookingRepository->getEventBookingsCount();
        $data['approved_appointment_bookings_count'] = $customerBookingRepository->getApprovedAppointmentBookingsCount();
        $data['approved_appointments_count'] = $appointmentRepository->getApprovedAppointmentsCount();

        return array_filter($data, static function ($value) {
            return $value !== null;
        });
    }

    protected function resolveAmeliaLicence(): ?string
    {
        $licence = \AmeliaBooking\Infrastructure\Licence\Licence::getLicence();

        return is_string($licence) ? $licence : null;
    }

    private function isOnAmeliaAdminPage(): bool
    {
        $page = $this->currentAdminPageSlug();

        if ($page !== '' && strpos($page, 'wpamelia') === 0) {
            return true;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        return $screen !== null && strpos($screen->id, 'wpamelia') !== false;
    }

    private function isOnWelcomePage(): bool
    {
        return $this->currentAdminPageSlug() === 'wpamelia-welcome';
    }

    private function currentAdminPageSlug(): string
    {
        return isset($_GET['page'])
            ? sanitize_text_field(wp_unslash((string) $_GET['page']))
            : '';
    }
}
