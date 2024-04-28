<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\ServerEvent;

// bot broadcasting example via sub process

$debug = $_ENV['DEBUG'] === 'true';

function chat(string $message, callable $callback): void {
    global $debug;

    usleep(100000);
    $uniqueId = uniqid();
    $callback($_ENV['BOT_START'], $uniqueId);
    usleep(100000);

    $client = new Client();
    try {
        $response = $client->request('POST', $_ENV['BOT_API_URL'], [
            'stream' => true,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $_ENV['API_KEY'],
            ],
            'body' => json_encode([
                'model' => $_ENV['MODEL'],
                'stream' => true,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful assistant, and for every user message, be objective and respond in a few sentences only.',
                    ], [
                        'role' => 'user',
                        'content' => $message,
                    ],
                ],
            ]),
        ]);

        $body = $response->getBody();
        $buffer = '';
        while (!$body->eof()) {
            $buffer .= $body->read(1);
            if (substr($buffer, -1) !== PHP_EOL) {
                continue;
            }

            $buffer = trim($buffer);
            if (str_starts_with($buffer, 'data:')) {
                $buffer = trim(str_replace('data:', '', $buffer));
            }
            $jsonObject = json_decode($buffer, true);
            if (!$jsonObject) {
                if ($debug) {
                    echo "Error decoding JSON: " . json_last_error_msg() . PHP_EOL;
                    echo "Message Received: " . PHP_EOL;
                    var_dump($buffer);
                }
                $buffer = '';
                continue;
            }
            $buffer = '';

            if (
                'completions' === $_ENV['PATTERN']
                && isset($jsonObject['choices'][0]['delta']['content'])
            ) {
                $callback($jsonObject['choices'][0]['delta']['content'], $uniqueId);
            } elseif (
                'chat' === $_ENV['PATTERN']
                && isset($jsonObject['message']['content'])
            ) {
                $callback($jsonObject['message']['content'], $uniqueId);
            }
        }
    } catch (RequestException $e) {
        echo '===============================================================' . PHP_EOL;
        echo '!!! Bot interaction failed due to: ' . $e->getMessage() . '!!!' . PHP_EOL;
        echo '===============================================================' . PHP_EOL;
    } finally {
        $callback($_ENV['HARD_STOP'], $uniqueId);
    }
}

addListener(ServerEvent::PRE_START, function (ServerEvent $event) {
    $event->server->set([
        'worker_num'      => 2,
        'task_worker_num' => 4,
        'task_ipc_mode' => 3,
    ]);
});

addListener(ServerEvent::MESSAGE, function (ServerEvent $event) {
    $parsedData = json_decode($event->frame->data, true);

    if (
        !$parsedData
        || $parsedData['event'] !== 'client_message'
        || !isset($parsedData['data']['message'])
    ) {
        return;
    }

    if (isset($data['data'])) {
        $parsedData['data']['fd'] = $event->frame->fd;
    }

    $channel = $parsedData['data']['channel'] ?? '';

    broadcast($event->server, [
        'event' => 'bot-update',
        'data' => [
            'channel' => $channel,
            'message' => 'Bot is processing...',
        ],
    ]);
    $event->server->task($event->frame->data);
});

addListener(ServerEvent::TASK, function (ServerEvent $event) {
    $parsedData = json_decode($event->data, true);

    chat($parsedData['data']['message'], function (string $message, string $uniqueId) use ($event, $parsedData) {
        $channel = $parsedData['data']['channel'] ?? '';

        broadcast($event->server, [
            'event' => 'bot-update',
            'data' => [
                'channel' => $channel,
                'message' => 'Bot is typing...',
            ],
        ]);

        if ($message === $_ENV['HARD_STOP']) {
            broadcast($event->server, [
                'event' => 'bot-stop',
                'interaction' => $uniqueId,
                'data' => ['channel' => $channel],
            ]);
            broadcast($event->server, [
                'event' => 'bot-update',
                'data' => [
                    'channel' => $channel,
                    'message' => 'Bot finished!',
                ],
            ]);
            return;
        } elseif ($message === $_ENV['BOT_START']) {
            broadcast($event->server, [
                'event' => 'bot-start',
                'interaction' => $uniqueId,
                'data' => ['channel' => $channel],
            ]);
            return;
        }

        broadcast($event->server, [
            'event' => 'bot-chunk',
            'message' => $message,
            'interaction' => $uniqueId,
            'data' => ['channel' => $channel],
        ]);
    });
});
