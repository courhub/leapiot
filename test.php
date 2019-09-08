<?php
require_once __DIR__ . '\app\yama\Events.php';
require_once __DIR__ . '\vendor\autoload.php';

date_default_timezone_set('Asia/Shanghai');

use \GatewayWorker\Lib\Gateway;
use \Workerman\Worker;

/**
 * $dataaddr = array(
 *   'dryer' => array(
 *      'operation'         => '010300000001840A',
 *      'abnormal'          => '010300010001D5CA',
 *      'operatingtype'     => '0103000B0001F5C8',
 *      'grainsortmp'       => '0103000C00014409',
 *      'targetmst'         => '0103000D000115C9',
 *      'loadedamt'         => '0103000E0001E5C9',
 *      'settmp'            => '010300110001D40F',
 *      'mstcorrection'     => '010300120001240F',
 *      'currentmst'        => '010300140001C40E',
 *      'mstvar'            => '01030015000195CE',
 *      'hotairtmp'         => '01030016000165CE',
 *      'outsidetmp'        => '010300170001340E',
 *      'operatinghour1'    => '0103001C000145CC',
 *      'operatinghour2'    => '0103001D000114O0',
 *      'fullloaded'        => '01030021000115C9',
 *      'hotairtmptarget'   => '01030025000195C1',
 *      'timersetting'      => '0103002E0001E403',
 *      'error12'           => '010300640001C5D5',
 *      'error34'           => '0103006500019415',
 *      'operatedhour1'     => '0103006E0001E5D7',
 *      'operatedhour2'     => '0103006F0001B417',
 *      'model'             => '0103007800010413')
 *);
 */



/**
 * pack/unpack的模板字符含義
 * a 一個填充空的字符串
 * A 一個填充空格的字符串
 * b 一個位串，在每個字節里位的順序都是升序
 * B 一個位串，再每個字節裡位的順序都是降序
 * c 一個有符號char（8位整數）值
 * C 一個無符號char（8位整數）值    ascii轉字符串
 * h 一個十六進制串，低四位在前
 * H 一個十六進制串，高四位在前
 * s 一个有符号短整数值，16位
 * S 一个無符号短整数值，16位
 * 
 * 1.每个字母后面都可以跟着一个数字，表示 count（计数），如果 count 是一个 * 表示剩下的所有东西。
 * 2.如果你提供的参数比 $format 要求的少，pack 假设缺的都是空值。如果你提供的参数比 $format 要求的多，那么多余的参数被忽略
 */

//心跳包$$$$，及設備ID  format=a*
$message = "$$$0001";
//$message = unpack("C*",$message);
//$message = pack("C*",0x64,0x37,0x38,0x04,0x04,0x04,0x04,0x04);

//設備數據地址  format=H2h1/H2h2/H2h3
//$message = '010300640001C5D5';
//$message = pack("H*",$message);

//GPS   format=a*
//$message = '$GPRMC,225530.000,A,3637.26040,N,11700.56340,E,10.000,97.17,220512,,,D*57';
var_dump($message);
//var_dump(pack("H*",$message)); 
$client_id = 'FFFF0001CCCC0001';

//$amsg = unpack("a*", $message);

//$data = join($amsg);
//$head = substr($data, 0, 3);
//$eid = substr($data, 3, 4);
//var_dump(array($amsg,$data, $head, $eid));

Events::onConnect($client_id);
Events::onMessage($client_id, $message);
//Events::onClose($client_id);
//Events::sendRecordAddr();

var_dump($_SESSION);