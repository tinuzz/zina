<?php
function zdb_connect($host, $db, $user, $pwd, $newlink) {
	if (!function_exists('mysqli_init') && !extension_loaded('mysqli')) {
		zina_set_message('Unable to use the MySQLi database because the MySQLi extension for PHP is not installed. Check your <code>php.ini</code> to see how you can enable it.','error');
		return false;
	}
	$connection = mysqli_init();

	@mysqli_real_connect($connection, $host, $user, $pwd, $db);

	if (mysqli_connect_errno()) {
		zina_set_message(mysqli_connect_error(),'error');
		return false;
	}
	mysqli_query($connection, 'SET NAMES "utf8"');
	return $connection;
}

function zdb_query($result, $active_db) {
	return mysqli_query($active_db, $result);
}

function zdb_result($result, $row, $field) {
	$array = mysqli_fetch_row($result);
	return $array[0];
}

function zdb_num_rows($result) {
	return mysqli_num_rows($result);
}

function zdb_error() {
	global $z_dbc;
	return mysqli_error($z_dbc);
}

function zdb_errno($active_db) {
	return mysqli_errno($active_db);
}

function zdb_fetch_array($result) {
  return mysqli_fetch_array($result, MYSQLI_ASSOC);
}

function zdb_fetch_array_num($result) {
  return mysqli_fetch_array($result, MYSQLI_NUM);
}

function zdb_escape_string($text) {
	global $z_dbc;
	return mysqli_real_escape_string($z_dbc, $text);
}

function zdb_last_insert_id($table, $field) {
  return zdbq_single('SELECT LAST_INSERT_ID()');
}
?>
