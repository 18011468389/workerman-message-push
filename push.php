<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/4
 * Time: 16:18
 * 服务端异步推送方法,将需要推送的数据组装成数据，调用方法即可
 */
function push_order_info($data){
    try{
        $client = stream_socket_client('tcp://127.0.0.1:5678', $errno, $errmsg, 1);
        // 推送的数据，包含uid字段，表示是给这个uid推送
        $send_data = array('type' => 'push','data'=>$data);
        // 发送数据，注意5678端口是Text协议的端口，Text协议需要在数据末尾加上换行符
        fwrite($client, json_encode($send_data)."\n");
        // 读取推送结果
        return fread($client, 8192);
    }catch (\Exception $e){
        echo $e->getMessage();
    }
}