<?php
/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * ZINA (Zina is not Andromeda)
 *
 * Zina is a graphical interface to your MP3 collection, a personal
 * jukebox, an MP3 streamer. It can run on its own, embeded into an
 * existing website, or as a Drupal/Joomla/Wordpress/etc. module.
 *
 * http://www.pancake.org/zina
 * Author: Ryan Lathouwers <ryanlath@pacbell.net>
 * Support: http://sourceforge.net/projects/zina/
 * License: GNU GPL2 <http://www.gnu.org/copyleft/gpl.html>
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

function zina_updates() {
	return array(
		1 => array(
			'title' => 'Update version 1 to version 2',
			'desc' => 'Update version 1 to version 2',
			'func' => 'update',
			'from' => array('1.0rc3','1.0rc4'),
			'when' => '2.0a1',
			),
		2 => array(
			'title' => 'Update 2',
			'desc' => 'Additional genre functionality',
			'func' => 'update_2',
			'from' => array('any'),
			'when' => '2.0a9',
			),
		3 => array(
			'title' => 'Update 3',
			'desc' => 'Live Search Database',
			'func' => 'update_3',
			'from' => array('any'),
			'when' => '2.0a10',
			),
		4 => array(
			'title' => 'Update 4',
			'desc' => 'Live Search Database Mod',
			'func' => 'update_4',
			'from' => array('2.0a10'),
			'when' => '2.0a11',
			),
		5 => array(
			'title' => 'Update 5',
			'desc' => 'sum_plays index',
			'func' => 'update_5',
			'from' => array('2.0a11'),
			'when' => '2.0b11',
			),
		6 => array(
			'title' => 'Update 6',
			'desc' => 'cms_map',
			'func' => 'update_6',
			'from' => array('2.0b14'),
			'when' => '2.0b14',
			),
		7 => array(
			'title' => 'Update 7',
			'desc' => 'batch',
			'func' => 'update_7',
			'from' => array('2.0b14'),
			'when' => '2.0b14',
			),
		8 => array(
			'title' => 'Update 8',
			'desc' => 'batch',
			'func' => 'update_8',
			'from' => array('2.0b16'),
			'when' => '2.0b16',
			),
		9 => array(
			'title' => 'Update 9',
			'desc' => 'batch',
			'func' => 'update_9',
			'from' => array('2.0b18'),
			'when' => '2.0b18',
			),


		);
}

function zina_updates_check() {
	$updates = zina_updates();
	foreach ($updates as $num => $update) {
		if (zina_update_check($num, $update)) {
			return $update['title'];
		}
	}
	return false;
}

function zina_updates_execute() {
	$updates = zina_updates();
	foreach ($updates as $num => $update) {
		if (zina_update_check($num, $update)) {
			$success = call_user_func('zina_'.$update['func']);
			if ($success) zvar_set('version', $update['when']);
			return $success;
		}
	}
	return true;
}

function zina_updates_embed($num) {
	$updates = zina_updates();
	$update = $updates[$num];
	$success = call_user_func('zina_'.$update['func']);
	if ($success) zvar_set('version', $update['when']);
	return $success;
}

function zina_update_check($num, $update) {
	switch($num) {
		case 1 :
			return (!zdbq("SELECT 1 FROM {variable} LIMIT 0"));
			break;
		case 2 :
			return (!zdbq("SELECT 1 FROM {genres} LIMIT 0"));
			break;
		case 3 :
			return (!zdbq("SELECT 1 FROM {search_index} LIMIT 0"));
			break;
		case 8 :
			return (!zdbq("SELECT 1 FROM {playlists_stats} LIMIT 0"));
			break;
		case 9 :
			return (!zdbq_array_list("SHOW COLUMNS FROM {genres} LIKE 'actual'"));
			break;
		default :
			return (version_compare(zvar_get('version'), $update['when'], '<'));
			break;
	}
	return false;
}

function zina_update_9() {
	$success = true;

	$updates[] = array(
		'sql' => "ALTER TABLE {genres} ADD COLUMN actual TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER other",
		'test' => "SHOW COLUMNS FROM {genres} LIKE 'actual'",
		'error' => 'Zina Update could not alter table: {genres}',
	);
	$updates[] = array(
		'sql' => "ALTER TABLE {genres} DROP INDEX `genre`",
		'error' => 'Zina Update could not drop index: {genres}.genre',
	);
	$updates[] = array(
		'sql' => "ALTER TABLE {genres} ADD UNIQUE (genre)",
		'error' => 'Zina Update could not add unique index: {genres}.genre.  YOU MUST DO THIS MANUALLY!',
	);
	/*
	$updates[] = array(
		'sql' => "ALTER TABLE {genre_tree} DROP INDEX `id`",
		'error' => 'Zina Update could not drop index: {genre_tree}.id',
	);
	 */
	$updates[] = array(
		'sql' => "ALTER TABLE {genre_tree} ADD UNIQUE (id)",
		'error' => 'Zina Update could not add unique index: {genre_tree}.id.  YOU MUST DO THIS MANUALLY!',
	);
	global $zc;
	$zc['debug'] = true;

	foreach ($updates as $update) {
		if (isset($update['test']) && zdbq_array_list($update['test'])) continue;
		

		if (!zdbq($update['sql'])) {
			zina_set_message(zt($update['error']), 'warn');
			$success = false;
		}
	}
	#if ($success)
	zdb_genre_populate(time(), $context);

	return $success;
}

function zina_update_8() {
	$success = true;
	$tables = zdb_schema('tables');

	foreach(array(12,13,14) as $t) {
		if (!zdb_create_table($tables[$t])) {
			zina_set_message(zt('Zina Update could not create @table table.', array('@table'=>$tables[$t]['name'])), 'error');
			$success = false;
		}
	}

	return $success;
}

function zina_update_7() {
	$success = true;
	$tables = zdb_schema('tables');

	foreach(array(11) as $t) {
		if (!zdb_create_table($tables[$t])) {
			zina_set_message(zt('Zina Update could not create @table table.', array('@table'=>$tables[$t]['name'])), 'error');
			$success = false;
		}
	}

	return $success;
}

function zina_update_6() {
	$success = true;

	$sql = "ALTER TABLE {dirs} ".
		"ADD cms_id INT NOT NULL DEFAULT 0 AFTER parent_id, ".
		"ADD INDEX(cms_id)";

	if (!zdbq($sql)) {
		zina_set_message(zt('Zina Update could not alter table: {dirs}'), 'warn');
		$success = false;
	}

	return $success;
}

function zina_update_5() {
	$success = true;
	$sql = "ALTER TABLE {files} ADD INDEX(sum_plays)";

	if (!zdbq($sql)) {
		zina_set_message(zt('Zina Update could not alter table: {files}'), 'warn');
		$success = false;
	}

	return $success;
}

function zina_update_4() {
	$success = true;
	$sql = "ALTER TABLE {search_index} ".
		"ADD genre VARCHAR(128) AFTER context, ".
		"ADD year SMALLINT AFTER genre, ".
		"ADD file_mtime INT AFTER year, ".
		"ADD INDEX(genre), ".
		"ADD INDEX(file_mtime), ".
		"ADD INDEX(year)";
	if (!zdbq($sql)) {
		zina_set_message(zt('Zina Update could not alter table: {search_index}'), 'warn');
		$success = false;
	}

	$sql = "ALTER TABLE {files} ".
		"ADD year SMALLINT AFTER genre, ".
		"ADD INDEX(year)";

	if (!zdbq($sql)) {
		zina_set_message(zt('Zina Update could not alter table: {search_index}'), 'warn');
		$success = false;
	}

	return $success;
}

function zina_update_3() {
	$success = true;
	$tables = zdb_schema('tables');

	foreach(array(10) as $t) {
		if (!zdb_create_table($tables[$t])) {
			zina_set_message(zt('Zina Update could not create @table table.', array('@table'=>$tables[$t]['name'])), 'error');
			$success = false;
		}
	}

	return $success;
}

function zina_update_2() {
	$success = true;
	$tables = zdb_schema('tables');

	foreach(array(8,9) as $t) {
		if (!zdb_create_table($tables[$t])) {
			zina_set_message(zt('Zina Update could not create @table table.', array('@table'=>$tables[$t]['name'])), 'error');
			$success = false;
		}
	}

	return $success;
}

function zina_update() {
	$success = true;
	foreach (array('dir_views','dir_ratings','file_plays','file_downloads','file_ratings') as $table) {
		$sql = "ALTER TABLE {".$table."} ".
			"ADD user_id INT NOT NULL DEFAULT 0, ".
			"ADD INDEX(user_id)";
		if (!zdbq($sql)) {
			zina_set_message(zt('Zina Update could not alter table: @tab',array('@tab'=> $table)), 'warn');
			$success = false;
		}
	}

	$sql = "ALTER TABLE {files} ".
		"ADD path VARCHAR(255) NOT NULL, ".
		"ADD description TEXT, ".
		"ADD genre VARCHAR(255), ".
		"ADD type VARCHAR(6), ".
		"ADD mtime INT NOT NULL, ".
		"ADD sum_plays INT NOT NULL DEFAULT 0, ".
		"ADD sum_downloads INT NOT NULL DEFAULT 0, ".
		"ADD sum_votes INT NOT NULL DEFAULT 0, ".
		"ADD sum_rating FLOAT NOT NULL DEFAULT 0, ".
		"ADD id3_info TEXT, ".
		"ADD other TEXT, ".
		"ADD INDEX(path),".
		"ADD INDEX(mtime), ".
		"ADD INDEX(genre)";
	if (!zdbq($sql)) {
		zina_set_message(zt('Zina Update could not alter table: {files}'), 'warn');
		$success = false;
	}

	$sql = "ALTER TABLE {dirs} ".
		"CHANGE path_full path  VARCHAR(255) NOT NULL, ".
		"ADD title VARCHAR(255), ".
		"ADD description TEXT, ".
		"ADD genre VARCHAR(255), ".
		"ADD year SMALLINT, ".
		"ADD mtime INT NOT NULL, ".
		"ADD sum_views INT NOT NULL DEFAULT 0, ".
		"ADD sum_downloads INT NOT NULL DEFAULT 0, ".
		"ADD sum_votes INT NOT NULL DEFAULT 0, ".
		"ADD sum_rating FLOAT NOT NULL DEFAULT 0, ".
		"ADD other TEXT, ".
		"ADD INDEX(genre), ".
		"ADD INDEX(mtime), ".
		"ADD INDEX(level), ".
		"ADD INDEX(year)";

	if (!zdbq($sql)) {
		zina_set_message(zt('Zina Update could not alter table: {dirs}'), 'warn');
		$success = false;
	}

	$update[] = "UPDATE {files} AS f, {dirs} AS d SET f.path = d.path WHERE f.dir_id = d.id";
	$update[] = "UPDATE {files} AS f SET f.sum_plays = (SELECT COUNT(*) FROM {file_plays} AS p WHERE p.file_id = f.id)";
	$update[] = "UPDATE {files} AS f SET f.sum_downloads = (SELECT COUNT(*) FROM {file_downloads} AS d WHERE d.file_id = f.id)";
	$update[] = "UPDATE {files} AS f SET f.sum_rating = (SELECT AVG(r.rating) FROM {file_ratings} AS r WHERE r.file_id = f.id)";
	$update[] = "UPDATE {files} AS f SET f.sum_votes = (SELECT COUNT(r.rating) FROM {file_ratings} AS r WHERE r.file_id = f.id)";

	$update[] = "UPDATE {dirs} AS d SET d.sum_rating = (SELECT AVG(r.rating) FROM {dir_ratings} AS r WHERE r.dir_id = d.id)";
	$update[] = "UPDATE {dirs} AS d SET d.sum_votes = (SELECT COUNT(r.rating) FROM {dir_ratings} AS r WHERE r.dir_id = d.id)";
	$update[] = "UPDATE {dirs} AS d SET d.sum_views = (SELECT COUNT(*) FROM {dir_views} AS r WHERE r.dir_id = d.id)";

	foreach($update as $sql) {
		if (!zdbq($sql)) {
			zina_set_message(zt('Zina Update could not update tables with new values'), 'warn');
			$success = false;
		}
	}

	$tables = zdb_schema('tables');
	if (!zdb_create_table($tables[0])) {
		zina_set_message(zt('Zina Update could not create "variable" table.'), 'error');
		$success = false;
	}

	return $success;
}
?>
