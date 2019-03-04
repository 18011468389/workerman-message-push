# 功能说明：
    利用workerman简单实现服务端异步消息推送


# 目录结构
~~~
   workerman-message-push   文件目录
    |-- nginx_wss        利用nginx做wss反向代理（服务器配置ssl情况下）
    |-- push-server.php  workerman服务端
    |-- push.php         消息推送方法
    |-- test.html        前端接收样例
~~~


使用说明：
~~~
    * 启动后端服务  php push-server.php start -d (-d守护模式启动，调试阶段可不加，方便查看数据)
    * 组装推送数据调用push.php中push_info()方法;
    * 前端接收数据并处理
~~~

