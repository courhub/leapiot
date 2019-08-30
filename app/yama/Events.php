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

use \GatewayWorker\Lib\Gateway;
use \Workerman\Worker;

global $dataaddr;
global $datakeys;
$dataaddr = Array('dryer'=>Array('operation' => '01 03 00 00 00 01 84 0A',
    'abnormal' => '01 03 00 01 00 01 D5 CA',
    'operatingtype' => '01 03 00 0B 00 01 F5 C8',
    'grainsortmp' => '01 03 00 0C 00 01 44 09',
    'targetmst' => '01 03 00 0D 00 01 15 C9',
    'loadedamt' => '01 03 00 0E 00 01 E5 C9',
    'settmp' => '01 03 00 11 00 01 D4 0F',
    'mstcorrection' => '01 03 00 12 00 01 24 0F',
    'currentmst' => '01 03 00 14 00 01 C4 0E',
    'mstvar' => '01 03 00 15 00 01 95 CE',
    'hotairtmp' => '01 03 00 16 00 01 65 CE',
    'outsidetmp' => '01 03 00 17 00 01 34 0E',
    'operatinghour1' => '01 03 00 1C 00 01 45 CC',
    'operatinghour2' =>'01 03 00 1D 00 01 14 O0',
    'fullloaded' => '01 03 00 21 00 01 15 C9',
    'hotairtmptarget' => '01 03 00 25 00 01 95 C1',
    'timersetting' => '01 03 00 2E 00 01 E4 03',
    'error12' => '01 03 00 64 00 01 C5 D5',
    'error34' => '01 03 00 65 00 01 94 15',
    'operatedhour1' => '01 03 00 6E 00 01 E5 D7',
    'operatedhour2' => '01 03 00 6F 00 01 B4 17',
    'model' => '01 03 00 78 00 01 04 13'));
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
       $db = new \Workerman\MySQL\Connection('host', 'port', 'user', 'password', 'db_name');
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
        Gateway::setSession($client_id,array('id'=>'','clientid'=>$client_id,'sort'=>'','cycleindex'=>0,'connectbegin'=>new DataTime(),'cyclecount'=>0));
        $_SESSION['id'] = '';
        $_SESSION['clintid'] = $client_id;
        $_SESSION['sort'] = '';
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
        Gateway::sendToAll("$client_id said $message\r\n");
        //解包数据
        $data = unpack("C*",$message);
        $head = substr($data,0,3);
        //Dryer 心跳包
        if($head == '$$$' )
        {
            $eid = substr($data,3,4);
            //第一次心跳 初始化设备参数
            if(!Gateway::getSession($client_id,'sort')){
                $_SESSION['sort'] = 'dryer';
                $_SESSION['addr'] = 0x01;
                $_SESSION['data'] = array_fill_keys($datakeys['dryer'],'');
                $_SESSION['cyclecount'] = 0;
                $_SESSION['cycleindex'] = 0;
                $_SESSION['connectbegin'] = new DateTime();
                $_SESSION['gps'] = Array('lat'=> 0,'lon'=>0, 'velocity'=>0, 'direction'=>0, 'type' = '');   //纬度
            
            //持续心跳  循环次数递增 参数地址恢复
            }elseif($_SESSION['cycleindex']+1==count($datakeys[$_SESSION['sort']])){
                $_SESSION['cyclecount'] = $_SESSION['cyclecount'] + 1;
                $_SESSION['cycleindex'] = 0;
            }
            //发送第一笔数据请求
            GateWay::sendAddr($client_id);
        //Dryer GPS包
        }elseif($head == '$GP'){
            $adata = $data.explode(',');
            if($adata[0]!='$GPRMC'){
            }elseif(count($adata)<10){
            }elseif($adata[2]=='A'){
                $lat=$adata[3];
                $fLat=($adata[4]=='N'?1:-1) * (int)substr($lat,0,strlen($lat)-7) + (float)substr($lat,-7) / 60;
                $lon=$adata[5];
                $fLon=($adata[6]=='E'?1:-1) * (int)substr($lon,0,strlen($lon)-7) + (float)substr($lon,-7) / 60;
                $_SESSION['gps']['lat']=$fLat; //纬度
                $_SESSION['gps']['lon']=$fLon; //经度
                $_SESSION['gps']['velocity']=(float)$adata[7] * 1.852 / 3.6; //速度 m/s
                $_SESSION['gps']['direction']=$adata[8]; //方向
                $_SESSION['gps']['type']=substr($adata[12],1); //定位态别
            }
        //Dryer 数据
        }elseif($data[0]==0x01 && $data[1]==0x03 && $data[2]==0x02){
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
    public static function sendAddr($client_id)
    {
        global $dataaddr;
        global $datakeys;
        if($_SESSION['cycleindex']<count($datakeys[$_SESSION['sort']])){
            $hexs=$addaddr[$_SESSION['sort']][$datakeys[$_SESSION['cycleindex']]];
            GateWay::sendToClient($client_id,pack('C*', $_SESSION['addr'],0x03,
                                                        hex2bin(substr($hexs,0,2)),
                                                        hex2bin(substr($hexs,2,2)),
                                                        hex2bin(substr($hexs,4,2)),
                                                        hex2bin(substr($hexs,6,2)),
                                                        hex2bin(substr($hexs,8,2)),
                                                        hex2bin(substr($hexs,10,2)) ));
        }
    }
    public static function saveStatus($client_id)
    {
        global $dataaddr;
        global $datakeys;
        global $db;
        if($_SESSION['cycleindex']+1>count($datakeys[$_SESSION['sort']]))
        {
            if($_SESSION['cyclecount'] % 10 == 0){
                
            }
        }
    }
    public static function saveCycle($client_id)
    {
        global $dataaddr;
        global $datakeys;
        global $db;
        
    }
}
