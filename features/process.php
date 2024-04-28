<?php

use League\Plates\Engine;
use OpenSwoole\Coroutine\System;
use OpenSwoole\Process;
use App\ServerEvent;
use OpenSwoole\Table;

$process = null;

$processTable = new Table(1024);
$processTable->column('pid', Table::TYPE_INT, 4);
$processTable->create();

addListener(ServerEvent::REQUEST, function (ServerEvent $event) {
    global $port;

    $templates = new Engine(ROOT_DIR . '/public');
    $event->response->header('Content-Type', 'text/html');
    $event->response->header('Charset', 'UTF-8');
    $event->response->end($templates->render('pong_client', [
        'port' => $port,
    ]));
});

addListener(ServerEvent::PRE_START, function (ServerEvent $event) {
    global $process;

    $process = new Process(function (Process $worker) use ($event) {
        while (true) {
            $data = $worker->pop();
            echo "Received by the subprocess layer 1 ({$worker->pid})\n";

            $process = new Process(function (Process $worker) use ($event, $data) {
                echo "Received by the sub subprocess layer 2 ({$worker->pid})\n";
                $parsedData = json_decode($data, true);
                foreach ($event->server->connections as $fd) {
                    if ($event->server->isEstablished($fd)) {
                        $event->server->push($fd, $parsedData['data']);
                    }
                }
                $worker->write('finished');
            });
            $pid = $process->start();
            $process->read();
            Process::kill($pid);
        }
    });
    $process->useQueue();
});

addListener(ServerEvent::START, function (ServerEvent $e) use ($processTable) {
    global $process;

    $pid = $process->start();

    $processTable->set('process', ['pid' => $pid]);
});

addListener(ServerEvent::MESSAGE, function (ServerEvent $e) {
    global $process;

    $message = json_encode([
        'fd' => $e->frame->fd,
        'data' => $e->frame->data,
    ]);

    if (!$message) {
        return;
    }

    echo '===========================================' . PHP_EOL;

    $pid = getmypid();
    echo "Passing by the \"message\" event ({$pid})\n";

    $process->push($message);
});

addListener(ServerEvent::SHUTDOWN, function (ServerEvent $e) use ($processTable) {
    Process::kill($processTable->get('process', 'pid'), SIGKILL);
});

// TODO: this feature doesn't work with Hot Reactor due to
//       a conflict between signals and process.
// Co::run(fn() => System::waitSignal(SIGKILL, -1));
