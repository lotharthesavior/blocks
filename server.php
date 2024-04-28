<?php

use App\ServerEvent;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Server as BaseServer;
use OpenSwoole\WebSocket\Frame;
use OpenSwoole\WebSocket\Server;
use Dotenv\Dotenv;

const ROOT_DIR = __DIR__;

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/helpers.php';

foreach ($_ENV as $key => $value) {
    if (str_starts_with($key, 'FEATURE_') && $value === 'true') {
        $featureName = strtolower(substr($key, strlen('FEATURE_')));
        $filePath = __DIR__ . '/features/' . $featureName . '.php';
        include_once $filePath;
    }
}

// parameters example: --host 0.0.0.0 --port 8080
$host = $_ENV['HOST'];
$port = $_ENV['PORT'];

foreach ($argv as $key => $arg) {
    if ($arg === '--port') {
        $port = (int) $argv[$key + 1];
    } elseif ($arg === '--host') {
        $host = $argv[$key + 1];
    }
}

$server = new Server($host, $port);
$server->set([
    'worker_num' => $_ENV['WORKER_NUM'] ?? 10,
]);

dispatch(
    event: new ServerEvent(server: $server),
    eventName: ServerEvent::PRE_START,
);

$server->on('start', function (Server $server) {
    dispatch(
        event: new ServerEvent(server: $server),
        eventName: ServerEvent::START,
    );
});

$server->on('request', function (
    Request $request,
    Response $response,
) use ($server) {
    dispatch(
        event: new ServerEvent(
            server: $server,
            request: $request,
            response: $response,
        ),
        eventName: ServerEvent::REQUEST,
    );
});

$server->on('open', function (Server $server, Request $request) {
    dispatch(
        event: new ServerEvent(
            server: $server,
            request: $request,
        ),
        eventName: ServerEvent::OPEN,
    );
});

$server->on('message', function (Server $server, Frame $frame) {
    dispatch(
        event: new ServerEvent(
            server: $server,
            frame: $frame,
        ),
        eventName: ServerEvent::MESSAGE,
    );
});


$server->on('task', function (
    BaseServer $s,
    int $task_id,
    int $reactorId,
    string $data,
) use ($server) {
    dispatch(
        event: new ServerEvent(
            server: $server,
            baseServer: $s,
            taskId: $task_id,
            reactorId: $reactorId,
            data: $data,
        ),
        eventName: ServerEvent::TASK,
    );
});

$server->on('finish', function (
    BaseServer $s,
    int $task_id,
    string $data,
) use ($server) {
    dispatch(
        event: new ServerEvent(
            server: $server,
            baseServer: $s,
            taskId: $task_id,
            data: $data,
        ),
        eventName: ServerEvent::FINISH,
    );
});

$server->on('close', function (Server $s, int $fd) use ($server) {
    dispatch(
        event: new ServerEvent(server: $server, serverFd: $fd),
        eventName: ServerEvent::CLOSE,
    );
});

$server->on('shutdown', function (Server $server) {
    dispatch(
        event: new ServerEvent(server: $server),
        eventName: ServerEvent::SHUTDOWN,
    );
});

$server->start();
