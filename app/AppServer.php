<?php
/**
 * Created by mohuishou<1@lailin.xyz>.
 * User: mohuishou<1@lailin.xyz>
 * Date: 2016/11/18 0018
 * Time: 12:16
 */

namespace App;

use App\Models\Lock;
use App\Models\User;
use Workerman\Lib\Timer;
use PHPSocketIO\SocketIO;

class AppServer{
    /**
     * 缓存文件路径
     * @var string
     */
    protected $_tmp_path=__DIR__."/../tmp";

    /**
     * @var
     */
    protected $_old_data;

    /**
     * @var
     */
    protected $_time_id_map;

    /**
     * @var SocketIO
     */
    protected $_sender_io;

    /**
     * @var
     */
    protected $_connection_map;

    /**
     * AppServer constructor.
     */
    public function __construct()
    {
        $this->_sender_io=new SocketIO(8400);
    }

    /**
     * 开始方法
     * @author mohuishou<1@lailin.xyz>
     */
    public  function start(){
        //建立连接
        $this->connection();
    }


    /**
     * 连接
     * @author mohuishou<1@lailin.xyz>
     */
    protected function connection(){
        $this->_sender_io->on('connection',function($socket){
            $this->login($socket);

            $this->bind($socket);

            $this->openLock($socket);

            $this->disconnect($socket);

        });
    }

    /**
     * 登录事件
     * @author mohuishou<1@lailin.xyz>
     * @param $socket
     */
    protected function login($socket){
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
            if(!isset($this->_time_id_map[$uid])){

                //设置定时器
                $this->_time_id_map[$uid]=Timer::add(3,function () use($socket) {
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
                    if((time()-$data->time)>12){
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
                //返回用户设备情况
                if(file_exists($this->_tmp_path."/".$this->_connection_map[$uid]."-tmp.json")){
                    $data=@file_get_contents($this->_tmp_path."/".$this->_connection_map[$uid]."-tmp.json");
                }else{
                    $data='{"lock_id": "12345","is_stolen": "0","is_low_battery": "0","lon": "104.06","lat": "30.67","time":0}';
                    $data=json_decode($data);
                    $data->status=0;
                    $this->_old_data=$data;
                    $socket->emit("lock_status",$data);
                    $this->debug("初始化数据已发送!文件不存在");
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
                $this->debug("初始化数据已发送!");
                $socket->emit("lock_status",$data);
            }
        });
    }

    /**
     * 绑定事件
     * @author mohuishou<1@lailin.xyz>
     * @param $socket
     */
    protected function bind($socket){
        //绑定设备
        $socket->on("bind",function ($datas) use ($socket){
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
    }

    /**
     * 断开连接
     * @author mohuishou<1@lailin.xyz>
     * @param $socket
     */
    protected function disconnect($socket){
        // 当客户端断开连接是触发（一般是关闭网页或者跳转刷新导致）
        $socket->on('disconnect', function () use($socket) {
            if(!isset($socket->uid))
            {
                return;
            }
            $user=User::find($socket->uid);
            $this->debug("user: ".$user->phone."disconnect ");
            if(isset($this->_time_id_map[$socket->uid])){
                Timer::del($this->_time_id_map[$socket->uid]);
                $this->debug("定时器".$socket->uid."已关闭");
                unset($this->_time_id_map[$socket->uid]);
            }
            if(isset($this->_connection_map[$socket->uid]))
                unset($this->_connection_map[$socket->uid]);

        });
    }

    /**
     * 开锁
     * @author mohuishou<1@lailin.xyz>
     * @param $socket
     */
    protected function openLock($socket){
        $socket->on("open_lock",function ($data) use ($socket){
            if(isset($data["lock_id"]))
                @file_put_contents($this->_tmp_path."/".$data["lock_id"]."-open.json",1);
        });
    }

    public function debug($info){
        echo "[AppServer][".date("Y-m-d h-i-s")."]: ".$info."\r\n ";
    }
}