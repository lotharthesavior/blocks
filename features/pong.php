<?php

use App\ServerEvent;
use League\Plates\Engine;

// simple ping/pong

addListener(ServerEvent::REQUEST, function (ServerEvent $event) {
    global $port;

    $templates = new Engine(ROOT_DIR . '/public');
    $event->response->header('Content-Type', 'text/html');
    $event->response->header('Charset', 'UTF-8');
    $event->response->end($templates->render('pong_client', [
        'port' => $port,
    ]));
});

addListener(ServerEvent::MESSAGE, function (ServerEvent $event) {
    if ($event->frame->data !== 'ping') {
        return;
    }
    $event->server->push($event->frame->fd, 'pong');
});
xx
