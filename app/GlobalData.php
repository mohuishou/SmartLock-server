<?php
/**
 * Created by PhpStorm.
 * User: lxl
 * Date: 16-11-16
 * Time: 下午9:28
 */

namespace App;


use GlobalData\Server;
use Workerman\Worker;

class GlobalData
{
    public function server(){
        $global_data_server=new Server('127.0.0.1',8400);
    }
}