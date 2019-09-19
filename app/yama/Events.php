<?php
require_once __DIR__ . '\..\..\config.php';

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

$db = null; //database
$sv = null; //conntoserver
$dataaddr = array(1 => array(
    'operating'         => '0000',
    'abnormal'          => '0001',
    'operatingtype'     => '000B',
    'grainsortmp'       => '000C',
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
    public static function onWorkerStart($businessWorker)
    {
        Events::connDatabase();
        Events::connServer();
    }

    public static function connDatabase()
    {
        global $db;
        global $config;
        var_dump($config);
        $db = new \Workerman\MySQL\Connection($config['db']['host'], $config['db']['port'], $config['db']['user'], $config['db']['pwd'], $config['db']['name']);
        //$all_tables = $db->query('show tables');
        //查詢
        //var_dump($all_tables);
        //$rows = $db->select('*')->from('ym_para')->where('flag= :flag')->bindValues(array('flag' => 1))->query();
        //var_dump($rows);
        //$row = $db->select('*')->from('ym_para')->where('flag= :flag')->bindValues(array('flag' => 1))->row();
        //var_dump($row);

        //插入
        //$insert_id = $db->insert('ym_para')->cols(array('name'=>'', 'sort'=>'', 'key'=>''))->query();
        //更新
        //$row_count = $db->update('ym_para')->cols(array('name'))->where('ID=1')->bindValue('name','NAMES')->
        //json
        //$json = $db->query('SELECT "{\"a\": 1, \"b\": {\"c\": 30}}"');
        //var_dump($json);
        //select coalesce(null,2,3)
        //var_dump(md5('yama' . 'LEAPIOT'));
    }
    public static function connServer()
    {
        global $sv;
        //異步链接远端TCP服务器
        $sv = new AsyncTcpConnection('');
        $sv->onMessage = function ($sv, $msg) {
            //$sv->send('');
        };
        $sv->onClose = function ($sv) {
            $sv->reConnect(1);
        };
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
        // session_destroy();
        // session_start();
        $_SESSION['id'] = 0;
        $_SESSION['clientid'] = $client_id;
        $_SESSION['sort'] = 0;
        $_SESSION['recordindex'] = 0;
        $_SESSION['cyclecount'] = 0;
        $_SESSION['connectbegin'] = new DateTime();
        $_SESSION['connectend'] = '';
        $_SESSION['heartcount'] = 0;
        $_session['gpscount'] = 0;
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
        global $db;
        if (!array_key_exists('sort', $_SESSION)) {
            Events::onConnect($client_id);
        }
        $now = (new DateTime())->format('Y-m-d H:i:s');
        // 向所有人发送
        //Gateway::sendToAll("$client_id said $message\r\n");
        //解包数据
        $amsg = unpack("a*", $message);                     //字符數組
        $data = join($amsg);                                //字符串
        $head = substr($data, 0, 3);                        //前三個字母
        $hexa = unpack("H2a/H2f/H2l/H4d/H4c", $message);    //十六進制字符串數組a-address；f-function;l-length;d-data;c-crc16
        
        //Dryer 註冊包  @@@00017162485b30dbe2644067b6ebc5ebe0af 字符串+PSN+PWD
        if ($head == '@@@') {
            $psn = hexdec(substr($data, 3, 4));
            $pwd = substr($data, 7);
            $entity = $db->select('id,psn,sort,addr')
                ->from('ym_entity')
                ->where("flag=:flag and pwd=:pwd and psn=:psn")
                ->bindValues(array('flag' => 1, 'psn' => $psn, 'pwd' => $pwd))
                ->row();
            if ($entity) {
                //登入，初始化session參數
                session_destroy();
                $_SESSION += $entity;
                $_SESSION['addrh'] = substr('0'.dechex($_SESSION['addr']),-2); //地址位十六进制
                $_SESSION['psnh'] = substr('000'.dechex($_SESSION['psn']),-4); //地址位十六进制
                $_SESSION['cyclecount'] = $_SESSION['recordindex'] = 0;
                $_SESSION['connectbegin'] = $_SESSION['cyclebegin'] = $_SESSION['recordbegin'] = $now;
                $_SESSION['gps'] = array_fill_keys(array('lat', 'lon', 'velocity', 'direction', 'type', 'locationdate'), null);
                $_SESSION['record'] = array_fill_keys($datakeys[$entity['sort']], null);
                //绑定Uid,group
              //  Gateway::bindUid($client_id,$entity['id']);
              //  Gateway::joinGroup($client_id,$entity['sort']);
            } else {
                //登出
                session_destroy();
                Gateway::destoryCurrentClient();
            }
        }
        //Dryer GPS包
        elseif ($head == '$GP') {
            $adata = explode(',', $data);
            if ($adata[0] == '$GPRMC' && count($adata) >= 12 && $adata[2] == 'A') {
                $lat = $adata[3];
                $fLat = ($adata[4] == 'N' ? 1 : -1) * (int) substr($lat, 0, strlen($lat) - 8) + (float) substr($lat, -8) / 60;
                $lon = $adata[5];
                $fLon = ($adata[6] == 'E' ? 1 : -1) * (int) substr($lon, 0, strlen($lon) - 8) + (float) substr($lon, -8) / 60;
                $type = substr($adata[12], 1);
                unset($_SESSION['gps']);
                $_SESSION['gps']['lat'] = $fLat; //纬度
                $_SESSION['gps']['lon'] = $fLon; //经度
                $_SESSION['gps']['velocity'] = (float) $adata[7] * 1.852 / 3.6; //速度 m/s
                $_SESSION['gps']['direction'] = $adata[8]; //方向
                $_SESSION['gps']['type'] = $type; //定位态别
                $_SESSION['gps']['cdate'] = (new DateTime())->format('Y-m-d H:i:s'); //定位时间
            }
        }
        //Dryer 心跳包  $$$0001 '字符串+PSN
        elseif ($head == '$$$') {
            $_SESSION['heartcount'] += 1;
            if ($_SESSION['recordindex'] >= count($datakeys[$_SESSION['sort']])) {
                $_SESSION['cyclebegin'] = $_SESSION['recordbegin'] = $now;
                $_SESSION['cyclecount'] += 1;
                $_SESSION['recordindex'] = 0;
            }
            //发送第一笔数据请求
            //Events::sendRecordAddr($client_id);
        }
        //Dryer 数据
        elseif ($hexa['a'].$hexa['f'].$hexa['l'] == $_SESSION['addrh'].'0302') {
            $crc16 = CrcTool::crc16(pack("H*", $hexa['a'] . $hexa['f'].$hexa['l'].$hexa['d']));
            if (unpack("H4s", $crc16)['s'] == $hexa['c']) {
                $_SESSION['record'][$datakeys[$_SESSION['sort']][$_SESSION['recordindex']]] = unpack("s1int", pack("H*", $hexa['d']))['int'];
                $_SESSION['cyclecount'] += 1;
            }
            Events::sendRecordAddr($client_id);
            Events::saveRecord($client_id);
            Events::saveCycle($client_id);
            var_dump($hexa);
            var_dump(unpack("H4s", $crc16)['s']);
            print_r("++++++++++++++++++++++");
            var_dump($data);
        }
        //Dryer ERROR CODE
        elseif ($hexa['a'].$hexa['f'].$hexa['l'] == $_SESSION['addrh'].'8301' )
        {
            $crc16 = CrcTool::crc16(pack("H*", $hexa['a'] . $hexa['f'].$hexa['l']));
            if (unpack("H4s", $crc16)['s'] == $hexa['d']) {
                $_SESSION['errorcode'] = unpack("s1int", pack("H*", $hexa['l']))['int'];
                $_SESSION['cyclecount'] += 1;
            }
             
        }
        //平台
        elseif ($hexa['a'] == 0x5A && $data[1] == 0xA5)
        {
            
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
            //var_dump($_SESSION['recordindex']);
            $hexs = substr('0' . dechex($_SESSION['addr']), -2) . '03' . $dataaddr[$_SESSION['sort']][$datakeys[$_SESSION['sort']][$_SESSION['recordindex']]] . '0001';
            $crc16 = CrcTool::crc16(pack("H*", $hexs));
            $sendAddr = $hexs . unpack("H4s", $crc16)['s'];
            //print_r('==================');
            var_dump($sendAddr . $client_id);
            //GateWay::sendToClient($client_id, $sendAddr);
            $_SESSION['recordindex'] += 1;
        }
    }
    public static function saveRecord()
    {
        global $datakeys;
        global $db;
        if ($_SESSION['recordindex'] + 1 > count($datakeys[$_SESSION['sort']])) {
            //每十次心跳保存一次record in 600s=10min
            if ($_SESSION['cyclecount'] % 10 == 9) {
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
            }
        }
    }
    public static function saveCycle()
    {
        global $dataaddr;
        global $datakeys;
        global $db;
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
    public static function dbCycleFmt($data)
    {
        $cycle = $data;
        
        return $cycle;
    }
    public static function svRecordFmt($data)
    {
        $record = $data;
        $record['targetmst'] = $record['targetmst'] > 0 ? ($record['targetmst'] / 10) : $record['targetmst'];
        $record['mstcorrection'] = $record['mstcorrection'] / 10;
        $record['currentmst'] = $record['currentmst'] / 10;
        $record['mstvar'] = $record['mstvar'] / 100;
        $record['operatinghour'] = $record['operatinghour1'] * 65536 / 3600 + $record['operatinghour2'] / 3600;
        unset($record['operatinghour1']);
        unset($record['operatinghour2']);
        $record['operatedhour'] = $record['operatedhour1'] * 10000 + $record['operatedhour2'];
        unset($record['operatedhour1']);
        unset($record['operatedhour2']);
        $record['timersetting'] = $record['timersetting'] * 10;
        return $record;
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
