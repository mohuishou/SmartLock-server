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
use Channel\Client;
use Workerman\Worker;
use Workerman\Lib\Timer;
use PHPSocketIO\SocketIO;

class Socket
{
    /**
     * 缓存文件路径
     * @var string
     */
    protected $_tmp_path=__DIR__."/../tmp";

    /**
     * @var
     */
    protected $_old_data;

    protected $_time_id_map;

    protected $_sender_io;

    protected $_connection_map;

    protected $_log;

    protected $_lock_obj;

    protected $_global;

    public function __construct()
    {
        $this->_sender_io=new SocketIO(8400);
    }

    public  function start(){
        //建立连接
        $this->connection();


        $this->lockServer();

    }


    /**
     * 连接
     */
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
                    $this->_sender_io->emit("error","数据库错误");
                    $this->debug("数据库错误".$e->getMessage());
                    return;
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

                $this->debug("user:".$user->phone."connection");
                // 将这个连接加入到uid分组，方便针对uid推送数据
                $socket->join($uid);
                $socket->uid = $uid;

                //返回用户信息
                $socket->emit('user',$user);

                //返回用户设备情况
                if(file_exists($this->_tmp_path)){
                    $data=@file_get_contents($this->_tmp_path);
                }else{
                    return;
                }
                $data=json_decode($data);
                if(!$data){
                    return;
                }
                $data->status=1;
                if((time()-$data->time)>10){
                    $data->status=0;
                };
                $this->_old_data=$data;
                $socket->emit("lock_status",$data);

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

            //设置定时器
            $this->_time_id_map[$socket->uid]=Timer::add(4,function () use($socket) {
                //缓存文件是否存在
                if(!isset($this->_connection_map[$socket->uid]))
                    return;
                if(file_exists($this->_tmp_path."/".$this->_connection_map[$socket->uid]."-tmp.json")){
                    $data=@file_get_contents($this->_tmp_path."/".$this->_connection_map[$socket->uid]."-tmp.json");
                }else{
                    return;
                }
                $data=json_decode($data);
                if(!$data){
                    return;
                }
                $data->status=1;
                if((time()-$data->time)>10){
                    $data->status=0;
                };

                //与上一次的数据进行比较，如果没有变化就不进行广播了
                if(!empty($this->_old_data)){
                    //判断两个数组差集是否为空
                    if($this->_old_data==$data){
                        $this->debug("数据相同");
                        return;
                    }
                }
                $this->_old_data=$data;
                $this->debug("数据已发送");
                $socket->emit("lock_status",$data);
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
                if(isset($this->_time_id_map[$socket->uid])){
                    Timer::del($this->_time_id_map[$socket->uid]);
                }
            });
        });
    }

    /**
     * 硬件连接服务
     */
    public function lockServer(){

        $worker = new Worker('tcp://0.0.0.0:8401');
        $worker->onConnect = function($connection)
        {
            echo $connection->id;
            // 设置连接的onMessage回调
            $connection->onMessage = function($connection, $data)
            {
                $datas=json_decode(trim($data));
                if(!$datas){
                    $this->debug("数据格式错误！");
                    print_r($data);
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
                @file_put_contents($this->_tmp_path."/".$data->lock_id."-tmp.json",json_encode($data));
                $connection->send('receive success');
            };
        };
    }


    public function debug($info){
        echo "[".date("Y-m-d h-i-s")."]: ".$info."\r\n ";
    }


}
