<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);


session_start();
date_default_timezone_set('Asia/Shanghai');

use \GatewayWorker\Lib\Gateway;
use \Workerman\Worker;

global $dataaddr;
global $datakeys;
$dataaddr = Array('dryer'=>Array(
    'operation'         => '010300000001840A',
    'abnormal'          => '010300010001D5CA',
    'operatingtype'     => '0103000B0001F5C8',
    'grainsortmp'       => '0103000C00014409',
    'targetmst'         => '0103000D000115C9',
    'loadedamt'         => '0103000E0001E5C9',
    'settmp'            => '010300110001D40F',
    'mstcorrection'     => '010300120001240F',
    'currentmst'        => '010300140001C40E',
    'mstvar'            => '01030015000195CE',
    'hotairtmp'         => '01030016000165CE',
    'outsidetmp'        => '010300170001340E',
    'operatinghour1'    => '0103001C000145CC',
    'operatinghour2'    => '0103001D000114O0',
    'fullloaded'        => '01030021000115C9',
    'hotairtmptarget'   => '01030025000195C1',
    'timersetting'      => '0103002E0001E403',
    'error12'           => '010300640001C5D5',
    'error34'           => '0103006500019415',
    'operatedhour1'     => '0103006E0001E5D7',
    'operatedhour2'     => '0103006F0001B417',
    'model'             => '0103007800010413'));
$datakeys = Array('dryer'=> array_keys($dataaddr['dryer']));
/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{
    public static function onWorkerStart($businessWorker)
    {
       global $db;
       $db = new \Workerman\MySQL\Connection('localhost', '3306', 'root', '', 'yamamoto');
    }

    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     * 
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id)
    {
        // 向当前client_id发送数据 
        // Gateway::sendToClient($client_id, "Hello $client_id\r\n");
        // 向所有人发送
        // Gateway::sendToAll("$client_id login\r\n");
        $_SESSION['id'] = '';
        $_SESSION['clintid'] = $client_id;
        $_SESSION['sort'] = '';
        $_SESSION['cycleindex'] = 0;
        $_SESSION['cyclecount'] = 0;
        $_SESSION['connectbegin'] = new DateTime();
        $_SESSION['connectend'] = '';
    }
    
    /**
     * 当客户端发来消息时触发
     * @param int $client_id 连接id
     * @param mixed $message 具体消息
     */
    public static function onMessage($client_id, $message)
    {
        global $dataaddr;
        global $datakeys;
        // 向所有人发送
        //Gateway::sendToAll("$client_id said $message\r\n");
        //解包数据
        $amsg = unpack("a*", $message);
        $data = join($amsg);
        $head = substr($data, 0, 3);
        //Dryer 心跳包
        if ($head == '$$$') {
            $_SESSION['now'] = new DateTime();
            $eid = substr($data, 3, 4);
            //第一次心跳 初始化设备参数
            if (!$_SESSION['sort']) {
                $_SESSION['eid'] = $eid;
                $_SESSION['sort'] = 'dryer';
                $_SESSION['addr'] = 0x01;
                $_SESSION['record'] = array_fill_keys($datakeys['dryer'], '');
                $_SESSION['cyclecount'] = 0;
                $_SESSION['cycleindex'] = 0;
                $_SESSION['connectbegin'] = new DateTime();
                $_SESSION['gps'] = array('lat' => 0, 'lon' => 0, 'velocity' => 0, 'direction' => 0, 'type' => '', 'locationdate'=>'');
                //print_r("===============================");
                //持续心跳  循环次数递增 参数地址恢复
            } elseif ($_SESSION['cycleindex'] + 1 == count($datakeys[$_SESSION['sort']])) {
                $_SESSION['cyclecount'] = $_SESSION['cyclecount'] + 1;
                $_SESSION['cycleindex'] = 0;
                //print_r("===============================");
            }
            //发送第一笔数据请求
            //GateWay::sendAddr($client_id);
            //Dryer GPS包
        }
        //Dryer GPS包
        elseif($head == '$GP')
        {
            $adata = explode(',', $data);
            if ($adata[0] != '$GPRMC') { } elseif (count($adata) < 12) { } elseif ($adata[2] == 'A') {
                $lat = $adata[3];
                $fLat = ($adata[4] == 'N' ? 1 : -1) * (int) substr($lat, 0, strlen($lat) - 8) + (float) substr($lat, -8) / 60;
                $lon = $adata[5];
                $fLon = ($adata[6] == 'E' ? 1 : -1) * (int) substr($lon, 0, strlen($lon) - 8) + (float) substr($lon, -8) / 60;
                $type = substr($adata[12], 1);

                $_SESSION['gps']['lat'] = $fLat; //纬度
                $_SESSION['gps']['lon'] = $fLon; //经度
                $_SESSION['gps']['velocity'] = (float) $adata[7] * 1.852 / 3.6; //速度 m/s
                $_SESSION['gps']['direction'] = $adata[8]; //方向
                $_SESSION['gps']['type'] = substr($adata[12], 1); //定位态别
                $_SESSION['gps']['date'] = new DateTime(); //定位时间
            }
        }
        //Dryer 数据
        elseif($data[0]==0x01 && $data[1]==0x03 && $data[2]==0x02)
        {
            $_SESSION['data'][$datakeys[$_SESSION['sort']][$_SESSION['cycleindex']]] = $data[3]*64 + $data[3];
            $_SESSION['cyclecount'] = $_SESSION['cyclecount'] + 1;
            GateWay::sendAddr($client_id);
            GateWay::saveStatus($client_id);
            GateWay::saveCycle($client_id);
        //平台
        }elseif($data[0]==0x5A && $data[1]==0xA5){

        }
    }
   
    /**
     * 当用户断开连接时触发
     * @param int $client_id 连接id
     */
    public static function onClose($client_id)
    {
        // 向所有人发送 
        GateWay::sendToAll("$client_id logout\r\n");
    }

    //发送数据地址
    public static function sendRecordAddr()
    {
        global $dataaddr;
        global $datakeys;
        var_dump($_SESSION['sort']);
        if(array_key_exists('cycleindex',$_SESSION) 
        && array_key_exists('sort',$_SESSION) 
        && array_key_exists($_SESSION['sort'],$datakeys) 
        && $_SESSION['cycleindex']<count($datakeys[$_SESSION['sort']])){
            $client_id = $_SESSION['clientid'];
            $hexs=$dataaddr[$_SESSION['sort']][$datakeys[$_SESSION['cycleindex']]];
            GateWay::sendToClient($client_id,pack('C*', $_SESSION['addr'],0x03,
                                                        hex2bin(substr($hexs,0,2)),
                                                        hex2bin(substr($hexs,2,2)),
                                                        hex2bin(substr($hexs,4,2)),
                                                        hex2bin(substr($hexs,6,2)),
                                                        hex2bin(substr($hexs,8,2)),
                                                        hex2bin(substr($hexs,10,2)) ));
        }
    }
    public static function saveRecord()
    {
        global $dataaddr;
        global $datakeys;
        global $db;
        if($_SESSION['cycleindex']+1>count($datakeys[$_SESSION['sort']]))
        {
            //每十次心跳保存一次cycle
            if($_SESSION['cyclecount'] % 10 == 9){
                
            }
        }
    }
    public static function saveCycle()
    {
        global $dataaddr;
        global $datakeys;
        global $db;
        
    }
}
