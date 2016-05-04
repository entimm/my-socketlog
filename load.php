<?php
// ws://localhost:1229
// client_id：slog_660c28
// 
// ws://slog.thinkphp.cn:1229
// client_id：slog_660c28
// 
// 本地执行：socketlog-server
// 本地后台执行：socketlog-server > /dev/null &
// 需开启1229和1116两个端口
// 
// 还可以用它来分析开源程序，分析SQL性能，结合taint分析程序漏洞
//  taint能自动检测出xss，sql注入， 如果只用php taint， 它warning报错只告诉了变量输出的地方，并不知道变量在那里赋值、怎么传递。
//  通过SocketLog， 能看到调用栈，轻松对有问题变量进行跟踪。

ini_set( 'display_errors', 'On' );
error_reporting(E_ALL ^ E_NOTICE);

include 'slog.function.php';

slog(array(
    'enable'              => true,                 // 是否打印日志的开关
    'host'                => 'slog.thinkphp.cn',   // websocket服务器地址，默认localhost | slog.thinkphp.cn
    'optimize'            => true,                 // 是否显示利于优化的参数，如运行时间，消耗内存等，默认为false
    'show_included_files' => true,                 // 是否显示本次程序运行加载了哪些文件，默认为false
    'error_handler'       => true,                 // 是否接管程序错误，将程序错误显示在console中，默认为false
    'force_client_ids'    => array('slog_660c28'), // 日志强制记录到配置的client_id,默认为空
    'allow_client_ids'    => array('slog_660c28')  //限制允许读取日志的client_id，默认为空,表示所有人都可以获得日志。
),'config');

slog('time = '.date('H:i:s').', IP = '.$_SERVER["REMOTE_ADDR"],'log','color:#4E17FF;font-size:12px;');
slog('user_agent = '.$_SERVER['HTTP_USER_AGENT']);
session_start();
slog(array('get' => $_GET, 'post' => $_POST, 'session' => $_SESSION, 'cookie' => $_COOKIE));

// slog('msg','log');  //一般日志
// slog('msg','error'); //错误日志
// slog('msg','info'); //信息日志
// slog('msg','warn'); //警告日志
// slog('msg','trace');// 输入日志同时会打出调用栈
// slog('msg','alert');//将日志以alert方式弹出
// slog('msg','log','color:red;font-size:20px;');//自定义日志的样式，第三个参数为css样式
// 第一个参数是日志内容，日志内容不光能支持字符串哟，大家如果传递数组,对象等一样可以打印到console中。
// 第二个参数是日志类型，可选，如果没有指定日志类型默认类型为log， 第三个参数是自定样式，在这里写上你自定义css样式即可。

// 用slog函数打印sql语句是，第二个参数传递为mysql或mysqli的对象即可。 示例代码：
/*
    $link=mysql_connect( 'localhost:3306' , 'root' , '123456' , true ) ;
    mysql_select_db('kuaijianli',$link);
    $sql="SELECT * FROM `user`";
    slog($sql,$link);
*/
// 注意，有时候在数据比较少的情况下，mysql查询不会使用索引，explain也会提示Using filesort等性能问题， 
// 其实这时候并不是真正有性能问题， 你需要自行进行判断，或者增加更多的数据再测试。
