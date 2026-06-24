<?php

namespace AmeliaBooking\Application\Services\Calendar;

use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\User\Provider;
use AmeliaBooking\Domain\ValueObjects\String\Status;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\User\ProviderRepository;

class CalendarProviderService
{
    /** @var Container */
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Returns providers visible in calendar context with the same criteria used by calendar slots.
     *
     * @param array $queryParams
     * @param bool $includeDatesAsRange
     *
     * @return Provider[]
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     */
    public function getVisibleProviders(array $queryParams, bool $includeDatesAsRange = false): array
    {
        $locationRepository = $this->container->get('domain.locations.repository');
        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        $queryParams['locations'] = array_map(
            static fn($location) => $location['id'],
            $locationRepository->getFiltered(
                ['status' => !empty($queryParams['providers']) ? null : Status::VISIBLE],
                0
            )->toArray()
        );

        if ($includeDatesAsRange) {
            $queryParams['dates'] = [$queryParams['calendarStartDate'], $queryParams['calendarEndDate']];
        }

        $criteria = ['providerStatus' => !empty($queryParams['providers']) ? null : Status::VISIBLE];
        foreach ($queryParams as $key => $value) {
            if ($key !== 'providerStatus') {
                $criteria[$key] = $value;
            }
        }

        return $providerRepository->getWithSchedule($criteria)->getItems();
    }

    /**
     * @param array $queryParams
     *
     * @return int[]
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     */
    public function getVisibleProviderIds(array $queryParams): array
    {
        $providerIds = [];

        foreach ($this->getVisibleProviders($queryParams, true) as $provider) {
            $providerIds[] = $provider->getId()->getValue();
        }

        return $providerIds;
    }
}
