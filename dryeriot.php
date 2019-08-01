<?php
require_once __DIR__ . '/vendor/autoload.php';

use think\Validate;
use Workerman\Lib\Timer;
use Workerman\Worker;

// #### create socket and listen 1234 port ####
$tcp_worker = new Worker("tcp://0.0.0.0:61234");

define('HEARTBEAT_TIME', 130);
/**
 *  向设备发送指令后，设备回传的数据，转发到指定URL
 */
define('SEND_BACK_URL', 'http://127.0.0.1/aaa/callback.php');

define('DEBUG', 1);

// 4 processes
$tcp_worker->count = 1;

$onlineUsers = [];

$thinkLog = new \think\Log;

// 进程启动后设置一个每秒运行一次的定时器
$tcp_worker->onWorkerStart = function($worker) {
    global $thinkLog;
    // 日志初始化
    $thinkLog->init([
        'debug'=>true,
        'type'=>'file',
        'path'=>'./logs/',
    ]);

    Timer::add(1, function()use($worker){
        $time_now = time();
        foreach($worker->connections as $connection) {
            // 有可能该connection还没收到过消息，则lastMessageTime设置为当前时间
            if (empty($connection->lastMessageTime)) {
                $connection->lastMessageTime = $time_now;
                continue;
            }
            // 上次通讯时间间隔大于心跳间隔，则认为客户端已经下线，关闭连接
            if ($time_now - $connection->lastMessageTime > HEARTBEAT_TIME) {
                    showLog(" timeout " .
                        $connection->getRemoteIp() . ":". $connection->getRemotePort());
                $connection->destroy();
            }
        }
    });
};

// Emitted when new connection come
$tcp_worker->onConnect = function($connection)
{
    // 给connection临时设置一个lastMessageTime属性，用来记录上次收到消息的时间
    $connection->lastMessageTime = time();
    $connection->id = $connection->worker->id .'_' . $connection->id;

    // 连接到来后，定时30秒关闭这个链接，需要30秒内发认证并删除定时器阻止关闭连接的执行
    $auth_timer_id = Timer::add(60, function($connection){
        showLog('60秒未注册，关闭连接');
        $connection->destroy();
    }, array($connection), false);
    $connection->auth_timer_id = $auth_timer_id;


    showLog(" onConnect " .
    $connection->getRemoteIp() . ":". $connection->getRemotePort());
};

// Emitted when data received
$tcp_worker->onMessage = function($connection, $data)
{
    // 给connection临时设置一个lastMessageTime属性，用来记录上次收到消息的时间
    $connection->lastMessageTime = time();

    // send data to client
    showLog("onMessage: ".$data);

    $tmp = json_decode($data,true);
    if(empty($tmp)){
        showLog('onMessage error data ' . $data);
    }
    else{
        if ('reg' == $tmp['action'] || 'xin' == $tmp['action']) {
            addOnlineUser($tmp, $connection);
        } elseif ('send_to_imei' == $tmp['action']) {
            sendToImei($connection,$tmp['data']);
        } elseif ('get_users' == $tmp['action']) {
            getUsers($connection);
        }
        elseif ('send_back' == $tmp['action']){
            sendCallBackData(array_merge($tmp['data'],['action'=>'send_back']));
        }
        else{
            $connection->send("error data $data \n");
        }
    }
};

// Emitted when new connection come
$tcp_worker->onClose = function($connection)
{
    $imei = '未注册';
    if (!empty($connection->imei)) {
        $imei = $connection->imei;
    }
    showLog(" onClose imei " .$imei . ' ' .
    $connection->getRemoteIp() . ":". $connection->getRemotePort());

    // 发送设备离线消息
    sendOfflineData($connection);
};
/**
 * 加入用户，自动判断 注册 和 心跳包
 * @param array $data
 * @param $connection
 */
function addOnlineUser(array $data,$connection){
    global $onlineUsers;
    if ('reg' == $data['action']) {
        $tmpCon = null;
        // 注册 如果 以前 有 IMEI 存在，先关闭掉连接，然后替换
        if (array_key_exists($data['data']['imei'], $onlineUsers)) {
            // 判断 id 是否相同，如果相同说明是重复注册，不需要关掉连接
            if($onlineUsers[$data['data']['imei']]->id != $connection->id){
//                $onlineUsers[$data['data']['imei']]->close();
                $tmpCon = $onlineUsers[$data['data']['imei']];
            }
        }
        // 给连接增加 IMEI 属性
        $connection->imei = $data['data']['imei'];
        $onlineUsers[$data['data']['imei']] = $connection;
        Timer::del($connection->auth_timer_id);
        // 赋值后再关闭，否则会造成 发送 离线 回调的bug
        if ($tmpCon != null) {
            $tmpCon->destroy();
        }

    } elseif ('xin' == $data['action']) {
        if (!array_key_exists($data['data']['imei'], $onlineUsers)) {
            // 提示 并加入
            showLog('心跳包找不到 IMEI '.$data['data']['imei'],'error');

            $onlineUsers[$data['data']['imei']] = $connection;
        }
    }
}

/**
 * 记录日志
 * @param $msg
 * @param string $type
 */
function showLog($msg, $type = 'info'){
    global $thinkLog;
    if (!empty(DEBUG)) {
        echo date('Y-m-d H:i:s') . ' '.$type.' '.$msg . "\n";
    }
    $thinkLog->record($msg, $type);
}

/**
 * 对某在线用户发送消息
 * @param $sendUser
 * @param $data
 */
function sendToImei($sendUser,$data){
    $rules = [
        'imei'  => 'require',
        'msg'  => 'require',
    ];

    $field = [
        'imei'  => ' imei ',
        'msg'  => ' msg ',
    ];
    $validate   = Validate::make($rules,[],$field);
    $result = $validate->check($data);

    if(!$result) {
        $msg = 'sendToImei ' . $validate->getError();
        showLog($msg,'error');
        backErrorData($sendUser, 'send_to_imei', [],$msg);
        return;
    }

    global $onlineUsers;
    if (!array_key_exists($data['imei'], $onlineUsers)) {
        showLog('sendToImei IMEI 不在线 '.$data['imei'],'error');
        backErrorData($sendUser, 'send_to_imei', [],"sendToImei IMEI offline");
    }
    else{
        $onlineUsers[$data['imei']]->send($data['msg']);
        backSuccessData($sendUser,'send_to_imei',[],'send ok');
    }
}

/**
 * 获取当前在线用户
 * @param $sendUser
 */
function getUsers($sendUser){
    global $onlineUsers;
    $datas = [];
    foreach ($onlineUsers as $key => $onlineUser) {
        $datas[] = [
            'imei' => $key,
            'last_time' => date('Y-m-d H:i:s',$onlineUser->lastMessageTime),
        ];
    }
    backSuccessData($sendUser, 'get_users', $datas);
}

function backSuccessData($con,$type,$data,$msg = ''){
    backData($con, $type,0, $data,$msg);
}

function backErrorData($con,$type,$data,$msg = ''){
    backData($con, $type,1, $data,$msg);
}

/**
 * 回发消息
 * @param $con
 * @param $type
 * @param $data
 * @param string $msg
 */
function backData($con,$type,$code,$data,$msg = ''){
    $fanhui = [
        'back_type' => $type,
        'code' => $code,
        'msg' => $msg,
        'data' => $data,
    ];
    $con->send(json_encode($fanhui,256));
}

/**
 * 发送设备离线消息
 * @param $con
 */
function sendOfflineData($con){
    // 检测 当前下线的 连接 是否存在 注册的设备，防止重复发送离线消息
    global $onlineUsers;
    $imei = '';
    foreach ($onlineUsers as $key => $onlineUser) {
        if ($onlineUser->id == $con->id) {
            $imei = $key;
        }
    }
    if (!empty($imei)) {
        sendCallBackData([
            'action' => 'offline',
            'imei' => $imei,
            'data' => [
                'time' => time()
            ],
        ]);
    }
}

function sendCallBackData($data){
    postData(SEND_BACK_URL,$data);
}

function postData($url,$data){
    $http = new Yurun\Util\HttpRequest;
    $response = $http->post($url,$data);
    showLog('postData ' . $response->body());
}

Worker::runAll();