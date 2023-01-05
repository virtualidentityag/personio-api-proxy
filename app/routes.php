<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {

    $app->any('/{routes:.*}', function (Request $request, Response $response, array $args) {

        $proxy = new \App\PersonioProxy($request, $response, $args);
        $proxy->process();
        return $response;
    });

};
