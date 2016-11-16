<?php
/**
 * Created by mohuishou<1@lailin.xyz>.
 * User: mohuishou<1@lailin.xyz>
 * Date: 2016/11/14 0014
 * Time: 18:50
 */
$database = [
    'driver' => 'mysql',
    'host' => "localhost",
    'database' => "smart_lock",
    'username' => "mohuishou",
    'password' => "mohuishou",
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => "",
];

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule;

// 创建链接
$capsule->addConnection($database);

// 设置全局静态可访问DB
$capsule->setAsGlobal();

// 启动Eloquent
$capsule->bootEloquent();