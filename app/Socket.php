<?php
/**
 * Created by mohuishou<1@lailin.xyz>.
 * User: mohuishou<1@lailin.xyz>
 * Date: 2016/11/14 0014
 * Time: 17:04
 */


namespace App;
use App\Models\Lock;
use App\Models\User;
use Workerman\Worker;
use Workerman\WebServer;
use Workerman\Lib\Timer;
use PHPSocketIO\SocketIO;

class Socket
{

    protected $_sender_io;

    protected $_connection_map;

    protected $_log;

    public function __construct()
    {
        $this->_sender_io=new SocketIO(2120);

    }

    public  function start(){

        //建立连接
        $this->connection();

        //连接建立之后监听
        $this->workStart();

        $this->lockServer();
        if(!defined('GLOBAL_START'))
        {
            Worker::runAll();
        }
    }

    public function connection(){
        $this->_sender_io->on('connection',function($socket){
            // 当客户端发来登录事件时触发
            $socket->on('login', function ($phone)use($socket){
                // 已经登录过了
                if(isset($socket->uid)){
                    return;
                }

                try{
                    $user=User::firstOrCreate(["phone"=>$phone]);
                    $uid=$user->id;
                }catch (\Exception $e){
                    $this->_sender_io->emit("error","数据库错误".$e->getMessage());
                }




                // 更新对应uid的在线数据
                $uid = (string)$uid;
                if(!isset($this->_connection_map[$uid]))
                {
                   $this->_connection_map[$uid] = 0;
                }

                //
                if(isset($user->lock_id)&&!empty($user->lock_id)){
                    $this->_connection_map[$uid] = $user->lock_id;
                }

                echo "user:".$user->phone."connection \r\n ";


                // 将这个连接加入到uid分组，方便针对uid推送数据
                $socket->join($uid);
                $socket->uid = $uid;

                //返回用户设备情况
                $socket->emit('user',$user);

            });


            //绑定设备
            $socket->on("bind",function ($datas) use ($socket){

                print_r($socket->uid);
                try{
                    $lock=Lock::where("lock_id",$datas["lock_id"])
                        ->where("lock_key",$datas["lock_key"])
                        ->first();
                }catch (\Exception $e){
                    $socket->emit("error","数据库错误：".$e->getMessage());
                }

                if(!isset($lock->id)||empty($lock->id)){
                    $socket->emit("bind_res",[
                        "status"=>0,
                        "msg"=>"设备不存在"
                    ]);
                    return;
                }
                $user=User::find($datas["uid"]);

                $user->lock_id=$lock->lock_id;
                $user->save();
                $socket->emit("bind_res",[
                    "status"=>1,
                    "msg"=>"绑定成功",
                    "lock_id"=>$datas["lock_id"]
                ]);
                $this->_connection_map[$datas["uid"]] = $datas["lock_id"];
            });

            // 当客户端断开连接是触发（一般是关闭网页或者跳转刷新导致）
            $socket->on('disconnect', function () use($socket) {
                if(!isset($socket->uid))
                {
                    return;
                }
                $user=User::find($socket->uid);
                echo "user: ".$user->phone."disconnect \r\n ";
                if(isset($this->_connection_map[$socket->uid]))
                    unset($this->_connection_map[$socket->uid]);
            });
        });
    }

    public function workStart(){
        $this->_sender_io->on('workerStart', function(){


            //设备已绑定，定时扫描设备状态
        });
    }


    public function lockServer(){

        $worker = new Worker('tcp://0.0.0.0:8484');
        $worker->onConnect = function($connection)
        {
            echo $connection->id;
            // 设置连接的onMessage回调
            $connection->onMessage = function($connection, $data)
            {
                var_dump($data);
                $connection->send('receive success');
            };
        };
    }


}
