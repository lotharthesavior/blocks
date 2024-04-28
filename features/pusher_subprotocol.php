<?php

use App\App;
use App\ServerEvent;
use League\Plates\Engine;
use OpenSwoole\WebSocket\Server;
use OpenSwoole\Table;

class PusherProtocolHandler
{
    public function __construct(protected Table $channelTable)
    {
        addListener(ServerEvent::MESSAGE, [$this, 'onMessage']);
        addListener(ServerEvent::CLOSE, [$this, 'onClose']);
        addListener(ServerEvent::REQUEST, [$this, 'onRequest']);
    }

    public function onMessage(ServerEvent $event): void
    {
        $data = json_decode($event->frame->data, true);
        $eventType = $data['event'] ?? '';

        if (isset($data['data'])) {
            $data['data']['fd'] = $event->frame->fd;
        }

        match ($eventType) {
            'subscribe' => $this->subscribe($event->server, $event->frame->fd, $data['data']['channel']),

            'unsubscribe' => $this->unsubscribe($event->server, $event->frame->fd, $data['data']['channel']),

            'ping' => $event->server->push($event->frame->fd, json_encode(['event' => 'pong'])),

            'client_message' => broadcast($event->server, $data),

            default => null,
        };
    }

    public function onClose(ServerEvent $event): void
    {
        dispatch(new ServerEvent(
            server: $event->server,
            serverFd: $event->serverFd,
            channel: getChannelForUser($event->serverFd),
        ), ServerEvent::BEFORE_CHANNEL_CLOSE);

        foreach ($this->channelTable as $channel => $row) {
            $subscribers = unserialize($row['fds']);
            if (($key = array_search($event->serverFd, $subscribers)) !== false) {
                unset($subscribers[$key]);
                $this->updateChannelSubscribers($channel, array_values($subscribers));
            }
        }

        echo "Connection closed (fd {$event->serverFd}).\n";
    }

    public function onRequest(ServerEvent $event): void {
        global $port;

        $templates = new Engine(ROOT_DIR . '/public');
        $event->response->header('Content-Type', 'text/html');
        $event->response->header('Charset', 'UTF-8');
        // $event->response->end('Version 1');
        // $event->response->end('Version 2');
        $event->response->end($templates->render('pusher_client', [
            'port' => $port,
        ]));
    }

    protected function updateChannelSubscribers(string $channel, array $fds): void
    {
        $this->channelTable->set($channel, ['fds' => serialize($fds)]);
    }

    protected function subscribe(Server $server, int $fd, string $channel): void
    {
        $subscribers = getChannelSubscribers($channel);
        if (!in_array($fd, $subscribers)) {
            $subscribers[] = $fd;
            $this->updateChannelSubscribers($channel, $subscribers);
        }
        echo "Client {$fd} subscribed to {$channel}.\n";

        $server->push($fd, json_encode([
            'event' => 'subscribed',
            'data' => [
                'channel' => $channel,
            ],
        ]));

        dispatch(new ServerEvent(
            server: $server,
            serverFd: $fd,
            channel: $channel,
        ), ServerEvent::CHANNEL_SUBSCRIBED);
    }

    protected function unsubscribe(Server $server, int $fd, string $channel): void
    {
        $subscribers = getChannelSubscribers($channel);
        if (($key = array_search($fd, $subscribers)) !== false) {
            unset($subscribers[$key]);
            $this->updateChannelSubscribers($channel, array_values($subscribers));
        }
        echo "Client {$fd} unsubscribed from {$channel}.\n";

        $server->push($fd, json_encode([
            'event' => 'unsubscribed',
            'data' => [
                'channel' => $channel,
            ],
        ]));

        dispatch(new ServerEvent(
            server: $server,
            serverFd: $fd,
            channel: $channel,
        ), ServerEvent::CHANNEL_UNSUBSCRIBED);
    }
}

$channelTable = new Table(1024);
$channelTable->column('channel', Table::TYPE_STRING, 64);
$channelTable->column('fds', Table::TYPE_STRING, 1024);
$channelTable->create();
App::container()['channelTable'] = $channelTable;

new PusherProtocolHandler($channelTable);
