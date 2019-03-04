<?php
/**
 * workerman服务端
 */
use Workerman\Worker;
use Workerman\Lib\Timer;
require_once './vendor/workerman/workerman/Autoloader.php';
// 初始化一个worker容器，监听1234端口

define('HEARTBEAT_TIME', 55);

global $worker;
$worker = new Worker('websocket://0.0.0.0:1234');
// 这里进程数必须设置为1
$worker->count = 1;
// worker进程启动后建立一个内部通讯端口
$worker->onWorkerStart = function($worker)
{
//    Timer::add(1, function()use($worker){
//        $time_now = time();
//        foreach($worker->connections as $connection) {
//            // 有可能该connection还没收到过消息，则lastMessageTime设置为当前时间
//            if (empty($connection->lastMessageTime)) {
//                $connection->lastMessageTime = $time_now;
//                continue;
//            }
//            // 上次通讯时间间隔大于心跳间隔，则认为客户端已经下线，关闭连接
//            if ($time_now - $connection->lastMessageTime > HEARTBEAT_TIME) {
//                $connection->close();
//            }
//        }
//    });
    global $redis;
    $redis = new Redis();
    $redis->connect('127.0.0.1',6379);
    // 开启一个内部端口，方便内部系统推送数据，Text协议格式 文本+换行符
    $inner_text_worker = new Worker('Text://0.0.0.0:5678');
    $inner_text_worker->onMessage = function($connection, $buffer)
    {
        global $worker;
        // $data数组格式，里面有uid，表示向那个uid的页面推送数据
        //$data = json_decode($buffer, true);
        //$uid = $data['uid'];
        #如果客户端链接为空，为避免等待，直接回复成功
        if(empty($worker->uidConnections)){
            $connection->send('ok');
            return true;
        }
        foreach($worker->uidConnections as $con)
        {
            $ret = sendMessageByUid($con->uid,$buffer);
            //var_dump($con->uid);
            // 返回推送结果
            $connection->send($ret ? 'ok' : 'fail');
        }
        // 通过workerman，向uid的页面推送数据
        //$ret = sendMessageByUid($uid, $buffer);
        // 返回推送结果
        //$connection->send($ret ? 'ok' : 'fail');
    };
    $inner_text_worker->listen();
};
// 新增加一个属性，用来保存uid到connection的映射
$worker->uidConnections = array();
// 当有客户端发来消息时执行的回调函数
$worker->onMessage = function($connection, $data)use($worker)
{
    global $redis;
    // 给connection临时设置一个lastMessageTime属性，用来记录上次收到消息的时间
    $connection->lastMessageTime = time();
    // 判断当前客户端是否已经验证,既是否设置了uid
    $data = json_decode($data,true);
    if(!isset($data['type'])) return;
    switch($data['type']){
        case 'login':
            #判断是否验证uid
            $connection->uid = $data['uid'];
            /* 保存uid到connection的映射，这样可以方便的通过uid查找connection，
             * 实现针对特定uid推送数据
             */
            if(!isset($worker->uidConnections[$connection->uid]) || !$worker->uidConnections[$connection->uid]){
                $worker->uidConnections[$connection->uid] = $connection;
            }
            $redis->sAdd('client_info',$connection->uid);
            break;
        case 'pong':    #客户端回应服务端的心跳
            //var_dump($data);
            sendMessageByUid($connection->uid, json_encode(['type'=>'ping']));  #发送心跳包
            break;
    }
    var_dump($redis->sMembers('client_info'));
//    if(!isset($connection->uid))
//    {
//        // 没验证的话把第一个包当做uid（这里为了方便演示，没做真正的验证）
//        var_dump($data);
//        $connection->uid = $data;
//        /* 保存uid到connection的映射，这样可以方便的通过uid查找connection，
//         * 实现针对特定uid推送数据
//         */
//        $worker->uidConnections[$connection->uid] = $connection;
//        return;
//    }else{
//        $data = json_decode($data,true);
//        switch($data['type'])
//        {
//            // 客户端回应服务端的心跳
//            case 'pong':
//                sendMessageByUid($connection->uid, json_encode(['type'=>'ping']));
//                return;
//        }
//    }
};

// 当有客户端连接断开时
$worker->onClose = function($connection)use($worker)
{
    global $worker;
    global $redis;
    if(isset($connection->uid))
    {
        // 连接断开时删除映射
        unset($worker->uidConnections[$connection->uid]);
    }
    $redis->sMove('client_info','removeSet',$connection->uid);
};

// 向所有验证的用户推送数据
function broadcast($message)
{
    global $worker;
    foreach($worker->uidConnections as $connection)
    {
        $connection->send($message);
    }
}

// 针对uid推送数据
function sendMessageByUid($uid, $message)
{
    global $worker;
    if(isset($worker->uidConnections[$uid]))
    {
        $connection = $worker->uidConnections[$uid];
        $connection->send($message);
        return true;
    }
    return false;
}

// 运行所有的worker（其实当前只定义了一个）
Worker::runAll();