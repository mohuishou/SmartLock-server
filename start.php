<?php
/**
 * Created by mohuishou<1@lailin.xyz>.
 * User: mohuishou<1@lailin.xyz>
 * Date: 2016/11/14 0014
 * Time: 19:01
 */
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ ."/database/start.php";


$socket=new \App\Socket();
$socket->start();



if(!defined('GLOBAL_START'))
{
    \Workerman\Worker::runAll();
}
