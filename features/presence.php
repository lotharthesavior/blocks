<?php

use App\App;
use App\ServerEvent;
use OpenSwoole\Table;
use OpenSwoole\WebSocket\Server;

class PresenceHandler
{
    public function __construct(protected Table $presenceTable)
    {
        addListener(ServerEvent::OPEN, [$this, 'onOpen']);
        addListener(ServerEvent::CHANNEL_SUBSCRIBED, [$this, 'onChannelSubscribed']);
        addListener(ServerEvent::CHANNEL_UNSUBSCRIBED, [$this, 'onChannelUnsubscribed']);
        addListener(ServerEvent::BEFORE_CHANNEL_CLOSE, [$this, 'onBeforeChannelClose']);
    }

    public function onOpen(ServerEvent $event): void
    {
        $user = getUser($event->request->get['token'] ?? '');
        if (null === $user) {
            $event->server->close($event->request->fd);
            return;
        }

        $event->server->push($event->request->fd, json_encode([
            'event' => 'welcome',
            'name' => $user['name'],
        ]));

        syncPresence(fd: $event->request->fd, user: $user);
    }

    public function onChannelSubscribed(ServerEvent $event): void
    {
        $user = getUserByFd($event->serverFd);
        if (null === $user || !isset($user['name'])) {
            return;
        }

        $event->server->push($event->serverFd, json_encode([
            'event' => 'welcome',
            'name' => $user['name'],
        ]));

        $this->broadcastPresence($event->server, $event->channel);
    }

    public function onChannelUnsubscribed(ServerEvent $event): void
    {
        $this->broadcastPresence($event->server, $event->channel);
    }

    public function onBeforeChannelClose(ServerEvent $event): void
    {
        $this->updatePresence(
            server: $event->server,
            fd: $event->serverFd,
            channel: getChannelForUser($event->serverFd),
        );
    }

    /**
     * Broadcasts the presence data to all subscribers of the channel.
     *
     * @param Server $server
     * @param string $channel
     * @return void
     */
    private function broadcastPresence(Server $server, string $channel): void
    {
        $counter = getCount($server, $channel);
        broadcast($server, [
            'event' => 'presence',
            'data' => [
                'channel' => $channel,
                'count' => $counter,
                'users' => getNamedChannelSubscribers($channel),
            ]
        ]);
    }

    private function updatePresence(Server $server, int $fd, ?string $channel): void
    {
        $counter = 0;
        foreach ($server->connections as $currentFd) {
            if ($server->isEstablished($currentFd)) {
                $counter++;
            }
        }

        broadcast($server, [
            'event' => 'presence',
            'data' => [
                'channel' => $channel,
                'count' => $counter - 1,
                'users' => syncPresence(
                    fd: $fd,
                    increment: false,
                ),
            ]
        ]);
    }
}

$presenceTable = new Table(1024);
$presenceTable->column('name', Table::TYPE_STRING, 25);
$presenceTable->create();
App::container()['presenceTable'] = $presenceTable;

new PresenceHandler($presenceTable);
