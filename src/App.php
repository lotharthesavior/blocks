<?php

namespace App;

use Pimple\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;

class App
{
    protected static ?Container $container = null;

    public static function container(): Container
    {
        if (self::$container === null) {
            self::$container = new Container();
            self::$container['dispatcher'] = new EventDispatcher();
        }

        return self::$container;
    }
}
