<?php

namespace AmeliaBooking\Infrastructure\Services\QrCode;

use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Common\Exceptions\NotFoundException;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;

/**
 * Class AbstractQrCodeInfrastructureService
 *
 * @package AmeliaBooking\Infrastructure\Services\QrCode
 */
abstract class AbstractQrCodeInfrastructureService
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws QueryExecutionException
     */
    abstract public function generateQrCode(array $qrData): array;
}
