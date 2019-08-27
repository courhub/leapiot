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
        Gateway::setSession($client_id,array('id'=>'','clientid'=>$client_id,'sort'=>'','addrindex'=>0));
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
            if(!Gateway::getSession($client_id,'sort')){
                Gateway::setSession($client_id,array('sort'=>'dryer','cycleindex'=>0,'data'=>array_fill_keys($datakeys['dryer'],''));
                Gateway::setSession($client_id,array('cyclestart'=>new DataTime()));
            }
        //Dryer GPS包
        }elseif($head == '$GP'){
            $adata = $data.explode();
        //Dryer 数据
        }elseif($data[0]==0x01 && $data[1]==0x03){
        
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
}
