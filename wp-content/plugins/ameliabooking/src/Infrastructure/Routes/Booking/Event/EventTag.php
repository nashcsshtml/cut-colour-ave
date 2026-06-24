<?php

namespace AmeliaBooking\Infrastructure\Routes\Booking\Event;

use AmeliaBooking\Application\Controller\Booking\Event\Tag\GetEventTagsController;
use AmeliaBooking\Application\Controller\Booking\Event\Tag\SaveEventTagsController;
use Slim\App;

/**
 * Class EventTag
 *
 * @package AmeliaBooking\Infrastructure\Routes\Booking\Event
 */
class EventTag
{
    /**
     * @param App $app
     */
    public static function routes(App $app)
    {
        $app->get('/event-tags', GetEventTagsController::class);

        $app->post('/event-tags/save', SaveEventTagsController::class);
    }
}
