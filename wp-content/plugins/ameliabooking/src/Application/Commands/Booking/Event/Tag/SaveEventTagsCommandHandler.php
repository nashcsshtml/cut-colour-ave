<?php

namespace AmeliaBooking\Application\Commands\Booking\Event\Tag;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\Stash\StashApplicationService;
use AmeliaBooking\Application\Services\User\UserApplicationService;
use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Common\Exceptions\AuthorizationException;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Booking\Event\EventTag;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Factory\Booking\Event\EventTagFactory;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Domain\ValueObjects\String\Name;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Booking\Event\EventTagsRepository;

/**
 * Class SaveEventTagsCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Booking\Event\Tag
 */
class SaveEventTagsCommandHandler extends CommandHandler
{
    /** @var array */
    public $mandatoryFields = ['tags'];

    /**
     * @param SaveEventTagsCommand $command
     *
     * @return CommandResult
     * @throws AccessDeniedException
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     */
    public function handle(SaveEventTagsCommand $command)
    {
        $result = new CommandResult();

        if (!$this->getContainer()->getPermissionsService()->currentUserCanWrite(Entities::SETTINGS)) {
            /** @var AbstractUser $loggedInUser */
            $loggedInUser = $this->container->get('logged.in.user');

            if (
                !$loggedInUser || !(
                    $loggedInUser->getType() === AbstractUser::USER_ROLE_ADMIN ||
                    $loggedInUser->getType() === AbstractUser::USER_ROLE_MANAGER
                )
            ) {
                throw new AccessDeniedException('You are not allowed to write settings.');
            }
        }

        $this->checkMandatoryFields($command);

        /** @var EventTagsRepository $eventTagsRepository */
        $eventTagsRepository = $this->container->get('domain.booking.event.tag.repository');

        $incomingTags = (array)$command->getField('tags');

        /** @var Collection $currentCollection */
        $currentCollection = $eventTagsRepository->getAllDistinctByCriteria([]);
        $currentTags       = $currentCollection->toArray();

        $currentIds    = array_column($currentTags, 'id');
        $incomingIds   = array_filter(array_column($incomingTags, 'id'));
        $currentTagMap = array_column($currentTags, 'name', 'id');

        // Delete tags that are no longer in the incoming list (cascade to all same-named tags)
        /** @var AbstractUser $loggedInUser */
        $loggedInUser = $this->container->get('logged.in.user');

        foreach ($currentIds as $currentId) {
            if (!in_array((int)$currentId, array_map('intval', $incomingIds), true)) {
                $oldName = $currentTagMap[$currentId] ?? null;
                if ($oldName !== null) {
                    $eventTagsRepository->deleteByName($oldName);
                } else {
                    $eventTagsRepository->delete((int)$currentId);
                }
            }
        }

        // Insert or update
        foreach ($incomingTags as $tag) {
            $name = isset($tag['name']) ? trim((string)$tag['name']) : '';

            if ($name === '') {
                continue;
            }

            if (empty($tag['id'])) {
                /** @var EventTag $entity */
                $entity = EventTagFactory::create(['name' => $name]);
                $eventTagsRepository->add($entity);
            } else {
                $oldName = $currentTagMap[(int)$tag['id']] ?? null;
                if ($oldName !== null && $oldName !== $name) {
                    // Rename all tags with the old name (cascade to event-specific tags)
                    $eventTagsRepository->updateNameByName($oldName, $name);
                } elseif ($oldName === null) {
                    /** @var EventTag $entity */
                    $entity = EventTagFactory::create(['name' => $name]);
                    $eventTagsRepository->update((int)$tag['id'], $entity);
                }
                // If name is unchanged ($oldName === $name), no update needed
            }
        }

        /** @var Collection $updatedCollection */
        $updatedCollection = $eventTagsRepository->getAllDistinctByCriteria([]);

        /** @var StashApplicationService $stashApplicationService */
        $stashApplicationService = $this->container->get('application.stash.service');
        $stashApplicationService->setStash();

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Event tags successfully saved.');
        $result->setData(
            [
                Entities::TAGS => $updatedCollection->toArray(),
            ]
        );

        return $result;
    }
}
