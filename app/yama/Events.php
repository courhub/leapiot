<?php
require_once __DIR__ . '/../../config.php';

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
use \Workerman\Connection\TcpConnection;
use \Workerman\Connection\AsyncTcpConnection;
use \Workerman\Lib\Timer;
/**
 * 将配置文件的配置信息复制到global变量$conf中
 */
global $conf;
$conf = $config;

/**
 * 接收設備的心跳和定位信息並查詢其他信息
 * 保存設備的狀態和cycle信息到數據庫 $db
 * 發送設備信息到Tcp平台 $sv
 * 接收Tcp平台的查詢並返回結果
 */
global $db, $sv, $svpipe, $svsn;
$db = null; //database
$sv = null; //conntoserver
$svpipe = array(); //向Tcp平台發送信息的緩存數據 key=>msg key為隨機順序號
$svsn = 0; //Tcp數據包的隨機序號 int，最大65535，循環使用。

global $dataaddr, $datakeys;
$dataaddr = array(1 => array(
    'operating'         => '0000',
    'abnormal'          => '0001',
    'operatingtype'     => '000B',
    'grain'             => '000C',
    'targetmst'         => '000D',
    'loadedamt'         => '000E',
    'settmp'            => '0011',
    'mstcorrection'     => '0012',
    'currentmst'        => '0014',
    'mstvar'            => '0015',
    'hotairtmp'         => '0016',
    'outsidetmp'        => '0017',
    'operatinghour1'    => '001C',
    'operatinghour2'    => '001D',
    'fullloaded'        => '0021',
    'hotairtmptarget'   => '0025',
    'timersetting'      => '002E',
    'error12'           => '0064',
    'error34'           => '0065',
    'operatedhour2'     => '006E',
    'operatedhour1'     => '006F',
    'model'             => '0078'
));
$datakeys = array(1 => array_keys($dataaddr[1]));

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{
    /**
     * 当服務啟動時触发
     * 鏈接數據庫
     * 鏈接TcpServer(外部數據接收平台)
     * @param obj $businessworker 啟動的Workerman
     */
    public static function onWorkerStart($businessWorker)
    {
        echo __LINE__." : Worker start.\n";
        Events::connDatabase();
        Events::connServer();
    }
    /**
     * 鏈接數據庫
     */
    public static function connDatabase()
    {
        global $conf;
        global $db;
        echo __LINE__." : Conn Database.\n";
	    $db = new \Workerman\MySQL\Connection($conf['db']['host'], $conf['db']['port'], $conf['db']['user'], $conf['db']['pwd'], $conf['db']['name']);
    }
    /**
     * 鏈接TcpServer(外部數據接收平台)
     */
    public static function connServer()
    {
        global $conf;
        global $sv;
	    echo __LINE__." : Conn Tcp server. \n";
        //異步链接远端TCP服务器
        $sv = new AsyncTcpConnection('tcp://'.$conf['tcp']['host']);
        $sv->onConnect = function ($sv) {
            echo __LINE__." : Tcp Server Call In. From " . $sv->getRemoteIp(). ":" .$sv->getRemotePOrt() . "\n";
            $timer_interval = 10;
            //每隔10秒向TCP服務器發送緩存的數據（FIFO),如果接收到發送成功的回復則承從緩存的數組中刪除此數據。
            Timer::add($timer_interval, function () {
                global $sv;
                global $svpipe;
                if ($svpipe) {
                    $sv->send(array_slice($svpipe, 0, 1)[0]);
                }
            });
        };
        /**
         * 設定Tcp鏈接數據處理方法
         * @param AsyncTcpConnection $sv 異步鏈接
         * @param bin $msg 收到的消息
         */
        $sv->onMessage = function ($sv, $msg) {
            global $svpipe;
            global $conf;
            $svh  = unpack("H4head/H4factory/H4psn/H4sort/H2io/H4sn/H8len/H4result/H*datatime", $msg);    //十六進制字符串數組a-address；f-function;l-length;d-data;c-crc16
            if ($svh['head'] != '5aa5' || hexdec($svh['factory']) != $conf['tcp']['fsn']) {
                //非法信息，不予處理
            } else if ($svh['io'] == '01' && $svh['result'] == '0000') {
                //應答信息，正確結果；刪除傳輸緩存中的信息
                $key = hexdec($svh['sn']);
                if (array_key_exists($key, $svpipe)) {
                    unset($svpipe[$key]);
                }
            } else if ($svh['io'] == '01') {
                //應答信息，錯誤結果
            } else if ($svh['io'] == '00' && $svh['sort'] == '0004') {
                //查詢設備狀態
                Events::tcpRecord(hexdec($svh['psn']), 1);
            }
        };
        $sv->onClose = function ($sv) {
            $sv->reConnect(1);
        };
        $sv->onError = function($connection, $err_code, $err_msg)
        {
            echo __LINE__." : TCP ERROR. ERR_CODE:$err_code, ERR_MSG:$err_msg\n";
        };
        $sv->connect();
    }
    /**
     * 產生Tcp信息隨機序列號，每次+1，超過65535時從1開始。
     */
    public static function getSvSn()
    {
        global $svsn;
        $svsn = $svsn >= 65535 ? 1 : ++$svsn;
        return $svsn;
    }
    /**
     * 增加Tcp信息緩存
     */
    public static function addSvPipe($sn, $data)
    {
        global $svpipe;
        global $conf;
        if (count($svpipe) > $conf['tcp']['pipelen']) {
            $svpipe = array_slice($svpipe, count($svpipe) - $conf['tcp']['pipelen'], $conf['tcp']['pipelen'], true);
        }
        $svpipe[$sn] = $data;
        return $svpipe;
    }
    public static function hexDateTime()
    {
        $now = explode(',', (new DateTime())->format('Y,m,d,H,i,s'));
        return  substr('000' . dechex((int) $now[0]), -4) .
            substr('000' . dechex((int) $now[1]), -4) .
            substr('000' . dechex((int) $now[2]), -4) .
            substr('000' . dechex((int) $now[3]), -4) .
            substr('000' . dechex((int) $now[4]), -4) .
            substr('000' . dechex((int) $now[5]), -4);
    }

    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id)
    {
        $_SESSION['id'] = 0;
        $_SESSION['clientid'] = $client_id;
        $_SESSION['psn'] = 0;
        $_SESSION['sort'] = 0;
        $_SESSION['connectbegin'] = '';
        $_SESSION['connectend'] = '';
        $_SESSION['heartcount'] = 0;
        $_SESSION['gpscount'] = 0;

        $_SESSION['recordindex'] = 0;
        $_SESSION['cyclecount'] = 0;
        $_SESSION['lastrecord'] = 0;
        $_SESSION['lastcycle'] = 0;
    }

    /**
     * 当客户端发来消息时触发
     * @param int $client_id 连接id
     * @param mixed $message 具体消息
     */
    public static function onMessage($client_id, $message)
    {
        global $datakeys;
        global $db;
        if (!array_key_exists('sort', $_SESSION)) {
            Events::onConnect($client_id);
        }
        $now = (new DateTime())->format('Y-m-d H:i:s');
        //解包数据
        $amsg = unpack("a*", $message);                     //字符數組
        $data = join($amsg);                                //字符串
        $head = substr($data, 0, 3);                        //前三個字母
        $hexa = null;
        echo __LINE__." : ONMESSAGE [".$data."][".join('',unpack('H*',$message))."] @".$client_id.'--'.$_SESSION['psn']."\n";
        
        if(strlen($data)>5){
            $hexa = unpack("H2a/H2f/H2l/H4d/H4c", $message);    //十六進制字符串數組a-address；f-function;l-length;d-data;c-crc16
        }
        //var_dump($hexa);
        //var_dump($_SESSION['addrh']);
        //Dryer 註冊包  @@@7162485b30dbe2644067b6ebc5ebe0af001 字符串+PWD+PSN
        if ($head == '@@@') {
            $psn = hexdec(substr($data, -4));
            $pwd = substr($data, 3,strlen($data) - 7);
            $entity = $db->select("id,psn,sort,addr,lastgps,cast(spec->'$.model' as signed) AS model,cast(spec->'$.tonnage' as signed) AS tonnage")
                ->from('ym_entity')
                ->where("flag=:flag and pwd=:pwd and psn=:psn")
                ->bindValues(array('flag' => 1, 'psn' => $psn, 'pwd' => $pwd))
                ->row();
            //var_dump($entity);
            if ($entity) {
                //登入，初始化session參數
                $_SESSION = $entity + $_SESSION;
                $_SESSION['addrh'] = substr('0' . dechex($_SESSION['addr']), -2); //地址位2位十六进制
                $_SESSION['psnh'] = substr('000' . dechex($_SESSION['psn']), -4); //厂家序号4位十六进制
                $_SESSION['cyclecount'] = $_SESSION['recordindex'] = 0;
                $_SESSION['connectbegin'] = $_SESSION['cyclebegin'] = $_SESSION['recordbegin'] = $now;
                $_SESSION['gps'] = array_fill_keys(array('lat', 'lon', 'velocity', 'direction', 'type', 'cdate', 'sdate'), null);
                $_SESSION['record'] = array_fill_keys($datakeys[$entity['sort']], null);
                $_SESSION += array('recordindex' => 0, 'recordcount' => 0, 'cyclecount' => 0, 'lastrecord' => 0, 'lastcycle' => 0, 'last' => 0);
                if($entity['lastgps']>0){
                    $gps = $db->select("lat,lon,velocity,direction,type,cdate,cdate as sdate")
                        ->from('ym_gps')
                        ->where("id=:id")
                        ->bindValues(array('id'=>$entity['lastgps']))
                        ->row();
                    if($gps){
                        $_SESSION['gps']=$gps;
                    }
                }
                //绑定Uid,group
                Gateway::bindUid($client_id, $_SESSION['psn']);
                Gateway::joinGroup($client_id, $_SESSION['sort']);
                echo __LINE__." : Client LOGIN Success!\n";
            } else {
                //登出
                //session_destroy();
                Gateway::destoryCurrentClient();
                echo __LINE__." : Client LOGIN Fail!\n";
            }
        }
        //Dryer GPS包
        elseif ($head == '$GP') {
            $adata = explode(',', $data);
            if ($adata[0] == '$GPRMC' && count($adata) >= 12 && $adata[2] == 'A') {
                $lat = $adata[3];
                $fLat = ($adata[4] == 'N' ? 1 : -1) * (int) substr($lat, 0, strlen($lat) - 7) + (float) substr($lat, -7) / 60;
                $lon = $adata[5];
                $fLon = ($adata[6] == 'E' ? 1 : -1) * (int) substr($lon, 0, strlen($lon) - 7) + (float) substr($lon, -7) / 60;
                $type = substr($adata[12], 0, 1);
                $_SESSION['gps']['lat'] = $fLat+3.14159; //纬度
                $_SESSION['gps']['lon'] = $fLon-2.71828; //经度
                $_SESSION['gps']['velocity'] = ((float) $adata[7]) * 1.852 / 3.6; //速度 m/s
                $_SESSION['gps']['direction'] = (float) $adata[8]; //方向
                $_SESSION['gps']['type'] = $type; //定位态别
                $_SESSION['gps']['cdate'] = (new DateTime())->format('Y-m-d H:i:s'); //定位时间
                $_SESSION['gpscount'] += 1;
                Events::saveGps();
		        echo __LINE__." : GPS OK ".$_SESSION['gps']['lat'].','.$_SESSION['gps']['lon'].' count:'.$_SESSION['gpscount']."\n";
            }else{
                echo __LINE__." : GPS NG \n";
            }
        }
        //Dryer 心跳包  $$$
        elseif ($head == '$$$') {
            $_SESSION['heartcount'] += 1;
            if ($_SESSION['recordindex'] >= count($datakeys[$_SESSION['sort']])) {
                $_SESSION['cyclebegin'] = $_SESSION['recordbegin'] = $now;
                $_SESSION['cyclecount'] += 1;
                $_SESSION['recordindex'] = 0;
            	echo __LINE__." : $$$ CYCLECOUNT+1 = ".$_SESSION['cyclecount']."\n";
	    }
            //发送第一笔数据请求
            Events::sendRecordAddr($client_id);
            //Gateway::sendToClient($client_id,pack('H*','010300000001840a'));
        }
        //Dryer 数据    
        elseif ($hexa['a'] . $hexa['f'] . $hexa['l'] == $_SESSION['addrh'] . '0302') {
            $keyscount = count($datakeys[$_SESSION['sort']]);
            //echo __LINE__." : RECORDINDEX = ".$_SESSION['recordindex'].' ; KEYSCOUNT = '.$keyscount."\n";
            $crc16 = CrcTool::crc16(pack("H*", $hexa['a'] . $hexa['f'] . $hexa['l'] . $hexa['d']));
            
            if (unpack("H4s", $crc16)['s'] != $hexa['c']) {
                echo "            CRC NG \n";
            }else if($_SESSION['recordindex'] < $keyscount){
                $_SESSION['record'][$datakeys[$_SESSION['sort']][$_SESSION['recordindex']]] = hexdec($hexa['d']);
                echo "            CRC OK\n";
                $_SESSION['recordindex'] += 1;
                Events::sendRecordAddr($client_id);
            }
            if($_SESSION['recordindex'] == $keyscount){
                echo "            RECORD OK\n";
                Events::saveRecord($client_id);
                Events::saveCycle($client_id);
                $_SESSION['recordindex'] += 1;
            }
        }
        //Dryer ERROR CODE
        elseif ($hexa['a'] . $hexa['f'] . $hexa['l'] == $_SESSION['addrh'] . '8301') {
            $crc16 = CrcTool::crc16(pack("H*", $hexa['a'] . $hexa['f'] . $hexa['l']));
            if (unpack("H4s", $crc16)['s'] == $hexa['d']) {
                $_SESSION['errorcode'] = unpack("s1int", pack("H*", $hexa['l']))['int'];
                $_SESSION['cyclecount'] += 1;
            }
            echo __LINE__." : ERROR CODE  @".$client_id.$_SESSION['psn']."\n";
        }
        //向SV发送位置信息
        if ($_SESSION['sort'] == 1 && ($_SESSION['gpscount'] == 1 || $_SESSION['recordcount'] == 1) && $_SESSION['connectbegin'] != '') {
            Events::tcpGps($_SESSION['psn']);
            echo __LINE__." : ".$now." SNED TCP SERVER GPS OF ".$_SESSION['psn']."\n";
        }
        //向SV发送状态信息
        if ($_SESSION['sort'] == 1 && $_SESSION['recordcount']) {
            Events::tcpRecord($_SESSION['psn'], 0);
            echo __LINE__." : ".$now."  SEND TCP SERVER RECORD OF ".$_SESSION['psn']."\n";
        }
        if ($_SESSION['psn']==3){
            //print_r($_SESSION['record']);
            echo __LINE__." : ";
            $i = 0;
            foreach($_SESSION['record'] as $k => $v){
                echo "$k=>$v;  ";
                if(++$i % 8 == 0)
                    echo "\n      ";
            }
            echo "\n";
        }
    }

    /**
     * 当用户断开连接时触发
     * @param int $client_id 连接id
     */
    public static function onClose($client_id)
    {
        // 向所有人发送 
        //GateWay::sendToAll("$client_id logout\r\n");
        global $db;
        $now = (new DateTime())->format('Y-m-d H:i:s');
        if ($_SESSION['lastrecord'] > 0) {
            $db->update('ym_record')->cols(array('tdate' => $now))->where('id = 0' . $_SESSION['lastrecord'])->query();
        }
        if ($_SESSION['lastcycle'] > 0) {
            $db->update('ym_cycle')->cols(array('edate' => $now, 'type' => 2, 'erecord' => $_SESSION['lastrecord']))->query();
            $cid = $db->select('id')->from('ym_cycle')
                ->where("stage=1 and flag=1 and erecord=:erecord and entity=:entity")
                ->bindValues(array('entity' => $_SESSION['entity'], 'erecord' => $_SESSION['lastrecord']))
                ->single();
            if ($cid) {
                Events::tcpCycle($cid);
            }
        }
        session_destroy();
    }

    //发送数据地址
    public static function sendRecordAddr($client_id)
    {
        global $dataaddr;
        global $datakeys;
        if (
            array_key_exists('recordindex', $_SESSION)
            && array_key_exists('sort', $_SESSION)
            && array_key_exists($_SESSION['sort'], $datakeys)
            && $_SESSION['recordindex'] < count($datakeys[$_SESSION['sort']])
        ) {
            $hexs = $_SESSION['addrh'] . '03' . $dataaddr[$_SESSION['sort']][$datakeys[$_SESSION['sort']][$_SESSION['recordindex']]] . '0001';
            $crc16 = CrcTool::crc16(pack("H*", $hexs));
            $data = pack('H*',$hexs . unpack("H4s", $crc16)['s']);
            Gateway::sendToClient($client_id, $data);
            //Gateway::sendToClient($client_id,pack("H*",'010300000001840a'));
            echo __LINE__.' : SEND ADDR OK ['.$_SESSION['cyclecount'].':'.$_SESSION['recordindex'].']';
        }else{
            echo __LINE__.' : SEND ADDR NG ['.$_SESSION['cyclecount'].';'.$_SESSION['recordindex'].']';
        }
    }
    public static function saveGps()
    {
        global $db;
        if (!array_key_exists('gpscount', $_SESSION) or $_SESSION['gpscount'] == 0 or $_SESSION['id'] == 0) {
            return;
        }
        if (
            $_SESSION['gps']['sdate'] == null
            or  strtotime($_SESSION['gps']['cdate']) - strtotime($_SESSION['gps']['sdate']) >= 3000
        ) {
            $insert_id = 0;
            $para = array(
                'entity' => $_SESSION['id'],
                'lon' => $_SESSION['gps']['lon'],
                'lat' => $_SESSION['gps']['lat'],
                'velocity' => $_SESSION['gps']['velocity'],
                'direction' => $_SESSION['gps']['direction'],
                'type' => $_SESSION['gps']['type'],
                'cdate' => $_SESSION['gps']['cdate']
            );
            $insert_id = $db->insert('ym_gps')->cols($para)->query();
            if ($insert_id > 0) {
                $_SESSION['lastgps'] = (int) $insert_id;
                $_SESSION['gps']['sdate'] = $_SESSION['gps']['cdate'];
                echo __LINE__." : SAVE GPS\n";
            }
        }
    }
    public static function saveRecord()
    {
        global $datakeys;
        global $db;
        if (
            $_SESSION['recordindex'] + 1 > count($datakeys[$_SESSION['sort']])
            && $_SESSION['cyclecount'] % 18 == 1
        ) {
            $insert_id = 0;
            $dt = (new DateTime())->format('Y-m-d H:i:s');
            $para = json_encode(Events::dbRecordFmt($_SESSION['record']));

            $db->beginTrans();
            if ($_SESSION['lastrecord'] > 0) {
                $db->update('ym_record')->cols(array('tdate' => $dt))->where('id = 0' . $_SESSION['lastrecord'])->query();
            }
            $insert_id = $db->insert('ym_record')->cols(array('entity' => $_SESSION['id'], 'operating' => $_SESSION['record']['operating'], 'fdate' => $dt, 'para' => $para))->query();
            $db->commitTrans();
            if ($insert_id > 0) {
                $_SESSION['lastrecord'] = $insert_id;
            }
            echo __LINE__." : SAVE RECORD\n";
        }
    }
    public static function saveCycle()
    {
        global $db;
        $insert_id = 0;
        $dt = (new DateTime())->format('Y-m-d H:i:s');
        if ($_SESSION['lastrecord'] > 0) {
            $db->beginTrans();
            if ($_SESSION['lastcycle'] > 0) {
                $db->update('ym_cycle')->cols(array('edate' => $dt, 'erecord' => $_SESSION['lastrecord']))->where('id = 0' . $_SESSION['lastcycle'])->query();
            }
            $insert_id = $db->insert('ym_cycle')->cols(array(
                'entity' => $_SESSION['id'], 'bdate' => $dt, 'stage' => 0,
                'type' => $_SESSION['record']['operatingtype'], 'amt' => $_SESSION['record']['loadedamt'], 'unit' => 'T',
                'kind' => $_SESSION['record']['grain'], 'brecord' => $_SESSION['lastrecord']
            ))->query();
            $db->commitTrans();
            if ($insert_id) {
                $_SESSION['lastcycle'] = $insert_id;
                echo __LINE__." : SAVE CYCLE\n";
            }
            Events::tcpCycle($insert_id);
        }
    }

    public static function dbRecordFmt($data)
    {
        $record = $data;
        $record['targetmst'] = $record['targetmst'] > 0 ? ($record['targetmst'] / 10) : $record['targetmst'];
        $record['mstcorrection'] = $record['mstcorrection'] / 10;
        $record['currentmst'] = $record['currentmst'] / 10;
        $record['mstvar'] = $record['mstvar'] / 100;
        $record['operatinghour'] = ($record['operatinghour1'] + $record['operatinghour2']) / 3600;
        unset($record['operatinghour1']);
        unset($record['operatinghour2']);
        $record['operatedhour'] = $record['operatedhour1'] * 10000 + $record['operatedhour2'];
        unset($record['operatedhour1']);
        unset($record['operatedhour2']);
        $record['timersetting'] = $record['timersetting'] * 10;
        return $record;
    }
    public static function tcpGps($psn)
    {
        global $sv;
        $data = array('model' => '0000', 'tonnage' => '0000', 'lond' => '00', 'lonf' => '00', 'lonm' => '00', 'latd' => '00', 'latf' => '00', 'latm' => '00');
        if (Gateway::isUidOnline($psn)) {
            //var_dump(GateWay::getClientIdByUid($psn)[0]);
            $session = Gateway::getSession(Gateway::getClientIdByUid($psn)[0]);
        } else {
            $session = array();
        }
        if ($session) {
            $data['model'] = substr('0000' . dechex($session['model']), -4);
            $data['tonnage'] = substr('0000' . dechex($session['tonnage']), -4);
            $lon = $session['gps']['lon'];
            $lond = (int) $lon;
            $lonf = (int) ($lon * 60) - $lond * 60;
            $lonm = (int) round($lon * 3600, 0) - $lond * 3600 - $lonf * 60;
            $data['lond'] = substr('00' . dechex($lond), -2);
            $data['lonf'] = substr('00' . dechex($lonf), -2);
            $data['lonm'] = substr('00' . dechex($lonm), -2);
            $lat = $session['gps']['lat'];
            $latd = (int) $lat;
            $latf = (int) ($lat * 60) - $latd * 60;
            $latm = (int) round($lat * 3600, 0) - $latd * 3600 - $latf * 60;
            $data['latd'] = substr('00' . dechex($latd), -2);
            $data['latf'] = substr('00' . dechex($latf), -2);
            $data['latm'] = substr('00' . dechex($latm), -2);
        }
        $sn = Events::getSvSn();
        $head = array('psn' => substr('0000' . dechex($psn), -4), 'sort' => '0001', 'io' => '00', 'sn' => substr('0000' . dechex($sn), -4), 'len' => '0000000E');

        $msg = pack(
            "H4H4H4H4H2H4H8" . "H4H4H2H2H2H2H2H2H8",
            '5aa5',
            '0003',
            $head['psn'],
            $head['sort'],
            $head['io'],
            $head['sn'],
            $head['len'],
            $data['model'],
            $data['tonnage'],
            $data['lond'],
            $data['lonf'],
            $data['lonm'],
            $data['latd'],
            $data['latf'],
            $data['latm'],
            '00000000'
        );
        Events::addSvPipe($sn, $msg);
        $sv->send($msg);
        echo __LINE__." : SEND TCP SERVER GPS ".join('',unpack('H*',$msg))."\n";
    }
    public static function tcpRecord($psn, $io)
    {
        global $sv;
        $data = array(
            'run' => '0000', 'operating' => '0005', 'grain' => '0004',
            'hotairtmp' => '00000000', 'outsidetmp' => '00000000',
            'status' => '0000', 'alert' => '0000', 'datetime' => Events::hexDateTime()
        );
        if (Gateway::isUidOnline($psn)) {
            $session = Gateway::getSession(Gateway::getClientIdByUid($psn));
        } else {
            $session = array();
        }
        if ($session) {
            $data['run'] = '0001';
            $data['operating'] = substr('0000' . dechex($session['operating']), -4);
            $data['grain'] = substr('0000' . dechex($session['grain']), -4);
            $data['hotairtmp'] = substr('00000000' . dechex((int) round($session['hotairtmp'] * 10, 0)), -8);
            $data['outsidetmp'] = substr('00000000' . dechex((int) round($session['outsidetmp'] * 10, 0)), -8);
            $data['status'] = substr('0000' . dechex($session['status']), -4);
            $data['alert'] = substr('0000' . deshex($session['alert']), -4);
        }
        $sn = Events::getSvSn();
        $head = array('psn' => substr('0000' . dechex($psn), -4), 'sort' => '0002', 'io' => '00', 'sn' => substr('0000' . dechex($sn), -4), 'len' => '0000001E');
        if ($io == 1) {
            $head['sort'] = '0004';
            $head['io'] = '01';
        }
        $msg = pack(
            "H4H4H4H4H2H4H8" . "H4H4H4H8H8H8H4H4H24",
            '5aa5',
            '0003',
            $head['psn'],
            $head['sort'],
            $head['io'],
            $head['sn'],
            $head['len'],
            $data['run'],
            $data['operating'],
            $data['grain'],
            $data['currentmst'],
            $data['hotairtmp'],
            $data['outsidetmp'],
            $data['status'],
            $data['alert'],
            $data['datetime']
        );

        if ($io == 0) {
            Events::addSvPipe($sn, $msg);
        }
        $sv->send($msg);
        echo __LINE__." : SEND TCP SERVER RECORD ".join('',unpack('H*',$msg))."\n";
    }
    public static function tcpCycle($cid)
    {
        global $db, $sv;

        $data = $db->select("ym_entity.id AS id,ym_entity.psn AS psn,round(ym_entity.spec->'$.model') AS model,round(ym_entity.spec->'$.tonnage') AS tonnage,date_format(ym_cycle.bdate,'%Y%m%d%H%i') bd,date_format(ym_cycle.edate,'%Y%m%d%H%i') ed,cast((ym_cycle.edate-ym_cycle.bdate)/10000*60 as signed) AS mins")
            ->from('ym_cycle')->innerJoin('ym_entity', 'ym_cycle.entity = ym_entity.id')
            ->where("ym_entity.flag=1 and ym_cycle.stage=1 and ym_cycle.flag=1 and ym_cycle.id=:cid")
            ->bindValues(array('cid' => $cid))
            ->row();
        if ($data) {
            $data['count'] = $db->select("count(*)")
                ->from('ym_cycle')
                ->where("stage=1 and flag=1 and entity=:entity")
                ->bindValues(array('entity' => $data['id']))
                ->single();

            $sn = Events::getSvSn();
            $head = array('psn' => substr('0000' . dechex($data['psn']), -4), 'sort' => '0003', 'io' => '00', 'sn' => substr('0000' . dechex($sn), -4), 'len' => '0000001E');

            $msg = pack(
                "H4H4H4H4H2H4H8" . "H4H4H4H8H4H4H4H4H4H4H4H4H4H4",
                '5aa5',
                '0003',
                $head['psn'],
                $head['sort'],
                $head['io'],
                $head['sn'],
                $head['len'],
                substr('0000' . dechex($data['model']), -4),
                substr('0000' . dechex($data['tonnage']), -4),
                substr('0000' . dechex($data['count']), -4),
                substr('00000000' . dechex($data['mins']), -8),
                substr('0000' . dechex((int) substr($data['bd'], 0, 4)), -4),
                substr('0000' . dechex((int) substr($data['bd'], 4, 2)), -4),
                substr('0000' . dechex((int) substr($data['bd'], 6, 2)), -4),
                substr('0000' . dechex((int) substr($data['bd'], 8, 2)), -4),
                substr('0000' . dechex((int) substr($data['bd'], 10, 2)), -4),
                substr('0000' . dechex((int) substr($data['ed'], 0, 4)), -4),
                substr('0000' . dechex((int) substr($data['ed'], 4, 2)), -4),
                substr('0000' . dechex((int) substr($data['ed'], 6, 2)), -4),
                substr('0000' . dechex((int) substr($data['ed'], 8, 2)), -4),
                substr('0000' . dechex((int) substr($data['ed'], 10, 2)), -4)
            );
            Events::addSvPipe($sn, $msg);
            $sv->send($msg);
            echo __LINE__." : SEND TCP SERVER CYCLE ".join('',unpack('H*',$msg))."\n";
        }
    }
}

class CrcTool
{

    static  function crc16($string, $length = 0)
    {

        $auchCRCHi = array(
            0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81,
            0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0,
            0x80, 0x41, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01,
            0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41,
            0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81,
            0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0,
            0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01,
            0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40,
            0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81,
            0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0,
            0x80, 0x41, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01,
            0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41,
            0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81,
            0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0,
            0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01,
            0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41,
            0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81,
            0x40
        );
        $auchCRCLo = array(
            0x00, 0xC0, 0xC1, 0x01, 0xC3, 0x03, 0x02, 0xC2, 0xC6, 0x06, 0x07, 0xC7, 0x05, 0xC5, 0xC4,
            0x04, 0xCC, 0x0C, 0x0D, 0xCD, 0x0F, 0xCF, 0xCE, 0x0E, 0x0A, 0xCA, 0xCB, 0x0B, 0xC9, 0x09,
            0x08, 0xC8, 0xD8, 0x18, 0x19, 0xD9, 0x1B, 0xDB, 0xDA, 0x1A, 0x1E, 0xDE, 0xDF, 0x1F, 0xDD,
            0x1D, 0x1C, 0xDC, 0x14, 0xD4, 0xD5, 0x15, 0xD7, 0x17, 0x16, 0xD6, 0xD2, 0x12, 0x13, 0xD3,
            0x11, 0xD1, 0xD0, 0x10, 0xF0, 0x30, 0x31, 0xF1, 0x33, 0xF3, 0xF2, 0x32, 0x36, 0xF6, 0xF7,
            0x37, 0xF5, 0x35, 0x34, 0xF4, 0x3C, 0xFC, 0xFD, 0x3D, 0xFF, 0x3F, 0x3E, 0xFE, 0xFA, 0x3A,
            0x3B, 0xFB, 0x39, 0xF9, 0xF8, 0x38, 0x28, 0xE8, 0xE9, 0x29, 0xEB, 0x2B, 0x2A, 0xEA, 0xEE,
            0x2E, 0x2F, 0xEF, 0x2D, 0xED, 0xEC, 0x2C, 0xE4, 0x24, 0x25, 0xE5, 0x27, 0xE7, 0xE6, 0x26,
            0x22, 0xE2, 0xE3, 0x23, 0xE1, 0x21, 0x20, 0xE0, 0xA0, 0x60, 0x61, 0xA1, 0x63, 0xA3, 0xA2,
            0x62, 0x66, 0xA6, 0xA7, 0x67, 0xA5, 0x65, 0x64, 0xA4, 0x6C, 0xAC, 0xAD, 0x6D, 0xAF, 0x6F,
            0x6E, 0xAE, 0xAA, 0x6A, 0x6B, 0xAB, 0x69, 0xA9, 0xA8, 0x68, 0x78, 0xB8, 0xB9, 0x79, 0xBB,
            0x7B, 0x7A, 0xBA, 0xBE, 0x7E, 0x7F, 0xBF, 0x7D, 0xBD, 0xBC, 0x7C, 0xB4, 0x74, 0x75, 0xB5,
            0x77, 0xB7, 0xB6, 0x76, 0x72, 0xB2, 0xB3, 0x73, 0xB1, 0x71, 0x70, 0xB0, 0x50, 0x90, 0x91,
            0x51, 0x93, 0x53, 0x52, 0x92, 0x96, 0x56, 0x57, 0x97, 0x55, 0x95, 0x94, 0x54, 0x9C, 0x5C,
            0x5D, 0x9D, 0x5F, 0x9F, 0x9E, 0x5E, 0x5A, 0x9A, 0x9B, 0x5B, 0x99, 0x59, 0x58, 0x98, 0x88,
            0x48, 0x49, 0x89, 0x4B, 0x8B, 0x8A, 0x4A, 0x4E, 0x8E, 0x8F, 0x4F, 0x8D, 0x4D, 0x4C, 0x8C,
            0x44, 0x84, 0x85, 0x45, 0x87, 0x47, 0x46, 0x86, 0x82, 0x42, 0x43, 0x83, 0x41, 0x81, 0x80,
            0x40
        );
        $length = ($length <= 0 ? strlen($string) : $length);
        $uchCRCHi = 0xFF;
        $uchCRCLo = 0xFF;
        $uIndex = 0;
        for ($i = 0; $i < $length; $i++) {
            $uIndex = $uchCRCLo ^ ord(substr($string, $i, 1));
            $uchCRCLo = $uchCRCHi ^ $auchCRCHi[$uIndex];
            $uchCRCHi = $auchCRCLo[$uIndex];
        }
        return (chr($uchCRCLo) . chr($uchCRCHi));
    }
}
