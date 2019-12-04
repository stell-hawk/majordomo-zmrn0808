<?php
/**
* zmrn-mqtt 

* @package project
* @author Wizard <sergejey@gmail.com>
* @copyright http://majordomo.smartliving.ru/ (c)
* @version 0.1 (wizard, 06:12:56 [Dec 04, 2019])
*/
//
//
class zmrn_mqtt extends module {
/**
* zmrn_mqtt
*
* Module class constructor
*
* @access private
*/
function __construct() {
  $this->name="zmrn_mqtt";
  $this->title="zmrn-mqtt";
  $this->module_category="<#LANG_SECTION_DEVICES#>";
  $this->checkInstalled();
}
/**
* saveParams
*
* Saving module parameters
*
* @access public
*/
function saveParams($data=1) {
 $p=array();
 if (IsSet($this->id)) {
  $p["id"]=$this->id;
 }
 if (IsSet($this->view_mode)) {
  $p["view_mode"]=$this->view_mode;
 }
 if (IsSet($this->edit_mode)) {
  $p["edit_mode"]=$this->edit_mode;
 }
 if (IsSet($this->data_source)) {
  $p["data_source"]=$this->data_source;
 }
 if (IsSet($this->tab)) {
  $p["tab"]=$this->tab;
 }
 return parent::saveParams($p);
}
/**
* getParams
*
* Getting module parameters from query string
*
* @access public
*/
function getParams() {
  global $id;
  global $mode;
  global $view_mode;
  global $edit_mode;
  global $data_source;
  global $tab;
  if (isset($id)) {
   $this->id=$id;
  }
  if (isset($mode)) {
   $this->mode=$mode;
  }
  if (isset($view_mode)) {
   $this->view_mode=$view_mode;
  }
  if (isset($edit_mode)) {
   $this->edit_mode=$edit_mode;
  }
  if (isset($data_source)) {
   $this->data_source=$data_source;
  }
  if (isset($tab)) {
   $this->tab=$tab;
  }
}
/**
* Run
*
* Description
*
* @access public
*/
function run() {
 global $session;
  $out=array();
  if ($this->action=='admin') {
   $this->admin($out);
  } else {
   $this->usual($out);
  }
  if (IsSet($this->owner->action)) {
   $out['PARENT_ACTION']=$this->owner->action;
  }
  if (IsSet($this->owner->name)) {
   $out['PARENT_NAME']=$this->owner->name;
  }
  $out['VIEW_MODE']=$this->view_mode;
  $out['EDIT_MODE']=$this->edit_mode;
  $out['MODE']=$this->mode;
  $out['ACTION']=$this->action;
  $out['DATA_SOURCE']=$this->data_source;
  $out['TAB']=$this->tab;
  $this->data=$out;
  $p=new parser(DIR_TEMPLATES.$this->name."/".$this->name.".html", $this->data, $this);
  $this->result=$p->result;
}

function _combine($arr,$key) {
	$out=array();
	foreach($arr as $v)
	{
		$out[$v[$key]]=$v;
	}
	return $out;
}


function api($params) {
    if ($_REQUEST['topic']) {
        $this->processMessage($_REQUEST['topic'], $_REQUEST['msg']);
     }
     /*if ($params['publish']) {
         $this->mqttPublish($params['publish'],$params['msg']);
     }*/
}

function processMessage($path, $value)
{
        $values=json_decode($value,true);
        if($values['cmd']=='post' ||$values['cmd']=='setr'||$values['cmd']=='state')
        {if(isset($values['sn']))
        	{
		        $v = SQLSelectOne("SELECT * FROM zmrn0808_devices WHERE sn = '" . DBSafe($values['sn']) . "'");
		        $old_value = $v['state'];
		        $new_value=$values["output"].$values["input"];
		        if($old_value!=$new_value)//Не меняем если значение не поменялось
		        {
		        	SQLUpdate('zmrn0808_devices',array('ID'=>$v['ID'],'state'=>$new_value));
		        	echo "newvalue:".$new_value."\n";
		        	$data=$values["output"];for($i=0;$i<strlen($data);$i++){$out[$i+1]=$data[$i];}
 		        	$data=$values["input"];for($i=0;$i<strlen($data);$i++){$out[$i+101]=$data[$i];}
		        	$rec = SQLSelect("SELECT * FROM zmrn0808_relays WHERE parent_id = '" . DBSafe($v['ID']) . "'");
		        	$rec=$this->_combine($rec,'ch_num');
		        	foreach ($out as  $ch_num => $val)
		        	{
		        		if(isset($rec[$ch_num]))//канал записан в таблицу
		        		{
		        			if($val!=$rec[$ch_num]['VALUE'])
		        			{
			        			echo "need update\n";
		        				$data=array();
		        				$data['ID']=$rec[$ch_num]['ID'];
		        				$data['VALUE']=$val;
		        				SQLUpdate('zmrn0808_relays',$data);
										$properties=$rec[$ch_num];
   									if($properties['LINKED_OBJECT']!='')
   									{
   										if (gg($properties['LINKED_OBJECT'].".".$properties['LINKED_PROPERTY'])<>$data['VALUE'])
   										{
   											echo "sg(".$properties['LINKED_OBJECT'].".".$properties['LINKED_PROPERTY'].",".$data['VALUE'].")\n";
   											sg($properties['LINKED_OBJECT'].".".$properties['LINKED_PROPERTY'],$data['VALUE']);
   										}
	        						if ($properties['LINKED_METHOD']) {
                    	callMethod($properties['LINKED_OBJECT'].'.'.$properties['LINKED_METHOD'], array('VALUE'=>$rec['VALUE'],'OLD_VALUE'=>$old_value,"data"=>$data));
          						}
   									}		        			
		        			}
		        		}
		        		else// канал не записан в таблицу
		        		{
		        			echo "need add\n";
		        			$data=array();
 	  							$data['VALUE']=$val;
 	  							$data['TITLE']="ch ".$ch_num;
 	  							$data['ch_num']=$ch_num;
 	  							$data['parent_id']=$v['ID'];
 	  							$data['updated']='now()';
 	  							SQLinsert('zmrn0808_relays',$data);
 	  							
		        		}
		        	}
		        	
		        }
        	}
        //echo "!!!!!!!!!!!!!!!!!\n";
        }
}



/**
* BackEnd
*
* Module backend
*
* @access public
*/
function admin(&$out) {
 $this->getConfig();
 $out['MQTT_URL']=$this->config['MQTT_URL'];
 if (!$out['MQTT_URL']) {
  $out['MQTT_URL']='182.61.18.191';
 }
 $out['MQTT_PORT']=$this->config['MQTT_PORT'];
 if (!$out['MQTT_PORT']) {
  $out['MQTT_PORT']='1883';
 }
 $out['MQTT_USERNAME']=$this->config['MQTT_USERNAME'];
 $out['MQTT_PASSWORD']=$this->config['MQTT_PASSWORD'];
 if ($this->view_mode=='update_settings') {
   global $mqtt_url;
   $this->config['MQTT_URL']=$mqtt_url;
   global $mqtt_port;
   $this->config['MQTT_PORT']=$mqtt_port;
   global $mqtt_username;
   $this->config['MQTT_USERNAME']=$mqtt_username;
   global $mqtt_password;
   $this->config['MQTT_PASSWORD']=$mqtt_password;
   $this->saveConfig();
   $this->redirect("?");
 }
 if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
  $out['SET_DATASOURCE']=1;
 }
 if ($this->data_source=='zmrn0808_devices' || $this->data_source=='') {
  if ($this->view_mode=='' || $this->view_mode=='search_zmrn0808_devices') {
   $this->search_zmrn0808_devices($out);
  }
  if ($this->view_mode=='edit_zmrn0808_devices') {
   $this->edit_zmrn0808_devices($out, $this->id);
  }
  if ($this->view_mode=='delete_zmrn0808_devices') {
   $this->delete_zmrn0808_devices($this->id);
   $this->redirect("?data_source=zmrn0808_devices");
  }
 }
 if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
  $out['SET_DATASOURCE']=1;
 }
 if ($this->data_source=='zmrn0808_relays') {
  if ($this->view_mode=='' || $this->view_mode=='search_zmrn0808_relays') {
   $this->search_zmrn0808_relays($out);
  }
  if ($this->view_mode=='edit_zmrn0808_relays') {
   $this->edit_zmrn0808_relays($out, $this->id);
  }
 }
}
/**
* FrontEnd
*
* Module frontend
*
* @access public
*/
function usual(&$out) {
 $this->admin($out);
}
/**
* zmrn0808_devices search
*
* @access public
*/
 function search_zmrn0808_devices(&$out) {
  require(DIR_MODULES.$this->name.'/zmrn0808_devices_search.inc.php');
 }
/**
* zmrn0808_devices edit/add
*
* @access public
*/
 function edit_zmrn0808_devices(&$out, $id) {
  require(DIR_MODULES.$this->name.'/zmrn0808_devices_edit.inc.php');
 }
/**
* zmrn0808_devices delete record
*
* @access public
*/
 function delete_zmrn0808_devices($id) {
  $rec=SQLSelectOne("SELECT * FROM zmrn0808_devices WHERE ID='$id'");
  // some action for related tables
  SQLExec("DELETE FROM zmrn0808_devices WHERE ID='".$rec['ID']."'");
 }
/**
* zmrn0808_relays search
*
* @access public
*/
 function search_zmrn0808_relays(&$out) {
  require(DIR_MODULES.$this->name.'/zmrn0808_relays_search.inc.php');
 }
/**
* zmrn0808_relays edit/add
*
* @access public
*/
 function edit_zmrn0808_relays(&$out, $id) {
  require(DIR_MODULES.$this->name.'/zmrn0808_relays_edit.inc.php');
 }
 function propertySetHandle($object, $property, $value) {
  	include_once(ROOT . "3rdparty/phpmqtt/phpMQTT.php");	
  	$this->getConfig();
		$table='zmrn0808_relays';
		$properties=SQLSelect("SELECT * FROM $table WHERE LINKED_OBJECT LIKE '".DBSafe($object)."' AND LINKED_PROPERTY LIKE '".DBSafe($property)."'");
		if(count($properties)==0) return 0;
		$v=SQLSelect("SELECT * FROM zmrn0808_devices");
   	$v=$this->_combine($v,'ID');
	
		$client_name = "MajorDoMo ZMRN MQTT";
		$host=($mqtt->config['MQTT_HOST'])?($mqtt->config['MQTT_HOST']):'localhost';
		$port=($mqtt->config['MQTT_PORT'])?($mqtt->config['MQTT_PORT']):1883;
		$username=($mqtt->config['MQTT_USERNAME'])?($mqtt->config['MQTT_USERNAME']):'';
		$password=($mqtt->config['MQTT_PASSWORD'])?($mqtt->config['MQTT_PASSWORD']):'';
		$mqtt_client = new Bluerhinos\phpMQTT($host, $port, $client_name . ' Client');
    if (!$mqtt_client->connect(true, NULL, $username, $password)) {return 0;}
	
	  $data="setr=xxxxxxxx";
	  foreach ($properties as $property)
		{
			if($property['ch_num']<100)
			{
				$topic=$v[$property['parent_id']]['SN']."ctr";
				$data[$property['ch_num']+4]=$value;
				$topics[$topic]=$data;
			}
			
    }
    print_r($topics);
    foreach ($topics as $topic => $value)$mqtt_client->publish($topic, $value,0,0);
	  $mqtt_client->close();

 }
/**
* Install
*
* Module installation routine
*
* @access private
*/
 function install($data='') {
  parent::install();
 }
/**
* Uninstall
*
* Module uninstall routine
*
* @access public
*/
 function uninstall() {
  SQLExec('DROP TABLE IF EXISTS zmrn0808_devices');
  SQLExec('DROP TABLE IF EXISTS zmrn0808_relays');
  parent::uninstall();
 }
/**
* dbInstall
*
* Database installation routine
*
* @access private
*/
 function dbInstall($data) {
/*
zmrn0808_devices - 
zmrn0808_relays - 
*/
  $data = <<<EOD
 zmrn0808_devices: ID int(10) unsigned NOT NULL auto_increment
 zmrn0808_devices: TITLE varchar(100) NOT NULL DEFAULT ''
 zmrn0808_devices: SN varchar(255) NOT NULL DEFAULT ''
 zmrn0808_devices: state varchar(20) NOT NULL DEFAULT ''
 zmrn0808_relays: ID int(10) unsigned NOT NULL auto_increment
 zmrn0808_relays: TITLE varchar(100) NOT NULL DEFAULT ''
 zmrn0808_relays: VALUE varchar(255) NOT NULL DEFAULT ''
 zmrn0808_relays: ch_num TINYINT NOT NULL DEFAULT '0'
 zmrn0808_relays: parent_id int(10) NOT NULL DEFAULT '0'
 zmrn0808_relays: LINKED_OBJECT varchar(100) NOT NULL DEFAULT ''
 zmrn0808_relays: LINKED_PROPERTY varchar(100) NOT NULL DEFAULT ''
 zmrn0808_relays: LINKED_METHOD varchar(100) NOT NULL DEFAULT ''
 zmrn0808_relays: UPDATED TIMESTAMP on update CURRENT_TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
EOD;
  parent::dbInstall($data);
 }
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgRGVjIDA0LCAyMDE5IHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
