<?php
if (!isset($_REQUEST['inst'])) die();
chdir(dirname(__FILE__)."/../../../".$_REQUEST['inst']);
include "config/config.php";

// connect to db
$db_link = mysql_connect(DB_HOST, DB_USER, DB_PASS) or die();
if (!mysql_select_db(DB_NAME, $db_link)) die();

$version = include 'version.php';
$all_statistics = array('config' => array('db_host' => DB_HOST, 'db_name' => DB_NAME, 'url' => ROOT_URL, 'version' => $version));

// build module usage information
if (isset($_REQUEST['modules']) && $_REQUEST['modules']) {
	$info = array();
	
	$db_res = mysql_query("select count(*) as num, o.object_type_id, ot.name from ".TABLE_PREFIX."objects o inner join ".TABLE_PREFIX."object_types ot on o.object_type_id=ot.id where trashed_by_id=0 group by o.object_type_id order by ot.name;", $db_link);
	while ($row = mysql_fetch_assoc($db_res)) {
		$info[$row['name']] = array(
			'objects' => $row['num'],
			'log_info' => array('total' => 0, 'details' => array()),
		);
	}
	$db_res = mysql_query("select count(*) as num from ".TABLE_PREFIX."contacts c where c.user_type > 0");
	while ($row = mysql_fetch_assoc($db_res)) {
		$info['Users'] = array(
			'objects' => $row['num'],
			'log_info' => array('total' => 0, 'details' => array()),
		);
	}
	
	$all_statistics['modules'] = $info;
}

// build last month logins information
if (isset($_REQUEST['logins']) && $_REQUEST['logins']) {
	$last_logins = array();
	
	$db_res = mysql_query("SELECT `created_on`, `object_name`, `rel_object_id` FROM `".TABLE_PREFIX."application_logs` WHERE `action` = 'login' AND `created_on` > ADDDATE(NOW(), INTERVAL -1 MONTH) ORDER BY `rel_object_id`", $db_link);
	while ($row = mysql_fetch_assoc($db_res)) {
		$last_logins[] = array(
			'id' => $row['rel_object_id'],
			'name' => htmlentities($row['object_name']),
			'date' => $row['created_on'],
		);
	}
	
	$all_statistics['logins'] = $last_logins;
}

// build last activity information
if (isset($_REQUEST['activity']) && $_REQUEST['activity']) {
	$activity = array();
	
	$db_res = mysql_query("select o.id, o.name, pg.name as `type`, c.last_activity as `date` from fo_contacts c 
		inner join fo_objects o on o.id=c.object_id 
		inner join fo_permission_groups pg on pg.id=c.user_type
		where o.trashed_by_id=0 and c.user_type>0 and c.last_activity>0
		order by c.last_activity desc;", $db_link);
	
	while ($row = mysql_fetch_assoc($db_res)) {
		$activity[] = array(
			'id' => $row['id'],
			'name' => htmlentities($row['name']),
			'type' => $row['type'],
			'date' => $row['date'],
		);
	}
	$all_statistics['activity'] = $activity;
	
	$last_month_activity = array();
	$db_res = mysql_query("select count(*) as num, DATE(created_on) as created from ".TABLE_PREFIX."application_logs WHERE action in ('add','edit','trash','comment') AND created_on > ADDDATE(NOW(), INTERVAL -1 MONTH) group by created;");
	while ($row = mysql_fetch_assoc($db_res)) {
		$last_month_activity[] = $row;
	}
	$all_statistics['last_month_activity'] = $last_month_activity;
}

// get db size and filesystem size
if (isset($_REQUEST['sizes']) && $_REQUEST['sizes']) {
	$sizes = array();
	
	$db_res = mysql_query("select sum(data_length + index_length) as bytes from `information_schema`.`TABLES` WHERE table_schema = '".DB_NAME."'");
	while ($row = mysql_fetch_assoc($db_res)) {
		$sizes['db'] = $row['bytes'];
	}
	
	$du_output = shell_exec("du -sb " . getcwd() . "/upload/");
	$splitted_output = preg_split("/[\s]+/", $du_output);
	if (count($splitted_output) > 0) $sizes['fs'] = $splitted_output[0];
	
	$all_statistics['sizes'] = $sizes;
}

// print response
echo "\r\n\r\n";
echo json_encode($all_statistics);
