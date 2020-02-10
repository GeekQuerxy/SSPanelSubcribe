<?php

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;

class Boot
{
    public static function setDebug()
    {
        define('DEBUG', Config::get('debug'));
        View::$beginTime = microtime(true);
    }

    public static function setTimezone()
    {
        // config time zone
        date_default_timezone_set(Config::get('timeZone'));
    }

    public static function bootDb()
    {
        // Init Eloquent ORM Connection
        $capsule = new Capsule();
        $capsule->addConnection(Config::getDbConfig());
        $capsule->bootEloquent();

        View::$connection = $capsule->getDatabaseManager();
        $capsule->getDatabaseManager()->connection('default')->enableQueryLog();
    }
}
