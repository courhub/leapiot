<?php
require_once __DIR__ . '/app/yama/Events.php';

use \GatewayWorker\Lib\Gateway;
use \Workerman\Worker;

Events::onConnect('clintid001');
var_dump(new DateTime());