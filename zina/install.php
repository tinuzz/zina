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

function zina_install_database($force = false) {
	global $zc;
	if (!isset($zc['charset'])) $zc['charset'] = 'utf-8';

	if ($zc['database']) {
		if (!zdbq("SELECT 1 FROM {dirs} LIMIT 0")) {
			$tables = zdb_schema("tables");

			foreach ($tables as $table) {
				$name = $table['name'];
				$create = true;
				#todo: if debug on, this displays error message
				if (@zdbq('SELECT 1 FROM {'.$name.'} LIMIT 0')) {
					zina_set_message(zt('Table {@name} seems to already have been installed.', 
						array('@name'=>$name)), 'warn');
					$create = false;
					if ($force && zdbq('DROP TABLE {'.$name.'}')) {
						$create = true;
					} else {
						zina_set_message(zt('Cannot drop {@name} table.',array('@name'=>$name)), 'error');
					}
				} 
				if ($create) zdb_create_table($table);
			} # end tables

			if (zdbq("INSERT {dirs} (parent_id, path, level, title) VALUES (0, '.', 0, '%s')", zt('Artists'))) {
				zvar_set('version', ZINA_VERSION);
				return true;
			} else {
				zina_set_message(zt('Could not insert default value.'), 'error');
			}
		} else {
			zina_set_message(zt('Zina seems to already have been installed.'), 'warn');
		}
	} else {
		zina_set_message(zt('Cannot connect to the database'), 'error');
	}
	return false;
}
?>
