<?php
chdir(dirname(__FILE__) . '/../');
include_once("./config.php");
include_once("./lib/loader.php");
include_once("./lib/threads.php");
set_time_limit(0);
// connecting to database
$db = new mysql(DB_HOST, '', DB_USER, DB_PASSWORD, DB_NAME);
include_once("./load_settings.php");
include_once(DIR_MODULES . "control_modules/control_modules.class.php");
$ctl = new control_modules();
include_once(ROOT . "3rdparty/phpmqtt/phpMQTT.php");
include_once(DIR_MODULES . 'zmrn_mqtt/zmrn_mqtt.class.php');
$zmrn_mqtt_module = new zmrn_mqtt();
$zmrn_mqtt_module->getConfig();

$client_name = "MajorDoMo ZMRN MQTT Cycle";
$client_name = $client_name . ' (#' . uniqid() . ')';
$host=($mqtt->config['MQTT_HOST'])?($mqtt->config['MQTT_HOST']):'localhost';
$port=($mqtt->config['MQTT_PORT'])?($mqtt->config['MQTT_PORT']):1883;
$username=($mqtt->config['MQTT_USERNAME'])?($mqtt->config['MQTT_USERNAME']):'';
$password=($mqtt->config['MQTT_PASSWORD'])?($mqtt->config['MQTT_PASSWORD']):'';

$query_list = SQLSelect("SELECT * FROM zmrn0808_devices");
if (count($query_list)==0){echo "no devices\n"; exit;} // no devices added -- no need to run this cycle

$mqtt_client = new Bluerhinos\phpMQTT($host, $port, $client_name);
if (!$mqtt_client->connect(true, NULL, $username, $password)) {
        exit(1);
}

$total = count($query_list);
echo date('H:i:s') . " Topics to watch: $query (Total: $total)\n";
for ($i = 0; $i < $total; $i++) {
    $path = trim($query_list[$i]['SN'])."state";
    echo date('H:i:s') . " Path: $path\n";
    $topics[$path] = array("qos" => 0, "function" => "procmsg");
}
foreach ($topics as $k => $v) {
    echo date('H:i:s') . " Subscribing to: $k  \n";
    $rec = array($k => $v);
    $mqtt_client->subscribe($rec, 0);
}
$previousMillis = 0;

while ($mqtt_client->proc()) {

    $currentMillis = round(microtime(true) * 10000);

    if ($currentMillis - $previousMillis > 10000) {
        $previousMillis = $currentMillis;

        setGlobal((str_replace('.php', '', basename(__FILE__))) . 'Run', time(), 1);

        if (file_exists('./reboot') || IsSet($_GET['onetime'])) {
            $mqtt_client->close();
            $db->Disconnect();
            exit;
        }
    }
}

$mqtt_client->close();

/**
 * Process message
 * @param mixed $topic Topic
 * @param mixed $msg Message
 * @return void
 */
function procmsg($topic, $msg) {
    if (!isset($topic) || !isset($msg)) return false;

    echo date("Y-m-d H:i:s") . " Topic:{$topic}\n\n";
    if (0) {
    //if (function_exists('callAPI')) {
        callAPI('/api/module/zmrn_mqtt','GET',array('topic'=>$topic,'msg'=>$msg));
    } else {
        global $zmrn_mqtt_module;
        $zmrn_mqtt_module->processMessage($topic, $msg);
    }
}

$db->Disconnect(); // closing database connection