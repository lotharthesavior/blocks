<?php

namespace App;

use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Server as BaseServer;
use OpenSwoole\WebSocket\Frame;
use OpenSwoole\WebSocket\Server;
use Symfony\Contracts\EventDispatcher\Event;

class ServerEvent extends Event
{
    public const PRE_START = 'server.pre-start';
    public const START = 'server.start';
    public const REQUEST = 'server.request';
    public const OPEN = 'server.open';
    public const MESSAGE = 'server.message';
    public const TASK = 'server.task';
    public const FINISH = 'server.finish';
    public const CLOSE = 'server.close';
    public const SHUTDOWN = 'server.shutdown';
    public const CHANNEL_SUBSCRIBED = 'server.channel.subscribed';
    public const CHANNEL_UNSUBSCRIBED = 'server.channel.unsubscribed';
    public const BEFORE_CHANNEL_CLOSE = 'server.channel.before-close';

    public function __construct(
        readonly public ?Server $server,
        readonly public ?BaseServer $baseServer = null,
        readonly public ?Frame $frame = null,
        readonly public ?Request $request = null,
        readonly public ?Response $response = null,
        readonly public ?int $taskId = null,
        readonly public ?int $reactorId = null,
        readonly public ?string $data = null,
        readonly public ?int $serverFd = null,
        readonly public ?string $channel = null,
    ) {
    }
}
