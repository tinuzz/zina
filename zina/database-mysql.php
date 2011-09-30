<?php
function zdb_connect($host, $db, $user, $pwd, $newlink) {
	if (!function_exists('mysql_connect')) {
		zina_set_message('Unable to use the MySQL database because the MySQL extension for PHP is not installed. Check your <code>php.ini</code> to see how you can enable it.','error');
		return false;
	}
	$connection = @mysql_connect($host, $user, $pwd, $newlink);
	if (!$connection || !mysql_select_db($db, $connection)) {
		zina_set_message(mysql_error(),'error');
		return false;
	}
	mysql_query('SET NAMES "utf8"', $connection);
	return $connection;
}

function zdb_query($result, $active_db) {
	return mysql_query($result, $active_db);
}

function zdb_result($result, $row, $field) {
	return mysql_result($result, $row, $field);
}

function zdb_num_rows($result) {
	return mysql_num_rows($result);
}

function zdb_error() {
	global $z_dbc;
	return mysql_error($z_dbc);
}

function zdb_errno($active_db) {
	return mysql_errno($active_db);
}

function zdb_fetch_array($result) {
  return mysql_fetch_array($result, MYSQL_ASSOC);
}

function zdb_fetch_array_num($result) {
  return mysql_fetch_array($result, MYSQL_NUM);
}

function zdb_escape_string($text) {
	global $z_dbc;
	return mysql_real_escape_string($text, $z_dbc);
}

function zdb_last_insert_id($table, $field) {
  return zdbq_single('SELECT LAST_INSERT_ID()');
}
?>
