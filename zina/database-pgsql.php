<?php
function zdb_connect($host, $db, $user, $pwd, $newlink) {
	if (!function_exists('pg_connect')) {
		zina_set_message('Unable to use the PostgreSQL database because the PostgreSQL extension for PHP is not installed. Check your <code>php.ini</code> to see how you can enable it.','error');
		return false;
	}

	$conn_string = '';
	$conn_string .= ' user='.urldecode($user);
	$conn_string .= ' password='.urldecode($pwd);
	$conn_string .= ' host='.urldecode($host);
	$conn_string .= ' dbname='.urldecode($db);
	#$conn_string .= ' dbname='. substr(urldecode($db), 1);
	#$conn_string .= ' port='. urldecode($url['port']);

	$track_errors_previous = ini_get('track_errors');
	ini_set('track_errors', 1);

	$connection = @pg_connect($conn_string);
	if (!$connection) {
		zina_set_message($php_errormsg,'error');
		return false;
	}

	ini_set('track_errors', $track_errors_previous);

	pg_query($connection, "set client_encoding=\"UTF8\"");
	return $connection;
}

function zdb_query($result, $active_db) {
	return pg_query($active_db, $result);
}

function zdb_result($result, $row, $field) {
	$array = pg_fetch_row($result);
	return $array[0];
}

function zdb_num_rows($result) {
	return pg_num_rows($result);
}

function zdb_error() {
	global $z_dbc;
	return pg_last_error($z_dbc);
}

function zdb_errno($active_db) {
	return '';
}

function zdb_fetch_array($result) {
	return pg_fetch_assoc($result);
}

function zdb_fetch_array_num($result) {
  return pg_fetch_array($result, PGSQL_NUM);
}

function zdb_escape_string($text) {
	return pg_escape_string($text);
}

function zdb_last_insert_id($table, $field) {
  return zdbq_single("SELECT CURRVAL('{".$table."}_".$field."_seq')");
}
?>
