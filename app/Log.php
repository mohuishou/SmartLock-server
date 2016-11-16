<?php
/**
 * Created by mohuishou<1@lailin.xyz>.
 * User: mohuishou<1@lailin.xyz>
 * Date: 2016/11/14 0014
 * Time: 22:54
 */

namespace App;


use Monolog\Logger;

class Log
{
    static public $log;
    public function __construct()
    {
        self::$log=new Logger("log");
        self::$log->popHandler(__DIR__."/../log/log.log");
    }


}