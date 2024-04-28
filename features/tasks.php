<?php

use App\ServerEvent;

addListener(ServerEvent::PRE_START, function (ServerEvent $event) {
    $event->server->set([
        'worker_num'      => 2,
        'task_worker_num' => 4,
        'task_ipc_mode' => 3,
    ]);
});

addListener(ServerEvent::MESSAGE, function (ServerEvent $event) {
    $message = json_encode([
        'fd' => $event->frame->fd,
        'data' => $event->frame->data,
    ]);

    if (!$message) {
        return;
    }

    for ($i = 0; $i < 10; $i++) {
        $event->server->task($event->frame->fd);
    }
});

addListener(ServerEvent::TASK, function (ServerEvent $event) {
    echo '======================' . PHP_EOL
        . "Task Started - ID: {$event->taskId}" . PHP_EOL
        . "Worker ID: {$event->server->worker_id}" . PHP_EOL
        . '======================' . PHP_EOL;
    $event->server->push($event->data, 'Task Running');

    sleep(4);

    $event->baseServer->finish($event->data);
});

addListener(ServerEvent::FINISH, function (ServerEvent $event) {
    echo '======================' . PHP_EOL
        . "Task Ended - ID: {$event->taskId}" . PHP_EOL
        . '======================' . PHP_EOL;

    $event->server->push($event->data, 'Task Finished');
});
