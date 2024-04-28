<?php

use App\App;
use App\ServerEvent;
use OpenSwoole\WebSocket\Server;

/**
 * @param string $token
 * @return array<array-key, string>|null
 */
function getUser(string $token): ?array
{
    $users = [
        [
            'name' => 'John',
            'token' => '123456',
        ],
        [
            'name' => 'Jane',
            'token' => '654321',
        ],
    ];

    $user = null;
    foreach ($users as $currentUser) {
        if ($currentUser['token'] === $token) {
            $user = $currentUser;
            break;
        }
    }

    return $user;
}

/**
 * Gets the count of connections subscribed to a specific channel.
 *
 * @param Server $server The Swoole WebSocket Server instance.
 * @param string|null $channel The name of the channel for which to count the subscriptions.
 *
 * @return int The number of connections subscribed to the specified channel.
 */
function getCount(Server $server, string $channel): int {
    $channelTable = App::container()['channelTable'] ?? null;
    $counter = 0;
    $fds = unserialize($channelTable->get($channel, 'fds'));

    foreach ($fds as $fd) {
        if (!$server->isEstablished($fd)) {
            continue;
        }

        $counter++;
    }

    return $counter;
}

/**
 * @param int $fd
 * @param array<array-key, string> $user
 * @param bool $increment
 * @return array<array-key, string>
 */
function syncPresence(
    int $fd,
    array $user = [],
    bool $increment = true,
): array {
    $presenceUsers = [];
    $presenceTable = App::container()['presenceTable'];

    foreach ($presenceTable as $key => $item) {
        $presenceUsers[$key] = $item['name'];
    }

    if ($increment) {
        $presenceUsers[$fd] = $user['name'];
        $presenceTable->set((string) $fd, ['name' => $user['name']]);
    } else {
        unset($presenceUsers[$fd]);
        $presenceTable->del((string) $fd);
    }

    return $presenceUsers;
}

/**
 * Dispatches an event to the event dispatcher.
 *
 * @param ServerEvent $event
 * @param string $eventName
 * @return void
 */
function dispatch(ServerEvent $event, string $eventName): void {
    App::container()['dispatcher']->dispatch($event, $eventName);
}

/**
 * Adds a listener to the event dispatcher.
 *
 * @param string $eventName
 * @param callable $listener
 * @return void
 */
function addListener(string $eventName, callable $listener): void {
    App::container()['dispatcher']->addListener($eventName, $listener);
}

/**
 * Broadcasts a message to all connections subscribed to the specified channel.
 *
 * @param Server $server The Swoole WebSocket Server instance.
 * @param array $data The message to broadcast, encoded as a JSON string.
 *                    The message must include a 'channel' key to specify the target channel.
 */
function broadcast(Server $server, array $data): void {
    $channel = $data['data']['channel'] ?? '';
    foreach (getChannelSubscribers($channel) as $currentFd) {
        $server->push($currentFd, json_encode($data));
    }
}

/**
 * Gets the list of connections subscribed to the specified channel.
 *
 * @param string $channel
 * @return array<int>
 */
function getChannelSubscribers(string $channel): array
{
    $channelTable = App::container()['channelTable'];
    $data = $channelTable->get($channel);

    return $data ? unserialize($data['fds']) : [];
}

/**
 * Gets the channel to which the specified connection is subscribed.
 *
 * @param int $fd
 * @return string|null
 */
function getChannelForUser(int $fd): ?string
{
    $channelTable = App::container()['channelTable'];
    foreach ($channelTable as $channel => $row) {
        $subscribers = unserialize($row['fds']);
        if (in_array($fd, $subscribers)) {
            return $channel;
        }
    }

    return null;
}

/**
 * Gets the names of the connections subscribed to the specified channel.
 *
 * @param string $channel
 * @return array<int, string>
 */
function getNamedChannelSubscribers(string $channel): array
{
    $presenceUsers = [];

    $presenceTable = App::container()['presenceTable'];

    $fds = getChannelSubscribers($channel);

    foreach ($presenceTable as $key => $item) {
        if (in_array($key, $fds)) {
            $presenceUsers[$key] = $item['name'];
        }
    }

    return $presenceUsers;
}

/**
 * Gets the user data associated with the specified connection.
 *
 * @param int $fd
 * @return array|null
 */
function getUserByFd(int $fd): ?array
{
    $presenceTable = App::container()['presenceTable'];

    return $presenceTable->get((string) $fd) ?? null;
}
