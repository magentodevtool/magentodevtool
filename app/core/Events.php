<?php

class Events
{

    static protected $events = array();

    static function register($eventName, $callback)
    {
        static::$events[$eventName][] = $callback;
    }

    static function dispatch($eventName, $args = array())
    {
        if (!isset(static::$events[$eventName])) {
            return;
        }
        foreach (static::$events[$eventName] as $callback) {
            call_user_func_array($callback, $args);
        }
    }

}
