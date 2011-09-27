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
 *
 * TODO:
 *  - organize this file
 *  - adopt drupal "schema"?
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * DATABASE DEFINITION
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

function zdb_schema($type) {
	$fkdir = 'FOREIGN KEY (dir_id) REFERENCES {dirs} (id)';
	$fkfile = 'FOREIGN KEY (file_id) REFERENCES {files} (id)';
	switch($type) {
		case 'tables':
			$arr = array(
				array('name'=>'variable', 'index'=>array('UNIQUE INDEX (name)')),
				array('name'=>'dirs', 'index'=>array('INDEX (parent_id)', 'INDEX(cms_id)', 'INDEX(path)', 'INDEX(genre)', 'INDEX(year)', 'INDEX(mtime)', 'INDEX(level)', 'UNIQUE INDEX (parent_id, path)')),
				#todo: uniq s/b path + file???
				array('name'=>'files', 'index'=>array('INDEX (dir_id)','INDEX(path)','INDEX(genre)', 'INDEX(year)','INDEX(mtime)', 'INDEX(sum_plays)', 'UNIQUE INDEX (dir_id,file)',$fkdir)),
				array('name'=>'dir_views', 'index'=>array('INDEX (dir_id)','INDEX (user_id)',$fkdir)),
				array('name'=>'dir_ratings', 'index'=>array('INDEX (dir_id)','INDEX (user_id)',$fkdir)),
				array('name'=>'file_plays', 'index'=>array('INDEX (file_id)','INDEX (user_id)',$fkfile)),
				array('name'=>'file_downloads', 'index'=>array('INDEX (file_id)','INDEX (user_id)',$fkfile)),
				array('name'=>'file_ratings', 'index'=>array('INDEX (file_id)','INDEX (user_id)',$fkfile)),
				array('name'=>'genres', 'index'=>array('INDEX (pid)','UNIQUE INDEX (genre)')),
				array('name'=>'genre_tree', 'index'=>array('UNIQUE INDEX (id)','INDEX (weight)','UNIQUE INDEX (path)','FOREIGN KEY (id) REFERENCES {genres} (id)')),
				array('name'=>'search_index', 'index'=>array('INDEX (title)','INDEX (path)','INDEX(type)','INDEX(type_id)','INDEX(mtime)','INDEX(genre)','INDEX(year)','INDEX(file_mtime)','UNIQUE INDEX(type,path)')),
				array('name'=>'batch', 'index'=>array('INDEX (token)')),
				array('name'=>'playlists', 'index'=>array('INDEX (dir_id)', 'INDEX (genre_id)', 'INDEX (user_id)', 'INDEX (date_created)',)),
				array('name'=>'playlists_map', 'index'=>array('INDEX (playlist_id)', 'INDEX (type)', 'INDEX (type_id)', 'INDEX (weight)', 'FOREIGN KEY (playlist_id) REFERENCES {playlists} (id)',)),
				array('name'=>'playlists_stats', 'index'=>array('INDEX (stat_type)', 'INDEX (stat)', 'INDEX (playlist_id)', 'INDEX (user_id)', 'INDEX (mtime)', 'INDEX (ip)', 'FOREIGN KEY (playlist_id) REFERENCES {playlists} (id)',)),
			);
			break;
		case 'variable':
			$arr = array(
				array('dc'=>'name', 'dt'=>'VARCHAR(128) PRIMARY KEY'),
				array('dc'=>'value', 'dt'=>'TEXT NOT NULL'),
			);
			break;
		case 'dirs':
			$arr = array(
				array('dc'=>'id', 'dt'=>'INTEGER PRIMARY KEY AUTO_INCREMENT'),
				array('dc'=>'parent_id', 'dt'=>'INT NOT NULL'),
				array('dc'=>'cms_id', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'path', 'dt'=>'VARCHAR(255) NOT NULL'),
				array('dc'=>'level', 'dt'=>'INT NOT NULL'),
				array('dc'=>'title', 'dt'=>'VARCHAR(255)'),
				array('dc'=>'sum_views', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'sum_votes', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'sum_rating', 'dt'=>'FLOAT NOT NULL DEFAULT 0'),
				#todo: not used yet... use for cmp_sel?
				array('dc'=>'sum_downloads', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'genre', 'dt'=>'VARCHAR(128)'),
				array('dc'=>'year', 'dt'=>'SMALLINT'),
				array('dc'=>'mtime', 'dt'=>'INT NOT NULL'),
				array('dc'=>'description', 'dt'=>'TEXT'),
				array('dc'=>'other', 'dt'=>'TEXT'),
			);
			break;
		case 'files':
			$arr = array(
				array('dc'=>'id', 'dt'=>'INTEGER PRIMARY KEY AUTO_INCREMENT'),
				array('dc'=>'dir_id', 'dt'=>'INT NOT NULL'),
				array('dc'=>'path', 'dt'=>'VARCHAR(255) NOT NULL'),
				array('dc'=>'file', 'dt'=>'VARCHAR(255) NOT NULL'),
				array('dc'=>'type', 'dt'=>'VARCHAR(6)'),
				array('dc'=>'sum_plays', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'sum_downloads', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'sum_votes', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'sum_rating', 'dt'=>'FLOAT NOT NULL DEFAULT 0'),
				array('dc'=>'mtime', 'dt'=>'INT NOT NULL'),
				array('dc'=>'description', 'dt'=>'TEXT'),
				array('dc'=>'genre', 'dt'=>'VARCHAR(128)'),
				array('dc'=>'year', 'dt'=>'SMALLINT'),
				array('dc'=>'id3_info', 'dt'=>'TEXT'),
				array('dc'=>'other', 'dt'=>'TEXT'),
			);
			break;
		case 'dir_views':
			$arr = array(
				array('dc'=>'id', 'dt'=>'INTEGER PRIMARY KEY AUTO_INCREMENT'),
				array('dc'=>'dir_id', 'dt'=>'INT NOT NULL'),
				array('dc'=>'user_id', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'ip', 'dt'=>'VARCHAR(15) DEFAULT NULL'),
				array('dc'=>'mtime', 'dt'=>'INT NOT NULL'),
			);
			break;
		case 'dir_ratings':
			$arr = array(
				array('dc'=>'id', 'dt'=>'INTEGER PRIMARY KEY AUTO_INCREMENT'),
				array('dc'=>'dir_id', 'dt'=>'INT NOT NULL'),
				array('dc'=>'user_id', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'ip', 'dt'=>'VARCHAR(15) DEFAULT NULL'),
				array('dc'=>'mtime', 'dt'=>'INT NOT NULL'),
				array('dc'=>'rating', 'dt'=>'TINYINT NOT NULL'),
			);
			break;
		case 'file_plays':
			$arr = array(
				array('dc'=>'id', 'dt'=>'INTEGER PRIMARY KEY AUTO_INCREMENT'),
				array('dc'=>'file_id', 'dt'=>'INT NOT NULL'),
				array('dc'=>'user_id', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'ip', 'dt'=>'VARCHAR(15) DEFAULT NULL'),
				array('dc'=>'mtime', 'dt'=>'INT NOT NULL'),
			);
			break;
		case 'file_downloads':
			$arr = array(
				array('dc'=>'id', 'dt'=>'INTEGER PRIMARY KEY AUTO_INCREMENT'),
				array('dc'=>'file_id', 'dt'=>'INT NOT NULL'),
				array('dc'=>'user_id', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'ip', 'dt'=>'VARCHAR(15) DEFAULT NULL'),
				array('dc'=>'mtime', 'dt'=>'INT NOT NULL'),
			);
			break;
		case 'file_ratings':
			$arr = array(
				array('dc'=>'id', 'dt'=>'INTEGER PRIMARY KEY AUTO_INCREMENT'),
				array('dc'=>'file_id', 'dt'=>'INT NOT NULL'),
				array('dc'=>'user_id', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'ip', 'dt'=>'VARCHAR(15) DEFAULT NULL'),
				array('dc'=>'mtime', 'dt'=>'INT NOT NULL'),
				array('dc'=>'rating', 'dt'=>'TINYINT NOT NULL'),
			);
			break;
		case 'genres':
			$arr = array(
				array('dc'=>'id', 'dt'=>'INTEGER PRIMARY KEY AUTO_INCREMENT'),
				array('dc'=>'pid', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'genre', 'dt'=>'VARCHAR(128) NOT NULL'),
				array('dc'=>'description', 'dt'=>'TEXT'),
				array('dc'=>'other', 'dt'=>'TEXT'),
			);
			break;
		case 'genre_tree':
			$arr = array(
				array('dc'=>'id', 'dt'=>'INT NOT NULL'),
				array('dc'=>'path', 'dt'=>'VARCHAR(128) NOT NULL'),
				array('dc'=>'weight', 'dt'=>'SMALLINT NOT NULL DEFAULT 0'),
			);
			break;
		case 'search_index':
			$arr = array(
				array('dc'=>'id', 'dt'=>'INTEGER PRIMARY KEY AUTO_INCREMENT'),
				array('dc'=>'type', 'dt'=>'VARCHAR(16) NOT NULL'),
				array('dc'=>'type_id', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'title', 'dt'=>'VARCHAR(128) NOT NULL'),
				array('dc'=>'path', 'dt'=>'VARCHAR(255) NOT NULL'),
				array('dc'=>'context', 'dt'=>'VARCHAR(128)'),
				array('dc'=>'genre', 'dt'=>'VARCHAR(128)'),
				array('dc'=>'year', 'dt'=>'SMALLINT'),
				array('dc'=>'file_mtime', 'dt'=>'INT NOT NULL'),
				array('dc'=>'mtime', 'dt'=>'INT NOT NULL'),
			);
			break;
		case 'batch':
			$arr = array(
				array('dc'=>'bid', 'dt'=>'INTEGER PRIMARY KEY AUTO_INCREMENT'),
				array('dc'=>'token', 'dt'=>'VARCHAR(64) NOT NULL DEFAULT ""'),
				array('dc'=>'timestamp', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'batch', 'dt'=>'LONGTEXT'),
			);
			break;
		case 'playlists':
			$arr = array(
				array('dc'=>'id', 'dt'=>'INTEGER PRIMARY KEY AUTO_INCREMENT'),
				array('dc'=>'image_type', 'dt'=>'TINYINT NOT NULL DEFAULT 0'),
				array('dc'=>'title', 'dt'=>'VARCHAR(128) NOT NULL'),
				array('dc'=>'description', 'dt'=>'TEXT'),
				array('dc'=>'dir_id', 'dt'=>'INT'),
				array('dc'=>'genre_id', 'dt'=>'INT'),
				array('dc'=>'user_id', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'sum_items', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'sum_plays', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'sum_views', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'sum_votes', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'sum_rating', 'dt'=>'FLOAT NOT NULL DEFAULT 0'),
				array('dc'=>'visible', 'dt'=>'TINYINT NOT NULL DEFAULT 1'),
				array('dc'=>'date_created', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'mtime', 'dt'=>'INT NOT NULL DEFAULT 0'),
			);
			break;
		case 'playlists_map':
			$arr = array(
				array('dc'=>'id', 'dt'=>'INTEGER PRIMARY KEY AUTO_INCREMENT'),
				array('dc'=>'playlist_id', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'type', 'dt'=>'VARCHAR(16) NOT NULL'),
				array('dc'=>'type_id', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'weight', 'dt'=>'SMALLINT NOT NULL DEFAULT 0'),
			);
			break;
		case 'playlists_stats':
			$arr = array(
				array('dc'=>'id', 'dt'=>'INTEGER PRIMARY KEY AUTO_INCREMENT'),
				array('dc'=>'stat_type', 'dt'=>'TINYINT NOT NULL DEFAULT 0'),
				array('dc'=>'stat', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'playlist_id', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'user_id', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'mtime', 'dt'=>'INT NOT NULL DEFAULT 0'),
				array('dc'=>'ip', 'dt'=>'VARCHAR(15) DEFAULT NULL'),
			);
			break;
	}
	return $arr;
}

function zina_content_dbclean_select(&$rows, $name, $arr) {
	$hidden='';
	if (empty($arr)) {
		$rows[] = array('label'=>-1,'item'=>zt('None'));
	} else {
		foreach($arr as $key=>$item) {
			$hidden .= '<input type="hidden" name="'.$name.'[]" value="'.$item['id'].'">';
			$row = '<select name="'.$name.'_new[]">'.
				#todo: onselect manual, display textbox
				'<option value="-1" selected>'.zt('Remove database entry').'</option>'.
				'<option value="-2">'.zt('Ignore').'</option>';
				#'<option value="-3">'.zt('Manual').'</option>';
			if (!empty($item['opts'])) {
				foreach($item['opts'] as $k=>$opt) {
					$row .= '<option value="'.$opt['id'].'">'.$opt['path'].'</option>';
				}
			}
			$row .= '</select>';
			$rows[] = array('label'=>$item['path'],'item'=>$row);
		}
	}
	return $hidden;
}

function zdb_get_others($path, $file = null) {
	if (empty($file)) {
		$table = 'dirs';
		$sql = '';
	} else {
		$table = 'files';
		$sql = "AND file = '%s'";
	}

	$other = array();
	$result = zdbq_single("SELECT other FROM {".$table."} WHERE path = '%s' $sql", $path, $file);

	if (!empty($result)) $other = unserialize_utf8($result);

	return $other;
}

function zdb_update_others($items, $path, $file = null) {
	if (empty($file)) {
		$table = 'dirs';
		$sql = '';
	} else {
		$table = 'files';
		$sql = "AND file = '%s'";
	}

	zdb_log_stat('insertonly', $path, $file);
	$other = zdb_get_others($path, $file);

	$items += $other;

	return zdbq("UPDATE {".$table."} SET other = '%s' WHERE path = '%s' $sql", serialize($items), $path, $file);
}

function zdb_get_song_user_ratings($path, $user_id) {
	$path = empty($path) ? '.' : $path;

	return zdbq_assoc('file',
		"SELECT IF(path!='.',CONCAT(path,IF(ISNULL(path), '','/'),file),file) AS file, r.rating ".
		"FROM {files} as f INNER JOIN {file_ratings} AS r ON (f.id = r.file_id) ".
		"WHERE path = '%s' AND r.user_id = %d", array($path, $user_id)
	);
}

function zdb_get_dir_user_rating($path, $user_id) {
	$path = empty($path) ? '.' : $path;

	return zdbq_single("SELECT rating FROM {dir_ratings} AS r INNER JOIN {dirs} AS d ON r.dir_id = d.id ".
		"WHERE r.user_id = %d AND d.path = '%s' LIMIT 1", $user_id, $path);
}

function zdb_stats_generate($runtime) {
	$pages = zina_get_stats_pages();
	$periods = zina_get_stats_periods();

	foreach($pages as $page=>$x) {
		foreach($periods as $period=>$x2) {
			$arr = zdb_get_stat_sql($page, $period);
			foreach($arr as $type=>$val) {
				$db_stat = 'stats_'.$page.$period.'_'.$type;
				$stats = zdbq_array($val['sql']);
				zvar_set($db_stat, $stats);
			}
		}
	}
	zvar_set("cron_stats_last_run", $runtime);
}

function zdb_search_playlist_populate($runtime, &$context) {
	global $zc;
	if (!$zc['playlists']) return;
	$context['message'] = zt('Populating search playlists');
	$playlist = array();

	#todo: might have to think about if lots O playlists...
	zdbq("DELETE FROM {search_index} WHERE type = 'playlist'");

	if ($zc['pls_public']) {
		$playlists = zdbq_array("SELECT p.id, p.title, p.date_created, g.genre ".
			"FROM {playlists} AS p ".
			"LEFT OUTER JOIN {genres} AS g ON (p.genre_id = g.id) ".
			"WHERE p.visible = 1");
	}

	if (!empty($playlists)) {
		foreach($playlists as $playlist) {
			if (!zdbq("INSERT {search_index} (title, type, type_id, path, file_mtime, mtime, genre) VALUES ('%s', '%s', %d, %d, %d, %d, '%s') ".
				"ON DUPLICATE KEY UPDATE title = '%s', type_id = %d, path = %d, mtime = %d, genre = '%s'", array($playlist['title'], 'playlist', $playlist['id'], $playlist['id'],
					$playlist['date_created'], $runtime, $playlist['genre'], $playlist['title'], $playlist['id'], $playlist['id'], $runtime, $playlist['genre']))) {
				zina_debug(zt('search play pop: Could not insert @playlist into {search_index}',array('@dir'=>$playlist['title'])));
			}
		}
	}
	zvar_set("cron_search_playlist_last_run", $runtime);
}

function zdb_genre_populate($runtime, &$context) {
	global $zc;
	$context['message'] = zt('Populating genres');

	$genres = "(SELECT DISTINCT genre FROM {dirs} WHERE genre IS NOT NULL ".
		"UNION ".
		"SELECT DISTINCT genre FROM {files} WHERE genre IS NOT NULL)";

	if ($zc['genres_custom']) {
		zdbq("UPDATE {genres} SET actual = 0 WHERE genre NOT IN $genres");
	} else {
		$empties = zdbq_array_list("SELECT id FROM {genres} WHERE genre NOT IN $genres AND ISNULL(description)");
		foreach($empties as $id) zdb_genre_delete($id);
	}

	zdbq("INSERT {genres} (genre, actual) SELECT genre, 1 FROM $genres as u ON DUPLICATE KEY UPDATE actual = 1");

	zdbq("INSERT {search_index} (title, type, path, context, file_mtime, mtime) ".
		"SELECT genre, 'genre' as type, genre as path, null as context, $runtime, $runtime ".
		"FROM {genres} ".
		"WHERE genre NOT IN (SELECT path FROM {search_index} WHERE type = 'genre') ".
		"GROUP BY genre "
	);

	zdbq("DELETE FROM {search_index} WHERE type = 'genre' AND path NOT IN (SELECT genre FROM {genres})");

	$strays = zdbq_array_list("SELECT id FROM {genre_tree} WHERE id NOT IN (SELECT id FROM {genres})");
	foreach($strays as $id) {
		zdbq("DELETE FROM {genre_tree} WHERE id = %d", array($id));
		zdbq("UPDATE {genre_tree} SET path = REPLACE(path, '%d/', '')", array($id));
	}

	$missing = zdbq_assoc_list("SELECT id, genre FROM {genres} WHERE id NOT IN (SELECT id FROM {genre_tree}) GROUP BY genre");
	if (!empty($missing)) {
		$order = zdbq_single('SELECT MAX(weight) FROM {genre_tree}') + 1;
		foreach($missing as $id => $g) {
			zdbq("INSERT INTO {genre_tree} SELECT g.id, IF(ISNULL(gt.path), g.id ,CONCAT(gt.path, '/', g.id)), $order ".
				"FROM {genres} AS g LEFT OUTER JOIN {genre_tree} AS gt ON g.pid = gt.id WHERE g.id = $id");
			$order++;
		}
	}
	zvar_set("cron_search_genre_last_run", $runtime);
}

function zdb_playlists_sum_items_update($runtime, &$context) {
	$context['message'] = zt('Updating playlists sum items column');
	if (zdbq("UPDATE {playlists} AS p SET p.sum_items = (SELECT COUNT(*) FROM {playlists_map} AS pm WHERE pm.playlist_id = p.id)")) {
		zvar_set("cron_playlists_sum_items", $runtime);
	}
}


function zdb_cron_run(&$operations = null) {
	global $zc;
	$runtime = time();

	if ($runtime - zvar_get("cron_populate_last_run", 0) > $zc['cache_expire'] * 86400) {
		#$operations[] = array('zdb_populate', array(zvar_get('cron_populate_last_run', 0), '', false, $runtime));
		$operations[] = array('zdb_populate_batch', array(false));
	}
	if ($runtime - zvar_get("cron_search_playlist_last_run", 0) > $zc['cache_expire'] * 86400) {
		$operations[] = array('zdb_search_playlist_populate', array($runtime));
	}
	if ($runtime - zvar_get("cron_search_genre_last_run", 0) > $zc['cache_expire'] * 86400) {
		$operations[] = array('zdb_genre_populate', array($runtime));
	}

	if ($zc['cache_stats']) {
		if ($runtime - zvar_get("cron_stats_last_run", 0) > $zc['cache_stats_expire'] * 3600) {
			$operations[] = array(array('zdb_stats_generate'),array($runtime));
		}
	}

	if ($runtime - zvar_get("cron_playlists_sum_items", 0) > $zc['cache_expire'] * 86400) {
		$operations[] = array(array('zdb_playlists_sum_items_update'),array($runtime));
	}
}

function zina_get_stats_pages() {
	return ztheme('stats_pages', array(
		'rating' =>zt('Highest Rated'),
		'votes'  =>zt('Most Voted On'),
		'views'  =>zt('Most Viewed'),
		'plays'  =>zt('Most Played'),
		'downloads'=>zt('Most Downloaded'),
		'latest' =>zt('Recently Listened To'),
		'added' =>zt('Recently Added'),
	));
}

function zina_get_stats_blocks() {
	$arr = zina_get_stats_pages();
	#unset($arr['stats']);
	$arr['random'] = zt('Random');
	return $arr;
}

function zina_get_stats_periods() {
	return ztheme('stats_periods', array(
		'all'=>zt('All-Time'),
		'year'=>zt('Last Year'),
		'month'=>zt('Last 30 days'),
		'week'=>zt('Last 7 days'),
		'day'=>zt('Last 24 hours')));
}

function zina_get_stats_types() {
	return ztheme('stats_types', array(
		'summary'  =>zt('Summary'),
		'artist'=>zt('Artists'),
		'album'=>zt('Albums'),
		'song'=>zt('Songs'),
		'playlist'=>zt('Playlists'),
		)
	);
}

#type = song, artist, album, etc
function zina_get_stat_helper($type, $stat, $period) {
	global $zc;

	$arr = zdb_get_stat_sql($stat, $period);
	if (empty($arr)) return array();
	$stats = array();

	foreach($arr as $key => $val) {
		if ($key == $type) {
			$db_stat = 'stats_'.$stat.$period.'_'.$type;
			if ($zc['cache_stats'] && $stat != 'latest') {
				$stats = zvar_get($db_stat, array());
			} else {
				$stats = zdbq_array($val['sql']);
			}
			break;
		}
	}
	return $stats;
}

function zina_get_block_stat($type, $page, $period, $num, $id3 = false, $images = true) {
	global $zc;
	# back-compat - remove in 3.0
	$map_old = array('f'=>'song', 'a'=>'artist', 't'=>'album');
	if (isset($map_old[$type])) $type = $map_old[$type];

	$limit = $zc['stats_limit'];
	$zc['stats_limit'] = $num;
	$stats = zina_get_stat_helper($type, $page, $period);
	$zc['stats_limit'] = $limit;

	zina_content_search_list($stats, false, array('images'=>$images,'types'=>false), array('stat_type'=>$type));

	if (!empty($stats)) {
		foreach($stats as $key=>$stat) {
			$item = &$stats[$key];
			$item['path'] = utf8_decode($stat['path']);
			$item['url'] = zurl($stat['title_link']['path'],$stat['title_link']['query']);
			$item['display'] = zl($stat['title'],$stat['title_link']['path'],$stat['title_link']['query'], null, false, isset($stat['title_link']['attr']) ? $stat['title_link']['attr'] : '');
			if ($images) {
				if (isset($stat['description']) && !empty($stat['description'])) {
					$item['display'] .= '<br/><small>'.ztheme('description_teaser', $stat['description']).'</small>';
				}
			}
			$item['image_url'] = ($type == 'song') ? zurl(dirname($item['path'])) : $item['url'];
		}
	}
	return $stats;
}

function zina_playlist_get_info($pls_id) {
	global $zc;

	$where = 'p.id = %d '.((!$zc['is_admin']) ? (($zc['pls_public']) ? 'AND (p.user_id = %d OR p.visible = 1)' : 'AND p.user_id = %d') : '');

	return zdbq_array_single("SELECT p.*, ".
		"d.path, g.genre, d.path as image_path ".
		"FROM {playlists} AS p ".
		"LEFT OUTER JOIN {dirs} AS d ON (p.dir_id = d.id) ".
		"LEFT OUTER JOIN {genres} AS g ON (p.genre_id = g.id) ".
		"WHERE $where", array($pls_id, $zc['user_id']));
}

function zina_playlist_get_items($pls_id) {
	return zdbq_array("SELECT pm.type, pm.weight, ".
		"CASE pm.type WHEN 'song' THEN IF(f.path!='.',CONCAT(f.path,IF(ISNULL(f.path), '','/'),f.file),f.file) WHEN 'album' THEN d.path WHEN 'playlist' THEN p.id END AS path, ".
		"CASE pm.type WHEN 'song' THEN f.id3_info WHEN 'album' THEN d.title WHEN 'playlist' THEN p.title END AS id3_info, ".
		"CASE pm.type WHEN 'song' THEN f.id WHEN 'album' THEN d.id WHEN 'playlist' THEN p.id END AS type_id, ".
		"CASE pm.type WHEN 'song' THEN f.year WHEN 'album' THEN d.year WHEN 'playlist' THEN NULL END AS year, ".
		"CASE pm.type WHEN 'song' THEN f.genre WHEN 'album' THEN d.genre WHEN 'playlist' THEN g.genre END AS genre, ".
		"CASE pm.type WHEN 'song' THEN f.sum_votes WHEN 'album' THEN d.sum_votes WHEN 'playlist' THEN p.sum_votes END AS sum_votes, ".
		"CASE pm.type WHEN 'song' THEN f.sum_rating WHEN 'album' THEN d.sum_rating WHEN 'playlist' THEN p.sum_rating END AS sum_rating ".
		",CASE pm.type WHEN 'playlist' THEN p.genre_id END AS genre_id ".
		",CASE pm.type WHEN 'playlist' THEN p.id END AS id ".
		",CASE pm.type WHEN 'playlist' THEN p.title END AS title ".
		",if(pm.type='playlist', p.image_type, FALSE) as image_type ".
		",if(pm.type='playlist', pd.path, FALSE) as image_path ".
		"FROM {playlists_map} as pm ".
		"LEFT JOIN {playlists} AS p ON (pm.type = 'playlist' AND pm.type_id = p.id) ".
		"LEFT JOIN {files} AS f ON pm.type = 'song' AND pm.type_id = f.id ".
		"LEFT JOIN {dirs} AS d ON pm.type = 'album' AND pm.type_id = d.id ".
		"LEFT OUTER JOIN {dirs} AS pd ON (pm.type = 'playlist' AND pm.type_id = p.id AND p.dir_id = pd.id) ".
		"LEFT OUTER JOIN {genres} AS g ON (pm.type = 'playlist' AND p.genre_id = g.id) ".
		"WHERE playlist_id = %d ".
		"ORDER BY weight",
		array($pls_id));
}

function zina_playlist_get_songs($pls_id) {
	static $playlists = array();

	if (isset($playlists[$pls_id]) && $playlists[$pls_id] > 1) {
		return array();
	} elseif (isset($playlists[$pls_id])) {
		$playlists[$pls_id]++;
	} else {
		$playlists[$pls_id] = 1;
	}

	$items = zina_playlist_get_items($pls_id);

	$key=0;
	foreach($items as $item) {
		if ($item['type'] == 'album') {
			#TODO: user SORT ORDER?
			$tracks = zdbq_array("SELECT 'song' AS type, IF(f.path!='.',CONCAT(f.path,IF(ISNULL(f.path), '','/'),f.file),f.file) AS path, ".
				"description, id3_info, genre, year, id ".
				"FROM {files} AS f ".
				"WHERE dir_id = %d ORDER BY file", array($item['type_id']));
			array_splice($items, $key, 1, $tracks);
			$key += sizeof($tracks);
		} elseif ($item['type'] == 'playlist') {
			$playlist = zina_playlist_get_songs($item['type_id']);
			array_splice($items, $key, 1, $playlist);
			$key += sizeof($playlist);
		} else {
			$key++;
		}
	}

	return $items;
}

function zina_playlist_feed($pls_id) {
	global $zc;

	$rss = zina_playlist_get_info($pls_id);
	if (!$rss) return zina_not_found();

	$rss['title'] = zt('@title Playlist', array('@title'=>$rss['title']));
	$rss['desc'] = $rss['description'];

	if (isset($zc['conf']['site_name'])) $rss['title'] = $zc['conf']['site_name'] .' - '.$rss['title'];

	$rss['page_url'] = zurl('', 'l=2&amp;pl='.$pls_id, null, true);
	$rss['link_url'] = zurl($pls_id.'/pls.xml', null, null, true);
	$rss['lang_code'] = $zc['lang'];

	$rss['type'] = 'playlist';
	$rss_image = zina_content_search_image($rss, 'sub', 'genre-image', true);
	if (preg_match('/src="(.*?)"/i', $rss_image, $matches)) {
		$rss['image_url'] = $matches[1];
	}
	#TODO: LOG???
	$items = zina_playlist_get_songs($pls_id);
	zina_podcast_items_helper($items, 'playlist');

	$rss['items'] = $items;
	while(@ob_end_clean());
	header('Content-type: application/xml');
	echo ztheme('rss', $rss, $zc['rss_podcast']);
	exit;
}

function zina_podcast_items_helper(&$items, $type, $type_opts = null) {
	global $zc;
	$direct = preg_match('#^'.$_SERVER['DOCUMENT_ROOT'].'#i', $zc['mp3_dir']);

	foreach($items as $key => $item) {
		$i = &$items[$key];
		if (isset($i['id3_info'])) {
			$i['info'] = $info = unserialize_utf8($i['id3_info']);
		} else {
			$i['info'] = $info = zina_get_file_info($zc['mp3_dir'].'/'.$item['path']);
		}

		$i['artist'] = (isset($info->artist)) ?  $info->artist : false;
		$i['album'] = (isset($info->album)) ? $info->album : false;
		$i['duration'] = (isset($info->time)) ? $info->time : false;
		$i['size'] = $info->filesize;
		$mtime = (isset($item['mtime'])) ? $item['mtime'] : filemtime($info->file);
		$i['pub'] = date('r', $mtime);

		if (!isset($i['title'])) {
			if (isset($info->title)) $i['title'] = ztheme('artist_song', $info->title, $info->artist);
			if (!isset($i['title'])) $i['title'] = zina_get_file_artist_title($item['path'], false);
		}

		if ($zc['play']) {
			$i['url'] = zurl($item['path'],NULL,NULL,TRUE, $direct);
			$i['url_enc'] = utf8_encode(rawurldecode($i['url']));
		} else {
			$i['url'] = zurl(dirname($item['path']),NULL,NULL,TRUE, $direct);
		}

		if ($type == 'stat') {
			$i['description'] = zina_content_stat_feed_desc($type_opts, $item);
		}

		if ($zc['stats_images']) {
			$i['image_url'] = zina_content_search_image($i, 'search', null, true);
		}
	}
}

function zina_stats_feed($path) {
	global $zc;
	$podcast = false;
	$opts = explode('/', $path);
	if (sizeof($opts) != 4) return false;

	$stat = $opts[0];
	$period = $opts[1];
	$type = $opts[2];

	$types = zina_get_stats_types();
	$pages = zina_get_stats_pages();
	$periods = zina_get_stats_periods();

	if (!array_key_exists($period, $periods)) return false;
	if (!array_key_exists($stat, $pages) && ($stat == 'none' && $type != 'summary')) return false;
	if (!array_key_exists($type, $types)) return false;

	$items = zina_get_stat_helper($type, $stat, $period);

	if ($type == 'summary') {
		$rss['title'] = zt('Summary');
	} elseif ($stat == 'latest' || $stat == 'added') {
		$rss['title'] = zt($pages[$stat] . ' ' .$types[$type]);
	} else {
		$rss['title'] = zt($pages[$stat] . ' ' .$types[$type].' ' .$periods[$period]);
	}
	$rss['desc'] = $rss['title'];

	if (isset($zc['conf']['site_name'])) $rss['title'] = $zc['conf']['site_name'] .' - '.$rss['title'];

	$rss['page_url'] = zurl('', 'l=15', null, true);
	$rss['link_url'] = zurl($stat.'/'.$period.'/'.$type.'/stats.xml', null, null, true);
	$rss['lang_code'] = $zc['lang'];

	if (!empty($items)) {
		if ($type == 'song') {
			$podcast = $zc['rss_podcast'];
			zina_podcast_items_helper($items, 'stat', $stat);
		} else {
			foreach($items as $key => $item) {
				$i = &$items[$key];
				if ($type == 'playlist') {
					$i['url'] = zurl('', 'l=2&pl='.$item['id'],NULL,TRUE);
					$i['description'] = zina_content_stat_feed_desc($stat, $item, $item['description']);
				} else {
					$i['title'] = ztheme('artist_album', $item['path'], true);
					$i['url'] = zurl($item['path'],NULL,NULL,TRUE);
					$i['description'] = zina_content_stat_feed_desc($stat, $item);
				}

				if ($zc['stats_images']) {
					$i['image_url'] = zina_content_search_image($i, 'search', null, true);
				}
			}
		}
	}
	$rss['items'] = $items;
	while(@ob_end_clean());
	header('Content-type: application/xml');
	echo ztheme('rss', $rss, $podcast);
	exit;
}

function zina_content_stat_feed_desc($stat, $item, $desc = '') {
	$description = (empty($desc)) ? '' : $desc."\n\n";

	if ($stat == 'votes' || $stat == 'rating') {
		$description .= zt('Rating: @stat', array('@stat'=>$item['stat'])).' '.ztheme('votes_display',$item['votes']);
	} elseif ($stat == 'latest' || $stat == 'added') {
		$description .= zt(ucfirst($stat)).': '.ztheme('stat_date', $item['stat']);
	} elseif ($stat == 'none') {
		$description .= number_format($item['stat']);
	} else {
		$description .= zt('@num @stat', array('@stat'=>$stat, '@num'=>number_format($item['stat'])));
	}
	return $description;
}

function zina_content_stats(&$zina, $opts) {
	global $zc;

	$stat = $opts['stat'];
	$period = $opts['period'];
	$type = $opts['type'];

	$types = zina_get_stats_types();
	$stat_pages = zina_get_stats_pages();
	$periods = zina_get_stats_periods();

	#$sumstat = (zvar_get('cron_populate_last_run', false));
	#if (!$sumstat) unset($stat_pages['stats']);

	if (!array_key_exists($type, $types)) $type = 'artist';
	if (!array_key_exists($stat, $stat_pages)) $stat = 'rating';
	if (!array_key_exists($period, $periods)) $period = 'all';

	if ($type == 'summary') {
		$page_title = zt('Summary');
		$stat = 'none';
		$period = 'all';
	} else {
		$page_title = $stat_pages[$stat].' '.$types[$type]. ' '.$periods[$period];
	}

	zina_content_breadcrumb($zina, '', $page_title);

	$statform = array(
		'type' => array(
			'options' => $types,
			'default' => $type,
		),
		'playlist' => array(
			'options' => $stat_pages,
			'default' => $stat,
		),
		'period' => array(
			'options' => $periods,
			'default' => $period,
		),
	);
	$zina['statsformselect'] = ztheme('stats_form_select', zurl('', 'l=15'), $statform);
	$items['checkboxes'] = (($zc['is_admin'] && $zc['cache']) || $zc['session_pls']);

	$html = '';
	$arr = zdb_get_stat_sql($stat, $period);

	if (isset($arr[$type])) {
		$val = $arr[$type];

		$db_stat = 'stats_'.$stat.$period.'_'.$type;
		#todo: latest not cached...test with big db?
		if ($zc['cache_stats'] && $stat != 'latest') {
			$stats = zvar_get($db_stat, array());
		} else {
			$stats = zdbq_array($val['sql']);
		}
	} else {
		$stats = array();
	}

	$rss_path = ($zc['stats_rss']) ? $stat.'/'.$period.'/'.$type.'/stats.xml' : false;

	if (!empty($stats)) {
		if ($type == 'playlist') {
			zina_playlists_list_helper($stats, false);
		}
		if ($rss_path) {
			if ($type == 'song' && !$zc['rss_podcast']) $type = 'xxx';
			$rss_title = ($type == 'song') ? zt($page_title.' Podcast') : zt($page_title.' RSS Feed');
			zina_set_html_head('<link rel="alternate" type="application/rss+xml" title="'.$rss_title.'" href="'.zurl($rss_path).'">');
		} else {
			$rss_title = '';
		}
		zina_content_search_list($stats, $items['checkboxes'], array('images'=>$zc['stats_images'],'types'=>false), array('stat_type'=>$stat));
		$html .= ztheme('stats_section',$val['title'] ,ztheme('stats_list',$stats, false, true), $type, $zina, $rss_title, $rss_path);
	}

	$form_id = 'zinastatsform';
	$form_attr = 'id="'.$form_id.'" action="'.zurl('','m=1').'"';
	$list_opts = '';
	if ($zc['playlists'] && !empty($html) && $type != 'summary') {
		$list_opts = ($zc['playlists']) ? ztheme('search_list_opts', zina_content_song_list_opts(false,  false, $items['checkboxes'], $form_id, true), $form_id) : null;
	}

	$zina['content'] = ztheme('stats_page', $form_attr, $html, $list_opts, $zina);
}

function zdb_populate($last, $path='', $regen = false, $runtime = false) {
	global $zc;
	$subdirs = $slash = '';
	if (!empty($path)) $slash = '/';

	@set_time_limit($zc['timeout']);
	$dir = $zc['mp3_dir'].$slash.$path;
	$d = dir($dir);

	if ($regen || filemtime($dir) > $last) {
		zdb_log_stat('insertonly', $path);
		$mod = true;
	} else {
		$mod = false;
	}
	while($entry = $d->read()) {
		if ($entry == '.' || $entry == '..') continue;
		if (is_dir($zc['mp3_dir'].$slash.$path.'/'.$entry) && substr($entry,0,1) != $zc['dir_skip']) {
			zdb_populate($last, $path.$slash.$entry, $regen);
		} elseif ($mod && (preg_match('/\.('.$zc['ext_mus'].')$/i', $entry) ||
			($zc['remote'] && preg_match('/\.('.$zc['remote_ext'].')$/i', $entry)))) {
			zdb_log_stat('insertonly', $path, $entry, null, $regen);
		}
	}
	if (function_exists('zina_cms_populate')) {
		zina_cms_populate($path);
	}
	$d->close();

	$semaphore = $zc['cache_dir_private_abs'].'/.cron';
	if ($runtime && file_exists($semaphore)) {
		zvar_set('cron_populate_last_run', $runtime);
	}
}

function zdb_clean_find($type) {
	global $zc;
	@set_time_limit($zc['timeout']);
	$missing = array();
	if ($type == 'file') {
		$sql = "SELECT f.id, f.file, IF(d.path!='.',CONCAT(d.path,IF(ISNULL(d.path), '','/'),f.file),f.file) AS path ".
			"FROM {files} AS f INNER JOIN {dirs} AS d ON (f.dir_id = d.id)";
	} else {
		$sql = 'SELECT id, path FROM {dirs}';
	}
	$found = zdbq_array($sql);

	foreach($found as $key=>$item) {
		#todo: whyz newlinez in db? track it down!
		$item_path = utf8_decode(rtrim($item['path'],"\r\n"));
		if (!file_exists($zc['mp3_dir'].'/'.$item_path)) {
			$search = preg_replace('/\.('.$zc['ext_mus'].')$/i','',$item_path);
			if (preg_match('/^.*[ _]?-[ _]?(.*?)$/i',$search,$match)) {
				$tmp = $match[1];
			} else {
				$len = strlen($search);
				$pos = ($len > 25) ? 25 : $len;
				$tmp = substr($search,-$pos);
			}
			if ($type == 'file') {
				$sql2 = "SELECT id, CONCAT(path,IF(ISNULL(path), '','/'),file) as path ".
					"FROM {files} ".
					"WHERE file LIKE '%%%s%%' AND id <> %d ORDER BY path LIMIT 25";
			} else {
				$sql2 = "SELECT id, path FROM {dirs} WHERE path LIKE '%%%s%%' AND id <> %d ".
					"ORDER BY path LIMIT 25";
				$tmp = basename($tmp);
				if (($pos = strripos($tmp, ',')) !== false) $tmp = substr($tmp, 0, $pos);//.','.substr($tmp, 0, $pos);
			}
			$moved = zdbq_array($sql2, array($tmp, $item['id']));
			$missing[] = array('id'=>$item['id'], 'path'=>$item['path'], 'opts'=>$moved);
		}
	}
	return $missing;
}

#type = dir or file
function zdb_clean($type, $arr) {
	$len = count($arr);
	$ids = 'z'.$type.'ids';
	$ids_new = $ids.'_new';

	for($i=0; $i<$len; $i++) {
		if($_POST[$ids_new][$i] == -1) {
			zdb_remove($type,$_POST[$ids][$i]);
		} elseif($_POST[$ids_new][$i] == -2) { #IGNORE
			continue;
		} elseif($_POST[$ids_new][$i] == -3) { #todo:MANUAL???
		} elseif($_POST[$ids_new][$i] > 0) {
			zdb_move($type, $_POST[$ids][$i], $_POST[$ids_new][$i]);
		}
	}
}

function zdb_remove($type, $id) {
	if ($type == 'file') {
		zdbq('DELETE FROM {file_ratings} WHERE file_id = %d', $id);
		zdbq('DELETE FROM {file_plays} WHERE file_id = %d', $id);
		zdbq('DELETE FROM {file_downloads} WHERE file_id = %d', $id);
		zdbq("DELETE FROM {search_index} WHERE type_id = %d AND type = 'song'", $id);
		zdbq("DELETE FROM {playlists_map} WHERE type_id = %d AND type = 'song'", $id);
		zdbq('DELETE FROM {files} WHERE id = %d', $id);
	} else { #dir
		zdbq('DELETE FROM {dir_views} WHERE dir_id = %d', $id);
		zdbq('DELETE FROM {dir_ratings} WHERE dir_id = %d', $id);
		zdbq("DELETE FROM {search_index} WHERE type_id = %d AND (type = 'album' OR type = 'artist' OR type = 'directory')", $id);
		zdbq("DELETE FROM {playlists_map} WHERE type_id = %d AND type = 'album'", $id);
		#todo: parent_id???
		zdbq('DELETE FROM {dirs} WHERE id = %d', $id);
	}

	$runtime = time();
	$context = array();
	zdb_playlists_sum_items_update($runtime, $context);
}

function zdb_move($type, $id, $new_id) {
	#todo: when updating the clean-up stuff,
	# - update {search_index} (test)
	# - sum_total updates!
	if ($type == 'file') {
		zdbq("UPDATE {file_ratings} SET file_id = %d WHERE file_id = %d", array($new_id, $id));
		zdbq("UPDATE {file_plays} SET file_id = %d WHERE file_id = %d", array($new_id, $id));
		zdbq("UPDATE {file_downloads} SET file_id = %d WHERE file_id = %d", array($new_id, $id));
		zdbq("UPDATE {search_index} SET type_id = %d WHERE type_id = %d AND type = 'song'", array($new_id, $id));
		zdbq("UPDATE {playlists_map} SET type_id = %d WHERE type_id = %d AND type = 'song'", array($new_id, $id));
		zdbq("DELETE FROM {files} WHERE id = %d", $id);
	} else { #dir
		zdbq("UPDATE {dir_ratings} SET dir_id = %d WHERE dir_id = %d", array($new_id, $id));
		zdbq("UPDATE {dir_views} SET dir_id = %d WHERE dir_id = %d", array($new_id, $id));
		zdbq("UPDATE {files} SET dir_id = %d WHERE dir_id = %d", array($new_id, $id));
		zdbq("UPDATE {dirs} SET parent_id = %d WHERE parent_id = %d", array($new_id, $id));
		zdbq("UPDATE {search_index} SET type_id = %d WHERE type_id = %d AND (type = 'album' OR type = 'artist' OR type = 'directory')", array($new_id, $id));
		zdbq("UPDATE {playlists_map} SET type_id = %d WHERE type_id = %d AND type = 'album'", array($new_id, $id));
		zdbq("DELETE FROM {dirs} WHERE id = %d", $id);
	}
}

function zdb_genres_get_children($genre) {
	$children = array();
	$paths = zdbq_array_list("SELECT gt.path FROM {genres} AS g INNER JOIN {genre_tree} AS gt ON g.id=gt.id ".
			"WHERE g.genre = '%s'", array($genre));
	if (!empty($paths)) {
		foreach($paths as $path) {
			$children = array_merge($children, zdbq_array_list("SELECT genre FROM {genres} AS g INNER JOIN {genre_tree} AS gt ON g.id=gt.id ".
				"WHERE path like '%s/%%'", array($path)));
		}
	}
	return $children;
}

function zdb_genres_save($items) {
	zdbq("TRUNCATE TABLE {genre_tree}");
	$i = 0;
	foreach ($items as $item) {
		if (gettype($item) == 'array') { #is_array didnt work?
			$result = zdbq_array_single('SELECT * FROM {genres} WHERE id = %d', array($item['id']));

			if (empty($result)) {
				zdbq("INSERT {genres} (id, pid, genre) VALUES (%d, %d, '%s')",
					array($item['id'], $item['pid'], $item['genre']));
			} else {
				if ($item['pid'] !== $result['pid'] || $item['genre'] !== $result['genre']) {
					zdbq("UPDATE {genres} SET pid = %d, genre = '%s' WHERE id = %d",
						array($item['pid'], $item['genre'], $item['id']));
				}
			}

			zdbq("INSERT {genre_tree} SELECT g.id, IF(ISNULL(gt.path), g.id ,CONCAT(gt.path, '/', g.id)), $i ".
				"FROM {genres} AS g LEFT OUTER JOIN {genre_tree} AS gt ON g.pid = gt.id WHERE g.id = %d",
				array($item['id']));
			$i++;
		}
	}
	zdb_genre_populate(time(), $context);
}

function zdb_genre_delete($id) {
	$result = zdbq_array_single('SELECT * FROM {genres} WHERE id = %d', array($id));
	if (!empty($result)) {
		zdbq("DELETE FROM {genre_tree} WHERE id = %d", array($id));
		zdbq("UPDATE {genre_tree} SET path = REPLACE(path, '%d/', '')", array($id));
		zdbq("DELETE FROM {genres} WHERE id = %d", array($id));
		zdbq("UPDATE {genres} SET pid = %d WHERE pid = %d", array($result['pid'], $id));
		zdb_genre_populate(time(), $context);
		return true;
	}
	return false;
}

function zdb_get_stat_sql($stat, $period) {
	global $zc;

	$time = time();
	if ($period == 'year') {
		$time -= 31536000;
		$rp = 1;
	} elseif ($period == 'month') {
		$time -= 2592000;
		$rp = 2;
	} elseif ($period == 'week') {
		$time -= 604800;
		$rp = 3;
	} elseif ($period == 'day') {
		$time -= 86400;
		$rp = 4;
	} else {
		$period = 'all';
		$rp = 0;
	}

	$limit = $zc['stats_limit'];

	# Playlists
	# types: 1 = views, 2 = plays, 3 = ratings, 4 = downloads
	$pls_a = "SELECT p.id, d.path, d.path as image_path, p.user_id, p.title, p.title as playlist, @STAT, 'playlist' as type ".
		",p.id,p.description,p.user_id, p.genre_id, g.genre, p.image_type ".
		'FROM {playlists} AS p ';
	$pls_b = "INNER JOIN {playlists_stats} as ps ON (p.id = ps.playlist_id AND ps.stat_type = @TYPE_ID) ";
	$pls_c = 'LEFT OUTER JOIN {dirs} AS d ON (p.dir_id = d.id) '.
		'LEFT OUTER JOIN {genres} AS g ON (p.genre_id = g.id) ';

	$sql_pls_all = $pls_a.$pls_c;
	$sql_pls_periods = $pls_a.$pls_b.$pls_c;
	$sql_pls_order_by = "ORDER BY @ORDER DESC, p.title LIMIT $limit";

	$sql_song = "SELECT IF(path!='.' AND path!='',CONCAT(path,IF(ISNULL(path), '','/'),file),file) AS path, 'song' as type, genre, year, f.id3_info";
	$dir_tag = ($zc['dir_tags']) ? 'd.title,' : '';

	switch ($stat) {

		Case 'random':

			$sql = "SELECT $dir_tag path, '@type' as type, genre, year FROM {dirs} AS d @WHERE ORDER BY RAND() DESC LIMIT ".$limit;
			if ($zc['stats_org']) {
				$arr['artist'] = array('title'=>$zc['main_dir_title'],
					'sql'=> str_replace(array('@WHERE','@type'), array('WHERE level = 1','artist'), $sql));
				$arr['album'] = array('title'=>zt('Albums'),
					'sql'=> str_replace(array('@WHERE','@type'), array('WHERE level = 2','album'), $sql));
			} else {
				$arr['directory'] = array('title'=>zt('Directories'),
					'sql'=> str_replace(array('@WHERE','@type'), array('','directory'), $sql));
			}

			$arr['song'] = array('title'=>zt('Files'),
				'sql'=>"$sql_song FROM {files} AS f ORDER BY RAND() LIMIT ".$limit);

			$arr['playlist'] = array('title'=>zt('Playlists'),
					'sql'=>str_replace('@STAT', "null as stat", $sql_pls_all) . "ORDER BY RAND() LIMIT ".$limit);
			break;

		Case 'added':

			$sql = "SELECT $dir_tag path, mtime as stat, '@type' as type, genre, year FROM {dirs} AS d @WHERE ORDER BY mtime DESC LIMIT ".$limit;
			if ($zc['stats_org']) {
				$arr['artist'] = array('title'=>$zc['main_dir_title'],
					'sql'=> str_replace(array('@WHERE','@type'), array('WHERE level = 1','artist'), $sql));
				$arr['album'] = array('title'=>zt('Albums'),
					'sql'=> str_replace(array('@WHERE','@type'), array('WHERE level = 2','album'), $sql));
			} else {
				$arr['directory'] = array('title'=>zt('Directories'),
					'sql'=> str_replace(array('@WHERE','@type'), array('','directory'), $sql));
			}

			$arr['song'] = array('title'=>zt('Files'),
				'sql'=>"$sql_song, mtime as stat FROM {files} AS f ORDER BY stat DESC LIMIT ".$limit
				);

			$arr['playlist'] = array('title'=>zt('Playlists'),
					'sql'=>str_replace('@STAT', "p.date_created as stat", $sql_pls_all) .
						str_replace('@ORDER', 'stat', $sql_pls_order_by)
				);
			break;

		Case 'latest': # recently listened to

			$sql = "SELECT $dir_tag @PATH, MAX(fp.mtime) as stat, '@type' as type, d.genre, d.year ".
				'FROM {file_plays} as fp '.
				'INNER JOIN {files} AS f ON (fp.file_id = f.id) '.
				'INNER JOIN {dirs} AS d ON (f.dir_id = d.id) '.
				'@WHERE GROUP BY @GROUP ORDER BY stat DESC LIMIT '.$limit;

			if ($zc['stats_org']) {
				$arr['artist'] = array('title'=>$zc['main_dir_title'],
					'sql'=> str_replace(array('@PATH', '@WHERE', '@GROUP', '@type'),
					array('d2.path', 'INNER JOIN {dirs} as d2 ON (d.parent_id = d2.id) WHERE d2.level = 1','d2.path', 'artist'), $sql));
				$arr['album'] = array('title'=>zt('Albums'),
					'sql'=> str_replace(array('@PATH', '@WHERE','@GROUP', '@type'), array('d.path', 'WHERE d.level = 2','f.dir_id', 'album'), $sql));
			} else {
				$arr['directory'] = array('title'=>zt('Directories'),
					'sql'=> str_replace(array('@PATH', '@WHERE','@GROUP', '@type'),array('d.path', '','f.dir_id', 'directory'), $sql));
			}

			$arr['song'] = array('title'=>zt('Files'),
				'sql'=>"$sql_song, MAX(fp.mtime) as stat ".
					"FROM {file_plays} as fp ".
					"INNER JOIN {files} AS f ON (fp.file_id = f.id) ".
					"GROUP BY f.id ".
					"ORDER BY stat DESC LIMIT ".$limit
				);

			$arr['playlist'] = array('title'=>zt('Playlists'),
				'sql'=>str_replace(array('@STAT','@TYPE_ID'), array("MAX(ps.mtime) as stat", 2), $sql_pls_periods) .
					"GROUP BY p.id ".
					str_replace('@ORDER', 'stat', $sql_pls_order_by)
			);

			break;

		Case 'views':

			if ($period == 'all') {
				$sql = "SELECT $dir_tag d.path, d.sum_views as stat, '@type' as type, genre, year FROM {dirs} as d WHERE d.level @level ".
					'GROUP BY d.path ORDER BY stat DESC, d.path LIMIT '.$limit;
				if ($zc['stats_org']) {
					$arr['artist'] = array('title'=>$zc['main_dir_title'],
						'sql'=> str_replace(array('@level','@type'), array('= 1','artist'), $sql));
					$arr['album'] = array('title'=>zt('Albums'),
						'sql'=> str_replace(array('@level','@type'), array('= 2','album'), $sql));
				} else {
					$arr['directory'] = array('title'=>zt('Directories'),
						'sql'=> str_replace(array('@level','@type'), array('!= 0','directory'), $sql));
				}

				$arr['playlist'] = array('title'=>zt('Playlists'),
					'sql'=>str_replace('@STAT', "p.sum_views as stat", $sql_pls_all) .
						"WHERE p.sum_$stat > 0 ".
						str_replace('@ORDER', 'stat', $sql_pls_order_by)
				);
			} else {
				$sql = "SELECT $dir_tag d.path, COUNT(*) as stat, '@type' as type, genre, year FROM {dirs} AS d ".
					'INNER JOIN {dir_views} AS v ON (d.id = v.dir_id) '.
					'WHERE d.level @level AND v.mtime > '.$time.
					' GROUP BY d.id ORDER BY stat DESC, d.path LIMIT '.$limit;

				if ($zc['stats_org']) {
					$arr['artist'] = array('title'=>$zc['main_dir_title'],
						'sql'=> str_replace(array('@level','@type'), array('= 1','artist'), $sql));
					$arr['album'] = array('title'=>zt('Albums'),
						'sql'=> str_replace(array('@level','@type'), array('= 2','album'), $sql));
				} else {
					$arr['directory'] = array('title'=>$zc['main_dir_title'],
						'sql'=> str_replace(array('@level','@type'), array('!= 0','directory'), $sql));
				}

				$arr['playlist'] = array('title'=>zt('Playlists'),
					'sql'=>str_replace(array('@STAT', '@TYPE_ID'), array("COUNT(*) as stat",1), $sql_pls_periods) .
						"WHERE ps.mtime > $time ".
						"GROUP BY p.id ".
						str_replace('@ORDER', 'stat', $sql_pls_order_by)
				);
			}
			break;

		Case 'plays':
		Case 'downloads':

			if ($period == 'all') {
				if ($zc['stats_org']) {
					$sql_a = 'IFNULL((SELECT sum(sum_'.$stat.') FROM {files} WHERE d.id = dir_id),0)';
					$arr['artist'] = array('title'=>$zc['main_dir_title'],
						'sql'=>"SELECT $dir_tag d.id, d.path, 'artist' as type, d.genre, d.year, ".$sql_a.' + '.
							'IFNULL((SELECT sum(f.sum_'.$stat.') FROM {dirs} as d2 INNER JOIN {files} as f ON (d2.id = f.dir_id) WHERE d.id = d2.parent_id),0) as stat '.
							'FROM {dirs} as d WHERE d.level = 1 ORDER BY stat desc, d.path LIMIT '.$limit);
					$arr['album'] = array('title'=>zt('Albums'),
						'sql'=>"SELECT $dir_tag d.id, d.path, 'album' as type, d.genre, d.year, ".$sql_a.' as stat '.
							'FROM {dirs} as d WHERE d.level = 2 ORDER BY stat desc, d.path LIMIT '.$limit);
				} else {
					$arr['directory'] = array('title'=>zt('Directories'),
						'sql'=>"SELECT path, SUM(sum_'.$stat.') as stat, 'directory' as type, genre, year FROM {files} ".
							'GROUP BY path ORDER BY stat desc, path LIMIT '.$limit);
				}

				$arr['song'] = array('title'=>zt('Files'),
					'sql'=>"$sql_song, sum_$stat as stat FROM {files} AS f ORDER BY stat DESC, path LIMIT $limit");

				if ($stat == 'plays') {
					$arr['playlist'] = array('title'=>zt('Playlists'),
						'sql'=>str_replace('@STAT', "p.sum_$stat as stat", $sql_pls_all) .
							"WHERE p.sum_$stat > 0 ".
							str_replace('@ORDER', 'stat', $sql_pls_order_by)
					);
				}
			} else {
				if ($zc['stats_org']) {
					$sql_a = 'SELECT COUNT(*) FROM {files} AS f '.
   						'INNER JOIN {file_'.$stat.'} AS fp ON (f.id = fp.file_id) '.
   						'INNER JOIN {dirs} AS d1 ON (d1.id = f.dir_id) '.
   						'WHERE d.id = d1.id AND fp.mtime > '.$time;
					$arr['artist'] = array('title'=>$zc['main_dir_title'],
						'sql'=>"SELECT $dir_tag d.path, 'artist' as type, d.genre, d.year, ( ".
   						'SELECT COUNT(*) FROM {files} AS f '.
   						'INNER JOIN {file_'.$stat.'} AS fp ON (f.id = fp.file_id) '.
   						'INNER JOIN {dirs} AS d1 ON (d1.id = f.dir_id) '.
   						'WHERE d.id = d1.parent_id AND fp.mtime > '.$time.
							')+('.$sql_a.') as stat FROM {dirs} as d WHERE d.level = 1 '.
							'GROUP BY d.id ORDER BY stat DESC, d.path LIMIT '.$limit);
					$arr['album'] = array('title'=>zt('Albums'),
						'sql'=>"SELECT $dir_tag d.path, 'album' as type, d.genre, d.year, (".$sql_a.') as stat '.
							'FROM {dirs} as d WHERE d.level = 2 GROUP BY d.id ORDER BY stat DESC, d.path LIMIT '.$limit);
				} else {
					#TODO: no dir_tags because no {dirs}?
					$arr['directory'] = array('title'=>zt('Directories'),
						'sql'=>"SELECT path, COUNT(*) AS stat, 'directory' as type, genre, year FROM {files} AS f ".
							'INNER JOIN {file_'.$stat.'} AS fr ON (f.id = fr.file_id) WHERE fr.mtime > '.$time.
							' GROUP BY path ORDER BY stat DESC, path LIMIT '.$limit);

				}

				$arr['song'] = array('title'=>zt('Files'),
					'sql'=>"$sql_song, stat FROM (".
						'SELECT file_id, COUNT(*) AS stat FROM {file_'.$stat.'} AS fp WHERE fp.mtime > '.$time.' GROUP BY file_id '.
						'ORDER BY stat DESC LIMIT '.$limit.
						') AS sub '.
						'INNER JOIN {files} AS f ON (sub.file_id = f.id)');

				if ($stat == 'plays') {
					$arr['playlist'] = array('title'=>zt('Playlists'),
						'sql'=>str_replace(array('@STAT', '@TYPE_ID'), array("COUNT(*) as stat", 2), $sql_pls_periods) .
							"WHERE ps.mtime > $time ".
							"GROUP BY p.id ".
							str_replace('@ORDER', 'stat', $sql_pls_order_by)
					);
				}
			}
			break;

		Case 'rating':
		Case 'votes':

			$ra = explode(',',$zc['rating_limit']);
			$votes = $ra[$rp];

			if ($period == 'all') {
				if ($zc['stats_org']) {
					$arr['artist'] = array('title'=>$zc['main_dir_title'],
						'sql'=>"SELECT $dir_tag path, sum_rating as stat, sum_votes as votes, 'artist' as type, genre, year ".
							'FROM {dirs} AS d WHERE level = 1 AND sum_votes >= '.$votes.
							' ORDER BY sum_'.$stat.' DESC, path LIMIT '.$limit);
					$arr['album'] = array('title'=>zt('Albums'),
						'sql'=>"SELECT $dir_tag path, sum_rating as stat, sum_votes as votes, 'album' as type, genre, year ".
							'FROM {dirs} AS d WHERE level = 2 AND sum_votes >= '.$votes.
							' ORDER BY sum_'.$stat.' DESC, path LIMIT '.$limit);

				} else {
					$arr['directory'] = array('title'=>zt('Directories'),
						'sql'=>"SELECT $dir_tag path, sum_rating as stat, sum_votes as votes, \'directory\' as type, genre, year FROM {dirs} AS d ".
							'WHERE sum_votes > '.$votes.
							' ORDER BY sum_'.$stat.' DESC, path LIMIT '.$limit);
				}

				$arr['song'] = array('title'=>zt('Files'),
					'sql'=>"$sql_song, sum_rating as stat, sum_votes as votes ".
						'FROM {files} AS f WHERE sum_votes >= '.$votes.' ORDER BY sum_'.$stat.' DESC, path LIMIT '.$limit);

				$arr['playlist'] = array('title'=>zt('Playlists'),
					'sql'=>str_replace('@STAT', "p.sum_rating as stat, p.sum_votes as votes", $sql_pls_all) .
						"WHERE p.sum_votes >= $votes ".
						str_replace('@ORDER', "p.sum_$stat", $sql_pls_order_by)
					);

			} else {
				$order = ($stat == 'rating') ? 'stat' : 'votes';
				$sql = "SELECT $dir_tag d.path, avg(rating) as stat, COUNT(*) as votes, '@type' as type, d.genre, d.year FROM {dirs} AS d ".
					'INNER JOIN {dir_ratings} AS v ON (d.id = v.dir_id) '.
					'WHERE d.level @level AND v.mtime > '.$time.
					' GROUP BY d.id ORDER BY '.$order.' DESC, d.path LIMIT '.$limit;

				if ($zc['stats_org']) {
					$arr['artist'] = array('title'=>$zc['main_dir_title'],
						'sql'=> str_replace(array('@level','@type'), array('= 1','artist'), $sql));
					$arr['album'] = array('title'=>zt('Albums'),
						'sql'=> str_replace(array('@level','@type'), array('= 2','album'), $sql));
				} else {
					$arr['directory'] = array('title'=>zt('Directories'),
						'sql'=> str_replace(array('@level','@type'), array('!= 0','directory'), $sql));
				}

				$arr['song'] = array('title'=>zt('Files'),
					'sql'=>"$sql_song, sub.rating as stat, sub.votes FROM(".
   					'SELECT file_id, AVG(rating) as rating, COUNT(*) as votes FROM {file_ratings} '.
   					'WHERE mtime > '.$time.'  GROUP BY file_id HAVING votes >= '.$ra[$rp].
						') as sub '.
						'INNER JOIN {files} AS f on (sub.file_id = f.id) '.
						'ORDER BY '.$order.' DESC, path LIMIT '.$limit);

				$arr['playlist'] = array('title'=>zt('Playlists'),
					'sql'=>str_replace(array('@STAT', '@TYPE_ID'), array("AVG(ps.stat) AS stat, COUNT(*) AS votes", 3), $sql_pls_periods) .
						"WHERE ps.mtime > $time ".
						"GROUP BY p.id HAVING votes >= ".$ra[$rp].' '.
						str_replace('@ORDER', $order, $sql_pls_order_by)
				);
			}
			break;
	}

	if ($zc['stats_org']) {
		$sql = "SELECT '".$zc['main_dir_title']."' AS path, '".$zc['main_dir_title']."' AS title, ".
			"COUNT(*) AS stat, 'none' as type FROM {dirs} WHERE level = 1 ".
			"UNION SELECT '".zt('Albums')."' AS path, '".zt('Albums')."' AS title, COUNT(*) AS stat, 'none' as type FROM {dirs} WHERE level = 2";
	} else {
		$sql = "SELECT '".zt('Directories')."' AS path, '".zt('Directories')."' AS title, COUNT(*) AS stat, 'none' as type FROM {dirs}";
	}
	$sql .= " UNION SELECT '".zt('Songs')."' AS path, '".zt('Songs')."' AS title, COUNT(*) AS stat, 'none' as type FROM {files}";
	$sql .= " UNION SELECT '".zt('Playlists')."' AS path, '".zt('Playlists')."' AS title, COUNT(*) AS stat, 'none' as type FROM {playlists}";
	$sql .= " UNION SELECT '".zt('Genres')."' AS path, '".zt('Genres')."' AS title, COUNT(*) AS stat, 'none' as type FROM {genres}";
	$arr['summary'] = array('title'=>zt('Totals'), 'sql'=>$sql);

	return $arr;
}

#todo: give opt to play only those by your user_id if logged in???
#TODO: zina_least_played for artist? album?
function zdb_get_random_by_rating($type, $rating, $num = 0) {
	global $zc;
	if ($type == 's') {
		if ($zc['random_least_played']) {
			$total = zdbq_single('SELECT COUNT(*) FROM {files} WHERE sum_rating >= %f', array($rating));
			if ($total > $num && $num != 0) {
				return zina_least_played($num, 'sum_rating >= %f AND', array($rating));
			}
		}
		$sql = "SELECT IF(path!='.',CONCAT(path,IF(ISNULL(path), '','/'),file),file) AS path ".
			"FROM {files} WHERE sum_rating >= %f";
	} elseif ($type == 'artist') {
		$sql = "SELECT IF(d.path!='.',CONCAT(d.path,IF(ISNULL(d.path), '','/'),f.file),f.file) AS path ".
			"FROM {files} as f ".
			"INNER JOIN {dirs} AS d ON (f.dir_id = d.id) ".
			"INNER JOIN {dirs} AS p ON (d.parent_id = p.id) ".
			"INNER JOIN {dir_ratings} AS r ON (r.dir_id = p.id) ".
			"WHERE p.level = 1 GROUP BY f.id HAVING SUM(r.rating) / COUNT(r.rating) >= %f";
	} else { # 'tt'
		$sql = "SELECT d.path FROM {dirs} AS d ".
			"INNER JOIN {dir_ratings} AS dr ON (d.id = dr.dir_id) ".
			"WHERE d.level = 2 GROUP BY d.id HAVING SUM(dr.rating) / COUNT(*) >= %f";
	}

	return zdbq_array_list($sql, $rating);
}

function zdb_log_stat($type, $path, $file = null, $rating = null, $regen = false, $generate = array()) {
	global $zc;
	static $runtime = false, $dir_ids = array();

	if (!$runtime) $runtime = time();

	$sql = array();
	$user_id = $zc['user_id'];

	if (empty($path) || $path == '.') {
		$level = 0;
		$path = '';
	} else {
		$path = preg_replace("|(/){2,}|",'$1',trim($path,'/'));
		$arr = explode('/', $path);
		$level = sizeof($arr);
	}

	if (empty($_SESSION['zpid'])) {
		$dir_id = $_SESSION['zpid'] = zdbq_single("SELECT id FROM {dirs} WHERE parent_id = 0 AND path = '.'");
	} else {
		$dir_id = $_SESSION['zpid'];
	}

	$cms_insert = $cms_tags = $cms_alias = false;

	if (function_exists('zina_cms_insert')) { # && is_admin?
		$cms_insert = true;
		$cms_tags = (function_exists('zina_cms_tags'));
		$cms_alias = (function_exists('zina_cms_alias'));
	}

	$path_cur = '';
	for($i=0; $i < $level; $i++) {
		unset($mp3);
		$title = $year = $genre = null;

		$cur_level = $i + 1;
		$parent_id = $dir_id;
		if ($i > 0) $path_cur .= '/';
		$path_cur .= $arr[$i];
		$path_cur = rtrim($path_cur, "\r\n");

		$dir_id = zdbq_single("SELECT id FROM {dirs} WHERE parent_id = %d AND path = '%s'", array($parent_id, $path_cur));

		if ($dir_id === false) return false;

		$dir_path = $zc['mp3_dir'].'/'.$path_cur;

		if (empty($dir_id) || ($regen && !in_array($dir_id, $dir_ids))) {
			$update = $info = array();
			$dir_update = ($regen && !empty($dir_id));
			$values = array($parent_id, $path_cur, $cur_level);
			$cms_id = null;

			$info['mtime'] = '%d';
			$values[] = $mtime = filemtime($dir_path);
			$update['mtime'] = array('%d' => $mtime);

			$subdir_file = zina_get_dir_item($dir_path,'/\.('.$zc['ext_mus'].')$/i');
			if (empty($subdir_file) && isset($arr[$i+1])) {
				$subdir_file = zina_get_dir_item($dir_path.'/'.$arr[$i+1],'/\.('.$zc['ext_mus'].')$/i');
				if (!empty($subdir_file)) $subdir_file = $arr[$i+1].'/'.$subdir_file;
			}

			if (!empty($subdir_file)) {
				$mp3 = zina_get_file_info($dir_path.'/'.$subdir_file, false, true, true);
				if (!isset($mp3->genre)) $mp3->genre = 'Unknown';
				$info['genre'] = "'%s'";
				$values[] = $genre = $mp3->genre;
				$update['genre'] = array("'%s'" => $mp3->genre);

				if ($mp3->tag) {
					if ($i > 0 && isset($mp3->album)) {
					  	$title =	trim($mp3->album);
						if (!empty($title)) {
							$info['title'] = "'%s'";
							$values[] = $title;
							$update['title'] = array("'%s'" => $title);
						}
					}
					if (isset($mp3->year) && !empty($mp3->year)) {
						$info['year'] = '%d';
						$values[] = $year = $mp3->year;
						$update['year'] = array('%d' => $mp3->year);
					}
				}
			}

			if (!isset($info['title'])) {
				$info['title'] = "'%s'";
				$values[] = $title = ztheme('title',$arr[$i]);
				$update['title'] = array("'%s'" => $title);
			}

			$other = array();
			$other['various'] = zina_is_various($dir_path, $path_cur);
			$other['category'] = zina_is_category($dir_path);
			$other['image'] = zina_get_dir_item($dir_path,'/\.('.$zc['ext_graphic'].')$/i');
			#TODO: what about rss?

			if ($dir_update) {
				if ($cms_insert) {
					# check for deleted cms page / update older versions
					$cms_id = zdbq_single("SELECT cms_id FROM {dirs} WHERE id = %d", $dir_id);
					if (!empty($cms_id) && !zina_cms_select($cms_id)) $cms_id = null;
				}

				$result = zdbq_single("SELECT other FROM {dirs} WHERE id = %d", $dir_id);
				if (!empty($result)) {
					$results = unserialize_utf8($result);
					$other = array_merge($other, $results);
				}
				$update['other'] = array("'%s'" => serialize($other));

				$update_values = array();
				$fields = '';
				foreach($update as $field => $item) {
					foreach($item as $placeholder=>$value) {
						$fields .= $field .'='.$placeholder.',';
						$update_values[] = $value;
					}
				}
				$update_values[] = $dir_id;

				if (!zdbq('UPDATE {dirs} SET '.substr($fields,0,-1).' WHERE id = %d', $update_values)) {
					zina_debug(zt('dbViewLog: Could not update @dir',array('@dir'=>$path_cur)));
					return false;
				}
				$dir_ids[] = $dir_id;
			} else { # INSERT
				$desc_file = $dir_path.'/'.$zc['dir_file'];
				if (file_exists($desc_file)) {
					$info['description'] = "'%s'";
					$values[] = file_get_contents($desc_file);
				}

				$alt_file = $dir_path.'/'.$zc['alt_file'];
				if (file_exists($alt_file)) {
					$other['alt_items'] = zunserialize_alt(file_get_contents($alt_file));
				}

				$info['other'] = "'%s'";
				$values[] = serialize($other);

				$cols = implode(',', array_keys($info));
				$vals = implode(',', $info);
				if (!empty($cols)) {
					$cols = ", $cols";
					$vals = ", $vals";
				}

				if (!zdbq("INSERT {dirs} (parent_id, path, level $cols) VALUES (%d, '%s', %d $vals)", $values)) {
					zina_debug(zt('dbViewLog: Could not insert @dir into database',array('@dir'=>$path_cur)));
					return false;
				}

				$dir_id = zdbq_single("SELECT id FROM {dirs} WHERE parent_id = %d AND path = '%s'", array($parent_id, $path_cur));
			}

			if ($cms_insert) {
				# do this stuff after in case error in Zina, then no stray entries in cms
				if (empty($cms_id)) {
					$cms_id = zina_cms_insert($path_cur);
					if (!empty($cms_id)) {
						if (!zdbq("UPDATE {dirs} SET cms_id = %d WHERE id = %d", array($cms_id, $dir_id))) {
							zina_debug(zt('dbViewLog: Could not update cms_id: @dir (@cmsid)',array('@dir'=>$path_cur,'@cmsid'=>$cms_id)));
						}
					} else {
						zina_debug(zt('dbViewLog: Could not get cms_id: @dir',array('@dir'=>$path_cur)));
					}
				}

				if (!empty($cms_id)) {
					if ($cms_tags && !empty($genre)) zina_cms_tags($cms_id, $genre);
					if ($cms_alias) zina_cms_alias($cms_id, $path_cur);
				}
			}

			$search_type = 'directory';
			$context = null;
			if ($zc['search_structure']) {
				if ($i == 0) {
					$search_type = 'artist';
					$year = null;
				} elseif ($i == 1) {
					$search_type = 'album';
					$context = ztheme('title',$arr[0]);
				}
			}

			if (!zdbq("INSERT {search_index} (title, type, type_id, path, context, genre, year, file_mtime, mtime) ".
					"VALUES ('%s', '%s', %d, '%s', '%s', '%s', %d, %d, %d) ".
					"ON DUPLICATE KEY UPDATE title = '%s', context = '%s', type_id = %d, genre= '%s', year = %d, file_mtime = %d, mtime = %d",
					array($title, $search_type, $dir_id, $path_cur, $context, $genre, $year, $mtime, $runtime,
					$title, $context, $dir_id, $genre, $year, $mtime, $runtime))) {
				zina_debug(zt('dbViewLog: Could not insert @dir into {search_index}',array('@dir'=>$path_cur)));
			}
		} # end directory new & regen
	} # end directory

	unset($mp3);
	$genre = $year = null;
	$test = false;
	$ip = $_SERVER['REMOTE_ADDR'];
	$timestamp = time();

	if (!empty($generate)) {
		$test = true;
		$user_id = $generate['user_id'];
		$timestamp = $generate['timestamp'];
		if (isset($generate['ip'])) $ip = $generate['ip'];
	}

	if ($type == 'view') {
		#todo: you have this stat_to here but not below???
		if ($test || (!isset($_SESSION['z_stats']['views'][$dir_id]) || time() - $_SESSION['z_stats']['views'][$dir_id] > $zc['stats_to'])) {
			zdbq("INSERT {dir_views} (dir_id, user_id, ip, mtime) VALUES (%d, %d, '%s', $timestamp)", array($dir_id, $user_id, $ip));
			zdbq("UPDATE {dirs} SET sum_views = sum_views + 1 WHERE id = %d", $dir_id);
			$_SESSION['z_stats']['views'][$dir_id] = time();
		}
	} else {
		$to = " AND $timestamp - mtime < %d ";
		if (!empty($file)) {
			$full_path = $zc['mp3_dir'].'/'.$path.'/'.$file;
			if (!file_exists($full_path)) return false;
			$file = rtrim($file,"\r\n");

			$file_id = zdbq_single("SELECT id FROM {files} WHERE dir_id = %d AND file = '%s'", array($dir_id, $file));
			if (empty($file_id) || $regen) {
				$values = array($dir_id, $path, $file);
				$info = $update = array();
				$update['file'] = array("'%s'" => $file);

				$mp3 = zina_get_file_info($full_path, true, true, true, true);
				if ($mp3->tag || $mp3->info)  {
					if (isset($mp3->image)) $mp3->image = true;
					$mp3_ser = serialize($mp3);

					$info['id3_info'] = "'%s'";
					$values[] = $mp3_ser;
					$update['id3_info'] = array("'%s'" => $mp3_ser);

					if (!isset($mp3->genre)) $mp3->genre = 'Unknown';
					$info['genre'] = "'%s'";
					$values[] = $genre = $mp3->genre;
					$update['genre'] = array("'%s'" => $mp3->genre);

					if (isset($mp3->year) && !empty($mp3->year)) {
						$year = $mp3->year;
						$info['year'] = '%d';
						$values[] = $year = $mp3->year;
						$update['year'] = array('%d' => $mp3->year);
					}
				}

				$mtime = filemtime($full_path);
				$info['mtime'] = "%d";
				$values[] = $mtime;
				$update['mtime'] = array("%d" => $mtime);

				if ($regen && !empty($file_id)) {
					if (!empty($update)) {
						$update_values = array();
						$fields = '';
						foreach($update as $field => $item) {
							foreach($item as $placeholder=>$value) {
								$fields .= $field .'='.$placeholder.',';
								$update_values[] = $value;
							}
						}
						$update_values[] = $file_id;

						if (!zdbq('UPDATE {files} SET '.substr($fields,0,-1).' WHERE id = %d', $update_values)) {
							zina_debug(zt('dbViewLog: Could not update @file',array('@file'=>$path_cur)));
							return false;
						}
					}
				} else {
					if (preg_match('/\.('.$zc['ext_mus'].')$/i', $full_path, $matches) || ($zc['remote'] && preg_match('/\.('.$zc['remote_ext'].')$/i', $full_path, $matches))) {
						$file_ext = $matches[1];
						$info['description'] = "'%s'";
						$values[] = zina_get_file_desc($zc['song_blurbs'],  substr($full_path, 0, -strlen($file_ext)).'txt');

						if ($zc['song_extras']) {
							$extras = array();
							foreach ($zc['song_es_exts'] as $ext) {
								$extra_file = substr($full_path, 0, -strlen($file_ext)).$ext;
								if (file_exists($extra_file)) {
									$extras[$ext] = file_get_contents($extra_file);
								}
							}
							if (!empty($extras)) {
								$info['other'] = "'%s'";
								$values[] = serialize($extras);
							}
						}
					}

					$cols = implode(',', array_keys($info));
					$vals = implode(',', $info);
					if (!empty($cols)) {
						$cols = ", $cols";
						$vals = ", $vals";
					}

					zdbq("INSERT {files} (dir_id, path, file $cols) VALUES(%d, '%s', '%s' $vals)", $values);
				}

				# search index
				$context = null;
				$search_type = 'song';
				$search_path = (!empty($path)) ? $path.'/'.$file : $file;

				if (isset($mp3->title) && !empty($mp3->title)) {
					$various = zina_is_various($zc['mp3_dir'].((empty($path)) ? '' : '/'.$path), $path);
					if ($various && isset($mp3->artist)) {
						$search_title = $mp3->artist .' - '.$mp3->title;
					} else {
						$search_title = $mp3->title;
					}
				} else {
					$search_title = ztheme('song_title', preg_replace('/\.('.$zc['ext_mus'].')$/i', '', $file));
				}

				$context = strip_tags(zina_content_pathlinks($path, 't', false));

				if (empty($file_id))
					$file_id = zdbq_single("SELECT id FROM {files} WHERE dir_id = %d AND file = '%s'", array($dir_id, $file));

				if (!zdbq("INSERT {search_index} (title, type, type_id, path, context, genre, year, file_mtime, mtime) ".
						"VALUES ('%s', '%s', %d, '%s', '%s', '%s', %d, %d, %d) ".
						"ON DUPLICATE KEY UPDATE title = '%s', context = '%s', type_id = %d, genre = '%s', year = %d, file_mtime = %d, mtime = %d",
						array($search_title, $search_type, $file_id, $search_path, $context, $genre, $year, $mtime, $runtime,
						$search_title, $context, $file_id, $genre, $year, $mtime, $runtime))) {
					zina_debug(zt('dbViewLog: Could not insert @dir into {search_index}',array('@dir'=>$search_path)));
				}
			} # end new & regen

			if ($type != 'insertonly') {
				$file_id = zdbq_single("SELECT id FROM {files} WHERE dir_id = %d AND file = '%s'", array($dir_id, $file));
				if ($type == 'play') { #NOTE: no session for media players
					#todo: if we use last.fm stylee, we can skip this crap?
					$doh = ($test) ? 0 : zdbq_single("SELECT 1 FROM {file_plays} WHERE file_id = %d AND ip = '%s' $to", array($file_id, $ip, $zc['stats_to']));
					if ($doh != 1) {
						zdbq("INSERT {file_plays} (file_id, user_id, ip, mtime) VALUES(%d, %d, '%s', $timestamp)", array($file_id, $user_id, $ip));
						zdbq("UPDATE {files} SET sum_plays = sum_plays + 1 WHERE id = %d", $file_id);
					}
				} elseif ($type == 'down' && ($test || !isset($_SESSION['z_stats']['downloads'][$file_id]))) {
					#todo: what about zip downloads of whole dirs??? -> {dirs}.sum_downloads
					$doh = ($test) ? 0 : zdbq_single("SELECT 1 FROM {file_downloads} WHERE file_id = %d AND ip = '%s' AND user_id = 0 $to", array($file_id, $ip, $zc['stats_to']));
					if ($doh != 1) {
						zdbq("INSERT {file_downloads} (file_id, user_id, ip, mtime) VALUES(%d, %d, '%s', $timestamp)", array($file_id, $user_id, $ip));
						zdbq("UPDATE {files} SET sum_downloads = sum_downloads + 1 WHERE id = %d", $file_id);
						$_SESSION['z_stats']['downloads'][$file_id] = true;
					}
				} elseif ($type == 'vote') {
					if ($user_id > 0) { #admin or registered user
						if ($rating == 0) {
							zdbq("DELETE FROM {file_ratings} WHERE user_id = %d AND file_id = %d", array($user_id, $file_id));
							zdbq("UPDATE {files} AS f SET sum_rating = (SELECT AVG(rating) FROM {file_ratings} as r WHERE r.file_id = %d), ".
								"sum_votes = sum_votes - 1 WHERE id = %d", array($file_id, $file_id));
						} else {
							$doh = zdbq_single("SELECT 1 FROM {file_ratings} WHERE file_id = %d AND user_id = %d", array($file_id, $user_id));
							if ($doh != 1) {
								zdbq("INSERT {file_ratings} (file_id, user_id, ip, mtime, rating) ".
									"VALUES(%d, %d, '%s', $timestamp, %d)", array($file_id, $user_id, $ip, $rating));
								zdbq("UPDATE {files} AS f SET sum_rating = (SELECT AVG(rating) FROM {file_ratings} as r WHERE r.file_id = %d), ".
									"sum_votes = sum_votes + 1 WHERE id = %d", array($file_id, $file_id));
							} else {
								zdbq("UPDATE {file_ratings} SET ip = '%s', mtime = $timestamp, rating = %d ".
									"WHERE user_id = %d AND file_id = %d", array($ip, $rating, $user_id, $file_id));
								zdbq("UPDATE {files} AS f SET sum_rating = (SELECT AVG(rating) FROM {file_ratings} as r WHERE r.file_id = %d) ".
									"WHERE id = %d", array($file_id, $file_id));
							}
						}
					} elseif ($test || !isset($_SESSION['z_stats']['votes'][$file_id])) {
						$doh = ($test) ? 0 : zdbq_single("SELECT 1 FROM {file_ratings} WHERE file_id = %d AND ip = '%s' AND user_id = 0 $to", array($file_id, $ip, $zc['stats_to']));
						if ($doh != 1) {
							zdbq("INSERT {file_ratings} (file_id, ip, mtime, rating) VALUES(%d, '%s', $timestamp, %d)", array($file_id, $ip, $rating));
							zdbq("UPDATE {files} AS f SET sum_rating = (SELECT AVG(rating) FROM {file_ratings} as r WHERE r.file_id = %d), ".
								"sum_votes = sum_votes + 1 WHERE id = %d", array($file_id, $file_id));
							$_SESSION['z_stats']['votes'][$file_id] = true;
						}
					}
				}
			}
		} elseif ($type == 'vote') {
			if ($user_id > 0) { #admin or registered user
				if ($rating == 0) {
					zdbq("DELETE FROM {dir_ratings} WHERE user_id = %d AND dir_id = %d", array($user_id, $dir_id));
					zdbq("UPDATE {dirs} SET sum_rating = (SELECT AVG(rating) FROM {dir_ratings} as r WHERE r.dir_id = %d), ".
						"sum_votes = sum_votes - 1 WHERE id = %d", array($dir_id, $dir_id));
				} else {
					$doh = zdbq_single("SELECT 1 FROM {dir_ratings} WHERE dir_id = %d AND user_id = %d", array($dir_id, $user_id));
					if ($doh != 1) {
						zdbq("INSERT {dir_ratings} (dir_id, user_id, ip, mtime, rating) ".
							"VALUES(%d, %d, '%s', $timestamp, %d)", array($dir_id, $user_id, $ip, $rating));
						zdbq("UPDATE {dirs} SET sum_rating = (SELECT AVG(rating) FROM {dir_ratings} as r WHERE r.dir_id = %d), ".
							"sum_votes = sum_votes + 1 WHERE id = %d",array($dir_id, $dir_id));
					} else {
						zdbq("UPDATE {dir_ratings} SET ip = '%s', mtime = $timestamp, rating = %d ".
							"WHERE user_id = %d AND dir_id = %d", array($ip, $rating, $user_id, $dir_id));
						zdbq("UPDATE {dirs} SET sum_rating = (SELECT AVG(rating) FROM {dir_ratings} as r WHERE r.dir_id = %d) ".
							"WHERE id = %d", array($dir_id, $dir_id));
					}
				}
			} elseif ($test || !isset($_SESSION['z_stats']['votes'][$dir_id])) { # anonymous
				$doh = ($test) ? 0 : zdbq_single("SELECT 1 FROM {dir_ratings} WHERE dir_id = %d AND ip = '%s' AND user_id = 0 $to", array($dir_id, $ip, $zc['stats_to']));
				if ($doh != 1) {
					zdbq("INSERT {dir_ratings} (dir_id, ip, mtime, rating) VALUES(%d, '%s', $timestamp, %d)", array($dir_id, $ip, $rating));
					zdbq("UPDATE {dirs} SET sum_rating = (SELECT AVG(rating) FROM {dir_ratings} as r WHERE r.dir_id = %d), ".
						"sum_votes = sum_votes + 1 WHERE id = %d", array($dir_id, $dir_id));
					$_SESSION['z_stats']['votes'][$dir_id] = true;
				}
			}
		}
	}
}

#$type  'views', 'plays', 'votes', 'downloads'
function zdb_log_stat_playlist($pls_id, $type, $stat = 0) {
	global $zc;
	$now = time();
	$ip = $_SERVER['REMOTE_ADDR'];
	$user_id = $zc['user_id'];
	$types = array(
		'views' => 1,
		'plays' => 2,
		'votes' => 3,
		'downloads' => 4,
	);
	$type_id = $types[$type];

	$exists = zdbq_single("SELECT 1 FROM {playlists} WHERE id = %d", array($pls_id));
	if (!$exists) {
		zina_set_message(zt('Playlist does not exist'),'error');
		return false;
	}

	$rating = false;

	if ($type == 'votes' && $user_id > 0) {
		$rating = true;
		$where = "playlist_id = %d AND user_id = %d AND stat_type = %d";
		$values = array($pls_id, $user_id, $type_id);

		if ($stat == 0) {
			zdbq("DELETE FROM {playlists_stats} WHERE $where", $values);
			zdbq("UPDATE {playlists} SET sum_$type = sum_$type - 1 WHERE id = %d", $pls_id);
		} else {
			$exists = zdbq_single("SELECT 1 FROM {playlists_stats} WHERE $where", $values);
			if ($exists) {
				array_unshift($values, $stat);
				zdbq("UPDATE {playlists_stats} SET stat = %d WHERE $where", $values);
			} else {
				zdbq("INSERT {playlists_stats} (stat_type, stat, playlist_id, user_id, ip, mtime) VALUES (%d, %d, %d, %d, '%s', %d)",
					array($type_id, $stat, $pls_id, $user_id, $ip, $now));
				zdbq("UPDATE {playlists} SET sum_$type = sum_$type + 1 WHERE id = %d", $pls_id);
			}
		}
	} else { # all other stats
		$to = " AND $now - mtime < ".$zc['stats_to'];

		if (!isset($_SESSION['z_stats_pls'][$type][$pls_id]) || $now - $_SESSION['z_stats_pls'][$type][$pls_id] > $zc['stats_to']) {
			$exists = zdbq_single("SELECT 1 FROM {playlists_stats} WHERE ip = '%s' AND playlist_id = %d AND stat_type = %d AND user_id = %d $to",
				array($ip, $pls_id, $type_id, $user_id));

			if (!$exists) {
				zdbq("INSERT {playlists_stats} (stat_type, stat, playlist_id, user_id, ip, mtime) VALUES (%d, %d, %d, %d, '%s', %d)",
					array($type_id, $stat, $pls_id, $user_id, $ip, $now));
				zdbq("UPDATE {playlists} SET sum_$type = sum_$type + 1 WHERE id = %d", $pls_id);
				$_SESSION['z_stats_pls'][$type][$pls_id] = $now;
				$rating = true;
			}
		}
	}

	if ($rating) {
		zdbq("UPDATE {playlists} AS p SET sum_rating = (SELECT AVG(stat) FROM {playlists_stats} AS s ".
			"WHERE s.playlist_id = %d AND s.stat_type = 3) ".
			"WHERE p.id = %d",
			array($pls_id, $pls_id));
	}
}

function zvar_get($name, $default = false) {
	$value = zdbq_single("SELECT value FROM {variable} WHERE name = '%s'", $name);
	return (empty($value)) ? $default : unserialize($value);
}

function zvar_set($name, $value) {
	$serialized_value = serialize($value);
	return zdbq("INSERT {variable} (name, value) VALUES ('%s', '%s')".
		" ON DUPLICATE KEY UPDATE value = '%s'", $name, $serialized_value, $serialized_value);
}

function zdbq_callback($match, $init = FALSE) {
  static $args = NULL;
  if ($init) {
    $args = $match;
    return;
  }

  switch ($match[1]) {
    case '%d': // We must use type casting to int to convert FALSE/NULL/(TRUE?)
      return (int) array_shift($args); // We don't need db_escape_string as numbers are db-safe
    case '%s':
		 global $zc;
		 if ($zc['charset'] == 'utf-8') {
			 $arg = array_shift($args);
			 if (!zvalidate_utf8($arg)) { $arg = utf8_encode($arg); }
      	return zdb_escape_string($arg);
		 } else {
      	return zdb_escape_string(array_shift($args));
		 }
      #return zdb_escape_string(array_shift($args));
    case '%%':
      return '%';
    case '%f':
      return (float) array_shift($args);
    case '%b': // binary data
      return array_shift($args);
  }
}

function zdbq($query) {
	global $z_dbc, $zc;
	$args = func_get_args();
	array_shift($args);
	$query = strtr($query, array('{' => $zc['db_pre'], '}' => ''));
	if (isset($args[0]) and is_array($args[0])) { // 'All arguments in one array' syntax
		$args = $args[0];
	}

	zdbq_callback($args, TRUE);
	$query = preg_replace_callback('/(%d|%s|%%|%f|%b)/', 'zdbq_callback', $query);
	$result = zdb_query($query, $z_dbc);
	if (!zdb_errno($z_dbc, $result)) {
		return $result;
	} else {
		if (strlen($query) > 1024) {
			$query = substr($query,0,512).' **SNIP** '.substr($query,-512);
		}
		zina_debug(zdb_error()."\n".$query, 'error');
		return false;
	}
}

function zdbq_single($sql) {
	$args = func_get_args();
	array_shift($args);
	if (isset($args[0]) and is_array($args[0])) $args = $args[0];

	if ($result = zdbq($sql, $args)) {
		return (zdb_num_rows($result) > 0) ? zdb_result($result,0,0) : null;
	}
	return false;
}

function zdbq_array($sql) {
	$args = func_get_args();
	array_shift($args);
	if (isset($args[0]) and is_array($args[0])) $args = $args[0];

	if ($result = zdbq($sql, $args)) {
		$array = array();
		while($row = zdb_fetch_array($result)) $array[] = $row;
		return $array;
	}
	return false;
}

function zdbq_array_single($sql) {
	$args = func_get_args();
	array_shift($args);
	if (isset($args[0]) and is_array($args[0])) $args = $args[0];

	if ($result = zdbq($sql, $args)) {
		return (zdb_num_rows($result) > 0) ?  zdb_fetch_array($result) : array();
	}
	return false;
}

function zdbq_assoc($key, $sql) {
	$args = func_get_args();
	array_shift($args);
	array_shift($args);
	if (isset($args[0]) and is_array($args[0])) $args = $args[0];

	if ($result = zdbq($sql, $args)) {
		$array = array();
		while($row = zdb_fetch_array($result)) $array[$row[$key]] = $row;
		return $array;
	}
 
	return false;
}

function zdbq_assoc_list($sql) {
	$args = func_get_args();
	array_shift($args);
	if (isset($args[0]) and is_array($args[0])) $args = $args[0];

	if ($result = zdbq($sql, $args)) {
		$array = array();
		while($row = zdb_fetch_array_num($result)) $array[$row[0]] = $row[1];
		return $array;
	}
	return false;
}

function zdbq_array_list($sql) {
	$args = func_get_args();
	array_shift($args);
	if (isset($args[0]) and is_array($args[0])) $args = $args[0];

	if ($result = zdbq($sql, $args)) {
		$array = array();
		while($row = zdb_fetch_array_num($result)) $array[] = $row[0];
		return $array;
	}
	return false;
}

function zdb_create_table($table) {
	$sql = 'CREATE TABLE {'.$table['name'].'} (';

	$fields = zdb_schema($table['name'], true);

	foreach($fields as $field) $sql.= "${field['dc']} ${field['dt']},";

	$sql = substr($sql, 0, -1);
	if (isset($table['index'])) {
		foreach($table['index'] as $index) { $sql .= ", $index"; }
	}
	$sql .= ') ENGINE=InnoDB';
	$sql .= "/*!40100 DEFAULT CHARACTER SET UTF8 */";
	if (!zdbq($sql)) {
		zina_set_message(zt('Cannot create {@name} table.',array('@name'=>$table['name'])), 'error');
		return false;
	}
	return true;
}

/*
 * Order is important because of foreign keys
 */
function zdb_uninstall_database() {
	$tables = zdb_schema('tables');
	$order = array(14,13,12,11,10,0,3,4,5,6,7,9,8,2,1);
	foreach($order as $i) {
		if (isset($tables[$i])) zdbq("DROP TABLE {".$tables[$i]['name']."}");
	}
}
?>
