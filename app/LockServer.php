<?php
/**
 * Created by mohuishou<1@lailin.xyz>.
 * User: mohuishou<1@lailin.xyz>
 * Date: 2016/11/18 0018
 * Time: 12:15
 */

namespace App;


use Workerman\Worker;
use Workerman\Lib\Timer;

class LockServer
{

    /**
     * 缓存文件路径
     * @var string
     */
    protected $_tmp_path=__DIR__."/../tmp";

    protected $_worker;

    protected $_connection_map;

    protected $_time_map;

    /**
     * LockServer constructor.
     */
    public function __construct()
    {
        $this->_worker=new Worker('tcp://0.0.0.0:8401');
    }

    /**
     * @author mohuishou<1@lailin.xyz>
     */
    public function start(){
        $this->onConnect();
    }

    /**
     * @author mohuishou<1@lailin.xyz>
     */
    protected function onConnect(){
        $this->_worker->onConnect = function($connection)
        {

            $this->debug("设备".$connection->id."已连接");
            // 设置连接的onMessage回调
            $connection->onMessage = function($connection, $data)
            {
                $datas=json_decode(trim($data));
                if(!$datas){
                    $this->debug("数据格式错误！");
                    return;
                }
                $data=$datas;
                $data_validate=["lock_id","is_low_battery","lon","lat","is_stolen"];
                foreach ($data_validate as $v){
                    if(!isset($data->$v)){
                        $this->debug("数据格式有误");
                        return;
                    }
                }
                $data->time=time();
                $this->_connection_map[$connection->id]=$data->lock_id;
                @file_put_contents($this->_tmp_path."/".$data->lock_id."-tmp.json",json_encode($data));
                $connection->send('receive success');
            };

            $this->openLockTimer($connection);

            $this->onClose($connection);
        };
    }

    /**
     * @author mohuishou<1@lailin.xyz>
     * @param $connection
     */
    protected function onClose($connection){
        $connection->onClose = function($connection)
        {
            $time_map=$this->_time_map;
            $this->debug("设备".$connection->id."已断开");
            if(isset($time_map[$connection->id])){
                Timer::del($time_map[$connection->id]);
                $this->debug("关闭设备".$connection->id."定时器");
            }
        };
    }

    /**
     * @author mohuishou<1@lailin.xyz>
     * @param $connection
     */
    protected function openLockTimer($connection){
        $time_map=$this->_time_map;
        $time_map[$connection->id]=Timer::add(3,function () use ($connection){
            $connection_map=$this->_connection_map;
            if(!isset($connection_map[$connection->id])){
                return;
            }
            $file_path=$this->_tmp_path."/".$connection_map[$connection->id]."-open.json";
            if(!file_exists($file_path)){
                return;
            }
            $data=@file_get_contents($file_path);
            if($data==1){
                // 计数
                $this->sendTimer($connection,$file_path);
            }
        });
    }

    /**
     * @author mohuishou<1@lailin.xyz>
     * @param $connection
     * @param $file_path
     */
    protected function sendTimer($connection,$file_path){
        $count = 1;
        // 要想$timer_id能正确传递到回调函数内部，$timer_id前面必须加地址符 &
        $timer_id = Timer::add(0.3, function()use(&$timer_id, &$count,$file_path,$connection)
        {
            $connection->send('11111');
            // 运行10次后销毁当前定时器
            if($count++ >= 10)
            {
                Timer::del($timer_id);
                @unlink($file_path);
            }
        });
    }



    /**
     * debug
     * @author mohuishou<1@lailin.xyz>
     * @param $info
     */
    protected function debug($info){
        echo "[LockServer][".date("Y-m-d h-i-s")."]: ".$info."\r\n ";
    }
}