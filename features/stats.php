<?php

use App\ServerEvent;

// Timer example

addListener(ServerEvent::START, function (ServerEvent $event) {
    $event->server->tick(5000, function () use ($event) {
        $stats = rand(10, 100);
        foreach ($event->server->connections as $fd) {
            if ($event->server->isEstablished($fd)) {
                $event->server->push($fd, json_encode([
                    'event' => 'server-health',
                    'message' => $stats,
                ]));
            }
        }
    });


    $event->server->after(5000, function () use ($event) {
        echo 'Doing something after 5 seconds...' . PHP_EOL;
    });
});
