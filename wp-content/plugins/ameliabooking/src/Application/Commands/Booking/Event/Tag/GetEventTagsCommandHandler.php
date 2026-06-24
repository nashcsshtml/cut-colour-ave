<?php

namespace AmeliaBooking\Application\Commands\Booking\Event\Tag;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Booking\Event\EventTagsRepository;

/**
 * Class GetEventTagsCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Booking\Event\Tag
 */
class GetEventTagsCommandHandler extends CommandHandler
{
    /**
     * @param GetEventTagsCommand $command
     *
     * @return CommandResult
     * @throws AccessDeniedException
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     */
    public function handle(GetEventTagsCommand $command)
    {
        if (!$command->getPermissionService()->currentUserCanRead(Entities::EVENTS)) {
            throw new AccessDeniedException('You are not allowed to read event tags.');
        }

        $result = new CommandResult();

        /** @var EventTagsRepository $eventTagsRepository */
        $eventTagsRepository = $this->container->get('domain.booking.event.tag.repository');

        /** @var Collection $tags */
        $tags = $eventTagsRepository->getAllDistinctByCriteria([]);

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully retrieved event tags.');
        $result->setData(
            [
                Entities::TAGS => $tags->toArray(),
            ]
        );

        return $result;
    }
}
