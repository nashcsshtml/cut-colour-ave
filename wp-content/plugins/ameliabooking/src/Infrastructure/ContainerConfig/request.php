<?php

use AmeliaBooking\Infrastructure\Common\Container;
use Slim\Factory\ServerRequestCreatorFactory;

$entries['request'] = function (Container $c) {
    $creator = ServerRequestCreatorFactory::create();

    $serverRequest = $creator->createServerRequestFromGlobals();

    $curUri = $serverRequest->getUri();

    // fix callback url for Razorpay payment through link since Razorpay encodes callback urls
    $queryWithPlaceholderFixes = str_replace(
        '__payments__callback',
        '/payments/callback',
        $curUri->getQuery()
    );

    // fix callback url for whatsapp webhooks
    $queryWithPlaceholderFixes = str_replace(
        '__notifications__whatsapp__webhook',
        '/notifications/whatsapp/webhook',
        $queryWithPlaceholderFixes
    );

    [$newPath, $newQuery] = (function ($queryWithPlaceholderFixes, array $get) {
        if (isset($get['call']) && is_string($get['call']) && $get['call'] !== '') {
            $callPath = str_replace(
                '__payments__callback',
                '/payments/callback',
                $get['call']
            );
            $callPath = str_replace(
                '__notifications__whatsapp__webhook',
                '/notifications/whatsapp/webhook',
                $callPath
            );

            $queryParams = $get;
            unset($queryParams['action'], $queryParams['call']);

            return [$callPath, http_build_query($queryParams)];
        }

        $newRoute = str_replace(
            ['XDEBUG_SESSION_START=PHPSTORM&' . AMELIA_ACTION_SLUG, AMELIA_ACTION_SLUG],
            '',
            $queryWithPlaceholderFixes
        );

        $newPath = strpos($newRoute, '&') ? substr(
            $newRoute,
            0,
            strpos($newRoute, '&')
        ) : $newRoute;

        $newQuery = strpos($newRoute, '&') ? substr(
            $newRoute,
            strpos($newRoute, '&') + 1
        ) : '';

        return [$newPath, $newQuery];
    })($queryWithPlaceholderFixes, $_GET);

    $request = $serverRequest->withUri(
        $curUri
            ->withPath($newPath)
            ->withQuery($newQuery)
    );

    $queryParams = $request->getQueryParams();

    if (!empty($queryParams['showAmeliaErrors'])) {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    }

    return $request;
};
