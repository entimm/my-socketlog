<?php

class Slog
{
    public static $start_time=0;
    public static $start_memory=0;
    public static $port=1116;//SocketLog 服务的http的端口号
    public static $log_types=array('log','info','error','warn','table','group','groupCollapsed','groupEnd','alert');
    
    protected static $_allowForceClientIds = array();    //配置强制推送且被授权的client_id

    protected static $_instance;

    protected static $config=array(
        'enable'=>true, //是否记录日志的开关
        'host'=>'localhost',
        //是否显示利于优化的参数，如果允许时间，消耗内存等
        'optimize'=>false,
        'show_included_files'=>false,
        'error_handler'=>false,
        //日志强制记录到配置的client_id
        'force_client_ids'=>array(),
        //限制允许读取日志的client_id
        'allow_client_ids'=>array()
    );

    protected static $logs=array();

    protected static $css=array(
        'sql'=>'color:#009bb4;',
        'sql_warn'=>'color:#009bb4;font-size:14px;',
        'error_handler'=>'color:#f4006b;font-size:14px;',
        'page'=>'color:#40e2ff;background:#171717;'
    );

    public static function __callStatic($method,$args)
    {
        if(in_array($method,self::$log_types))
        {
            array_unshift($args,$method);
            return call_user_func_array(array(self::getInstance(),'record'),$args);
        }
    }

   public static function sql($sql,$link)
    {
        if(is_object($link) && 'mysqli'==get_class($link))
        {
               return self::mysqlilog($sql,$link);
        }

        if(is_resource($link) && ('mysql link'==get_resource_type($link) || 'mysql link persistent'==get_resource_type($link)))
        {
               return self::mysqllog($sql,$link);
        }


        if(is_object($link) && 'PDO'==get_class($link))
        {
               return self::pdolog($sql,$link);
        }

        throw new Exception('SocketLog can not support this database link');
    }    



    public static function big($log)
    {
            self::log($log,'font-size:20px;color:red;');
    }

    public static function trace($msg,$trace_level=1,$css='')
    {
        if(!self::check())
        {
            return ;
        }
        self::groupCollapsed($msg,$css);
        $traces=debug_backtrace(false);
        $traces=array_reverse($traces);
        $max=count($traces)-$trace_level;
        for($i=0;$i<$max;$i++){
            $trace=$traces[$i];
            $fun=isset($trace['class'])?$trace['class'].'::'.$trace['function']:$trace['function'];
            $file=isset($trace['file'])?$trace['file']:'unknown file';
            $line=isset($trace['line'])?$trace['line']:'unknown line';
            $trace_msg='#'.$i.'  '.$fun.' called at ['.$file.':'.$line.']';
            if(!empty($trace['args'])){
                self::groupCollapsed($trace_msg);
                self::log($trace['args']);
                self::groupEnd();
            }else{
                self::log($trace_msg);
            }
        }
        self::groupEnd();
    }


    public static function mysqlilog($sql,$db)
    {
        if(!self::check())
        {
            return ;
        }

        $css=self::$css['sql'];
        if(preg_match('/^SELECT /i', $sql))
        {
            //explain
            $query = @mysqli_query($db,"EXPLAIN ".$sql);
            $arr=mysqli_fetch_array($query);
            self::sqlexplain($arr,$sql,$css);
        }
        self::sqlwhere($sql,$css);
        self::trace($sql,2,$css);
    }


    public static function mysqllog($sql,$db)
    {
        if(!self::check())
        {
            return ;
        }
        $css=self::$css['sql'];
        if(preg_match('/^SELECT /i', $sql))
        {
            //explain
            $query = @mysql_query("EXPLAIN ".$sql,$db);
            $arr=mysql_fetch_array($query);
            self::sqlexplain($arr,$sql,$css);
        }
        //判断sql语句是否有where
        self::sqlwhere($sql,$css);
        self::trace($sql,2,$css);
    }


    public static function pdolog($sql,$pdo)
    {
        if(!self::check())
        {
            return ;
        }
        $css=self::$css['sql'];
        if(preg_match('/^SELECT /i', $sql))
        {
            //explain
            try {
                $obj=$pdo->query( "EXPLAIN ".$sql);
                if(is_object($obj) && method_exists($obj,'fetch'))
                {
                    $arr=$obj->fetch(\PDO::FETCH_ASSOC);
                    self::sqlexplain($arr,$sql,$css);
                }
            } catch (Exception $e) {

            }
        }
        self::sqlwhere($sql,$css);
        self::trace($sql,2,$css);
    }

    private static function sqlexplain($arr,&$sql,&$css)
    {
        $arr = array_change_key_case($arr, CASE_LOWER);
        if(false!==strpos($arr['extra'],'Using filesort'))
        {
              $sql.=' <---################[Using filesort]';
              $css=self::$css['sql_warn'];
        }
        if(false!==strpos($arr['extra'],'Using temporary'))
        {
              $sql.=' <---################[Using temporary]';
              $css=self::$css['sql_warn'];
        }
    }
    private static function sqlwhere(&$sql,&$css)
    {
        //判断sql语句是否有where
        if(preg_match('/^UPDATE |DELETE /i',$sql) && !preg_match('/WHERE.*(=|>|<|LIKE|IN)/i',$sql))
        {
           $sql.='<---###########[NO WHERE]';
           $css=self::$css['sql_warn'];
        }
    }


    /**
     * 接管报错
     */
    public static function registerErrorHandler()
    {
        if(!self::check())
        {
            return ;
        }

        set_error_handler(array(__CLASS__,'error_handler'));
        register_shutdown_function(array(__CLASS__,'fatalError'));
    }

    public static function error_handler($errno, $errstr, $errfile, $errline)
    {
        $cur_error_report = error_reporting();
        if(!($errno & $cur_error_report)) return;
        switch($errno){
            case E_WARNING: $severity = 'E_WARNING'; break;
            case E_NOTICE: $severity = 'E_NOTICE'; break;
            case E_USER_ERROR: $severity = 'E_USER_ERROR'; break;
            case E_USER_WARNING: $severity = 'E_USER_WARNING'; break;
            case E_USER_NOTICE: $severity = 'E_USER_NOTICE'; break;
            case E_STRICT: $severity = 'E_STRICT'; break;
            case E_RECOVERABLE_ERROR: $severity = 'E_RECOVERABLE_ERROR'; break;
            case E_DEPRECATED: $severity = 'E_DEPRECATED'; break;
            case E_USER_DEPRECATED: $severity = 'E_USER_DEPRECATED'; break;
            case E_ERROR: $severity = 'E_ERR'; break;
            case E_PARSE: $severity = 'E_PARSE'; break;
            case E_CORE_ERROR: $severity = 'E_CORE_ERROR'; break;
            case E_COMPILE_ERROR: $severity = 'E_COMPILE_ERROR'; break;
            case E_USER_ERROR: $severity = 'E_USER_ERROR'; break;
            default: $severity= 'E_UNKNOWN_ERROR_'.$errno; break;
        }
        $msg="{$severity}: {$errstr} in {$errfile} on line {$errline} -- SocketLog error handler";
        self::trace($msg,2,self::$css['error_handler']);
    }

    public static function fatalError()
    {
        // 保存日志记录
        if ($e = error_get_last())
        {
                self::error_handler($e['type'],$e['message'],$e['file'],$e['line']);
                self::sendLog();//此类终止不会调用类的 __destruct 方法，所以此处手动sendLog
        }
    }



    public static function getInstance()
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }


    protected static function check()
    {
        if(!self::getConfig('enable'))
        {
            return false;
        }
        $tabid=self::getClientArg('tabid');
         //是否记录日志的检查
        if(!$tabid && !self::getConfig('force_client_ids'))
        {
            return false;
        }
        //用户认证
        $allow_client_ids=self::getConfig('allow_client_ids');
        if(!empty($allow_client_ids))
        {
            //通过数组交集得出授权强制推送的client_id
            self::$_allowForceClientIds = array_intersect($allow_client_ids, self::getConfig('force_client_ids'));
            if (!$tabid && count(self::$_allowForceClientIds)) {
                return true;
            }

            $client_id=self::getClientArg('client_id');
            if(!in_array($client_id,$allow_client_ids))
            {
                return false;
            }
        }
        else
        {
            self::$_allowForceClientIds = self::getConfig('force_client_ids');
        }
        return true;
    }

    protected static function getClientArg($name)
    {
        static $args=array();

        $key = 'HTTP_USER_AGENT';

        if (isset($_SERVER['HTTP_SOCKETLOG'])) {
            $key = 'HTTP_SOCKETLOG';
        }

        if(!isset($_SERVER[$key]))
        {
            return null;
        }
        if(empty($args))
        {
            if(!preg_match('/SocketLog\((.*?)\)/',$_SERVER[$key],$match))
            {
                $args=array('tabid'=>null);
                return null;
            }
            parse_str($match[1],$args);
        }
        if(isset($args[$name]))
        {
            return $args[$name];
        }
        return null;
    }


    //设置配置
    public static function  config($config)
    {
        $config=array_merge(self::$config,$config);
        if(isset($config['force_client_id'])){
            //兼容老配置
            $config['force_client_ids']=array_merge($config['force_client_ids'],array($config['force_client_id'])); 
        }
        self::$config=$config;
        if(self::check())
        {
            self::getInstance(); //强制初始化SocketLog实例
            if($config['optimize'])
            {
                self::$start_time=microtime(true);
                self::$start_memory=memory_get_usage();
            }

            if($config['error_handler'])
            {
                self::registerErrorHandler();
            }
        }
    }


    //获得配置
    public static function  getConfig($name)
    {
        if(isset(self::$config[$name]))
            return self::$config[$name];
        return null;
    }

    //记录日志
    public function record($type,$msg='',$css='')
    {
        if(!self::check())
        {
            return ;
        }

        self::$logs[]=array(
            'type'=>$type,
            'msg'=>$msg,
            'css'=>$css
        );
    }

    /**
     * @param null $host - $host of socket server
     * @param string $message - 发送的消息
     * @param string $address - 地址
     * @return bool
     */
    public static function send($host,$message='',$address='/')
    {
        $url='http://'.$host.':'.self::$port.$address;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $headers=array(
                 "Content-Type: application/json;charset=UTF-8"
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);//设置header
        $txt = curl_exec($ch);
        return true;
    }

    public static function sendLog()
    {
        if(!self::check())
        {
            return ;
        }

        $time_str='';
        $memory_str='';
        if(self::$start_time)
        {
            $runtime=microtime(true)-self::$start_time;
            $reqs=number_format(1/$runtime,2);
            $time_str="[运行时间：{$runtime}s][吞吐率：{$reqs}req/s]";
        }
        if(self::$start_memory)
        {
            $memory_use=number_format((memory_get_usage()-self::$start_memory)/1024,2);
            $memory_str="[内存消耗：{$memory_use}kb]";
        }

        if(isset($_SERVER['HTTP_HOST']))
        {
            $current_uri=$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        }
        else
        {
            $current_uri="cmd:".implode(' ',$_SERVER['argv']);
        }
        array_unshift(self::$logs,array(
                'type'=>'group',
                'msg'=>$current_uri.$time_str.$memory_str,
                'css'=>self::$css['page']
        ));

        if(self::getConfig('show_included_files'))
        {
            self::$logs[]=array(
                    'type'=>'groupCollapsed',
                    'msg'=>'included_files',
                    'css'=>''
            );
            self::$logs[]=array(
                    'type'=>'log',
                    'msg'=>implode("\n",get_included_files()),
                    'css'=>''
            );
            self::$logs[]=array(
                    'type'=>'groupEnd',
                    'msg'=>'',
                    'css'=>'',
            );
        }

        self::$logs[]=array(
                'type'=>'groupEnd',
                'msg'=>'',
                'css'=>'',
        );

        $tabid=self::getClientArg('tabid');
        if(!$client_id=self::getClientArg('client_id'))
        {
            $client_id='';
        }
        if(!empty(self::$_allowForceClientIds))
        {
            //强制推送到多个client_id
            foreach(self::$_allowForceClientIds as $force_client_id) {
                $client_id=$force_client_id;
                self::sendToClient($tabid, $client_id, self::$logs, $force_client_id);
            }
        } else {
            self::sendToClient($tabid, $client_id, self::$logs, '');
        }
    }

    /**
     * 发送给指定客户端
     * @author Zjmainstay
     * @param $tabid
     * @param $client_id
     * @param $logs
     * @param $force_client_id
     */
    protected static function sendToClient($tabid, $client_id, $logs, $force_client_id) {
         $logs=array(
            'tabid'=>$tabid,
            'client_id'=>$client_id,
            'logs'=>$logs,
            'force_client_id'=>$force_client_id,
        ); 
        $msg=@json_encode($logs);
        $address='/'.$client_id; //将client_id作为地址， server端通过地址判断将日志发布给谁
        self::send(self::getConfig('host'),$msg,$address); 
    }

    public function __destruct()
    {
        self::sendLog();
    }

}

function slog($log,$type='log',$css='')
{
    if(is_string($type))
    {
        $type=preg_replace_callback('/_([a-zA-Z])/',create_function('$matches', 'return strtoupper($matches[1]);'),$type);
        if(method_exists('Slog',$type) || in_array($type,Slog::$log_types))
        {
           return  call_user_func(array('Slog',$type),$log,$css);
        }
    }

    if(is_object($type) && 'mysqli'==get_class($type))
    {
           return Slog::mysqlilog($log,$type);
    }

    if(is_resource($type) && ('mysql link'==get_resource_type($type) || 'mysql link persistent'==get_resource_type($type)))
    {
           return Slog::mysqllog($log,$type);
    }


    if(is_object($type) && 'PDO'==get_class($type))
    {
           return Slog::pdolog($log,$type);
    }

    throw new Exception($type.' is not SocketLog method');
}

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
