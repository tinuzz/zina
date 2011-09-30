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
define('ZINA_VERSION', '2.0b22');

#TODO:
# - INSTRUCTIONS for cron for caches

#todo
# - uninstall? -> at least delete cache files created by webuser!
# - check settings descriptions...
# - normalize function names
# - organize files
# - comment functions?
# - look and remove unused language crap?

#TODO-EXTRA:
# - if clean urls... drop l=8&m=1 for songs???
# - See Also -> external links?
# - multiple mp3 dirs
# - Check output_buffering php.ini setting for video stuff?

#TEST:
# - pos

function zina($conf) {
	global $zc;

	zina_init($conf);

	$path = isset($_GET['p']) ? zrawurldecode($_GET['p']) : null;
	$level = isset($_GET['l']) ? $_GET['l'] : null;
	$m = isset($_GET['m']) ? $_GET['m'] : null;
	$imgsrc = isset($_GET['img']) ? $_GET['img'] : null;
	$playlist = isset($_POST['playlist']) ? $_POST['playlist'] : (isset($_GET['pl']) ? $_GET['pl'] : null);
	$songs = isset($_POST['mp3s']) ? $_POST['mp3s'] : (isset($_GET['mp3s']) ? $_GET['mp3s'] : array());

	$path = preg_replace("|(/){2,}|",'$1',trim($path,'/'));

	$badpath = false;
	if (strstr($path,'..') && !zfile_check_location($zc['mp3_dir'].'/'.$path, $zc['mp3_dir'])) {
		$badpath = true;
	}

	if (!$badpath && strstr($imgsrc,'..')) {
		$badpath = true;
		if ($level == 11) {
			$badpath = (!zfile_check_location($zc['mp3_dir'].'/'.$path, $zc['mp3_dir']));
		} elseif ($level == 7) {
			if (isset($_GET['it'])) {
				if ($_GET['it'] == 'genre') {
				} elseif (in_array($_GET['it'], array('sub','dir','full','search'))) {
					$badpath = (!zfile_check_location($zc['mp3_dir'].'/'.$path, $zc['mp3_dir']));
				}
			}
		}
	}

	if (!$badpath && strstr($playlist,'..')) $badpath = true;

	if (!$badpath && !empty($songs) && is_array($songs)) {
		foreach ($songs as $song) {
			if (strstr($song,'..') && !zfile_check_location($zc['mp3_dir'].'/'.$path, $zc['mp3_dir'])) {
				$badpath = true;
				break;
			}
		}
	}
	if ($badpath) {
		zina_debug(zt('Bad path: @path', array('@path'=>$path)));
		return zina_not_found();
	}

	$zc['cur_dir'] = $zc['mp3_dir']. (!empty($path) ? '/'.$path : '');

	if (!empty($path) && !file_exists($zc['cur_dir'])) {
		$file_not_found = true;
		if (substr($path,-3) == '.lp') {
			$tmp_path = substr($path, 0, strlen($path) -3);
			if (file_exists($zc['mp3_dir'].'/'.$tmp_path)) {
				$file_not_found = false;
				$zc['cur_dir'] = $zc['mp3_dir'].'/'.$tmp_path;
			}
		} elseif ($zc['sitemap'] && $path == $zc['sitemap_file']) {
			$level = 51;
			$path = '';
			$zc['cur_dir'] = $zc['mp3_dir'];
			$file_not_found = false;
		} elseif ($zc['rss'] && basename($path) == $zc['rss_file']) {
			$level = 50;
			$path = substr($path,0,-(strlen($zc['rss_file'])+1));
			$zc['cur_dir'] = $zc['mp3_dir'].'/'.$path;
			if (file_exists($zc['cur_dir'])) $file_not_found = false;
		} elseif ($zc['stats_rss'] && $zc['database'] && basename($path) == 'stats.xml') {
			zina_stats_feed($path);
		} elseif ($zc['playlists'] && $zc['database'] && basename($path) == 'pls.xml') {
			$pls_id = dirname($path);
			if (zina_validate('int',$pls_id)) {
				zina_playlist_feed($pls_id);
			}
		} elseif ($level == 46 && basename($path) == 'zina_id3_zina.jpg') {
			$file_not_found = false;
			$tmp_path = dirname($path);
			$tmp_path = $zc['mp3_dir'].(!empty($tmp_path) ? '/'.$tmp_path : '');
			if (file_exists($tmp_path) && is_dir($tmp_path)) $file_not_found = false;
		}

		if ($file_not_found) {
			$tmp_path = utf8_decode($path);
			$tmp_cur_dir = $zc['mp3_dir']. (!empty($tmp_path) ? '/'.$tmp_path : '');
			if (file_exists($tmp_cur_dir)) {
				$path = $tmp_path;
				$zc['cur_dir'] = $tmp_cur_dir;
			} else {
				if (substr($path,-11) != 'favicon.ico') zina_debug(zt('Path does not exist: @path', array('@path'=>$path)));
				return zina_not_found();
			}
		}
	}

	#todo: validate?
	if ($zc['settings_override']) {
		$override_file = $zc['cur_dir'].'/'.$zc['settings_override_file'];
		if (file_exists($override_file)) {
			$override = false;
		  	if (($dir_xml = file_get_contents($override_file)) !== false) {
				$dir_settings = xml_to_array($dir_xml, 'settings');
				if (!empty($dir_settings[0])) {
					foreach($dir_settings[0] as $key => $val) {
						if (isset($zc[$key])) $zc[$key] = $val;
					}
					$override = true;
				}
			}
			if (!$override) {
				zina_set_message(zt('Cannot read override file.'), 'error');
				return zina_access_denied();
			}
		}
	}

	/*
	 * MAIN
	 *
	 * Determines what zina does.
	 */
	if (in_array($level, array(18,19,20,21,26,27,28,30,31,32,33,34,35,42,45,47,48,49,52,58,59,60,61,62,63,64,67,71,72,73,75,76,78))) {
		# ADMIN FUNCTIONS

		if (!$zc['is_admin']) return zina_access_denied();
		#todo: needed?
		#header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
		#header('Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT');
		#header('Cache-Control: no-cache, must-revalidate, max-age=0');
		#header('Pragma: no-cache');

		switch($level) {

			Case 18 : # INSTALL OR UPDATE DB
				if ($zc['database'] && ($m == 'update' || $m == 'install')) {
					$file = $zc['zina_dir_abs'].'/'.$m.'.php';
					if ($zc['database'] && file_exists($file)) {
						require_once($file);
						if ($m == 'install') {
							$result = zina_install_database();
						} else {
							$result = zina_updates_execute();
						}
						if ($result) {
							if ($m == 'install') zvar_set('version', ZINA_VERSION);
							zina_set_message(zt('Database @m succeeded!',array('@m'=>$m)));
						} else {
							zina_set_message(zt('Database @m failed!', array('@m'=>$m)),'error');
						}
					} else {
						zina_set_message(zt('Cannot @m the database...either no database connection or the file does not exist.',array('@m'=>$m)), 'error');
					}
					zina_goto('', 'l=20');
				}
				return zina_not_found();
				break;

			Case 19 : # try to manually submit last.fm queue
				if ($zc['lastfm']) {
					require_once($zc['zina_dir_abs'].'/extras/scrobbler.class.php');
					@set_time_limit($zc['timeout']);

					$scrobbler = new scrobbler($zc['lastfm_username'],$zc['lastfm_password']);
					$scrobbler->handshake_socket_timeout = 2;
					$scrobbler->submit_socket_timeout = 30;
					$scrobbler->queued_tracks = zina_set_scrobbler_queue();
					if ($scrobbler->submit_tracks()) {
						zina_set_message(zt('Queued Last.fm tracks submitted successfully.'));
						zina_set_scrobbler_queue(array(), true);
					} else {
						zina_set_message(zt('Queued Last.fm tracks failed:').$scrobbler->error_msg,'warn');
					}
					zina_goto('', 'l=20');
				}
				break;

			Case 20 : # CFG
				return zina_page_main($path, 'config');
				break;

			Case 21 : # CFG POST
				if (zina_write_settings()) {
					zina_set_message(zt('Settings updated.'));
				} else {
					zina_set_message(zt('Your settings were not saved!'),'error');
				}
				zina_goto('', 'l=20');
				break;

			Case 26 : # regen
				require_once($zc['zina_dir_abs'].'/batch.php');
				@trigger_error('');
				$error = false;
				if ($m == 1) { # dir/files caches
					if ($zc['database']) {
						foreach(array('dirs','files_assoc') as $type) {
							$operations[] = array('zina_core_cache_batch', array($type, '', array('force'=>true)));
						}
						if ($zc['low']) {
							foreach(array('files_assoc') as $type) {
								$operations[] = array('zina_core_cache_batch', array($type, '', array('force'=>true, 'low'=>true)));
							}
						}

						$batch = array(
							'title' => zt('Regenerating directory and file caches.'),
							'finished_message' => zt('Caches generated successfully.'),
							'operations' => $operations,
    					);
						zbatch_set($batch);
						zbatch_process();
					} else {
						foreach(array('dirs','files_assoc') as $type) zina_core_cache($type, '', array('force'=>true));

						if ($zc['low']) {
							foreach(array('files_assoc') as $type) zina_core_cache($type, '', array('force'=>true,'low'=>true));
						}

						$message = zt('Cache generated successfully.');
					}
				} elseif ($m == 2) { # genre cache
					#todo: use db or cache but not both?  for genres?  for dirs/files?
					if ($zc['database']) {
						$operations = array();

						$operations[] = array('zina_core_cache_batch', array('genre', '', array('force'=>true)));
						$operations[] = array('zdb_genre_populate', array(time()));

						$batch = array(
							'title' => zt('Regenerating genre caches.'),
							'finished_message' => zt('Genre cache generated successfully.'),
							'operations' => $operations,
    					);
						zbatch_set($batch);
						zbatch_process();

					} else {
						$message = zt('Genre cache generated successfully.');
						zina_core_cache('genre', '', array('force'=>true));
					}
				} elseif ($m == 3 || $m == 4) { # populate missing
					#TODO: combine 3 & 4... combine 1,3&4?
					if ($zc['database']) {
						$runtime = time();
						$operations = array();

						foreach(array('dirs','files_assoc') as $type) {
							$operations[] = array('zina_core_cache_batch', array($type, '', array('force'=>true)));
						}

						if ($m == 3) {
							$regen = false;
							$title = zt('Populating database with missing entries.');
							$finished = zt('Database populated.');
						} else { # 4
							$regen = true;
							$title = zt('Synchronising database');
							$finished = zt('Synchronized database.');
						}

						$operations[] = array('zdb_populate_batch', array($regen));
						$operations[] = array('zdb_search_playlist_populate', array($runtime));
						$operations[] = array('zdb_genre_populate', array($runtime));

						$batch = array(
							'title' => $title,
							'finished_message' => $finished,
							'finished' => 'zdb_populate_finished',
							'operations' => $operations,
    					);
						zbatch_set($batch);
						zbatch_process();
					}
				} elseif ($m == 5) { # image from id3 tags
					$operations[] = array('zbatch_extract_images', array());

					$batch = array(
						'title' => zt('Extracting images from id3 tags.'),
						'finished_message' => zt('Images extracted successfully.'),
						'operations' => $operations,
						'finished' => 'zbatch_extract_images_finished',
  					);
					zbatch_set($batch);
					zbatch_process();
				} else {
					$error = true;
					zina_set_message(zt('Invalid option'), 'error');
				}
				$e	=	error_get_last();

				if ($error || ($e['type'] < 2048 && !empty($e['message']))) {
					#todo: ? zunserialize throws error on older custom_files...
					if (!empty($e['message'])) zina_set_message(zt('PHP returned an error[@type]: @message', array('@type'=>$e['type'], '@message'=>$e['message'])), 'error');
				} else {
					zina_set_message($message);
				}
				zina_goto('','l=20');
				break;

			Case 27 : # Find and Clean
				if ($zc['database']) {
					if ($_POST && !zina_token_sess_check()) return zina_page_main($path);

					if (!empty($_POST['zfileids'])) zdb_clean('file',$_POST['zfileids']);
					if (!empty($_POST['zdirids'])) zdb_clean('dir',$_POST['zdirids']);
					if ($_POST) {
						zina_set_message(zt('Database cleaned.'));
						zina_goto('', 'l=20');
					} else {
						return zina_page_main($path, 'clean');
					}
				}
				break;

			Case 33 : # DELETE SITEMAP
				$file = $zc['cache_dir_public_abs'].'/'.$zc['sitemap_file'];
				if (file_exists($file) && @unlink($file)) {
					zina_set_message(zt('Sitemap cached file deleted successfully'));
				} else {
					zina_set_message(zt('Sitemap cached file could not be deleted'),'warn');
				}
				zina_goto('', 'l=20');
				break;

			Case 30 :# DELETE TMPL CACHE
			Case 31 :# DELETE IMGS CACHE
			Case 34 :# DELETE ZIP CACHE
				$func = array(
					'30'=> array(
						'dir' => $zc['cache_tmpl_dir'],
						'text' => zt('Template cache files deleted.'),
					),
					'31'=> array(
						'dir' => $zc['cache_imgs_dir'],
						'text' => zt('Images cache files deleted.'),
					),
					'34'=> array(
						'dir' => $zc['cache_zip_dir'],
						'text' => zt('Compressed cache files deleted.'),
					),
				);
				if (zina_delete_files($func[$level]['dir'])) {
					zina_set_message($func[$level]['text']);
				}
				zina_goto('', 'l=20');
				break;

			Case 32 : #get language phrases for translation
				$files = array('index.php', 'theme.php', 'database.php', 'lang-cfg.php');
				$source = '';
				foreach($files as $file) {
					$source .= file_get_contents($zc['zina_dir_abs'].'/'.$file);
				}

				if (preg_match_all("/zt\('(.*?)'(\)|,)/is", $source, $matches)) {
					$reduced = array_unique($matches[1]);
					$instr = array(
						zt('These are most of the translation strings currently in Zina.'),
						zt('Save this file to LANGCODE.php and modify it.'),
						zt('Format: \'english words\' => \'your translation\'')."\n *",
						zt('You do not have to do them all (just delete the lines you do not do).'),
						zt('If you are making a new translation or completing an older one, move the file to the "lang" directory.'),
						zt('If languages.txt file exists in your cache directory, delete it.'),
						zt('Or a copy in your theme folder will override the default language file.'),
						zt('English users can change wording/phrasings this way.')."\n *",
						zt('Test it out.')."\n *",
						zt('If you would like it to be included in Zina, please email it: to: <@email>', array('@email'=>'ryanlath@pacbell.net')),
					);

					$text = '<?php'."\n".
						"/*\n * ".zt('Zina Translation Instructions')."\n *\n";
					foreach($instr as $i) {
						$text .= ' * '.$i."\n";
					}
					$lang = zina_get_languages();
					$language = (isset($lang[$zc['lang']])) ? $lang[$zc['lang']] : zt('Language');
					$text .= " */\n\n".'$language = "'.$language.'";'."\n\n";
					$text .= '$lang[\''.$zc['lang'].'\'] = array('."\n";
					foreach ($reduced as $en) {
						$text .= "\t'".$en."' => '";
						if ($zc['lang'] != 'en') {
							$trans = zt($en);
							if ($trans != $en) {
								$text .= $trans;
							}
						}
						$text .= "',\n";
					}
					$text .= ");\n?>";

					while(@ob_end_clean());
					header('Content-type: text/plain');
					if (!$zc['debug']) header('Content-Disposition: attachment; filename="'.$zc['lang'].'.php"');
					echo $text;
					exit;
				} else {
					zina_set_message(zt('Nothing Found'),'error');
					zina_goto('', 'l=20');
				}
				break;

			Case 42 : # Add Custom Playlist Title
				if (!zina_token_sess_check()) return zina_page_main($path);

				zina_write_playlist($songs, str_replace('/',' - ',$path).'.m3u', 't');
				if ($zc['cache_tmpl']) {
					if (!zina_delete_tmpl_file(zina_get_tmpl_cache_file($path)))
						zina_debug(zt('Could not delete cache file'));
				}
				zina_goto($_SERVER['HTTP_REFERER']);
				break;

			Case 45 : # sync database to mp3 files...
				$files_assoc = zina_core_cache('files_assoc', $path);

				if (isset($files_assoc[$path])) {
					$files = $files_assoc[$path];
					foreach($files as $file) {
						zdb_log_stat('insertonly', $path, $file, null, true, true);
					}
				} else {
					zdb_log_stat('insertonly', $path, null, null, true, true);
				}
				zina_goto($path);
				break;

			Case 47 : # regen statistics
				if ($zc['database']) {
					zdb_stats_generate(time());
					zina_goto('', 'l=20');
				}
				break;

			Case 48 :
				if ($zc['genres'] && $zc['database']) {
					return zina_page_main($path, 'genre_hierarchy');
				}
				break;

			Case 49 :
				if ($zc['genres'] && $zc['database']) {
					if (!zina_token_sess_check()) return zina_page_main($path);
					zdb_genres_save($_POST);
					#todo: check for errors?
					zina_set_message(zt('Genre hierarchy saved.'));
					zina_goto('', 'l=48');
				}
				break;

			Case 52 :
				if ($zc['genres'] && $zc['database']) {
					if (zina_validate('int',$m)) {
						if (zdb_genre_delete($m)) zina_set_message(zt('Genre deleted.'));
					}
					zina_goto('', 'l=48');
				}
				break;

			Case 58 :
				return zina_page_main($path, 'edit_images');
				break;

			Case 59 : # ajax return images
				@session_write_close();
				if (!$zc['debug']) while(@ob_end_clean());
				echo zina_content_3rd_images($path, $m);
				exit;
				break;

			Case 60 : # Delete Image
				$file = $zc['mp3_dir'] .'/'.$path;
				$result = zt('Could not delete file.');
				if (preg_match('/\.('.$zc['ext_graphic'].')$/i', $path) && file_exists($file)) {
					if (@unlink($file)) {
						$result = zt('Deleted: @file', array('@file'=>$file));
						$other = zdb_get_others($path);
						if (isset($other['image']) && $other['image'] == basename($path)) {
							$image = zina_get_dir_item(dirname($path),'/\.('.$zc['ext_graphic'].')$/i');
							zdb_update_others(array('image'=>$image), dirname($path));
						}
					}
				}
				echo $result;
				exit;
				break;

			Case 61 : # Save Image
				$result = zt('Failed');
				if (is_writeable($zc['cur_dir'])) {
					if (($image = file_get_contents($imgsrc)) !== false) {
						$filename = $zc['cur_dir'].'/'.basename($imgsrc);
						$i=1;
						while (file_exists($filename)) {
							$filename = $zc['cur_dir'].'/copy'.$i.'_'.basename($imgsrc);
						}
						if (file_put_contents($filename, $image)) {
							$result = zt('Image Saved: @src -> @dest', array('@src'=> $imgsrc, '@dest' => $filename));
							if (isset($_SESSION['zina_missing'][$path])) unset($_SESSION['zina_missing'][$path]);
						}
					}
				}
				if (!$zc['debug']) while(@ob_end_clean());
				echo $result;
				exit;
				break;

			Case 62 : # Find album art
				$missing = zina_search_dirs_for_missing_images();
				if (!empty($missing)) {
					$_SESSION['zina_missing'] = $missing;
					zina_goto(current($missing),'l=58');
				} else {
					zina_set_message(zt('No missing artist/album artwork.'));
					unset($_SESSION['zina_missing']);
					zina_goto('', 'l=20');
				}
				exit;

				break;

			Case 63 : # Close Album Art
				unset($_SESSION['zina_missing']);
				zina_goto($path);
				break;

			Case 64 :
				return zina_page_main('', 'help');
				break;

			Case 67 : # import textfile playlists into database...
				#remove in 3.0 / make part of upgrade
				if ($zc['database']) {
					$playlists = zina_get_playlists_custom();
					if (empty($playlists)) {
						zina_set_message(zt('No playlists to convert'));
						zina_goto('', 'l=20');
					}
					foreach($playlists as $playlist) {
						$filename = $zc['cache_pls_dir'].'/_zina_'.$playlist.'.m3u';
						if (file_exists($filename)) {
							$pls_id = zdbq_single("SELECT id FROM {playlists} WHERE title = '%s' AND user_id = %d", array($playlist, $zc['user_id']));

							if (!empty($pls_id)) {
								zina_set_message(zt('Playlist already exists: @pls', array('@pls'=>$playlist)));
								continue;
							}
							$genre_id = null;
							$dir_id = zdbq_single("SELECT id FROM {dirs} WHERE path = '%s' AND level = 1", array($playlist));
							if ($dir_id) {
								$image_type = 1;
							} else {
								$dir_id = null;
								$image_type = 0;
							}
							$mtime = filemtime($filename);

							if (zdbq("INSERT {playlists} (title, user_id, dir_id, genre_id, image_type, date_created, mtime) VALUES ('%s', %d, %d, '%s', '%s', %d, %d)",
								array($playlist, $zc['user_id'], $dir_id, $genre_id, $image_type, $mtime, $mtime))) {

								$pls_id = zdbq_single("SELECT id FROM {playlists} WHERE title = '%s' AND user_id = %d", array($playlist, $zc['user_id']));
								if (!empty($pls_id)) {
									$songs = zunserialize_alt(file_get_contents($filename));
									zina_playlist_insert($pls_id, $songs, 1);
								}
							}
						} else {
							zina_set_message(zt('Cannot open playlist: @pls', array('@pls'=>$playlist)));
						}
					}
					zina_goto('', 'l=20');
				}
				break;

			Case 71 : # edit tags
				require_once($zc['zina_dir_abs'].'/extras/tag_editor.php');
				return zina_page_main($path, 'edittags');
				break;

			Case 72 : # tag search
			Case 73 : # tag search
				require_once($zc['zina_dir_abs'].'/extras/tag_editor.php');
				if ($level == 72) {
					if (($result = zina_extras_tags_freedb_matches(rawurldecode($playlist))) !== false) {
						echo $result;
					}
				} else {
					if (($result = zina_extras_tags_freedb_match($_GET['cat'], $_GET['discid'])) !== false) {
						echo $result;
					}
				}

				exit;
				break;

			Case 75 : # delete file
				if (($path = zina_delete_file($zc['cur_dir'])) !== false) {
					zina_set_message(zt('Deleted file: @file', array('@file'=>$zc['cur_dir'])));
				}
				zina_goto($path);

				break;
			Case 76 : # delete dir
				if (!zfile_check_location($zc['cur_dir'], $zc['mp3_dir']) || !is_dir($zc['cur_dir'])) {
					zina_set_message(zt('Directory does not exist: @dir', array('@dir'=>$zc['cur_dir'])));
					zina_goto('');
				}
				$dir = zina_get_directory($path);
				if (!empty($dir['subdirs'])) {
					zina_set_message(zt('Cannot delete directory.  Directory has subdirectories.'));
					zina_goto($path);
				}

				if (!empty($dir['files'])) {
					foreach($dir['files'] as $file => $xxx) {
						if (zina_delete_file($zc['mp3_dir'].'/'.$file)) zina_set_message(zt('Deleted file: @file', array('@file'=>$file)));
					}
				}

				if (zina_delete_directory($zc['cur_dir'])) {
					if ($zc['database']) {
						$id = zdbq_single("SELECT id FROM {dirs} WHERE path = '%s'", array($path));
						if (!empty($id)) zdb_remove('dir', $id);
					}
				} else {
					zina_set_message(zt('Cannot delete directory: @dir', array('@dir'=>$path)), 'error');
				}

				if (($pos = strrpos($path, '/')) == 0) {
					$path = '';
				} else {
					$path = substr($path, 0, $pos);
				}
				zina_goto($path);

				break;

			Case 78 :
				if ($path != '') return zina_page_main($path, 'rename_directory');
				break;

		} # end admin switch

		return zina_not_found();

	} elseif (in_array($level, array(3,5,6,7,8,10,11,12,16,17,25,53,54,56,57,66,68,70,74))) {
		# STREAM FUNCTIONS

		switch ($level) {

			Case 3 :
				if ($zc['cmp_sel']) {
					zina_send_zip_selected($songs, (isset($_GET['lf'])));
				}
				break;

			Case 5 :
				if ($zc['cmp_sel']) {
					zina_send_zip_selected_dir($path, (isset($_GET['c'])), (isset($_GET['lf'])));
				}
				break;

			Case 6 : # Return resampled MP3
				if ($zc['resample']) {
					if ($zc['database'] && $zc['stats']) zdb_log_stat('play', dirname($path), basename($path));
					zina_send_file_music($path, true);
				}
				break;

			Case 7 : # return resized image
				$type = isset($_GET['it']) ? $_GET['it'] : null;

				if (in_array($type, array('sub','dir','full','search','genre','genresearch','pls','plssearch'))) {
					$cache_file = $string = false;
					$text = null;
					if (empty($imgsrc)) {
						if ($type == 'genresearch' || $type == 'plssearch') $type = 'search';
						$tmp = $zc['theme_path_abs'].'/images';
						$imgsrc = ztheme('missing_image',$type);
						$text = ztheme('title',basename($path));
					} else {
						$res_out_type = ($zc['res_out_type'] == 'jpeg') ? 'jpg' : $zc['res_out_type'];
						if ($type == 'genre' || $type == 'genresearch') {
							if ($type == 'genresearch') $type = 'search';
							$tmp = $zc['theme_path_abs'].'/images';
							$genre_file = ztheme('image_genre', $imgsrc);
							if (file_exists($tmp.'/'.$genre_file)) {
								$imgsrc = $genre_file;
							} else {
								$text = strtoupper($imgsrc);
								$imgsrc = ztheme('missing_image','genre');
							}
							$cache_file = $zc['cache_imgs_dir'].'/'.$type.md5($tmp.'/'.$genre_file).'.'.$res_out_type;
						} elseif ($type == 'pls' || $type == 'plssearch') {
							$tmp = $zc['theme_path_abs'].'/images';
							$text = strtoupper($imgsrc);
							$imgsrc = ztheme('missing_image','playlist');
							$type = ($type == 'pls') ? 'sub' : 'search';
							#$cache_file = $zc['cache_imgs_dir'].'/'.$type.md5($tmp.'/'.$genre_file).'.'.$res_out_type;
						} elseif ($imgsrc == 'zina_id3_zina.jpg') {
							$subdir_file = zina_get_dir_item($zc['mp3_dir'].'/'.$path,'/\.('.$zc['ext_mus'].')$/i');
							$tmp = $zc['mp3_dir'].'/'.$path;
							if (!empty($subdir_file)) {
								$info = zina_get_file_info($zc['mp3_dir'].'/'.$path.'/'.$subdir_file, false, true, false, true);
								if (isset($info->image)) {
									$string = $info->image;
									$cache_file = $zc['cache_imgs_dir'].'/'.$type.md5($zc['mp3_dir'].'/'.$path.'/'.$imgsrc).'.'.$res_out_type;
								}
							}
						} else {
							$tmp = $zc['mp3_dir'].'/'.$path;
							$cache_file = $zc['cache_imgs_dir'].'/'.$type.md5($zc['mp3_dir'].'/'.$path.'/'.$imgsrc).'.'.$res_out_type;
						}

						if ($zc['cache_imgs'] && $cache_file) {
							if (file_exists($cache_file)) {
								while(@ob_end_clean());
								Header('Content-type: image/'.$zc['res_out_type']);
								readfile($cache_file);
								exit;
							}
						}
					}

					$image_source = $tmp.'/'.$imgsrc;
					if (!file_exists($image_source) && !$string) {
						$image_source = $zc['theme_path_abs'].'/images/'.ztheme('missing_image',$type);
						$text = zt('Error');
						$cache_file = $string = false;
					}

					zina_send_image_resized($image_source, $type, $text, $cache_file, $string);
				}
				break;

			Case 11 : # return img
				if ($zc['stream_int'] && preg_match('/\.('.$zc['ext_graphic'].')$/i', $imgsrc)) {
					$file = $zc['mp3_dir'].'/'.((!empty($path)) ? $path.'/' : '').$imgsrc;
					if (file_exists($file)) {
						@ob_end_clean();
						readfile($file);
					}
				}
				break;

			Case 8 : # RETURN PLAYLISTS
				if ($zc['play']) {
					if (!isset($m)) $m = isset($_POST['m']) ? $_POST['m'] : null;
					$lofi = (isset($_GET['lf']));
					$cus = (isset($_GET['c']));
					$num = isset($_POST['n']) ? $_POST['n'] : (isset($_GET['n']) ? $_GET['n'] : null);
					if (!in_array($num, $zc['ran_opts'])) $num = $zc['ran_opts_def'];
					if ($playlist == null && isset($_GET['playlist'])) $playlist = $_GET['playlist'];
					$random = (isset($_GET['rand']));
					$store = (isset($_GET['store']));

					switch($m) {
						Case 0 :
							zina_send_playlist_title($path, $cus, $lofi);
							break;
						Case 1 :
							zina_send_playlist_song($path, $lofi);
							break;
						Case 3 :
							zina_send_playlist_custom($playlist, $lofi, $random);
							break;
						Case 4 : # Random Albums
							zina_send_playlist_random($num,'t',$lofi, true, null, $playlist);
							break;
						Case 5 : # Random Songs
							zina_send_playlist_random($num,'s',$lofi, true, null, $playlist);
							break;
						Case 6 : # Random Songs By Year
							zina_send_playlist_random($num,'s',$lofi, true, null, null, $playlist);
							break;
						Case 7 :
							zina_send_playlist_selected($songs, $lofi, $store);
							break;
						Case 8 :
							zina_send_playlist_selected_random($songs, $lofi, $store);
							break;
						Case 10 : # Play Recursively & Recursively Random ($cus = random)
							zina_send_playlist_random(0,'s',$lofi,$cus,$path);
							break;
						Case 11 : # Random Songs via Rated Songs
							zina_send_playlist_random($num,'tt',$lofi, true, null, $playlist);
							break;
						Case 12 : # Random Songs via Rated Artists
							zina_send_playlist_random($num,'artist',$lofi, true, null, $playlist);
							break;
					}
				}
				break;

			Case 10 : # internal streaming
				if ($zc['play']) {
					if ($zc['remote'] && preg_match('/\.('.$zc['remote_ext'].')$/i', $path)) {
						$rem = new remoteFile($zc['mp3_dir'].'/'.$path, false, true);
						if (isset($rem->url)) {
							if ($zc['database'] && $zc['stats']) zdb_log_stat('play', dirname($path), basename($path));
							while(@ob_end_clean());
							header('Location: '.$rem->url);
							exit;
						}
					} elseif ($zc['play'] && $zc['stream_int']) {
						if ($zc['database'] && $zc['stats']) zdb_log_stat('play', dirname($path), basename($path));
						zina_send_file_music($path);
					}
				}
				break;

			Case 12 : # download mp3
				if ($zc['download'] && preg_match('/\.('.$zc['ext_mus'].')$/i', $path, $exts)) {
					$file = $zc['mp3_dir'].'/'.$path;
					if (file_exists($file)) {
						if ($zc['database'] && $zc['stats']) zdb_log_stat('down', dirname($path), basename($path));
						if ($zc['stream_int']) {
							$filename = html_entity_decode(zina_get_file_artist_title($file, $zc['mp3_id3'])).'.'.$exts[1];
							zina_set_header('Content-Type: application/force-download');
							zina_set_header('Content-Disposition: inline; filename="'.$filename.'"');
							zina_set_header('Content-Length: '.filesize($file));
							zina_set_header('Cache-control: private'); #IE seems to need this.
							zina_send_file($file);
						} else {
							zina_goto($path,NULL,NULL,TRUE,TRUE);
						}
					}
				}
				break;

			Case 16 : # VOTE
				if ($zc['database']) {
					$num = isset($_POST['n']) ? $_POST['n'] : (isset($_GET['n']) ? $_GET['n'] : null);
					if (zina_validate('int',$num) && $num <= 5 && ($num >= 1 || ($zc['user_id'] > 0 && $num == 0))) {
						if (preg_match('/\.('.$zc['ext_mus'].')$/i', $path) || ($zc['remote'] && preg_match('/\.('.$zc['remote_ext'].')$/i', $path))) {
							if ($zc['rating_files']) zdb_log_stat('vote',dirname($path), basename($path), $num);
							$path = dirname($path);
						} else {
							if ($zc['rating_dirs']) zdb_log_stat('vote',$path, null, $num);
						}
						if ($zc['cache_tmpl']) {
							if (!zina_delete_tmpl_file(zina_get_tmpl_cache_file($path))) zina_debug(zt('Cannot delete cache file'));
						}
						#todo: return 'error' on bad result
						echo ($num == 0) ? zt('Deleted') : zt('Thank you');
					}
				}
				break;

			Case 68 : # Vote Playlists
				if ($zc['database'] && $zc['pls_ratings']) {
					$num = isset($_POST['n']) ? $_POST['n'] : (isset($_GET['n']) ? $_GET['n'] : null);
					if (zina_validate('int',$num) && $num <= 5 && ($num >= 1 || ($zc['user_id'] > 0 && $num == 0))) {
						zdb_log_stat_playlist((int)$playlist, 'votes', $num);
						#todo: return 'error' on bad result
						echo ($num == 0) ? zt('Deleted') : zt('Thank you');
					}
				}
				break;

			Case 25 : # download MM
				if ($zc['mm'] && $zc['mm_down'] && preg_match('/\.('.$zc['mm_ext'].')$/i', $path, $exts)) {
					$file = $zc['mp3_dir'].'/'.$path;
					if (file_exists($file)) {
						if ($zc['stream_int']) {
							$ext = strtolower($exts[1]);
							zina_set_header('Content-Type: '.$zc['mm_types'][$ext]['mime']);
							$disposition = (isset($zc['mm_types'][$ext]['disposition'])) ? $zc['mm_types'][$ext]['disposition'] : 'attachment';
							zina_set_header('Content-Disposition: '.$disposition.'; filename="'.basename($path).'"');
							zina_set_header('Content-Length: '.filesize($file));
							zina_set_header('Cache-control: private'); #IE seems to need this.
							zina_send_file($file);
						} else {
							zina_goto($path,NULL,NULL,TRUE,TRUE);
						}
					}
				}
				break;

			Case 53 : # LIVE SEARCH RETURN
				$search_term = (isset($_GET['zinaq'])) ? $_GET['zinaq'] : '';

				if (strlen($search_term) >= $zc['search_min_chars']) {
					$num = isset($_GET['limit']) ? $_GET['limit'] : $zc['search_live_limit'];
					$num = (zina_validate('int', $num) && $num > 0 && $num < $zc['search_live_limit']) ? $num : $zc['search_live_limit'];
					if ($zc['db_search']) {
						$results = zdbq_array("SELECT i.title, i.type, i.context, i.id, i.path, i.genre ".
							",if(i.type='playlist', p.image_type, FALSE) as image_type ".
							",if(i.type='playlist', pd.path, FALSE) as image_path ".
							"FROM {search_index} AS i ".
							"LEFT OUTER JOIN {playlists} AS p ON (i.type = 'playlist' AND i.type_id = p.id) ".
							"LEFT OUTER JOIN {dirs} AS pd ON (i.type = 'playlist' AND i.type_id = p.id AND p.dir_id = pd.id) ".
							"WHERE i.title LIKE '%%%s%%' ".
							"ORDER BY i.title LIMIT %d",
							array($search_term, $num));
					} else {
						$results = zina_search_cache($search_term, $num);
					}

					if (!empty($results)) {

						if ($zc['search_images']) {
							foreach ($results as $key => $item) {
								$results[$key]['image'] = zina_content_search_image($item, 'search');
							}
						}

						foreach ($results as $item) {
							unset($item['image_type']);
							unset($item['image_path']);
							$item['type'] = zt(ucfirst($item['type']));
							echo implode('|', $item)."\n";
						}
					}
				}
				exit;

				break;

			Case 54 : # XML file info for flash app
				if ($zc['zinamp']) {
					$output = zina_get_file_xml($path);
					while(@ob_end_clean());
					header('Content-type: application/xml');
					echo $output;
				}
				break;

			Case 56 :
				if ($zc['zinamp'] && $zc['lastfm'] && isset($_GET['n'])) {
					zina_play_complete($path, intval($_GET['n']));
				}
				break;

			Case 57 : # 3rd party lyrics
				if ($zc['song_extras'] && in_array($m, $zc['song_es_exts'])) {
					@session_write_close();
					if ($zc['zinamp'] && $playlist == 'zinamp') {
						$content = zina_content_blurb($zina, $path, array('type'=>$m, 'return'=>true));
						if (isset($content['output']) && !empty($content['output'])) {
							if (!$zc['debug']) @ob_end_clean();
							echo nl2br($content['output']);
							exit;
						}
					}
					if (isset($zc['third_'.$m]) && $zc['third_'.$m]) {
						$info = array();
						zina_get_file_artist_title($zc['mp3_dir'].'/'.$path, true, $info);
						$lyr_opts = zina_get_extras_opts($m);

						$opts = explode(',', $zc['third_lyr_order']);
						$output = '';

						if (!empty($info['artist']) && !empty($info['title'])) {
							foreach($opts as $source) {
								if (!in_array($source, $lyr_opts)) continue;
								require_once($zc['zina_dir_abs'].'/extras/extras_'.$m.'_'.$source.'.php');
								$result = array();

								if (($result = call_user_func('zina_extras_'.$m.'_'.$source, $info['artist'], $info['title'])) !== false) {
									$output .= $result['output'];
									if ($zc['third_'.$m.'_save']) {
										zina_save_blurb($path, $m, $output, null, false);
									}
									$output = nl2br($output);
									if (isset($result['source'])) $output .= ztheme('extras_source', $result['source']);
									break;
								}
							}
						}
						if (empty($output)) $output .= zt('No @type found.', array('@type'=>$zc['song_es'][$m]['name']));
						if (!$zc['debug']) while(@ob_end_clean());
						echo $output;
					}
				}
				break;

			Case 66 :
				if ($zc['zinamp'] && $zc['lastfm']) {
					zina_zinamp_start($path);
				}
				break;

			Case 70 :
				if ($zc['playlists'] && $zc['database'] && zina_validate('int',$playlist)) {
					zina_playlist_feed($playlist);
				}
				break;

			Case 74:
				if (isset($_SESSION['zina_store'])) {
					$store = $_SESSION['zina_store'];
					unset($_SESSION['zina_store']);
					if (!empty($store)) {
						zina_send_playlist_content($store['type'], $store['content']);
					}
				}
				break;
		}
		exit;

	} else {
		# PAGE DISPLAYS (2,4,9,13,14,15,22,23,24,29,40,41,43,44,50,51,55,69,77,99)

		switch ($level) {

			Case 2 :
				if ($zc['playlists']) return zina_page_main($path, 'playlists', array('pl'=>$playlist, 'id'=>$m));
				break;

			Case 4 : # SEARCH
				if ($zc['search']) return zina_page_main($path, 'search', array('m'=>$m));
				break;

			Case 9 : # LOGIN
				if ($zc['login']) {
					if (isset($_POST['un']) && isset($_POST['up'])) {
						if (zina_check_password($_POST['un'], $_POST['up'])) {
							$_SESSION['za-'.ZINA_VERSION] = true;
							if ($zc['session']) { // standalone only
								$sess_id = zina_token_sess('1');
								setcookie('ZINA_SESSION', $sess_id, time() + (60*60*$zc['session_lifetime']), '/');
								$sess_file = $zc['cache_dir_private_abs'].'/sess_'.$sess_id;
								@touch($sess_file);
							}
							zina_goto($path);
						} else {
							sleep(3);
							$_SESSION['za-'.ZINA_VERSION] = false;
							zina_set_message(zt('Username and/or password are incorrect.'),'warn');
						}
					}
					return zina_page_main($path, 'login');
				}
				break;

			Case 13 : # Genre Listing
				if ($zc['genres']) return zina_page_main($path, 'searchgenre');
				break;

			Case 14 : # Genres
				if ($zc['genres']) return zina_page_main($path, 'genres');
				break;

			Case 15 : # STATS
				if ($zc['database'] && $zc['stats'] && ($zc['stats_public'] || $zc['is_admin'])) {
					$period = isset($_POST['period']) ? $_POST['period'] : null;
					$type = isset($_POST['type']) ? $_POST['type'] : null;
					return zina_page_main($path, 'stats', array('stat'=>$playlist, 'period'=>$period, 'type'=>$type));
				}
				break;

			Case 22 : # VARIOUS EDIT WINDOWS
				if ($zc['is_admin'] || (zina_cms_access('editor') && (in_array($m, array(1,2,3,4,6)) || in_array($m, $zc['song_es_exts'])))) {
					return zina_page_main($path, 'blurb', array('type'=>$m, 'item'=>$playlist));
				} else {
					return zina_access_denied();
				}
				break;

			Case 23 : # VARIOUS EDIT WINDOWS SAVE
				if (!zina_token_sess_check()) return zina_page_main($path);
				if ($zc['is_admin'] || (zina_cms_access('editor') && (in_array($m, array(1,2,3,4,6)) || in_array($m, $zc['song_es_exts'])))) {
					zina_save_blurb($path, $m, $songs, $playlist);
				} else {
					return zina_access_denied();
				}
				break;

			Case 77 :
				if ($zc['is_admin'] || (zina_cms_access('editor'))) {
					if (isset($_POST) && !empty($_POST) && !zina_token_sess_check()) return zina_access_denied();

					return zina_page_main($path, 'dir_opts');
				} else {
					return zina_access_denied();
				}
				break;

			Case 24 : # PLAY MM
				if ($zc['mm']) return zina_page_main($path, 'mm');
				break;

			Case 29 :# Song Extras
				if ($zc['song_extras'] && in_array($m, $zc['song_es_exts'])) {
					return zina_page_main($path, 'songextras', array('type'=>$m, 'item'=>null));
				}
				break;

			Case 40 : # Add New Playlist && Add To Playlist
				if (!zina_token_sess_check()) return zina_page_main($path);

				if ($zc['database']) {
					$access = zina_cms_access('edit_playlists', $zc['user_id']);
					if (!($access || ($zc['session_pls'] && $playlist == 'zina_session_playlist'))) {
						zina_set_message(zt('Not authorized'));
						return zina_page_main($path);
					}
					if ($access && $playlist == 'new_zina_list') {
						if (!$zc['is_admin']) {
							$count = zdbq_single("SELECT COUNT(*) FROM {playlists} WHERE user_id = %d", array($zc['user_id']));
							if ($count > $zc['pls_limit']) {
								zina_set_message(zt('Cannot create playlist.').' '.zt('Maximum number of playlists reached.'));
								return zina_page_main($path);
							}
						}
						return zina_page_main($path, 'newplaylist',array('songs'=>$songs));
					} else {
						if (isset($_POST['fromnew'])) {
							if (($pls_id = zina_playlist_form_submit('insert')) !== false) {
								$playlist = $pls_id;
								$start = 1;
							} else {
								$start = false;
							}
						} else {
							if ($playlist == 'zina_session_playlist') {
								$existing = (isset($_SESSION['z_sp'])) ? unserialize_utf8($_SESSION['z_sp']) : array();
								$start = count($existing);
							} else {
								$start = zdbq_single("SELECT MAX(weight) FROM {playlists_map} WHERE playlist_id = %d", array($playlist, $zc['user_id']));
							}
						}
						if ($start !== false) {
							if (zina_playlist_insert($playlist, $songs, $start+1)) {
								zina_set_message(zt('Added to playlist'));
							} else {
								zina_set_message(zt('Could not add to playlist'), 'warn');
							}
						}

						if (isset($_POST['fromnew'])) {
							if (empty($path)) {
								zina_goto('','l=2&pl='.rawurlencode($playlist));
							} else {
								return zina_page_main($path);
							}
						} else {
							echo ztheme('messages');
							exit;
						}
					}
				} elseif ($zc['is_admin'] || $zc['session_pls']) {
					if (!$zc['is_admin']) $playlist = 'zina_session_playlist';
					if ($playlist == 'new_zina_list') {
						return zina_page_main($path, 'newplaylist',array('songs'=>$songs));
					} else {
						zina_write_playlist($songs, '_zina_'.$playlist.'.m3u', 'a');
						zina_set_message(zt('Added to playlist'));

						if (isset($_POST['fromnew'])) {
							if (empty($path)) {
								zina_goto('','l=2&pl='.rawurlencode($playlist));
							} else {
								return zina_page_main($path);
							}
						} else {
							echo ztheme('messages');
							exit;
						}
					}
				}
				break;

			Case 41 : # Update Playlist
				if (!zina_token_sess_check()) return zina_page_main($path);

				$order = isset($_POST['order']) ? $_POST['order'] : null;
				if ($zc['database']) {
					$pls_user_id = zdbq_single("SELECT user_id FROM {playlists} WHERE id = %d", array($playlist));
					$access = zina_cms_access('edit_playlists', $pls_user_id);

					if (!($access || ($zc['session_pls'] && $playlist == 'zina_session_playlist'))) {
						zina_set_message(zt('Not authorized'));
						return zina_page_main($path);
					}

					$songs = zina_reorder_playlist($songs, $order);
					if ($playlist == 'zina_session_playlist') {
						$_SESSION['z_sp'] = utf8_encode(serialize($songs));
					} else {
						zdbq("DELETE FROM {playlists_map} WHERE playlist_id = %d", array($playlist));
						foreach($songs as $weight => $type_id) {
							if (preg_match('/\.lp$/i', $type_id)) {
								$type = 'album';
								$type_id = preg_replace('/\/\.lp$/i','',$type_id);
							} elseif (preg_match('/\.pls$/i', $type_id)) {
								$type = 'playlist';
								$type_id = preg_replace('/\.pls/i','',$type_id);
							} else {
								$type = 'song';
							}
							if (!zdbq("INSERT {playlists_map} (playlist_id, type, type_id, weight) VALUES (%d, '%s', %d, %d)",
									array($playlist, $type, $type_id, $weight+1))) {
								zina_set_message(zt('Could not insert into playlist: @file', array('@file'=>$type_id)));
							}
						}
						if (($sum_items = zdbq_single("SELECT COUNT(*) FROM {playlists_map} WHERE playlist_id = %d", array($playlist))) !== false) {
							zdbq("UPDATE {playlists} SET sum_items = %d WHERE id = $playlist", array($sum_items, $playlist));
						}
					}
					return zina_page_main($path, 'playlists',array('pl'=>$playlist));
				} elseif ($zc['is_admin'] || $zc['session_pls']) {
					if (!$zc['is_admin']) $playlist = 'zina_session_playlist';
					zina_write_playlist(zina_reorder_playlist($songs, $order), '_zina_'.$playlist.'.m3u');
					return zina_page_main($path, 'playlists',array('pl'=>$playlist));
				}
				break;

			Case 43 : # DELETE CUSTOM PLAYLIST
				if ($zc['database'] && $playlist != 'zina_session_playlist') {
					if ($zc['is_admin'] || $zc['pls_user']) {
						if ($zc['is_admin']) {
							$access = true;
						} else {
							$access = zdbq_single("SELECT 1 FROM {playlists} WHERE id = %d AND user_id = %d", array($playlist, $zc['user_id']));
						}
						if ($access) {
							zdbq("DELETE FROM {playlists_map} WHERE playlist_id = %d", array($playlist));
							zdbq("DELETE FROM {playlists} WHERE id = %d", array($playlist));
							zina_set_message(zt('Playlist deleted'));
							zina_goto('','l=2');
						}
					}
				} else {
					if ($zc['is_admin'] || $zc['session_pls']) {
						if (!$zc['is_admin']) $playlist = 'zina_session_playlist';
						zina_delete_playlist_custom($playlist);
						zina_goto('','l=2');
					}
				}
				break;

			Case 44 : # EDIT PLAYLIST
				if ($_POST && !zina_token_sess_check()) return zina_page_main($path);
				if (!($zc['playlists'] && ($zc['is_admin'] || ($zc['pls_user'] && $zc['user_id'] > 0)))) return zina_access_denied();
				$playlist_new = isset($_POST['playlist_new']) ? $_POST['playlist_new'] : '';
				return zina_page_main($path, 'renameplaylist', array('playlist'=>$playlist, 'new'=>$playlist_new));
				break;

			case 46 :
				if ($zc['res_full_img'] && preg_match('/\.('.$zc['ext_graphic'].')$/i', $path)) {
					return zina_page_main($path, 'image');
				}
				break;

			Case 50 : #podcast
				if ($zc['rss']) {
					#TODO: make common output function...
					while(@ob_end_clean());
					header('Content-type: application/xml');
					echo zina_content_rss($path);
					exit;
				}
				break;

			Case 51 : # SITEMAP
				if ($zc['sitemap']) {
					$output = zina_cache('sitemap', 'zina_content_sitemap', null, ($zc['sitemap'] == 2));
					while(@ob_end_clean());
					header('Content-type: text/xml');
					echo $output;
					exit;
				}
				break;

			Case 99 : # logout
				session_unregister('za-'.ZINA_VERSION);
				if ($zc['session']) {
				  	if (isset($_COOKIE['ZINA_SESSION'])) {
						$sess_file = $zc['cache_dir_private_abs'].'/sess_'.zcheck_plain($_COOKIE['ZINA_SESSION']);
						setcookie('ZINA_SESSION', $_COOKIE['ZINA_SESSION'], time() - 42000, '/');
						if (file_exists($sess_file)) @unlink($sess_file);
					}

					# remove expired sessions
					$old_sessions = glob($zc['cache_dir_private_abs']."/sess_*");
					if (is_array($old_sessions)) {
						foreach ($old_sessions as $filename) {
							if (filemtime($filename) + (60*60*$zc['session_lifetime']) < time()) {
      						@unlink($filename);
    						}
						}
  					}
				}

				zina_set_message(zt('Logged out succesfully.'));
				zina_goto($path);
				break;

			case 55 :
				#todo: move to stream?
				if ($zc['zinamp'] == 2) {
					$content = ztheme('zinamp');
					zina_set_js('inline',
					'window.onunload = function() {'.
						'zina_cookie("zinamp_window", "screenX="+window.screenX+",screenY="+window.screenY, {expires:7});'.
					'};');
					$zina = zina_page_simple('zinamp', $content);
					echo ztheme('page_zinamp', $zina);
					exit;
				}
				break;

			Case 65 :
				require_once($zc['zina_dir_abs'].'/batch.php');
				$output = _zbatch_page();
				if ($output === FALSE) {
					return zina_access_denied();
				} elseif (isset($output)) {
					zina_set_css('file', 'extras/progress.css');
					return zina_page_simple(zbatch_set_title(), $output);
				}
				return;

			Case 69 : # Year Listing
				if ($zc['db_search']) return zina_page_main($path, 'searchyear');
				break;


			default : # MAIN PAGE
				# Allows files to stream without l=8 (for RSS and prettiness)
				if (is_file($zc['cur_dir']) && $zc['play'] && (($zc['stream_int'] && preg_match('/\.('.$zc['ext_mus'].')$/i', $path)) ||
					($zc['remote'] && preg_match('/\.('.$zc['remote_ext'].')$/i', $path, $matches)))) {
					if ($zc['database'] && $zc['stats']) zdb_log_stat('play', dirname($path), basename($path));
					zina_send_file_music($path);
				}

				if (is_file($zc['cur_dir']) && $zc['rss'] && basename($path) == $zc['rss_file']) {
					$output = file_get_contents($zc['cur_dir']);
					$output = utf8_decode($output);
					header('Content-type: application/xml');
					echo $output;
					exit;
				}

				if (!is_dir($zc['cur_dir'])) return zina_not_found();
				if ($zc['database']) zdb_log_stat('view', $path);
				return zina_page_main($path);
			}
			return zina_not_found();
	}
} #END MAIN

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * PAGES
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

function zina_page_main($path, $type='main', $opts=null) {
	global $zc;
	$page = zina_get_page_opt($path);
	$cat_sort = zina_get_catsort_opt($path);

	if ($zc['cache_tmpl']) {
		$cache_file = $zc['cache_tmpl_dir'].'/'.zina_get_tmpl_cache_file($path);

		if (!$zc['is_admin'] && $_SERVER['REQUEST_METHOD'] == 'GET' && file_exists($cache_file)) {
			$mtime = filemtime($cache_file);
			if ($mtime > filemtime($zc['cur_dir'].'/.') && $mtime + ($zc['cache_tmpl_expire'] * 86400) > time()) {
				$zina = unserialize(implode('', gzfile($cache_file)));
				$zina['cached'] = true;
				return $zina;
			}
		}
	}

	$zina = array();
	$zina['lang']['main_dir_title'] = zt($zc['main_dir_title']);
	$zina['embed'] = $zc['embed'];
	$zina['site_name'] = isset($zc['conf']['site_name']) ? $zc['conf']['site_name'] : '';
	$zina['amg'] = $zc['amg'];
	$zina['theme_path'] = zpath_to_theme();
	$zina['searchform'] = $zina['stats'] = $zina['genres'] = $random = '';
	$zina['charset'] = $zc['charset'];
	$zina['addthis'] = $zc['third_addthis'];
	$zina['addthis_id'] = $zc['third_addthis_id'];
	$zina['addthis_options'] = $zc['third_addthis_options'];
	$zina['addthis_path'] = $path;
	$zina['addthis_query'] = null;

	if ($zc['search']) {
		$search_term = isset($_POST['searchterm']) ? $_POST['searchterm'] : (isset($_GET['pl']) ? $_GET['pl'] : (isset($_GET['searchterm']) ? $_GET['searchterm'] : ''));
		$zina['searchform'] = ztheme('searchform', array(
			'action' => zurl('','l=4'),
			'search_min_chars' => $zc['search_min_chars'],
			'search_live_limit' => $zc['search_live_limit'],
			'live_url' => zurl('', 'l=53'),
			'images' => $zc['search_images'],
			),
			(($type == 'search') ? htmlentities($search_term) : '')
		);
	}

	if ($zc['genres']) {
		if ($type == 'genres' && $path = 'genre') $path = '';
		# not used
		$zina['genres'] = array('path'=>null, 'query'=>'l=14');
	}

	if ($zc['is_admin'] && $type != 'config') {
		$zina['admin_config'] = array('path'=>$path, 'query'=>'l=20');
	}
	if ($zc['login']) {
		if ($zc['is_admin']) {
			$zina['login'] = array('type'=>'logout', 'path'=>$path,'query'=>'l=99');
		} else {
			$zina['login'] = array('type'=>'login', 'path'=>$path,'query'=>'l=9');
		}
	}

	# not used
	if ($zc['database'] && ($zc['is_admin'] || ($zc['stats'] && $zc['stats_public']))) {
		$zina['stats'] = array('path'=>$path, 'query'=>'l=15');
	}

	$zina['zinamp'] = ($zc['zinamp']) ? ztheme('zinamp_embed') : '';

	switch($type) {
		case 'main':
		default:
			$zina['page_type'] = 'main';
			//zina_content_breadcrumb($zina, $path, $zc['main_dir_title']);
			zina_content_main($zina, $path, $page, $cat_sort);
			break;
		case 'playlists';
			zina_content_breadcrumb($zina, $path);
			if ($zc['database']) {
				$id = (isset($opts['id'])) ? $opts['id'] : null;
				zina_content_playlist_db($zina, $path, $opts['pl'], $id);
			} else {
				zina_content_playlists($zina, $path, $opts['pl']);
			}
			break;
		case 'genres':
			zina_content_breadcrumb($zina, '', zt('Genres'));
			$genres = zina_get_genres_list();
			$genre_num = sizeof($genres);
			$full_page_split = $genres_navigation = false;
			$page = isset($_GET['page']) ? $_GET['page'] : 1;

			#todo: combine with cat_split
			if ($zc['genres_split'] == 2 || $zc['genres_split'] == 3) {
				$splits = ztheme('category_alphabet_split', $genres, $zc['dir_si_str']);
				$splits_sum = array_keys($splits);

				if ($zc['genres_split'] == 2) {
					if (!in_array($page, $splits_sum)) $page = $splits_sum[0];
					$genres_navigation = ztheme('category_alphabet',null, $page, $splits_sum, 'l=14&amp;');
					$genres = $splits[$page];
				} else { # $genres_split == 3
					$full_page_split = 0;
					foreach ($splits as $i) { foreach($i as $item) $full_page_split++; }
					$genres_navigation = ztheme('category_alphabet',null, $page, $splits_sum, 'l=14&amp;', true);
					$genres = &$splits;
				}
			} elseif ($zc['genres_split'] && $genre_num > $zc['genres_pp']) {
				$pages_total = ceil($genre_num/$zc['genres_pp']);
				if (!zina_validate('int', $page) || $page < 1 || $page > $pages_total) $page = 1;
				$genres_navigation = ztheme('category_pages',null, $page, $pages_total,'l=14&amp;');
				$cstart = ($page - 1) * $zc['genres_pp'];
				if ($cstart > $genre_num) $cstart = 0;
				$genres = array_slice($genres, $cstart, $zc['genres_pp']);
			}
			$zina['messages'] = ztheme('messages');
			$zina['content'] = ztheme('genres', $genres, $zc['genres_cols'], $zc['genres_images'], $zc['genres_truncate'], $zina, $genres_navigation, $full_page_split);
			break;

		case 'search':
			#todo: move this whole thing somewhere...
			zina_content_breadcrumb($zina, '', zt('Search Results'));

			$mode_opts = array_keys(zina_get_opt_search());
			$mode = (in_array($opts['m'], $mode_opts)) ? $opts['m'] : $zc['search_default'];
			if ($mode == 'play' && !$zc['play']) $mode = 'browse';

			$result = array();
			$search_id = (isset($_POST['searchid'])) ? $_POST['searchid'] : (isset($_GET['searchid']) ? $_GET['searchid'] : false);
			if ($zc['db_search']) {
				if ($search_id && zina_validate('int', $search_id)) {
					$result = zdbq_array_single("SELECT path, type FROM {search_index} WHERE id = %d && title = '%s'", array($search_id, $search_term));
				}
			} else {
				if ($search_id) {
					if ((!strstr($search_id,'..') || zfile_check_location($zc['mp3_dir'].'/'.$search_id, $zc['mp3_dir'])) && file_exists($zc['mp3_dir'].'/'.$search_id)) {
						$result['path'] = $search_id;
						if (preg_match('/\.('.$zc['ext_mus'].')$/i', $search_id)) {
							$result['type'] = 'song';
						} else {
							$result['type'] = 'directory';
							if ($zc['search_structure']) {
								$count = substr_count($path, '/');
								if ($count == 0) {
									$result['type'] = 'artist';
								} elseif ($count == 1) {
									$result['type'] = 'album';
								}
							}
						}
					}
				}
			}

			if (!empty($result)) {
				$search_type = $result['type'];
				$search_path = $result['path'];

				if ($search_type == 'song') {
					if ($mode == 'play') {
						if (file_exists($zc['mp3_dir'].'/'.$search_path)) zina_send_playlist_song($search_path);
					} else {
						$search_dir = dirname($search_path);
						if (file_exists($zc['mp3_dir'].'/'.$search_dir)) zina_goto($search_dir);
					}
				} elseif ($search_type == 'genre') {
					if ($mode == 'play') {
           				zina_send_playlist_random($zc['ran_opts_def'], 's', false, true, null, $search_path);
					} else {
						zina_goto('', 'l=13&pl='.rawurlencode($search_path));
					}
				} elseif (in_array($search_type, array('directory', 'artist', 'album'))) {
					if (is_dir($zc['mp3_dir'].'/'.$search_path)) {
						if ($mode == 'play') {
							if ($search_type == 'album') {
								zina_send_playlist_title($search_path, false, false);
							} else {
								zina_send_playlist_random(0,'s',false,true,$search_path);
							}
						} else {
							zina_goto($search_path);
						}
					}
				} elseif ($search_type == 'playlist') {
					if ($mode == 'play') {
						zina_send_playlist_custom($search_path, false);
					} else {
						zina_goto('', 'l=2&pl='.rawurlencode($search_path));
					}
				} else {
					zina_debug(zt('Search type not found in index.'));
				}
			}

			$results = array();
			$count = 0;
			$action = $selected = $search_navigation = false;
			if (strlen($search_term) < $zc['search_min_chars']) {
				zina_set_message(zt('Search term must be longer than @num characters',@array('@num'=>$zc['search_min_chars'])),'warn');
			} else {
				$page = (isset($_GET['page'])) ? '&amp;page='.$_GET['page'] : '';
				$action = zurl('','l=4'.$page);
				$sql = ($zc['db_search']) ? array('where'=>"i.title LIKE '%%%s%%'") : array();
				$results = zina_search_pager_query($search_term, 'l=4', $count, $search_navigation, $sql, false, $selected);
			}
			$checkbox = (($zc['is_admin'] && $zc['cache']) || $zc['session_pls']);

			zina_content_search_list($results, $checkbox, array('highlight'=>$search_term));
			$list = ztheme('search_list', $results, $zc['search_images']);

			$form_id = 'zinasearchresultsform';
			#todo: why m=1?
			$form_attr = 'id="'.$form_id.'" action="'.zurl('','m=1').'"';
			$list_opts = ($zc['playlists']) ? ztheme('search_list_opts', zina_content_song_list_opts(false,  false, $checkbox, $form_id, true), $form_id) : null;

			$zina['content'] = ztheme('search_page', $search_term, $results, $count, $search_navigation, $form_attr, $list, $list_opts, $action, $selected, $zina);
			break;
		case 'searchgenre':
			zina_content_search('genres', $zina);
			break;
		case 'searchyear':
			zina_content_search('year', $zina);
			break;
		case 'blurb':
			zina_content_blurb($zina, $path, $opts);
			break;
		case 'dir_opts':
			zina_content_directory_opts($zina, $path);
			break;
		case 'edittags':
			zina_content_edit_tags($zina, $path, $opts);
			break;
		case 'songextras':
			$opts['return'] = true;
			$content = zina_content_blurb($zina, $path, $opts);
			$ajax_url = false;

			if (empty($content['output']) && isset($zc['third_'.$opts['type']]) && $zc['third_'.$opts['type']]) {
				$ajax_url = zurl($path, 'l=57&m='.$opts['type']);
				zina_set_js('file', 'extras/jquery.js');
			}
			$zina['content'] = ztheme('song_extra', $opts['type'], $content['title'], nl2br($content['output']), $ajax_url);
			break;
		case 'image':
			$img_path = dirname($path);
			$dir = zina_get_directory($img_path);
			zina_content_breadcrumb($zina, $img_path, $dir['title'], true);
			$zina['content'] = ztheme('page_image', $zina, $img_path, $dir['images'], basename($path), $dir['captions']);
			break;
		case 'login':
			zina_content_breadcrumb($zina, '', zt('Login'));
			$rows[] = array('label'=>zt('Username'),'item'=>'<input type="text" name="un"/>');
			$rows[] = array('label'=>zt('Password'),'item'=>'<input type="password" name="up"/>');
			$rows[] = array('label'=>null,'item'=>'<input type="submit" value="'.zt('Login').'"/>');

			$form = array(
				'title'=>zt('Login'),
				'attr'=>'action="'.zurl($path, 'l=9').'"',
				'rows'=>$rows
			);

			$zina['content'] = ztheme('login', $form);
			break;
		case 'newplaylist':
			zina_content_breadcrumb($zina, '', zt('New Playlist'));

			if ($zc['database']) {
				$item = array('title'=>'', 'description'=>'', 'genre_id'=> '', 'dir_id'=>'', 'image_type'=>'', 'visible'=>true);
				$rows = zina_content_playlist_form($item);
			} else {
				$rows[] = array('label'=>zt('Playlist Name'),'item'=>'<input name="playlist" type="text" size="25" maxlength="30"/>');
			}
			$rows[] = array('label'=>null,'item'=>'<input type="submit" value="'.zt('Submit').'"/>');

			$hidden = '';
			if (sizeof($opts['songs']) != 0) {
				sort($opts['songs']);
				foreach($opts['songs'] as $song) {
					$hidden .= '<input type="hidden" name="mp3s[]" value="'.$song.'"/>';
				}
			}
			$form = array(
				'attr'  =>'action="'.zurl($path,'l=40').'"',
				'hidden'=>$hidden.ztheme('form_hidden','fromnew',1),
				'rows'  =>$rows
			);
			$zina['content'] = ztheme('newplaylist',$form);
			break;

		case 'renameplaylist':

			if (!empty($opts['new']) || ($zc['database'] && $_POST)) {
				if ($zc['database']) {
					if (zina_playlist_form_submit('update', $opts['playlist'])) {
						zina_goto('', 'l=2&pl='.$opts['playlist']);
					}
				} else {
					if (zina_rename_playlist($opts['playlist'], $opts['new'])) {
						zina_goto('', 'l=2');
					}
				}
				zina_set_message(zt('Cannot edit playlist.'), 'warn');
			}

			if ($zc['database']) {
				$title = zt('Edit Playlist');

				$item = zdbq_array_single("SELECT title, description, dir_id, user_id, genre_id, image_type, visible FROM {playlists} WHERE id = %d", array($opts['playlist']));
				if (empty($item)) {
					zina_set_message(zt('Cannot edit playlist.'), 'warn');
					zina_goto('', 'l=2');
				}
				$rows = zina_content_playlist_form($item);
			} else {
				$title = zt('Rename');
				$rows[] = array('label'=>zt('Rename').' "'.htmlentities($opts['playlist']).'" '.zt('Playlist'),
					'item'=>'<input name="playlist_new" type="text" size="25" maxlength="30" value="'.htmlentities($opts['new']).'"/>');
			}

			zina_content_breadcrumb($zina, '', $title);
			$rows[] = array('label'=>null,'item'=>
				'<input type="submit" value="'.zt('Submit').'"/> '.
				'<input type="button" value="'.zt('Cancel').'" onClick="location.href=\''.zurl('', 'l=2&pl='.$opts['playlist']).'\'"/>'
			);
			$form = array(
				'attr'  => 'action="'.zurl('','l=44').'"',
				'hidden'=> '<input type="hidden" name="playlist" value="'.htmlentities($opts['playlist']).'"/>',
				'rows'  => $rows
			);
			$zina['content'] = ztheme('renameplaylist',$form);
			break;

		case 'stats':
			zina_content_stats($zina, $opts);
			break;

		case 'config':
			zina_content_breadcrumb($zina, '', zt('Settings'));
			$zina['content'] = zina_content_settings($path);
			break;

		case 'genre_hierarchy':
			zina_content_breadcrumb($zina, '', zt('Genres and Hierarchy'));
			$zina['content'] = zina_content_genre_hierarchy();
			break;

		case 'edit_images':
			$zina['content'] = zina_content_edit_images($zina, $path);
			break;

		case 'rename_directory':
			$dir = zina_get_directory($path, false, array('get_files'=>false));

			if ($dir['dir_write']) {
				if (isset($_POST) && !empty($_POST) && zina_token_sess_check() && !empty($_POST['new_directory'])) {
					$new_dir = $_POST['new_directory'];

					$base = dirname($path);
					$new_path = ($base == '.') ? $new_dir : $base.'/'.$new_dir;
					$new_full = $zc['mp3_dir'].'/'.$new_path;

					if (zfile_check_location($new_full, $zc['mp3_dir']) && $zc['cur_dir'] != $new_full) {
						if (rename($zc['cur_dir'], $new_full)) {
							zina_set_message(zt('Directory renamed'));
							if ($zc['database']) {
								if (isset($dir['id']) && !empty($dir['id'])) {
									$dir_id = $dir['id'];
								} else {
									$dir_id = zdbq_single("SELECT id FROM {dirs} WHERE path = '%s'", array($new_path));
								}

								if (!empty($dir_id)) {
									$sql =  "SET path = REPLACE(path, '%s', '%s') WHERE path LIKE '%s%%'";
									$vars = array($path, $new_path, $path);
									zdbq("UPDATE {dirs}  $sql", $vars);
									zdbq("UPDATE {dirs} SET title = '%s' WHERE id = %d AND title = '%s'", array(basename($new_path), $dir_id, basename($path)));
									zdbq("UPDATE {files}  $sql", $vars);
									zdbq("UPDATE {search_index}  $sql", $vars);
									zdbq("UPDATE {search_index} SET context = REPLACE(path, '%s', '%s') WHERE context LIKE '%s%%'", $vars);
									zdbq("UPDATE {search_index} SET title = '%s' WHERE type_id = %d AND title = '%s' AND type IN ('artist', 'album', 'directory')", array(basename($new_path), $dir_id, basename($path)));

									$alts = array();
									if (isset($dir['alt_items'])) {
										$alts = $dir['alt_items'];
									} else {
										$alt_file = $new_full.'/'.$zc['alt_file'];
										if (file_exists($alt_file)) {
											$alts = zunserialize_alt(file_get_contents($alt_file));
										}
									}

									if (!empty($alts)) {
										foreach($alts as $alt) {
											# backwards compatibility...strip /
											$alt_path = rtrim($alt, '/');
											$others = zdb_get_others($alt_path);
											$items = array();
											if (!empty($others) && isset($others['alt_items'])) {
												$items = $others['alt_items'];
											} else {
												$alt_file = $zc['mp3_dir'].(!empty($alt_path) ? '/'.$alt_path : '') . '/'.$zc['alt_file'];
												if (file_exists($alt_file)) {
													$items = zunserialize_alt(file_get_contents($alt_file));
												}
											}

											if (!empty($items)) {
												$change = false;
												foreach($items as $key => $item) {
													$item = rtrim($item, '/');

													if ($item == $path) {
														$items[$key] = $new_path;
														$change = true;
													} else {
														$items[$key] = $item;
													}
												}
												if ($change) {
													zdb_update_others(array('alt_items'=>$items), $alt_path);
												}
											}
										}
									}

									zina_set_message(zt('Database updated'));
								} else {
									zina_set_message(zt('Database not updated'), 'warn');
								}
							}
							zina_goto($new_path);
						} else {
							zina_set_message(zt('Could not rename directory: @old to @new', array('@old'=>$path, '@new'=>$new_path)), 'error');
						}
					} else {
						zina_set_message(zt('Cannot rename directory.'), 'error');
					}
				}
				zina_content_breadcrumb($zina, $path, $dir['title'], true);

				$rows[] = array('label'=>zt('New Directory Name'), 'item'=>zina_content_form_helper('new_directory', array('type'=>'textfield', 'def'=>'', 'size'=>30, 'v'=>array('req')), ''));
				$rows[] = array('label'=>0,'item'=>'<input type="Submit" value="'.zt('Submit').'"/>');

				$form = array(
					'attr'=>'action="'.zurl($path, 'l=78').'"',
					'rows'=>$rows,
					'title' => zt('Rename Directory'),
				);
				$zina['content'] = ztheme('form_table', $form);
			} else {
				zina_set_message(zt('Cannot rename directory.').' '.zt('Directory is not writeable.'), 'error');
				zina_goto($path);
			}
			break;

		case 'help':
			require_once($zc['zina_dir_abs'].'/extras/help.php');
			zina_content_breadcrumb($zina, '', zt('Help and Support Information'));
			$zina['content'] = zina_content_help();
			break;

		case 'mm':
			zina_content_breadcrumb($zina, $path);
			if (preg_match('/\.('.$zc['mm_ext'].')$/i', $path, $exts)) {
				$ext = strtolower($exts[1]);
				if (isset($zc['mm_types'][$ext]['player'])) {
					$ext = strtolower($exts[1]);
					if (preg_match('#^'.$_SERVER['DOCUMENT_ROOT'].'#i',$zc['mp3_dir'])) {
						$file = zurl($path, null, null, true, true);
					} else {
						#todo: doesnt work
						$file = zurl($path, 'l=25', NULL, TRUE);
					}
					$zina['content'] = ztheme('mm', strtolower($zc['mm_types'][$ext]['player']), $file);
				}
			}
			break;
		case 'clean':
			zina_content_breadcrumb($zina, '', zt('Clean up database'));

			#todo: THEME?
			$form_id = 'zinacleanform';
			zina_set_js('inline', 'function selAll(x){for(var i=0;i<document.forms.'.$form_id.'.elements.length;i++){var e=document.forms.'.$form_id.'.elements[i];if(e.type==\'select-one\'){e.selectedIndex=x;}}}');
			$rows[] = array('label'=>zt('All'),'item'=>zl(zt('Remove'),'javascript: void 0;',NULL,NULL,FALSE,' onclick="selAll(0);"').' | '.
				zl(zt('Ignore'),'javascript: void 0;',NULL,NULL,FALSE,' onclick="selAll(1);"') );
			$rows[] = array('label'=>-1,'item'=>'<h3>'.zt('Database entries with missing directories').'</h3>');
			$hidden = zina_content_dbclean_select($rows,'zdirids', zdb_clean_find('dir'));
			$rows[] = array('label'=>-1,'item'=>'<h3>'.zt('Database entries with missing files').'</h3>');
			$hidden .= zina_content_dbclean_select($rows,'zfileids', zdb_clean_find('file'));

			$rows[] = array('label'=>-1,'item'=>'<input type="Submit" value="'.zt('Submit').'"/>');

			$form = array(
				'attr'=>'action="'.zurl($path, 'l=27').'" id="'.$form_id.'"',
				'rows'=>$rows,
				'hidden'=>$hidden,
			);

			$zina['content'] = '<p><strong>'.zt('This feature is experimental.  It should not hurt anything, but it might not do everything it is supposed to.').'</strong></p>'.ztheme('clean',$form);

			break;
	}

	if ($zc['play'] && $zc['random']) {
		$genres = ($zc['genres']) ? zina_core_cache('genres') : array();
		$random = ztheme('random', $zc, $zc['database'], $genres, zurl('','l=8'), zurl('','l=8&amp;lf'), zurl('','l=8'));
	}

	$zina['randomplayform'] = $random;
	$zina['time'] = $zc['conf']['time'];
	$zina['messages'] = ztheme('messages');
	$zina['head_js'] = zina_get_js();
	$zina['head_css'] = zina_set_css();
	$zina['head_html'] = zina_get_html_head();

	if ($zc['cache_tmpl'] && !$zc['is_admin'] && empty($zina['messages']) && is_writeable($zc['cache_tmpl_dir'])) {
		$fp = gzopen ($cache_file, 'w1');
		gzwrite($fp,serialize($zina));
		gzclose($fp);
	}

	return $zina;
}

function zina_page_simple($title, $content) {
	global $zc;
	$zina['charset'] = $zc['charset'];
	$zina['embed'] = $zc['embed'];
	$zina['theme_path'] = zpath_to_theme();
	$zina['head_js'] = zina_get_js();
	$zina['head_css'] = zina_set_css();
	$zina['head_html'] = zina_get_html_head();
	zina_content_breadcrumb($zina, '', $title);
	$zina['searchform'] = false;
	$zina['randomplayform'] = null;
	$zina['content'] = $content;
	$zina['messages'] = ztheme('messages');
	$zina['time'] = $zc['conf']['time'];
	return $zina;
}

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * CONTENT
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

function zina_content_main(&$zina, $path, $page, $category_sort) {
	global $zc;

	if ($cat = zina_is_category($zc['cur_dir'])) {
		$category = true;
	} else {
		$category = $cat['images'] = false;
	}

	$dir = zina_get_directory($path, $category, array('cat_images'=>$cat['images']));

	if (empty($path) || $path == '.') $dir['title'] = $zina['lang']['main_dir_title'];
	zina_content_breadcrumb($zina, $path, $dir['title']);

	if ($dir['dir_edit']) {
		$zina['dir_edit_opts']['dir_opts'] = array('path'=>$path, 'query'=>'l=77');
		$zina['dir_edit_opts']['dir'] = array('path'=>$path, 'query'=>'l=22&amp;m=1');
	}

	$zina['description'] = (isset($dir['description'])) ? $dir['description'] : false;
	$zina['subdir_num'] = $subdir_num = sizeof($dir['subdirs']);
	$zina['alt_num'] = 0;
	$zina['pls_included'] = false;
	$files_found = (!empty($dir['files']));
	$subdirs = &$dir['subdirs'];
	$dirinfo = &$dir['info'];

	if ($category) {
		if ($subdir_num > 0) {
			$full_page_split = false;

			# 0 = none, 1 = pages, 2 = alphabet, 3 = full_page_split
			if ($cat['split'] == 2 || $cat['split'] == 3) {
				zina_directory_sort($subdirs);
				$sort_ignore_str = ($zc['dir_sort_ignore']) ? $zc['dir_si_str'] : false;
				$splits = ztheme('category_alphabet_split', $subdirs, $sort_ignore_str);

				if ($zc['cat_various_lookahead'] || $cat['split'] == 3) {
					$count = 0;
					foreach ($splits as $subkey => $i) {
						foreach($i as $itemkey => $item) {
							if (isset($item['category']) && !empty($item['category'])) {
								$cat_dir_tags = (isset($item['category']['override']['dir_tags'])) ? $item['category']['override']['dir_tags'] : $zc['dir_tags'];
								$v = zina_get_directory($item['path'], true, array('get_files'=>false, 'dir_tags'=>$cat_dir_tags));
								$letter = ($subkey == 'zzz') ? $item['title']: $item['path'];

								if (!empty($v['subdirs'])) {
									zina_directory_sort($v['subdirs']);

									foreach($v['subdirs'] as $various_subkey => $vdir) {
										$splits[$letter][$various_subkey] = $vdir;
										$count++;
									}
									if ($subkey == 'zzz') unset($splits[$subkey][$itemkey]);
								} else {
									$count++;
								}
							} else {
								$count++;
							}
						}
					}
				}
				$splits_sum = array_keys($splits);

				if ($cat['split'] == 2) {
					if (!in_array($page, $splits_sum)) $page = $splits_sum[0];
					$zina['category']['navigation'] = ztheme('category_alphabet',$path, $page, $splits_sum);
					$subdirs = $splits[$page];
				} else { # $cat['split'] == 3
					$zina['category']['navigation'] = ztheme('category_alphabet',$path, null, $splits_sum, null, true);
					$full_page_split = $count;
					$subdirs = &$splits;
				}
			} else {
				#todo: won't always be cat sort? or what if catsort == 0
				if ($cat['sort'] && $subdir_num > 1) {
					if ($category_sort == 'ad') { # alpha desc
						zina_directory_sort($subdirs);
						$subdirs = array_reverse($subdirs, true);
						$cat['alpha'] = array('sort'=>'asc','query'=>'zs=a');
					} elseif ($category_sort == 'd') { #date asc
						uasort($subdirs, 'zsort_date');
						$cat['date'] = array('sort'=>'desc','query'=>'zs=dd');
					} elseif ($category_sort == 'dd') { #date desc
						uasort($subdirs, 'zsort_date_desc');
						$cat['date'] = array('sort'=>'asc','query'=>'zs=d');
					} else { # alpha asc
						$category_sort = 'a';
						zina_directory_sort($subdirs);
						$cat['alpha']=array('sort'=>'desc','query'=>'zs=ad');
					}

					if (!empty($cat['alpha'])) {
						$cat['date']=array('sort'=>'desc','query'=>'zs=dd');
					} elseif (!empty($cat['date'])) {
						$cat['alpha']=array('sort'=>'asc','query'=>'zs=a');
					}
					$zina['category']['sort'] = $t_sort = ztheme('category_sort',$path, $cat);
				} else {
					zina_directory_sort($subdirs);
					$zina['category']['sort'] = '';
				}

				if ($cat['split'] && $subdir_num > $cat['pp']) {
					$pages_total = ceil($subdir_num/$cat['pp']);
					if (!zina_validate('int', $page) || $page < 1 || $page > $pages_total) $page = 1;
					$zina['category']['navigation'] = ztheme('category_pages',$path, $page, $pages_total);
					$cstart = ($page - 1) * $cat['pp'];
					if ($cstart > $subdir_num) $cstart = 0;

					$subdirs = array_slice($subdirs, $cstart, $cat['pp']);
				}
			}

			if ($cat['images']) {
				if ($cat['split'] == 3) {
					foreach($subdirs as $key => $val) zina_get_dir_list($subdirs[$key], false);
				} else {
					zina_get_dir_list($subdirs, false);
				}
			}
			$zina['category']['content'] = ztheme('category', $subdirs, $cat['cols'], $cat['images'], $cat['truncate'], $full_page_split);
		}
	} else { # Not Category
		$zina['dir_list'] = $zc['dir_list'];
		$zina['subdir_images'] = $zc['subdir_images'];
		$list = false;
		if ($subdir_num > 0 && ($zc['dir_list'] || $zc['subdir_images'])) {
			$list = true;
			if (!$zc['dir_list_sort']) {
				($zc['dir_sort_ignore']) ? uksort($subdirs, 'zsort_ignore') : uksort($subdirs, 'strnatcasecmp');
			}
			zina_get_dir_list($subdirs);
			$zina['subdirs'] = $subdirs;
		} else {
			if ($dir['dir_write']) {
				$zina['dir_opts']['delete'] = array('path'=>$path, 'query'=>'l=76');
			}
		}

		$zina['dir_image'] = ztheme('images', 'dir', $zina, $path, $dir['images'], 'l=46', $dir['image'], $dir['captions']);
		$zina['dir_image_sub'] = ztheme('image',zina_get_image_url($path, (isset($dir['images'][0])?$dir['images'][0]:$dir['images']), 'sub'), $zina['title'], $zina['title']);

		$alt_num = 0;
		if ($zc['alt_dirs']) {
			$zina['alt_items'] = array();
			if (isset($dir['alt_items'])) {
				$alts = $dir['alt_items'];
				$list = true;
			} else {
				$alt_file = $zc['cur_dir'].'/'.$zc['alt_file'];
				if (file_exists($alt_file)) {
					$alts = zunserialize_alt(file_get_contents($alt_file));
					$list = true;
				}
			}
			if (!empty($alts)) {
				$alt_num = count($alts);
				$alts_db = array();
				if ($zc['database']) {
					$alts_db = zdbq_assoc_list("SELECT path, other FROM {dirs} WHERE path IN (".substr(str_repeat(",'%s'", $alt_num),1).")", $alts);
				}

				foreach($alts as $alt) {
					# backwards compatibility...strip /
					$alt = rtrim($alt, '/');
					$alt_dirs[$alt] = array('path'=>$alt,'new'=>false, 'lofi'=>1);
					$alt_img = false;
					if (isset($alts_db[$alt])) {
						$temp = unserialize_utf8($alts_db[$alt]);
						if (isset($temp['image']) && $temp['image']) $alt_img = $temp['image'];
					}
					if (!$alt_img) $alt_img = zina_get_dir_item($zc['mp3_dir'].'/'.$alt,'/\.('.$zc['ext_graphic'].')$/i'); 
					$alt_dirs[$alt]['image'] = $alt_img;
					
					if ($zc['low_lookahead']) { $alt_dirs[$alt]['lofi'] = zina_get_dir_item($zc['mp3_dir'].'/'.$alt,'/('.$zc['low_suf'].')\.('.$zc['ext_mus'].')$/i'); }
					if (empty($alt)) $alt_dirs[$alt]['title'] = $zc['main_dir_title'];
				}

				zina_get_dir_list($alt_dirs);
				$zina['alt_items'] = $alt_dirs;
			}

			if ($dir['dir_edit']) $zina['alt_list_edit']['query'] = 'l=22&amp;m=2';
		}

		$zina['alt_num'] = $alt_num;
		$zina['pls_included'] = false;

		if ($zc['playlists'] && $zc['database'] && $zc['pls_included'] && isset($dir['id'])) {
			$where = (!$zc['is_admin']) ? (($zc['pls_public']) ? 'AND (p.user_id = %d OR p.visible = 1)' : 'AND p.user_id = %d') : '';

			$dir_playlists = zdbq_array("SELECT p.id, p.title, NULL as path, CONCAT('l=2&amp;pl=',p.id) as query, FALSE as new ".
				"FROM {playlists_map} as pm ".
				"INNER JOIN {playlists} as p ON (pm.playlist_id = p.id) ".
				"LEFT OUTER JOIN {files} AS f ON pm.type = 'song' AND pm.type_id = f.id ".
				"LEFT OUTER JOIN {dirs} AS d ON pm.type = 'album' AND pm.type_id = d.id ".
				"WHERE (d.id = %d OR f.dir_id = %d) $where ".
				"GROUP BY p.id ".
				"ORDER BY p.sum_rating DESC, p.title LIMIT ".$zc['pls_included_limit'], array($dir['id'], $dir['id'], $zc['user_id']));
			if (!empty($dir_playlists)) {
				foreach ($dir_playlists as $key => $pls) {
					$item = &$dir_playlists[$key];
					$item['opts']['play'] = array('path'=>null, 'query'=>'l=8&amp;m=3&amp;pl='.$item['id']);
				}
				$zina['pls_included']['items'] = $dir_playlists;
				if (sizeof($dir_playlists) >= $zc['pls_included_limit']) {
					$zina['pls_included']['more'] = array('path'=>null, 'query'=>'l=2&m='.$dir['id']);
				}
			}
		}

		if ($list && $zc['playlists']) {
			$form_id = 'zinadirsform';
			$type = ($subdir_num + $alt_num > 0) ? 'l a x p r q v' : 'l x p r q v';
			$zina['list_form'] = zina_content_playlist_form_opts($form_id, $zina['title'], $type);
			$zina['list_form_opts'] = 'id="'.$form_id.'" action="'.zurl($path).'"';
		} else {
			$zina['list_form'] = $zina['list_form_opts'] = '';
		}

		if ($zc['database']) {
			if ($zc['rating_dirs']) {
				$user_rating = ($zc['user_id'] > 0) ? zdb_get_dir_user_rating($path, $zc['user_id']) : 0;
				$zina['dir_rate'] = ztheme('vote', zina_get_vote_url($path), $user_rating);
			}

			if ($zc['rating_dirs_public'] || $zc['is_admin']) {
				$zina['dir_rating']['sum_views'] = $dir['sum_views'];
				$zina['dir_rating']['sum_votes'] = $dir['sum_votes'];
				$zina['dir_rating']['sum_rating'] = $dir['sum_rating'];
			}
		}
	} #NOT CAT

	if ($files_found) {
		$lofi = ($zc['low'] && ($zc['resample'] || $dir['lofi']));
		$custom_pls = ($zc['cache'] && file_exists($zc['cache_pls_dir'].'/'.str_replace('/',' - ',$path).'.m3u'));

		# Dir Play
		if ($zc['play']) {
			$zina['dir_opts']['play'] = array('path'=>$path, 'query'=>'l=8&amp;m=0', 'class'=>'zinamp');

			if ($custom_pls) {
				$zina['dir_opts']['play_custom'] = array('path'=>$path, 'query'=>'l=8&amp;m=0&amp;c');
			}

			# Recursive Play & Random
			if ($zc['play_rec'] && $subdir_num > 1) {
				$zina['dir_opts']['play_rec'] = array('path'=>$path, 'query'=>'l=8&amp;m=10');
			}
			if ($zc['play_rec_rand'] && $subdir_num > 0) {
				$zina['dir_opts']['play_rec_rand'] = array('path'=>$path, 'query'=>'l=8&amp;m=10&amp;c');
			}
		}

		if ($lofi) {
			$zina['dir_opts']['play_lofi'] = array('path'=>$path, 'query'=>'l=8&amp;m=0&amp;lf');
			if ($custom_pls) {
				$zina['dir_opts']['play_lofi_custom'] = array('path'=>$path, 'query'=>'l=8&amp;m=0&amp;c&amp;lf');
			}
		}
		if ($zc['cmp_sel']) {
			$zina['dir_opts']['download'] = array('path'=>$path.'/.lp', 'query'=>'l=5');
			if ($custom_pls) {
				$zina['dir_opts']['download_custom'] = array('path'=>$path.'/.lp', 'query'=>'l=5&amp;c');
			}
		}

		#todo: this is done twice
		$key = key($dir['files']);
		$dir_year = (isset($dir['year'])) ? $dir['year'] : ((isset($subdirs[$key]['info']->year)) ? $subdirs[$key]['info']->year : false);
		$zina['dir_year'] = ($dir_year && $zc['db_search']) ? zl($dir_year,$path,'l=69&amp;pl='.rawurlencode($dir_year)) : $dir_year;
		$dir_genre = ($zc['genres'] && isset($dir['genre'])) ? $dir['genre'] : ((isset($subdirs[$key]['info']->genre)) ? $subdirs[$key]['info']->genre : false);
		$zina['dir_genre'] = ($dir_genre) ? zl($dir_genre,$path,'l=13&amp;pl='.rawurlencode($dir_genre)) : $dir_genre;

		if ($zc['files_sort'] == 1) {
			krsort($dir['files']);
		} elseif ($zc['files_sort'] == 2) {
			usort($dir['files'],'zsort_date');
		} elseif ($zc['files_sort'] == 3) {
			usort($dir['files'],'zsort_date_desc');
		} elseif ($zc['files_sort'] == 4) {
			usort($dir['files'],'zsort_trackno');
		}

		$songrow = ztheme('song_list', $dir['files'], $dir['various']);
		$check_boxes = (($zc['is_admin'] && $zc['cache']) || ($zc['playlists'] && $zc['session_pls']) || $zc['play_sel'] || $zc['cmp_sel']);

		$form_id = 'zinasongsform';
		$form_attr = 'id="'.$form_id.'" action="'.zurl($path).'"';
		$songopts = ztheme('song_list_opts', zina_content_song_list_opts($custom_pls, $lofi, $check_boxes,$form_id),$form_id);
		$zina['songs'] = ztheme('song_section',$form_attr, $songrow, $songopts);

		if ($zc['rss']) {
			if ($zc['clean_urls']) {
				$zina['podcast']['url'] = (empty($path)) ? $zc['rss_file'] : $path.'/'.$zc['rss_file'];
				$zina['podcast']['query'] = null;;
			} else {
				$zina['podcast']['url'] = $path;
				$zina['podcast']['query'] = 'l=50';
			}
			$zina['podcast']['type'] = ($zc['rss_podcast']) ? 'podcast' : 'rss';
			zina_set_html_head('<link rel="alternate" type="application/rss+xml" title="'.zt('!title Podcast', array('!title'=>$zina['title'])).'" href="'.zurl($zina['podcast']['url'], $zina['podcast']['query']).'"/>');

			if ($dir['dir_edit'] && $zc['is_admin']) {
				$zina['dir_edit_opts']['podcast'] = array('path'=>$path, 'query'=>'l=22&amp;m=5');
			}
		}
	} else { #NO FILES FOUND
		if($zc['dir_genre_look'] && $subdir_num > 0) {
			if (isset($dir['genre'])) {
				$dir_genre = $dir['genre'];
			} else {
				$keys = array_keys($subdirs);
				$key = $keys[0];
				$dir_genre = (isset($subdirs[$key]['info']->genre)) ? $subdirs[$key]['info']->genre : false;
			}
			$zina['dir_genre'] = ($dir_genre) ? zl($dir_genre,$path,'l=13&amp;pl='.rawurlencode($dir_genre)) : $dir_genre;
		}

		# Recursive Play & Random
		if ($zc['play'] && $zc['play_rec'] && $subdir_num > 1) {
			$zina['dir_opts']['play_rec'] = array('path'=>$path, 'query'=>'l=8&amp;m=10');
		}
		if ($zc['play'] && $zc['play_rec_rand'] && $subdir_num > 0) {
			$zina['dir_opts']['play_rec_rand'] = array('path'=>$path, 'query'=>'l=8&amp;m=10&amp;c');
		}
	}

	if ($zc['is_admin']) {
	  	$zina['dir_edit_opts']['images'] = array('path'=>$path, 'query'=>'l=58');
		if ($dir['dir_edit'] && $files_found) $zina['dir_edit_opts']['tags'] = array('path'=>$path, 'query'=>'l=71');
	}

	if ($zc['mm'] && !empty($dir['mm'])) $zina['multimedia'] = $dir['mm'];
	$zina['subdir_truncate'] = $zc['subdir_truncate'];
	$zina['subdir_cols'] = $zc['subdir_cols'];
	$zina['page_main'] = true;
	$zina['content'] = ztheme('page_main', $zina);
}

function zina_content_breadcrumb(&$zina, $path, $alt_title = null, $full = false) {
	$zina['path'] = $path;

	if (!empty($path)) {
		$zina['breadcrumb'] = zina_get_breadcrumb($path, $alt_title, $full);
		$dir_current = zina_get_current_dir();
		$title = ztheme('page_title',zina_get_page_title());
	} else {
		$title = $dir_current = $alt_title;
		$zina['breadcrumb'] = zina_get_breadcrumb($alt_title, null, $full);
	}
	$zina['html_title'] = zcheck_utf8($dir_current);
	$zina['title_raw'] = zdecode_entities($dir_current);
	$zina['title'] = $title;
}

function zina_content_search_list(&$results, $checkbox, $opts = array(), $extras = array()) {
	global $zc;
	if (!empty($results)) {
		$ratings = (isset($opts['rating'])) ? $opts['rating'] : ($zc['rating_files'] && $zc['db_search']);
		$images = (isset($opts['images'])) ? $opts['images'] : $zc['search_images'];
		$types = (isset($opts['types'])) ? $opts['types'] : true;
		$genres = (isset($opts['genres'])) ? $opts['genres'] : $zc['genres'];
		$years = (isset($opts['years'])) ? $opts['years'] : true;

		foreach($results as $key=>$item) {
			$play = $zc['play'];
			$download = $zc['download'];
			$rem_title = false;
			$result = &$results[$key];

			$result['new'] = false;
			$result['image'] = (isset($item['image'])) ? $item['image'] : false;
			$result['ratings'] = $ratings;
			if (isset($result['title']) && isset($opts['highlight'])) {
				$result['alt'] = $result['title'];
				$result['title'] = preg_replace("|(".preg_quote($opts['highlight']).")|i",'<span class="ac_match_results">$1</span>',$result['title']);
			}
			#TODO: test ratings w/o DB

			if ($item['type'] == 'song') {
				if ($checkbox) {
					$result['checkbox'] = array('name'=>'mp3s[]', 'value'=>zrawurlencode($item['path']), 'checked'=>false);
				} else {
					$result['checkbox'] = false;
				}

				if (!isset($result['title'])) {
					if (isset($item['id3_info'])) {
						$mp3 = unserialize_utf8($item['id3_info']);
						if (isset($mp3->title)) $result['title'] = ztheme('song_title', $mp3->title, true);
					}
					if (!isset($result['title'])) {
						$result['title'] = zina_content_song_title($item['path']);
					}
				}

				$desc_opts = array();
				if ($types && !empty($item['type'])) $desc_opts['type'] = zt(ucfirst($result['type']));
				if ($years && isset($item['year']) && !empty($item['year'])) $desc_opts['year'] = zl($item['year'],'','l=69&amp;pl='.rawurlencode($item['year']));
				if ($genres && isset($item['genre'])) $desc_opts['genre'] = zl($item['genre'],'','l=13&amp;pl='.rawurlencode($item['genre']));

				#todo: put in "description" if exists?
				$result['description'] = ztheme('search_description', zina_content_pathlinks(dirname($item['path']), 't', false, $rem_title), $desc_opts);
				$dir_path = dirname($item['path']);
				$title_link = (zvalidate_utf8($dir_path)) ? utf8_decode($dir_path) : $dir_path;
				$result['image_link'] = array('path'=>$title_link, 'query'=>null);
				if ($play) {
					$title_link = (zvalidate_utf8($item['path'])) ? utf8_decode($item['path']) : $item['path'];
					$result['title_link'] = $result['opts']['play'] = array('path'=>$title_link, 'query'=>'l=8&amp;m=1', 'attr'=>' class="zinamp"');
				} else {
					$result['title_link'] = array('path'=>$title_link, 'query'=>null);
				}
				if ($download) $result['opts']['download'] = array('path'=>$item['path'], 'query'=>'l=12');
				if (isset($item['id3_info']))	$result['info'] = unserialize_utf8($item['id3_info']);
			} elseif ($item['type'] == 'genre') {
				$result['description'] = ($types) ? ucfirst($item['type']) : '';
				$result['checkbox'] = false;
				if ($play) {
					$result['opts']['play'] = array('path'=>null, 'query'=>'l=8&amp;m=5&amp;pl='.rawurlencode($item['path']));
				}
				$result['image_link'] = $result['title_link'] = array('path'=>null, 'query'=>'l=13&pl='.rawurlencode($item['path']));

			} elseif (in_array($item['type'], array('artist','album','directory'))) {
				zina_content_subdir_opts($result, $item['path'], $checkbox, NULL);
				if (!isset($result['title'])) {
					if ($zc['dir_tags'] && isset($item['id3_info']) && !empty($item['id3_info'])) {
						$title_path = $item['id3_info'];
					} else {
						$title_path = (substr_count($item['path'], '/') > 0) ? substr($item['path'], strrpos($item['path'],'/')+1) : $item['path'];
						if (zvalidate_utf8($title_path)) $title_path = utf8_decode($title_path);
					}
					$result['title'] = ztheme('title', $title_path);
				}
				#todo: redudant with songs
				$desc_opts = array();
				if ($types && !empty($item['type'])) $desc_opts['type'] = zt(ucfirst($result['type']));
				if ($years && isset($item['year']) && !empty($item['year'])) $desc_opts['year'] = zl($item['year'],'','l=69&amp;pl='.rawurlencode($item['year']));
				if ($genres && isset($item['genre'])) $desc_opts['genre'] = zl($item['genre'],'','l=13&amp;pl='.rawurlencode($item['genre']));

				$title_link = (zvalidate_utf8($item['path'])) ? utf8_decode($item['path']) : $item['path'];
				$result['image_link'] = $result['title_link'] = array('path'=>$title_link, 'query'=>null);

				if ($item['type'] == 'album') {
					$result['description'] = ztheme('search_description', zina_content_pathlinks(dirname($item['path']), 't', false, $rem_title), $desc_opts);
				} else {
					if ($item['type'] == 'artist') unset($desc_opts['year']);
					$result['description'] = ztheme('search_description', '', $desc_opts);
					#todo: have '/.art' so artists can be added to playlists...?
					$result['checkbox'] = false;
				}
			} elseif ($item['type'] == 'playlist') {
				if (isset($item['description'])) {
					$result['description'] = zina_url_filter($item['description']);
				} else {
					$result['description'] = ($types) ? ucfirst($item['type']) : '';
				}

				# SEARCH RESULTS
				if ($images && !$result['image']) {
					if (isset($item['image_type']) || !empty($item['image_type'])) {
						if ($item['image_type'] == 1 && isset($item['image_path'])) {
							$tmp = $item['id'];
							$item['id'] = $item['path'];
							$item['path'] = $item['image_path'];
							$item['type'] = 'artist';
							$result['image'] = zina_content_search_image($item, 'search');
							$item['type'] = 'playlist';
							$item['path'] = $item['id'];
							$item['id'] = $tmp;
						}
					}
				}
				$pls = ($zc['database'] && isset($item['id'])) ? $item['id'] : $item['path'];

				if ($zc['database'] && $item['id'] != 'zina_session_playlist') {
					$result['checkbox'] = array('name'=>'mp3s[]', 'value'=>zrawurlencode($item['id']).'.pls', 'checked'=>false);
				} else {
					$result['checkbox'] = false;
				}
				if ($play) {
					$result['opts']['play'] = array('path'=>null, 'query'=>'l=8&amp;m=3&amp;pl='.rawurlencode($pls));
				}
				$result['image_link'] = $result['title_link'] = array('path'=>null, 'query'=>'l=2&pl='.rawurlencode($pls));
			} else {
				$result['description'] = null;
				$result['checkbox'] = false;
			}

			if ($images && !$result['image']) {
				$result['image'] = zina_content_search_image($result, 'search');
			}

			foreach($extras as $key=>$extra) { $result[$key] = $extra; }
		}
	}
}

# genre & year search
function zina_content_search($type, &$zina) {
	global $zc;
	$search_opts = false;
	$count = 0;
	$results = array();
	$selected = false;

	$term = isset($_POST['pl']) ? $_POST['pl'] : (isset($_GET['pl']) ? $_GET['pl'] : null);

	$checkbox = ($zc['playlists'] && (($zc['is_admin'] && $zc['cache']) || $zc['session_pls'] || $zc['pls_public'] || ($zc['pls_user'] && $zc['user_id'] > 0)));

	if ($type == 'year') {
		$query = 'l=69';
		$qp = '6';
		$sql = array('where'=>"i.year = %d", 'orderby'=>"i.title");
		$items = zdbq_array_list("SELECT DISTINCT year, year FROM {dirs} WHERE year IS NOT NULL ORDER BY year DESC");
		$label = zt('Year');
	} else {
		$type = 'genres';
		$query = 'l=13';
		$qp = '5';
		$sql = array('where'=>"i.genre = '%s'", 'orderby'=>"i.title");
		$items = zina_core_cache('genres');

		$label = false;
		if (!$zc['db_search']) $sql = array();
	}

	if (!in_array($term, $items)) {
		zina_set_message(zt('Unknown term: @term', array('@term'=>$term)));
		$bad_query = ($type == 'year') ? '' : 'l=14';
		zina_goto('', $bad_query);
	}

	$navigation = ztheme($type.'_form', $zc['genres'], zurl('',$query), $items, $term, $label);
	$zina['html_title'] = $zina['title'] = htmlentities($term);

	$results = zina_search_pager_query($term, $query, $count, $search_opts, $sql, ($type == 'genres'), $selected);

	if (!empty($results)) {
		zina_content_breadcrumb($zina, '', $term);

		if ($zc['genres_images']) {
			$genre_image = ztheme('image', zina_get_genre_image_path($term), $term, null, 'class="genre-image"');
		} else {
			$genre_image = false;
		}
	} else {
		zina_content_breadcrumb($zina, '', zt('Search Results'));
	}

	zina_content_search_list($results, $checkbox, array('highlight'=>$term, 'genres'=>($zc['genres'] && $type != 'genres'), 'years'=>($zc['db_search'] && $type != 'year')));
	$list = ztheme('search_list', $results, $zc['search_images']);

	$form_id = 'zinasearchresultsform';
	$form_attr = 'id="'.$form_id.'" action="'.zurl('','m=1').'"';
	$list_opts = ($zc['playlists']) ? ztheme('search_list_opts', zina_content_song_list_opts(false,  false, $checkbox, $form_id, true), $form_id) : null;

	$opts['image'] = $zc['genres_images'];
	$opts['play_query'] = 'l=8&amp;m='.$qp.'&amp;pl='.rawurlencode($term);

	$opts['genre_edit'] = $opts['description'] = false;

	if ($type == 'genres' && $zc['database']) {
		$page = zdbq_array_single("SELECT * FROM {genres} as g INNER JOIN {genre_tree} as gt ON g.id=gt.id ".
			"WHERE g.genre = '%s'", array($term));
		if ($page['pid'] != 0) {
			$crumbs = explode('/', $page['path']);
			array_pop($crumbs);
			$gs = zdbq_assoc_list("SELECT id, genre FROM {genres} WHERE id IN (".implode(',', $crumbs).")");
			foreach($crumbs as $crumb) {
				$links[] = zl($gs[$crumb],null, 'l=13&pl='.zrawurlencode($gs[$crumb]));
			}
			$zina['breadcrumb'] = zina_get_breadcrumb(null, null, false, $links);
		}
		$children = zdbq_array_list("SELECT genre FROM {genres} AS g INNER JOIN {genre_tree} AS gt ON g.id=gt.id ".
			"WHERE g.pid = %d ORDER BY weight", array($page['id']));

		if (!empty($children)) {
			$children = zina_get_genres_list($children, 'genresearch');
			$zina['subgenres'] = ztheme('genres', $children, $zc['genres_cols']*2, $zc['genres_images'], $zc['genres_truncate'], $zina, false, false);
		}

		if ($zc['is_admin'] && $zc['database']) {
			if (!empty($page['description'])) $opts['description'] = zina_url_filter(nl2br($page['description']));
			$opts['genre_edit'] = array('path'=>'', 'query'=>'l=22&amp;m=6&amp;pl='.rawurlencode($term));
		}
	}
	$zina['search_page_type'] = $type;

	$zina['content'] = ztheme($type.'_page', $term, $results, $count, $search_opts, $navigation, $form_attr, $list, $list_opts, $opts, zurl('', $query.'&amp;pl='.zrawurlencode($term)), $selected, $zina);
}

function zina_content_search_image($item, $size = 'sub', $class="search-image", $absolute = false) {
	global $zc;

	# some of these utf8s can be removed in 3.0 when db is installed as utf-8
	if (isset($item['path']) && zvalidate_utf8($item['path'])) $item['path'] = utf8_decode($item['path']);

	$image = $img_url = null;
	if ($item['type'] == 'song') {
		$img_path = dirname($item['path']);
		$img = zina_get_dir_item($zc['mp3_dir'].'/'.$img_path,'/\.('.$zc['ext_graphic'].')$/i');
		$img_url = zina_get_image_url($img_path, $img, $size, $absolute);
	} elseif (in_array($item['type'], array('artist','album','directory'))) {
		$img_path = $item['path'];
		$img = zina_get_dir_item($zc['mp3_dir'].'/'.$img_path,'/\.('.$zc['ext_graphic'].')$/i');
		$img_url = zina_get_image_url($img_path, $img, $size, $absolute);
	} elseif (($item['type'] == 'genre' || $item['type'] == 'genresearch') && $zc['genres_images']) {
		#todo: absolute
		#$img_url = zina_get_genre_image_path($item['path'], 'genresearch');
		if ($size != 'genre') $size = 'genresearch';
		$img_url = zina_get_genre_image_path($item['path'], $size);
	} elseif ($item['type'] == 'playlist') {
		# SEARCH BOX
		if (isset($item['image_type'])) {
			if ($item['image_type'] == 1) {
				$item['type'] = 'artist';
				$item['path'] = $item['image_path'];
				return zina_content_search_image($item, $size, $class, $absolute);
			} elseif ($item['image_type'] == 2) {
				$item['type'] = ($size == 'search') ? 'genresearch' : 'genre';
				$item['path'] = $item['genre'];
				$item['title'] = $item['genre'];
				return zina_content_search_image($item, $item['type'], $class, $absolute);
			} else {
				$size = ($size == 'search') ? 'plssearch' : 'pls';
				if ($zc['res_genre_img']) {
					$img_url = zurl(null,'l=7&amp;it='.$size.'&amp;img='.rawurlencode($item['title']), null, $absolute);
				}
			}
		} else {
			zina_debug(zt('No playlist image info'));
		}
	}
	if (!empty($img_url)) {
		$alt = (isset($item['alt'])) ? $item['alt'] : $item['title'];
		$image = ztheme('image', $img_url, $alt, null, 'class="'.$class.'"');
	}
	return $image;
}

function zina_directory_sort(&$subdirs) {
	global $zc;
	if ($zc['dir_tags']) {
		($zc['dir_sort_ignore']) ? usort($subdirs, 'zsort_title_ignore') : usort($subdirs, 'zsort_title');
	} else {
		($zc['dir_sort_ignore']) ? uksort($subdirs, 'zsort_ignore') : uksort($subdirs, 'strnatcasecmp');
	}
}

function zina_playlist_insert($pls_id, $items, $start) {
	global $zc;

	$weight = $start;
	$error = false;

	foreach($items as $item) {
		if ($weight > $zc['pls_length_limit']) {
			zina_set_message(zt('You cannot have more than @num items per playlist.', array('@num'=>$zc['pls_length_limit'])));
			$error = true;
			break;
		}

		$item = zrawurldecode($item);

		if (preg_match('/\.('.$zc['ext_mus'].')$/i', $item) || ($zc['remote'] && preg_match('/\.('.$zc['remote_ext'].')$/i', $item))) {
			$type = 'song';
			if (substr_count($item, '/') > 0) {
				$pos = strrpos($item,'/');
				$file_name = substr($item, $pos+1);
				$file_path = substr($item, 0, $pos);
			} else {
				$file_name = $item;
				$file_path = '';
			}
			if ($file_path == '.') $file_path = '';
			$type_id = zdbq_single("SELECT id FROM {files} WHERE path = '%s' AND file = '%s'", array($file_path, $file_name));
			if (empty($type_id)) {
				if (zfile_check_location($zc['mp3_dir'].'/'.$file_path.'/'.$file_name, $zc['mp3_dir'])) {
					zdb_log_stat('insertonly', $file_path, $file_name);
					$type_id = zdbq_single("SELECT id FROM {files} WHERE path = '%s' AND file = '%s'", array($file_path, $file_name));
				}
			}
		} elseif (preg_match('/\.lp$/i', $item)) {
			$type = 'album';
			$file_path = preg_replace('/\/\.lp$/i','',$item);
			$type_id = zdbq_single("SELECT id FROM {dirs} WHERE path = '%s'", array($file_path));
			if (empty($type_id)) {
				if (zfile_check_location($zc['mp3_dir'].'/'.$file_path, $zc['mp3_dir'])) {
					zdb_log_stat('insertonly', $file_path);
					$type_id = zdbq_single("SELECT id FROM {dirs} WHERE path = '%s'", array($file_path));
				}
			}
		} elseif (preg_match('/\.pls$/i', $item)) {
			$type = 'playlist';
			$pls_id_add = preg_replace('/\.pls/i','',$item);
			$type_id = zdbq_single("SELECT id FROM {playlists} WHERE id = %d", array($pls_id_add));
		} else {
			$type_id = null;
		}

		if (!empty($type_id)) {
			if ($pls_id == 'zina_session_playlist') {
				#$item[] = array('type'=> $type, 'type_id' => $type_id, 'weight'=>$weight);
				$pls_items = array();
				if ($type == 'album') {
					$type_id .= '/.lp';
				} elseif ($type == 'playlist') {
					$type_id .= '.pls';
				}
				$pls_items[] = $type_id;
				$existing = (isset($_SESSION['z_sp'])) ? unserialize_utf8($_SESSION['z_sp']) : array();
				$_SESSION['z_sp'] = utf8_encode(serialize(array_merge($existing, $pls_items)));
			} else {
				if (!zdbq("INSERT {playlists_map} (playlist_id, type, type_id, weight) VALUES (%d, '%s', %d, %d)",
					array($pls_id, $type, $type_id, $weight))) {
					zina_set_message(zt('Could not insert into playlist: @file', array('@file'=>$item)));
					$error = true;
				}
			}
		} else {
			zina_set_message(zt('Could not find item[@pls]: @item', array('@pls'=>$pls_id, '@item'=>$item)));
			$error = true;
		}
		$weight++;
	}
	if (($sum_items = zdbq_single("SELECT COUNT(*) FROM {playlists_map} WHERE playlist_id = %d", array($pls_id))) !== false) {
		zdbq("UPDATE {playlists} SET sum_items = %d WHERE id = %d", array($sum_items, $pls_id));
	}
	return (!$error);
}

function zina_playlists_list_helper(&$playlists, $editable = true, $checkbox = false) {
	global $zc;

	foreach($playlists as $key => $playlist) {
		$item = &$playlists[$key];
		$pl = $playlist['id'];
		$edit = ($editable && ($pl == 'zina_session_playlist' || zina_cms_access('edit_playlists', $playlist['user_id'])));

		if ($zc['play']) {
			$item['opts']['play'] = array('path'=>null, 'query'=>'l=8&amp;m=3&amp;pl='.$pl);
			if ($zc['play_rec_rand'])
				$item['opts']['play_rec_rand'] = array('path'=>null, 'query'=>'l=8&amp;m=3&amp;pl='.$pl.'&amp;rand');
		}
		if ($edit && $pl != 'zina_session_playlist') {
			$item['options'][] = zl(zt("Edit"), null, 'l=44&amp;pl='.$pl);
			$item['options'][] = zl(zt("Delete"), null, 'l=43&amp;pl='.$pl);
		}

		$item['image_link'] = $item['title_link'] = array('path' => null, 'query'=>'l=2&amp;pl='.$pl);
		$item['title'] = htmlentities($playlist['playlist']);
		$item['image'] = false;

		$desc_opts = array();
		if (!empty($item['date_created'])) $desc_opts['date_created'] = $item['date_created'];
		$user = zina_cms_user($item['user_id']);
		if ($user) {
			if ($user['profile_url']) $desc_opts['profile_url'] = $user['profile_url'];
			$desc_opts['username'] = $user['name'];
		}
		if ($zc['genres'] && !empty($item['genre'])) $desc_opts['genre'] = zl($item['genre'],'','l=13&amp;pl='.rawurlencode($item['genre']));
		$stats = array();
		if (isset($item['sum_items']) && !empty($item['sum_items'])) $stats['sum_items'] = $item['sum_items'];
		if (isset($item['sum_views']) && !empty($item['sum_views'])) $stats['sum_views'] = $item['sum_views'];
		if (isset($item['sum_plays']) && !empty($item['sum_plays'])) $stats['sum_plays'] = $item['sum_plays'];

		$item['description'] = ztheme('playlist_description', $item['description'], $desc_opts, $stats, $item);
		$item['new'] = $item['ratings'] = $item['checkbox'] = false;

		# PLAYLIST LIST PAGE
		$item['type'] = 'playlist';
		$item['image'] = zina_content_search_image($item, 'search');
		$item['ratings'] = $zc['pls_ratings'];

		if ($checkbox && $pl != 'zina_session_playlist') {
			$item['checkbox'] = array('name'=>'mp3s[]', 'value'=>$pl.'.pls', 'checked'=>false);
		}
	}
}

function zina_content_playlists_db(&$zina, $id = null) {
	global $zc;

	#TODO: need to verify pls access crap throughout
	if (!($zc['playlists'] && ($zc['is_admin'] || $zc['pls_public'] || ($zc['pls_user'] && $zc['user_id'] > 0)))) return zina_access_denied();

	$zina['title'] = zt('Playlists');
	$count = 0;
	$navigation = false;
	$selected = array();

	$page = (isset($_GET['page'])) ? '&amp;page='.$_GET['page'] : '';
	$query = 'l=2'.$page;
	if (!empty($id)) {
		$query.='&amp;m='.$id;
		$path = zdbq_single("SELECT path FROM {dirs} WHERE id = %d", array($id));
		if (empty($path)) {
			zina_set_message(zt('Sorry, there are no playlists'));
			$zina['content'] = '';
			return;
		}
		$title = ztheme('title',$path);
		$title = zt('@album Tracks Appear on These Playlists', array('@album'=>$title));
		#TEST
		zina_content_breadcrumb($zina, '', $title, true);
	}
	$playlists = zina_playlist_pager_query($query, $count, $navigation, $selected, $id);

	if ($zc['session_pls']) {
		if (!isset($_GET['page']) || $_GET['page'] == 1) {
			$results = (isset($_SESSION['z_sp'])) ? unserialize_utf8($_SESSION['z_sp']) : array();
			$zsp = array('id'=>'zina_session_playlist','image_type'=>0,'playlist'=>zt('Session Playlist'),'description'=>'',
				'user_id' => 0, 'date_created'=>null, 'sum_items'=>sizeof($results), 'sum_votes'=>0,'sum_rating'=>0);
			if (empty($playlists)) {
				$playlists['zina_session_playlist'] = $zsp;
			} else {
				array_unshift($playlists, $zsp);
			}
		}
		$count++;
	}

	if (!empty($playlists)) {
		$checkbox = true;
		zina_playlists_list_helper($playlists, true, $checkbox);
		$form_id = 'pls-form';
		$list_opts = ztheme('search_list_opts', zina_content_song_list_opts(false, false, $checkbox, $form_id, true), $form_id);
		$form_attr = 'id="'.$form_id.'" action="'.zurl('', 'l=2').'"';

		$zina['content'] = ztheme('playlists_list_db', $playlists, $count, $navigation, zurl('', $query), $form_attr, $list_opts, $selected, $checkbox);
	} else {
		zina_set_message(zt('Sorry, there are no playlists'));
		$zina['content'] = '';
	}
}

function zina_get_session_playlist() {
	$songs = $dirs = $playlists = array();

	$results = (isset($_SESSION['z_sp'])) ? unserialize_utf8($_SESSION['z_sp']) : array();

	#todo: change .lp & .pls stuff to substr
	foreach($results as $result) {
		if (preg_match('/\.lp$/i', $result)) {
			$dirs[] = preg_replace('/\/\.lp$/i','',$result);
		} elseif (preg_match('/\.pls/i', $result)) {
			$playlists[] = preg_replace('/\.pls/i','',$result);
		} else {
			$songs[] = $result;
		}
	}

	if (!empty($songs)) {
		$sql = "SELECT f.id as type_id, 'song' as type, IF(f.path!='.',CONCAT(f.path,IF(ISNULL(f.path), '','/'),f.file),f.file) AS path, ".
			"f.id3_info, f.year, f.genre, f.sum_votes, f.sum_rating ".
			"FROM {files} AS f ".
			"WHERE f.id IN (".implode(',', $songs).")";
		$result_songs = zdbq_assoc('type_id', $sql);
	}
	if (!empty($dirs)) {
		$sql = "SELECT d.id as type_id, 'album' as type, d.path, ".
			"d.title as id3_info, d.year, d.genre, d.sum_votes, d.sum_rating ".
			"FROM {dirs} AS d ".
			"WHERE d.id IN (".implode(',', $dirs).")";
		$result_dirs = zdbq_assoc('type_id', $sql);
	}
	if (!empty($playlists)) {
		$sql = "SELECT p.id as type_id, 'playlist' as type, p.id as path, p.title as id3_info, p.title, ".
			"d.path as image_path, g.genre, p.image_type, p.id, p.sum_votes, p.sum_rating ".
			"FROM {playlists} AS p ".
			"LEFT OUTER JOIN {dirs} AS d ON (p.dir_id = d.id) ".
			"LEFT OUTER JOIN {genres} AS g ON (p.genre_id = g.id) ".
			"WHERE p.id IN (".implode(',', $playlists).")";
		$result_playlists = zdbq_assoc('type_id', $sql);
	}

	foreach($results as $key => $val) {
		$item = &$results[$key];

		if (preg_match('/\.lp$/i', $val)) {
			$dir_id = preg_replace('/\/\.lp$/i','',$val);
			if (isset($result_dirs[$dir_id])) $item = $result_dirs[$dir_id];
		} elseif (preg_match('/\.pls/i', $val)) {
			$pls_id2 = preg_replace('/\.pls/i','',$val);
			if (isset($result_playlists[$pls_id2])) $item = $result_playlists[$pls_id2];
		} else {
			if (isset($result_songs[$val])) $item = $result_songs[$val];
		}
	}
	return $results;
}


function zina_content_playlist_db(&$zina, $path, $pls_id, $id = null) {
	global $zc;

	if (empty($pls_id)) return zina_content_playlists_db($zina, $id);

	if ($pls_id == 'zina_session_playlist') {
		if (!$zc['session_pls']) {
			zina_set_message(zt('Access denied'), 'warn');
			return zina_content_playlists_db($zina);
		}
		$reorder_access = true;
		$edit_access = false;
		$delete_access = true;
		$results = zina_get_session_playlist();
		$page['title'] = zt('Session');
		$page['user_id'] = $zc['user_id'];
		$page['type'] = 'playlist';
		$page['image_type'] = 0;
		$page['date_created'] = time();
		$page['title'] = $title = zt('Session Playlist');
		$page['image'] = zina_content_search_image($page, 'sub', 'genre-image');
		$page['addthis'] = false;
	} else {
		$page = zina_playlist_get_info($pls_id);

		if (!$page) {
			zina_set_message(zt('Playlist not found'), 'warn');
			return zina_content_playlists_db($zina);
		}

		zdb_log_stat_playlist($pls_id, 'views');

		$user_rating = 0;

		if ($zc['pls_ratings']) {
			$page['pls_ratings'] = true;
			$user_ratings = zdbq_assoc('playlist_id',"SELECT playlist_id, stat ".
				"FROM {playlists_stats} ".
				"WHERE playlist_id = %d AND user_id = %d AND stat_type = 3", array($pls_id, $zc['user_id']));
			if (isset($user_ratings[$pls_id])) $user_rating = $user_ratings[$pls_id]['stat'];
		}

		$page['pls_rate_output'] = ztheme('vote', zurl('','l=68&pl='.$pls_id).'&n=', $user_rating);

		# PLAYLIST VIEW PAGE
		$page['type'] = 'playlist';
		if (!empty($page['path'])) $page['image_path'] = $page['path'];

		$page['description'] = zina_url_filter(nl2br($page['description']));
		$page['addthis'] = $zina['addthis'];
		$page['addthis_id'] = $zina['addthis_id'];
		$page['addthis_options'] = $zina['addthis_options'];
		$page['addthis_path'] = null;
		$page['addthis_query'] = 'l=2&amp;pl='.$pls_id;
		$page['site_name'] = $zina['site_name'];

		#TODO:XXX playlist image size???
		$page['image'] = zina_content_search_image($page, 'sub', 'genre-image');

		if ($zc['genres'] && !empty($page['genre'])) {
			$zina['dir_genre'] = zl($page['genre'],$path,'l=13&amp;pl='.rawurlencode($page['genre']));
		}

		$reorder_access = $edit_access = $delete_access = zina_cms_access('edit_playlists', $page['user_id']);

		$results = zina_playlist_get_items($pls_id);
		$title = zt('@title Playlist', array('@title'=>$page['title']));
	}

	$zina['title'] = $title;
	$page['count'] = sizeof($results);

	if ($page['count'] > 0) {
		zina_set_js('file', 'extras/jquery.js');
		zina_set_js('file', 'extras/drupal.js');

		$opts['checkbox'] = $opts['submit'] = ($edit_access || $reorder_access);

		zina_content_search_list($results, $opts['checkbox']);

		if ($opts['checkbox']) {
			foreach($results as $key => $val) {
				$item = &$results[$key];
				if ($val['type'] == 'song') {
					$item['checkbox'] = array('name'=>'mp3s[]', 'value'=>zrawurlencode($val['type_id']), 'checked'=>true);
				} elseif ($val['type'] == 'playlist') {
					$item['checkbox'] = array('name'=>'mp3s[]', 'value'=>zrawurlencode($val['type_id']).'.pls', 'checked'=>true);
				} else {
					$item['checkbox'] = array('name'=>'mp3s[]', 'value'=>zrawurlencode($val['type_id']).'/.lp', 'checked'=>true);
				}
			}
		}

		if ($zc['rss'] && $zc['database'] && $pls_id != 'zina_session_playlist') {
			if ($zc['clean_urls']) {
				$page['podcast']['url'] = $pls_id.'/pls.xml';
				$page['podcast']['query'] = null;;
			} else {
				$page['podcast']['url'] = null;
				$page['podcast']['query'] = 'l=70&amp;pl='.$pls_id;
			}
			$page['podcast']['type'] = ($zc['rss_podcast']) ? 'podcast' : 'rss';

			zina_set_html_head('<link rel="alternate" type="application/rss+xml" title="'.zt('!title Podcast', array('!title'=>$zina['title'])).'" href="'.zurl($page['podcast']['url'], $page['podcast']['query']).'"/>');
		}

		$list = ztheme('playlist_list', $results, $zc['search_images'], $opts['checkbox']);
	} else {
		$list = zt('This playlist is empty.');
		$opts['submit'] = $opts['checkbox'] = false;
	}

	$plopts['user']['play'] = ($zc['play']) ? array('path'=>null, 'query'=>'l=8&amp;m=3&amp;pl='.$pls_id, 'attr'=>'class="zinamp"') : false;
	if ($zc['play'] && $zc['play_rec_rand'])
		$plopts['user']['play_rec_rand'] = ($zc['play']) ? array('path'=>null, 'query'=>'l=8&amp;m=3&amp;pl='.$pls_id.'&amp;rand', 'attr'=>'class="zinamp"') : false;

	$plopts['admin']['edit'] = $plopts['admin']['delete'] = false;

	$user = zina_cms_user($page['user_id']);
	if ($user) {
		if ($user['profile_url']) $page['profile_url'] = $user['profile_url'];
		$page['username'] = $user['name'];
	}

	if ($edit_access) $plopts['admin']['edit'] = array('path'=>null, 'query'=>'l=44&amp;pl='.$pls_id);
	if ($delete_access) $plopts['admin']['delete'] = array('path'=>null, 'query'=>'l=43&amp;pl='.$pls_id);

	$form_id = 'zinaplaylistform';
	$form_attr = 'id="'.$form_id.'" action="'.zurl($path, 'l=41').'"';
	$list .= '<input type="hidden" name="playlist" value="'.$pls_id.'"/>';
	$list_opts = ztheme('playlist_list_opts', $opts, $form_id);
	$zina['content'] = ztheme('playlists_section', $page, $form_attr, $list, $list_opts, $plopts);
}

# NON DB
function zina_content_playlists_view(&$zina) {
	global $zc;
	$zina['title'] = zt('Playlists');

	$playlists = zina_get_playlists_custom();

	if ($zc['session_pls']) {
		if (empty($playlists)) {
			$playlists[] = 'zina_session_playlist';
			} else {
			array_unshift($playlists, 'zina_session_playlist');
		}
	}

	if (!empty($playlists)) {
		foreach($playlists as $playlist) {
			$item = null;
			$pl = rawurlencode($playlist);
			if ($zc['play']) {
				$item['opts']['play'] = array('path'=>null, 'query'=>'l=8&amp;m=3&amp;pl='.$pl);
			}
			$item['opts']['more'] = array('path'=>null, 'query'=>'l=2&amp;pl='.$pl);

			if ($zc['is_admin'] && $zc['cache'] && $playlist != 'zina_session_playlist') {
				$item['opts']['rename'] = array('path'=>null, 'query'=>'l=44&amp;pl='.$pl);
			}
			if ($zc['is_admin'] || ($zc['session_pls'] && $playlist == 'zina_session_playlist')) {
				$item['opts']['delete'] = array('path'=>null, 'query'=>'l=43&amp;pl='.$pl);
			}
			$item['image_link'] = $item['title_link'] = array('path' => null, 'query' => 'l=2&amp;pl='.$pl);
			$item['title'] = ($zc['session_pls'] && $playlist == 'zina_session_playlist') ? zt('Session Playlist') : htmlentities($playlist);
			$items[] = $item;
		}
		$zina['content'] = ztheme('playlists_list',$items, $zina);
	} else {
		zina_set_message(zt('Sorry, there are no playlists'));
		$zina['content'] = '';
	}
}

function zina_content_playlists(&$zina, $path, $playlist) {
	global $zc;

	if ($zc['session_pls'] && $playlist == 'zina_session_playlist') {
		$sp = true;
	} else {
		$sp = false;
		$filename = $zc['cache_pls_dir'].'/_zina_'.$playlist.'.m3u';
		$edit = (!empty($playlist) && file_exists($filename));
	}
	if ($sp || $edit) { # Edit Playlist
		if ($sp) {
			$songs = (isset($_SESSION['z_sp'])) ? unserialize_utf8($_SESSION['z_sp']) : array();
			$title = zt('Session Playlist');
		} else {
			$title = zt('@title Playlist', array('@title'=>$playlist));
			$songs = zunserialize_alt(file_get_contents($filename));
		}
		$zina['title'] = $title;
		$count = sizeof($songs);
		if ($count > 0) {
			$download = $zc['download'];
			#todo: similar loop with search...combine?
			foreach($songs as $song) {
				$play = $zc['play'];
				$rem_title = false;
				$item = array();
				$arr = explode('/', $song);
				$num = sizeof($arr);
				$item['checkbox'] = array('name'=>'mp3s[]', 'value'=>zrawurlencode($song), 'checked'=>true);
				$type = (preg_match('/\.('.$zc['ext_mus'].')$/i', $song) || ($zc['remote'] && preg_match('/\.('.$zc['remote_ext'].')$/i', $song))) ? 's' : 'a';

				if ($type == 's') {
					if ($zc['remote'] && preg_match('/\.('.$zc['remote_ext'].')$/i', $song, $matches)) {
						$rem = new remoteFile($zc['mp3_dir'].'/'.$song, false, true);
						if ($play && isset($rem->url)) {
							$item['opts']['play'] = array('path'=>$song, 'query'=>'l=8&amp;m=1');
							if (isset($rem->title)) $rem_title = $rem->title;
						} else {
							$play = false;
						}
						if ($zc['download'] && isset($rem->download)) {
							$item['opts']['download'] = array('path'=>$song, 'query'=>'l=12');
						}
					} else {
						if ($play) {
							$item['opts']['play'] = array('path'=>$song, 'query'=>'l=8&amp;m=1');
						}
						if ($download) {
							$item['opts']['download'] = array('path'=>$song, 'query'=>'l=12');
						}
					}
				} else {
					zina_content_subdir_opts($item, preg_replace('/\/\.lp$/i','',$song), false, NULL);
				}

				$item['title'] = zina_content_pathlinks($song, $type, $play, $rem_title);
				$items[] = $item;
			}

			zina_set_js('file', 'extras/jquery.js');
			zina_set_js('file', 'extras/drupal.js');
			$list = ztheme('playlists_list',$items, $zina, true);
			$opts['checkbox'] = $opts['submit'] = (($zc['is_admin'] && $zc['cache']) || ($zc['session_pls'] && $playlist == 'zina_session_playlist'));
		} else {
			$list = zt('This playlist is empty.');
			$opts['submit'] = $opts['checkbox'] = false;
		}

		$pl = rawurlencode($playlist);
		$plopts['edit'] = $plopts['delete'] = false;

		$plopts['play'] = ($zc['play']) ? array('path'=>null, 'query'=>'l=8&amp;m=3&amp;pl='.$pl) : false;
		if ($zc['is_admin'] && $zc['cache'] && $playlist != 'zina_session_playlist') {
			$plopts['rename'] = array('path'=>null, 'query'=>'l=44&amp;pl='.$pl);
		}
		if ($zc['is_admin'] || ($zc['session_pls'] && $playlist == 'zina_session_playlist')) {
			$plopts['delete'] = array('path'=>null, 'query'=>'l=43&amp;pl='.$pl);
		}

		$form_id = 'zinaplaylistform';
		$form_attr = 'id="'.$form_id.'" action="'.zurl($path, 'l=41').'"';
		$list .= '<input type="hidden" name="playlist" value="'.$playlist.'"/>';
		$list_opts = ztheme('playlist_list_opts', $opts, $form_id);
		$zina['content'] = ztheme('playlists_section',array('title'=>$title, 'count'=>$count),$form_attr,$list,$list_opts, $plopts);

	} else { # View Playlists
		zina_content_playlists_view($zina);
	}
}

function zina_content_blurb(&$zina, $path, $opts) {
	global $zc;

	$alt_title = $hidden = $text = '';
	$set_title = $seed = false;
	$rows = array();
	$type = $opts['type'];
	$return = (isset($opts['return']));
	$label = -1;
	$url = (isset($_SERVER['HTTP_REFERER'])) ? parse_url($_SERVER['HTTP_REFERER']) : false;
	$help = true;

	switch($type) {
		Case 1 : #DIR
			$dir = zina_get_directory($path);
			$title = $dir['title'];
			$file = $zc['dir_file'];
			if (empty($path)) {
				$display_path = $zc['main_dir_title'];
			} else {
				$display_path = $path;
				$alt_title = $title;
				$seed = true;
			}
			if ($zc['database']) {
				#TODO: make empty path be treated like a normal dir...
				if (empty($path)) {
					$text = utf8_decode(zdbq_single("SELECT description FROM {dirs} WHERE path = '.'"));
				} else {
					$text = utf8_decode(zdbq_single("SELECT description FROM {dirs} WHERE path = '%s'", $path));
				}
			}

			$text_file = $zc['cur_dir'].'/'.$file;
			break;
		Case 2 : #ALT
			$seed = true;
			$dir = zina_get_directory($path);
			$alt_title = $title = $dir['title'];

			$lang = zina_get_settings('lang');
			$titles = &$lang['titles'];
			$subs = &$lang['subs'];
			if ($zc['database']) {
				$other = zdb_get_others($path);
				if (isset($other['alt_items']) && !empty($other['alt_items'])) $text = $other['alt_items'];
			}

			if (empty($text)) {
				$file = $zc['alt_file'];
				$text_file = $zc['cur_dir'].'/'.$file;
				$text = (file_exists($text_file)) ? zunserialize_alt(file_get_contents($text_file)) : array();
			}

			$rows[] = array('label'=>-1,'item'=>$subs['alt_dirs']);
			$label = $title = $titles['alt_dirs'];

			$dirs = zina_core_cache('dirs');

			if (!empty($dirs)) {
				$row = '<select size="10" multiple name="mp3s[]">';
				foreach($dirs as $d) {
					$selected = (in_array($d, $text)) ? ' selected="selected"' : '';
					$val = (empty($d)) ? $zc['main_dir_title'] : htmlentities($d);
					$row .= '<option value="'.zrawurlencode($d).'"'.$selected.'>'.$val.'</option>';
				}
				$row .= '</select>';
				$rows[] = array('label'=>$label,'item'=>$row);
			}
			break;
		Case 4 : #MM
			$title = preg_replace('/\.('.$zc['mm_ext'].')$/i', '', basename($path));
			$file = $path.'.txt';
			$text_file = $zc['mp3_dir'].'/'.$file;

			$dir = zina_get_directory(dirname($path));
			$display_path = dirname($path);
			$alt_title = $dir['title'];
			$seed = true;
			$set_title = true;

			break;
		Case 5 : #PODCAST
			$label = zt('Podcast');
			$dir = zina_get_directory($path);
			$alt_title = $title = $dir['title'];
			$seed = true;
			$help = false;

			if ($zc['database']) {
				$other = zdb_get_others($path);
				if (isset($other['rss']) && !empty($other['rss'])) $text = utf8_decode($other['rss']);
			}

			if (empty($text)) {
				$file = $zc['rss_file'];
				$text_file = $zc['cur_dir'].'/'.$file;
				if (!file_exists($text_file)) {
					$text = zina_content_rss($path);
					$text = utf8_decode($text);
				}
			}
			break;
		Case 6 : # GENRE DESC
			$term = $_GET['pl'];
			$text_file = $zc['cache_dir_private_abs'].'/genre_desc_'.rawurlencode($term).'.txt';
			if (!zfile_check_location($zc['cache_dir_private_abs'], $text_file)) $text_file = false;
			$display_path = $title = htmlentities($term);
			$text = zdbq_single("SELECT description FROM {genres} WHERE genre = '%s'", array($term));
			$hidden = ztheme('form_hidden','playlist',$term);
			break;

		Case 3: # songs
		Default: # SONG EXTRAS
			if ($type == 3 || ($zc['song_extras'] && in_array($type, $zc['song_es_exts']))) {
				if ($type != 3) {
					$label = $zc['song_es'][$type]['name'];
					$url['path'] = $path;
				}
				if ($zc['database']) {
					$result = zdbq_array_single("SELECT description, other, id3_info FROM {files} WHERE path = '%s' AND file = '%s'", dirname($path), basename($path));

					if (!empty($result)) {
						if ($type == 3) {
							$text = utf8_decode($result['description']);
						} else {
							$other = unserialize_utf8($result['other']);
							if (isset($other[$type])) $text = $other[$type];
						}

						$mp3 = unserialize_utf8($result['id3_info']);
						if (isset($mp3->title)) $title = ztheme('song_title', $mp3->title, true);
						if ($type == 'lyr' && empty($text) && isset($mp3->lyrics) && !empty($mp3->lyrics)) {
							$text = $mp3->lyrics;
						}
					}
				} elseif ($type == 'lyr') {
					$mp3 = zina_get_file_info($zc['mp3_dir'].'/'.$path);
					if (isset($mp3->lyrics) && !empty($mp3->lyrics)) $text = $mp3->lyrics;
				}

				$id3 = $zc['mp3_id3'];
				if (empty($text)) {
					if ($zc['remote'] && preg_match('/\.('.$zc['remote_ext'].')$/i', $path)) {
						$ext = $zc['remote_ext'];
						$id3 = true;
					} elseif ($zc['fake'] && preg_match('/\.('.$zc['fake_ext'].')$/i', $path)) {
						$ext = $zc['fake_ext'];
					} else {
						$ext = $zc['ext_mus'];
					}
					$file_ext = ($type == 3) ? 'txt' : $type;
					$file = preg_replace('/\.('.$ext.')$/i', '.'.$file_ext, $path);
					$text_file = $zc['mp3_dir'].'/'.$file;
				}

				if (empty($title)) {
					$title = zina_content_song_title($zc['mp3_dir'].'/'.$path, $id3);
				}

				$dir = zina_get_directory(dirname($path));
				$display_path = dirname($path);
				$alt_title = $dir['title'];
				$seed = true;
				$set_title = true;

			} else {
				return zina_not_found();
			}
			break;
	}

	if (!isset($display_path)) $display_path = preg_replace('/\.('.$zc['ext_mus'].')$/i', '', $path);

	zina_content_breadcrumb($zina, $display_path, $alt_title, $seed);
	if ($set_title) {
		$zina['title'] = ztheme('page_title', array($zina['title'], $title));
	}
	$ajax_url = false;

	if ($type == 2) {
	} else {
		if (empty($text)) $text = (file_exists($text_file)) ? utf8_decode(file_get_contents($text_file)) : '';
		if ($return) return array('output'=>$text, 'title'=>$title);

		$id = '';
		if (empty($text) && isset($zc['third_'.$type]) && $zc['third_'.$type]) {
			$ajax_url = zurl($path, 'l=57&m='.$type);
			zina_set_js('file', 'extras/jquery.js');
			$id = ' id="zina-'.$type.'"';
			if (!$zc['third_'.$type.'_save']) {
				zina_set_message(zt('If lyrics are present, they have not been saved.'));
			}
		}

		$rows[] = array(
			'label'=>$label,
			'item'=>'<textarea name="mp3s" cols="70" rows="20" wrap="virtual"'.$id.'>'.htmlentities($text).'</textarea>',
			'help'=> ($help) ? zt('HTML tags allowed: @tags', array('@tags' => $zc['cms_tags'])) : false,
		);
	}

	$space = ($label == -1) ? -1 : null;
	$rows[] = array('label'=>$space,'item'=>'<input type="submit" value="'.zt('Submit').'"/>');

	$form = array(
		'title'=>$title,
		'attr'=>'action="'.zurl($path, 'l=23&amp;m='.$type).'"',
		'hidden'=>$hidden,
		'rows'=>$rows
	);

	$zina['content'] = ztheme('blurb',$form, $type, $ajax_url);
}

function zina_save_blurb($path, $m, $songs, $playlist = null, $goto = true) {
	global $zc;

	$success = false;
	$cache_path = $path;
	$query = null;
	$check_loc = $zc['mp3_dir'];

	if ($m == 1) { # DIR
		$cd = $zc['cur_dir'];
		$file = $zc['dir_file'];
		$songs = zina_filter_html($songs, $zc['cms_tags']);

		if ($zc['database']) {
			zdb_log_stat('insertonly', $path);
			if (empty($path)) {
				$success = zdbq("UPDATE {dirs} SET description = '%s' WHERE path = '.' AND parent_id = 0", array($songs));
			} else {
				$success = zdbq("UPDATE {dirs} SET description = '%s' WHERE path = '%s'", array($songs, $path));
			}
		}
	} elseif ($m == 2) { # ALT
		$cd = $zc['cur_dir'];
		$file = $zc['alt_file'];

		$mp3s = array();
		foreach($songs as $song) {
			$fp = $zc['mp3_dir'].'/'.zrawurldecode($song);
			if (file_exists($fp) && is_dir($fp)) {
				$mp3s[] = zrawurldecode($song);
			} else {
				zina_debug(zt('@file does not exist.',array('@file'=>$fp)),'warn');
			}
		}
		$songs = (!empty($mp3s)) ? serialize($mp3s) : '';
		if ($zc['database']) {
			$result = zdb_update_others(array('alt_items'=>$mp3s), $path);
		}
	} elseif ($m == 3 || $m == 4) { # Song or MM
		$cd = dirname($zc['cur_dir']);
		$filename = basename($path);
		$path = dirname($path);
		if ($path == '.') $path = '';
		$songs = zina_filter_html($songs, $zc['cms_tags']);

		if ($m == 3) {
			if ($zc['remote'] && preg_match('/\.('.$zc['remote_ext'].')$/i', $filename)) {
				$file = preg_replace('/\.('.$zc['remote_ext'].')$/i', '.txt', $filename);
			} elseif ($zc['fake'] && preg_match('/\.('.$zc['fake_ext'].')$/i', $filename)) {
				$file = preg_replace('/\.('.$zc['fake_ext'].')$/i', '.txt', $filename);
			} else {
				$file = preg_replace('/\.('.$zc['ext_mus'].')$/i', '.txt', $filename);
			}
		} else {
			$file = $filename.'.txt';
		}

		if ($zc['database']) {
			zdb_log_stat('insertonly', $path, $filename);
			#$success = zdbq("UPDATE {files} SET description = '%s', mtime = %d WHERE path = '%s' AND file = '%s'", array($songs, time(), $path, $filename));
			$success = zdbq("UPDATE {files} SET description = '%s' WHERE path = '%s' AND file = '%s'", array($songs, $path, $filename));
		}
	} elseif ($m == 5) { # PODCAST
		$cd = $zc['cur_dir'];
		$file = $zc['rss_file'];

		if ($zc['database']) $result = zdb_update_others(array('rss'=>$songs), $path);
		$songs = utf8_encode($songs);
	} elseif ($m == 6) { # GENRE DESC
		$check_loc = $cd = $zc['cache_dir_private_abs'];
		$file = 'genre_desc_'.rawurlencode($playlist).'.txt';
		$songs = zina_filter_html($songs, $zc['cms_tags']);

		if ($zc['database']) {
			zdbq("UPDATE {genres} SET description = '%s' WHERE genre = '%s'", array($songs, $playlist));
		}
		$query = 'l=13&pl='.rawurlencode($playlist);
	} elseif ($zc['song_extras'] && in_array($m, $zc['song_es_exts'])) {
		#TODO: similar to 3 & 4 (see "entry")
		$cd = dirname($zc['cur_dir']);
		$filename = basename($path);
		$path = dirname($path);
		if ($path == '.') $path = '';
		$songs = zina_filter_html($songs, $zc['cms_tags']);

		if ($zc['remote'] && preg_match('/\.('.$zc['remote_ext'].')$/i', $filename)) {
			$file = preg_replace('/\.('.$zc['remote_ext'].')$/i', '.'.$m, $filename);
		} elseif ($zc['fake'] && preg_match('/\.('.$zc['fake_ext'].')$/i', $filename)) {
			$file = preg_replace('/\.('.$zc['fake_ext'].')$/i', '.'.$m, $filename);
		} else {
			$file = preg_replace('/\.('.$zc['ext_mus'].')$/i', '.'.$m, $filename);
		}
		if ($zc['database']) $result = zdb_update_others(array($m=>$songs), $path, $filename);
	} else {
		return zina_not_found();
	}

	$text_file = $cd.'/'.$file;
	if (is_writeable($cd) && zfile_check_location($text_file, $check_loc)) {
		$emptytest = trim($songs);
		if (!empty($emptytest)) {
			if (file_put_contents($text_file, $songs)) {
				if ($goto) zina_set_message(zt('Updated.'));
				$success = true;
			} else {
				zina_set_message(zt('Could not write file.'), 'error');
			}
		} else {
			if (file_exists($text_file)) unlink($text_file);
		}
	} else {
		if (!$zc['database']) {
			zina_set_message(zt('Cannot save. Directory is not writeable.'),'error');
		}
	}

	if ($success && $zc['cache_tmpl']) {
		if (!zina_delete_tmpl_file(zina_get_tmpl_cache_file($cache_path))) {
			zina_set_message(zt('Cannot delete cache file'),'error');
		}
		if ($cache_path != $path) zina_delete_tmpl_file(zina_get_tmpl_cache_file($path));
	}
	if ($goto) {
		zina_goto($path, $query);
	} else {
		return $success;
	}
}


function zina_content_directory_opts(&$zina, $path) {
	global $zc;

	$query = 'l=77';
	$lang = zina_get_settings('lang');
	$titles = &$lang['titles'];
	$cats = &$lang['cats'];
	$subs = &$lang['subs'];

	$titles += array(
		'person' => zt('Person'),
		'image' => zt('Default Image'),
		'category' => $cats['categories']['t'],
		'images' => $titles['cat_images'],
		'cols' => $titles['cat_cols'],
		'truncate' => $titles['cat_truncate'],
		'split' => $titles['cat_split'],
		'sort' => $titles['cat_sort'],
		'sort_default' => $titles['cat_sort_default'],
		'pp' => $titles['cat_pp'],
	);

	$subs['person'] = zt('Assume last name first for sorting');
	$fields = zina_get_settings('directory');

	$dir = zina_get_directory($path);
	$over = true;
	if (empty($path) || $path == '.') {
		$dir['title'] = $zina['lang']['main_dir_title'];
		$over = false;
	}
	$zina['title'] = $dir['title'];

	zina_content_breadcrumb($zina, $path, $zina['title'], $over);

	if (isset($_POST) && !empty($_POST)) { # SAVE SETTINGS
		$settings = array();
		$errors = false;
		$fields['directory_opts']['image'] = array('type'=>'select');

		foreach($fields as $cat => $x) {
			foreach($x as $name => $field) {
				$input = (isset($_POST[$name])) ? $_POST[$name] : null;

				if (isset($field['v'])) {
					foreach($field['v'] as $key => $type) {
						$opts = null;
						if (is_array($type)) {
							$opts = $type;
							$type = $key;
						}

						if (!zina_validate($type, $input, $opts)) {
							$errors = true;
							$title = $titles[$name];
							if ($opts) $type = current($opts);
							zina_validate_error_message($type, $title, $input);
							continue 2;
						}
					}
				} elseif ($name == 'image') {
					if (empty($dir['images'])) {
						continue;
					} else {
						if (!in_array($input, $dir['images'])) {
							$errors = true;
							zina_set_message(zt('Unknown image: @image', array('@image'=>$input)), 'error');
						}
					}
				}
				if (is_array($input)) $input = serialize($input);
				$settings[$name] = $input;
			}
		}
		#dir_tags?

		if ($settings['category']) {
			unset($settings['category']);
			$cat_out = '<category>'."\n";
			foreach(array('images', 'cols', 'truncate', 'split', 'sort', 'sort_default', 'pp') as $cat_item) {
				$settings['category'][$cat_item] = $settings[$cat_item];
				$cat_out .= '<'.$cat_item.'>'.$settings[$cat_item].'</'.$cat_item.'>'."\n";
				unset($settings[$cat_item]);
			}
			$cat_out .= '</category>'."\n";
		} else {
			foreach(array('images', 'cols', 'truncate', 'split', 'sort', 'sort_default', 'pp') as $cat_item) {
				unset($settings[$cat_item]);
			}
		}

		if (!$errors && zdb_update_others($settings, $path)) {
			zina_set_message(zt('Directory Options Updated'));
			if ($dir['dir_write']) {
				$var_file = $zc['cur_dir'].'/'.$zc['various_file'];
				if ($settings['various']) {
					touch($var_file);
				} else {
					if (file_exists($var_file)) unlink($var_file);
				}
				$cat_file = $zc['cur_dir'].'/'.$zc['cat_file'];
				if ($settings['category']) {
					file_put_contents($cat_file, $cat_out);
				} else {
					if (file_exists($cat_file)) unlink($cat_file);
				}
			}
		} else {
			zina_set_message(zt('Directory Options Not Updated'), 'error');
		}
	}

	$category = false;

	$other = zdb_get_others($path);
	$other['various'] = ($dir['various'] || (isset($other['various']) && $other['various']));

	if (isset($other['category']) && $other['category']) {
		$category = $other['category'];
	} else {
		$category = zina_is_category($zc['cur_dir']);
	}

	if ($category) {
		$other['category'] = 1;
		foreach($category as $key => $val) {
			$other[$key] = $val;
		}
	}

	if (!empty($dir['images'])) {
		$images = $dir['images'];
		foreach($images as $key => $item) {
			$images[$item] = $item;
			unset($images[$key]);
		}

		$image = (isset($other['image'])) ? $other['image'] : zina_get_dir_item($zc['mp3_dir'].'/'.$path,'/\.('.$zc['ext_graphic'].')$/i');
		$img = ztheme('image', zina_get_image_url($path, $image,'sub'), $image, null, 'class="dir-image"');
	} else {
		$images = array(''=>zt('None'));
		$image = $img = '';
	}

	$fields['directory_opts']['image'] = array('type'=>'select', 'opts'=>$images, 'def'=>$image);

	$subs['image'] = '<div id="directory-image" class="directory-image">'.$img.'</div>';
	$cats['directory_opts']['t'] = zt('Directory Options');
	$cats['directory_opts']['d'] = '';

	$rows = array();

	foreach($fields as $cat => $x) {
		$items = null;
		foreach($x as $name => $field) {
			$value = isset($other[$name]) ? $other[$name] : null;
			$item = zina_content_form_helper($name, $field, $value);
			$row = array('label'=>$titles[$name], 'item'=>$item);
			if (isset($subs[$name])) $row['desc'] = $subs[$name];
			$items[] = $row;

			if (isset($field['break'])) $items[] = array('label'=>-1,'item'=>'<hr/>');
		}

		$item = ztheme('admin_section_header', $cats[$cat]['t'], $cat, $query);
		/*
		$desc = $cats[$cat]['d'];
		if (!empty($desc)) {
			if (is_array($desc)) {
				$output = '';
				foreach ($desc as $d) $output .= '<p>'.$d.'</p>';
				$item .= $output;
			} else {
				$item .= '<em>'.$desc.'</em>';
			}
		}
		 */
		$rows[] = array('label'=>-1, 'item'=>$item);
		$rows = array_merge($rows, $items);
	}

	$rows[] = array('label'=>-1, 'item'=>'<input type="submit" value="'.zt('Update').'"/>', 'class'=>'center');

	$lang_while = zt('This could take a while.');
	$lang_sure = zt('Are your sure you want to do this?');

	$funcs = array();
	if ($zc['is_admin']) {
		if ($zc['database']) {
			$funcs['regen'] = array(
				'title' => zt('Update Database'),
				'help' => zt('This will re-synchronize or add directory to the database.').' '.zt('Not usually necessary.'),
				'path'=>$path,
				'query'=>'l=45'
			);
		}
		if ($dir['dir_write'] && $path != '') {
			$funcs['dir_rename'] = array(
				'title' => zt('Rename Directory'),
				'help' => ($zc['dir_tags']) ? zt('This will have no affect if this is an album and ID3 tags are present.') : '',
				'path'=>$path,
				'query'=>'l=78');
		}
	}

	if (!empty($funcs)) {
		$output = '<ul class="zina-list">';
		foreach ($funcs as $i) {
			$output .= '<li class="zina-list">'.zl($i['title'], $i['path'], $i['query']);
			if (isset($i['help']) && !empty($i['help'])) $output .= '<br/><small>'.$i['help'].'</small>';
			$output .= '</li>';
		}
		$output .= '</ul>';

		$row1 = array('label'=>-1, 'item'=> ztheme('admin_section_header', zt('Directory Functions'), 'top').'<br/>'.$output);
		array_unshift($rows,$row1);
	}

	$form = array(
		'attr'=>'action="'.zurl($path, $query).'" id="zina-dir-form"',
		'rows'=>$rows,
	);

	$zina['content'] = ztheme('config',$form);
}

function zina_content_3rd_images($path, $m) {
	global $zc;
	$extra_opts = zina_get_extras_images_opts();
	if (!empty($path) && in_array($m, array_keys($extra_opts))) {
		$source = $extra_opts[$m];
		require_once($zc['zina_dir_abs'].'/extras/extras_images_'.$source.'.php');

		if (isset($_SESSION['zina_extra_images'][$path])) {
			$artist = $_SESSION['zina_extra_images'][$path]['artist'];
			$album = $_SESSION['zina_extra_images'][$path]['album'];
		} else {
			unset($_SESSION['zina_extra_images']);

			$artist = $album = null;

			$dir = zina_get_directory($path);

			if ($dir['files']) {
				$current = current($dir['files']);
			} elseif ($dir['subdirs']) {
				$current = current($dir['subdirs']);
			} else {
				#EMPTY DIRECTORY...
				$dir['subdirs'] = array('title'=>$path);
				$current = array('title'=>$path);
			}
			if (isset($current['info'])) {
				$mp3 = &$current['info'];
				if ($mp3->tag) {
					if (isset($mp3->artist)) $artist = $mp3->artist;
					if (isset($mp3->album)) $album = $mp3->album;
				}
			}

			if ($dir['subdirs']) { # ARTIST
				if (empty($artist)) $artist = $current['title'];
				$album = null;
			} else { # ALBUM
				if (empty($album)) $album = $current['title'];
				if (empty($artist)) {
					$x = explode('/', $path);
					$len = sizeof($x);
					if ($len > 1) {
						$artist =  $x[$len - 2];
					} else {
						echo zt('Error');
						exit;
					}
				}
			}

			$_SESSION['zina_extra_images'][$path]['artist'] = $artist;
			$_SESSION['zina_extra_images'][$path]['album'] = $album;
		}
		$result = false;

		if (isset($_SESSION['zina_images_source'][$path][$source]) && !empty($_SESSION['zina_images_source'][$path][$source])) {
			$result = array_pop($_SESSION['zina_images_source'][$path][$source]);
		} elseif (($results = call_user_func('zina_extras_images_'.$source, $artist, $album)) !== false) {
			$num = sizeof($results);
			if ($num == 1) {
				$result = $results[0];
			} elseif ($num > 1) {
				$result = array_pop($results);
				$_SESSION['zina_images_source'][$path][$source] = $results;
			}
		}

		if ($result) {
			$output = '';
			$src = (isset($result['thumbnail_url'])) ? $result['thumbnail_url'] : $result['image_url'];
			$output .= '<img src="'.$src.'" />';

			#TODO: have opt to display artist summaries...elsewhere
			#if (isset($result['summary'])) echo $result['summary'];

			$save_url = zurl($path, 'l=61&amp;img='.rawurlencode($result['image_url']));
			$class = "zina-newimg-".$m;

			$output .= '<div class="'.$class.'"><a href="'.$result['source_url'].'">'.$source.'</a>';
			if (isset($result['size'])) $output .= ' ('.$result['size'].')';
			if (isset($result['thumbnail_url'])) $output .= ' | <a href="'.$result['image_url'].'">'.zt('View Original').'</a>';
			$output .= ' | <a href="'.$save_url.'" onclick="zinaSaveImage(\''.$save_url.'\',\''.$class.'\'); return false;">'.zt('Save this image').'</a></div>';
			echo $output;
		} else {
			if ($zc['debug']) echo $source.': '.zt('Not found: ').$artist.'->'.$album;
		}
	}
}

function zina_content_sitemap() {
	global $zc;
	$dirs = zina_core_cache('dirs');

	$items = array();
	$mp3_dir = $zc['mp3_dir'];

	$item['mtime'] = filemtime($mp3_dir);
	$item['url'] = zurl('',NULL,NULL,TRUE);
	$item['priority'] = .8;
	$items[] = $item;

	foreach($dirs as $dir) {
		$path = $mp3_dir.'/'.$dir;
		if (!empty($dir) && file_exists($path) && is_dir($path)) {
			$item = null;
			$item['path'] = $path;
			$item['mtime'] = filemtime($path);
			$item['url'] = zurl($dir,NULL,NULL,TRUE);
			$items[] = $item;
		}
	}
	if ($items) {
		return ztheme('sitemap', $items);
	}
	return false;
}

function zina_content_rss($path) {
	global $zc;
	if ($zc['database']) {
		$other = zdb_get_others($path);
		if (isset($other['rss']) && !empty($other['rss'])) {
			return $other['rss'];
		}
	}

	$rss['page_url'] = preg_replace('/&([^a])/','&amp;${1}', zurl($path,NULL,NULL,TRUE));
	$rss['link_url'] = $rss['page_url'].'/'.$zc['rss_file'];
	$direct = preg_match('#^'.$_SERVER['DOCUMENT_ROOT'].'#i', $zc['mp3_dir']);

	$zina = array();
	zina_content_breadcrumb($zina, $path);
	$rss['title'] = $zina['title'];
	$rss['lang_code'] = $zc['lang'];

	$dir = zina_get_directory($path);

	$rss['desc'] = (isset($dir['description'])) ? $dir['description'] : '';
	if (!empty($dir['images'])) {
		$rss['image_url'] = preg_replace('/&([^a])/','&amp;${1}', zina_get_image_url($path, $dir['images'][0], 'sub', true));
	} else {
		$rss['image_url'] = false;
	}

	if (!empty($dir['files'])) {
		#todo: redudancy with zina_stats_feed()
		foreach ($dir['files'] as $key => $item) {
			if ($item['fake']) continue;
			if (!isset($rss['genre']) && isset($item['genre'])) {
			  $rss['genre'] = $item['genre'];
			}
			$info = &$item['info'];
			$i = array();
			$i['title'] = $item['title'];
			$i['url'] = preg_replace('/&([^a])/','&amp;${1}',zurl($key,NULL,NULL,TRUE,$direct));
			$i['url_enc'] = utf8_encode(rawurldecode($i['url']));
			$i['artist'] = (isset($info->artist)) ?  $info->artist : false;
			$i['album'] = (isset($info->album)) ? $info->album : false;
			$i['duration'] = (isset($info->time)) ? $info->time : false;
			$i['size'] = (isset($info->filesize)) ? $info->filesize : false;
			$i['pub'] = (isset($dir['files'][$key]['mtime'])) ? date('r',$dir['files'][$key]['mtime']) : false;
			$i['type'] = (preg_match('/\.('.$zc['ext_mus'].')$/i', $key, $exts)) ? $zc['media_types'][strtolower($exts[1])]['mime'] : 'audio/mpeg';
			$i['description'] = (!empty($item['description'])) ? preg_replace('/&([^a])/','&amp;${1}',$item['description']) : false;

			if ($dir['various']) {
				if ($i['artist'] && $i['title']) {
					$i['title'] = ztheme('artist_song', $i['title'], $i['artist']);
				}
			}
			$rss['items'][] = $i;
		}
	}

	if ($zc['mm'] && $zc['rss_mm'] && isset($dir['mm']) && !empty($dir['mm'])) {
		foreach ($dir['mm'] as $key => $item) {
			$i = array();
			$i['title'] = $item['title'];
			$i['url'] = preg_replace('/&([^a])/','&amp;${1}',zurl($key,NULL,NULL,TRUE,$direct));
			$i['url_enc'] = utf8_encode(rawurldecode($i['url']));
			$i['size'] = (isset($item['filesize'])) ? $item['filesize'] : false;
			$i['type'] = (preg_match('/\.('.$zc['mm_ext'].')$/i', $key, $exts)) ? $zc['mm_types'][strtolower($exts[1])]['mime'] : 'audio/mpeg';
			$i['description'] = (!empty($item['description'])) ? preg_replace('/&([^a])/','&amp;${1}',$item['description']) : false;
			$rss['items'][] = $i;
		}
	}

	return ztheme('rss', $rss, $zc['rss_podcast']);
}

#move to theme?
function zina_content_edit_images(&$zina, $path) {
	global $zc;
	$dir = zina_get_directory($path);
	$title = $dir['title'];
	#$title = ztheme('title',basename($path));
	zina_content_breadcrumb($zina, $path, $title, true);
	$js = '';

	if (!is_writeable($zc['cur_dir'])) {
		zina_set_message(zt('Directory is not writeable.  You cannot save or delete images.'),'warn');
		$dir_write = false;
	} else {
		$dir_write = true;
		$js2 = 'function zinaDeleteImage(url, imgclass){'.
			'if (confirm("'.zt('Delete this image?').'")){'.
			'$.get(url, function(data){'.
				'$("div."+imgclass).html(data);'.
				'});'.
			'}'.
		'}';
		$js2 .= 'function zinaSaveImage(url, imgclass){'.
			'$.get(url, function(data){'.
				'$("div."+imgclass).html(data);'.
			'});'.
		'}';
		zina_set_js('inline', $js2);
	}

	$dir = zina_get_directory($path);
	$images = array();

	if (isset($dir['images'])) {
		foreach($dir['images'] as $key=>$image) {
			$img = array();
			$source = $zc['cur_dir'].'/'.$image;
			if ($info = getimagesize($source)) {
				#$img['width'] = $info[0];
				#$img['height'] = $info[1];
			}
			$img_path = (!empty($path) ? $path.'/'.$image : $image);
			$img['image_raw'] = $image_raw = ztheme('image', zina_get_image_url($path, $image,'dir'), $image, null, 'class="dir-image"');
			$img['image'] = zl($image_raw, $img_path, 'l=46');
			$class = 'zina-imginfo-'.$key;
			$img['image'] .= '<div class="'.$class.'">'.$info[0].'x'.$info[1];
			if ($dir_write) {
				$img['image'] .= ' | '.zl(zt('Delete'),$img_path,'l=60',NULL,FALSE, ' onclick="zinaDeleteImage(\''.zurl($img_path,'l=60').'\',\''.$class.'\');return false;"');
			}
			$img['image'] .= '</div>';
			$images[] = $img;
		}
	}

	$holder = array();
	$missing_nav = '';

	if ($zc['third_images'] || isset($_SESSION['zina_missing'])) {
		if (isset($_SESSION['zina_missing'])) {
			if (isset($zc['url_query'])) {
				foreach($zc['url_query'] as $query) {
					$parts = explode('=', $query);
					$queries[$parts[0]] = $parts[1];
				}
			}
			$queries['l'] = 58;
 			$select = ztheme('images_missing_form', array('action'=>zurl(''), 'queries'=>$queries), $_SESSION['zina_missing'], $path);
			$missing_nav = ztheme('image_missing_nav',$select, array('path'=>$path, 'query'=>'l=63'));
		}
		zina_set_js('file', 'extras/jquery.js');

		$opts = zina_get_extras_images_opts();

		foreach($opts as $key => $val) {
			$js .= '$(".zina-img-ajax-'.$key.'").load("'.zurl($path, 'l=59&m='.$key).'");';
			$holder[] = array('image'=>'<p class="zina-img-ajax-'.$key.'">'.zt('Loading...').'</p>');
		}
	}
	zina_set_js('jquery', $js);
	return ztheme('edit_images_page', $title, $images, $holder, $missing_nav);
}

function zina_content_genre_hierarchy() {
	global $zc;

	zina_set_js('file', 'extras/jquery.js');
	zina_set_js('file', 'extras/drupal.js');
	$hierarchy = zdbq_array("SELECT g.*, gt.path, gt.weight FROM {genres} AS g INNER JOIN {genre_tree} AS gt ON g.id = gt.id ".
		"ORDER BY gt.weight");
	if (empty($hierarchy)) {
		zina_set_message(zt('Please generate genre caches'));
		zina_goto('','l-20');
	}
	$next_id = zdbq_single('SELECT MAX(id) FROM {genres}') + 1;
	foreach($hierarchy as $key => $genre) {
		$g = &$hierarchy[$key];
		$g['view_query'] =  'l=13&pl='.rawurlencode($genre['genre']);
		$g['delete_url'] = zurl('','l=52&amp;m='.$genre['id']);
	}
	return ztheme('genre_hierarchy', $hierarchy, $next_id, 'action="'.zurl('', 'l=49').'"', $zc['genres_custom']);
}

function zina_content_settings($path) {
	global $zc;

	if ($zc['cache']) {
		if (zina_check_directory($zc['cache_dir_private_abs'],1)) {
			$key_file = $zc['cache_dir_private_abs'].'/sitekey.txt';
			if (!file_exists($key_file)) {
				file_put_contents($key_file, md5(uniqid(mt_rand(), true)) . md5(uniqid(mt_rand(), true)));
			}
		}
		zina_check_directory($zc['cache_dir_public_abs'],1);
	}

	if ($zc['playlists'])
		zina_check_directory($zc['cache_pls_dir'],1);
	if ($zc['cache_tmpl'])
		zina_check_directory($zc['cache_tmpl_dir'],1);
	if ($zc['cmp_sel'])
		zina_check_directory($zc['cache_zip_dir'],1);
	if ($zc['cache_imgs'])
		zina_check_directory($zc['cache_imgs_dir'],1);

	if ($zc['zinamp']) {
		if ($zc['pos']) zina_set_message(zt('The Flash Player and Play on Server are incompatible options.'), 'warn');
		if (!$zc['stream_int']) zina_set_message(zt('The Flash Player must have Internal Streaming set to true.'), 'warn');
		if ($zc['playlist_format'] != 'xspf') zina_set_message(zt('The Flash Player must use the "xspf" Playlist Format.'), 'warn');
		if ($zc['apache_auth']) zina_set_message(zt('Apache Authentication may be incompatible (and unnecessary) with the Flash Player option.'), 'warn');
	}

	$rows = array();
	$query = 'l=20';
	$fields = zina_get_settings('cfg');
	$lang = zina_get_settings('lang');
	$cats = &$lang['cats'];
	$titles = &$lang['titles'];
	$subs = &$lang['subs'];

	# CRON #
	$cron_row = '';
	#todo: aren't we always admin here?
	if ($zc['is_admin']) {
		$cron_row = 'X: <select><option selected="selected">'.zt('FOR REFERENCE ONLY: NOT ALL THESE OPTIONS MAKE SENSE').'</option>';
		foreach($fields as $cat => $x) {
			foreach($x as $name => $field) {
				if (isset($field['opts']) && $field['opts'] == 'zina_get_opt_tf') {
					$cron_row .= '<option>'.$name.' &nbsp; ('.$titles[$name].')</option>';
				}
			}
		}
		$cron_row .= '</select>';
	}
	# END CRON
	$rows[] = array('label'=>-1, 'item'=>'<input type="submit" value="'.zt('Update').'"/>', 'class'=>'center');

	foreach($fields as $cat => $x) {
		$items = null;
		if (($zc['embed'] != 'standalone') && ($cat == 'auth' || $cat == 'integration' ||
				($cat == 'db' && $zc['db_cms']))) {
			continue;
		}

		foreach($x as $name => $field) {
			if ($name == 'adm_pwd') {
				$pass_fields = array('adm_pwd_old', 'adm_pwd', 'adm_pwd_con');
				foreach($pass_fields as $pf) {
					$item = zina_content_form_helper($pf, $field, $zc[$name]);
					$items[] = array('label'=>$titles[$pf], 'item'=>$item);
				}
			} else {
				$value = $zc[$name];
				if ($name == 'main_dir_title') $value = zt($value);
				$item = zina_content_form_helper($name, $field, $value);
				$row = array('label'=>$titles[$name], 'item'=>$item);
				if (isset($subs[$name])) {
					$row['desc'] = $subs[$name];
				}
				$items[] = $row;
			}
			if (isset($field['break'])) $items[] = array('label'=>-1,'item'=>'<hr/>');
		}

		$nav[] = zl($cats[$cat]['t'],'','l=20',$cat);

		# CAT HEADER
		$item = ztheme('admin_section_header', $cats[$cat]['t'], $cat, $query);
		$desc = $cats[$cat]['d'];
		if (is_array($desc)) {
			$output = '';
			foreach ($desc as $d) {
				$output .= '<p>'.$d.'</p>';
			}
			$item .= $output;
		} else {
			$item .= '<em>'.$desc.'</em>';
		}
		if ($cat == 'cron') {
			$item = str_replace('%CRON_SELECT%', $cron_row, $item);
		}

		$rows[] = array('label'=>-1, 'item'=>$item);
		$rows = array_merge($rows, $items);
	}

	$nav = ztheme('admin_nav', $nav);
	array_unshift($rows,array('label'=>-1,'item'=>$nav));
	$row = ztheme('admin_section_header', zt('Settings Navigation'), 'settings_nav', 'l=20');
	$rows[] = array('label'=>-1, 'item'=>'<input type="submit" value="'.zt('Update').'"/>', 'class'=>'center');
	array_unshift($rows,array('label'=>-1,'item'=>$row));
	$install = false;
	if ($zc['database'] && file_exists($zc['zina_dir_abs'].'/install.php') && !zdbq('SELECT 1 FROM {file_ratings} LIMIT 0')) {
		$funcs['install']['install_db'] = '<h2>'.zl(zt('Install the database'), $path, 'l=18&amp;m=install').'</h2>';
		$install = true;
	}

	$lang_while = zt('This could take a while.');
	$lang_sure = zt('Are your sure you want to do this?');

	if ($zc['database'] && !$install && file_exists($zc['zina_dir_abs'].'/update.php')) {
		require_once($zc['zina_dir_abs'].'/update.php');
		if ($update = zina_updates_check()) {
			$funcs['install']['update_db'] = '<h2>'.zl(zt('Database Update: ').zt($update), $path, 'l=18&amp;m=update').'</h2>';
		}
	}

	if ($zc['genres'] && $zc['database']) $funcs['general']['genre_hierarchy'] = zl(zt('Genres and Hierarchy'),$path,'l=48');

	$funcs['cron']['regen'] = zl(zt('Regenerate Directory/File Caches'),$path,'l=26&amp;m=1',NULL,FALSE,
		" onclick='return(confirm(\"".$lang_while.'\\n\\n'.$lang_sure."\"))'");

	if ($zc['genres']) {
		$funcs['cron']['regen_genres'] = zl(zt('Regenerate Genre Cache'),$path,'l=26&amp;m=2',NULL,FALSE,
			" onclick='return(confirm(\"".$lang_while.'\\n\\n'.$lang_sure."\"))'");
	}
	if ($zc['database']) {
		$db_search_text = ($zc['db_search']) ? ' / '. zt('Update search index') : '';
		$funcs['cron']['populate_db'] = zl(zt('Populate the database with missing entries'),$path,'l=26&amp;m=3',NULL,FALSE,
			" onclick='return(confirm(\"".$lang_while.' '.zt('Especially the first time!').'\\n\\n'.$lang_sure."\"))'");

		$funcs['adv']['repopulate_db'] = zl(zt('Synchronize database to filesystem').$db_search_text,$path,'l=26&amp;m=4',NULL,FALSE,
			" onclick='return(confirm(\"".$lang_while.'\\n\\n'.$lang_sure."\"))'");
		$help['repopulate_db'] = zt('This will re-populate the database with information from the entire filesystem.').' '.
			zt('It should only be necessary after upgrading your database or if files are changed and their timestamps are not modified for some reason.');

		$funcs['adv']['sync_db'] = zl(zt('Clean up database'),$path,'l=27',NULL,FALSE,
			" onclick='return(confirm(\"".$lang_while.'\\n\\n'.$lang_sure."\"))'");
		$help['sync_db'] = zt('This will find entries in the database that no longer exist in the filesystem and give you an opportunity to delete, ignore or update them.');

		if ($zc['cache_stats']) {
			$funcs['cron']['regen_stats'] = zl(zt('Regenerate Statistics'),$path,'l=47',NULL,FALSE,
				" onclick='return(confirm(\"".$lang_while.'\\n\\n'.$lang_sure."\"))'");
		}

		if (is_writeable($zc['mp3_dir'])) {
			$funcs['general']['extract_images'] = zl(zt('Extract ID3 images'),$path,'l=26&m=5',NULL,FALSE,
				" onclick='return(confirm(\"".$lang_while.'\\n\\n'.$lang_sure."\"))'");
		}
	}
	$funcs['general']['find_images'] = zl(zt('Find Missing Images/Artwork'),null,'l=62');

	if ($zc['cache_tmpl']) {
		$funcs['adv']['delete_cache'] = zl(zt('Delete template and statistics cache files'),$path,'l=30',NULL,FALSE,
			" onclick='return(confirm(\"".$lang_sure."\"))'");
	}
	if ($zc['cache_imgs']) {
		$funcs['adv']['delete_imgs_cache'] = zl(zt('Delete cached images files'),$path,'l=31',NULL,FALSE,
			" onclick='return(confirm(\"".$lang_sure."\"))'");
	}

	if ($zc['cmp_cache'] && is_writeable($zc['cache_zip_dir'])) {
		$funcs['adv']['delete_zip_cache'] = zl(zt('Delete cached compressed files'),$path,'l=34',NULL,FALSE,
			" onclick='return(confirm(\"".$lang_sure."\"))'");
	}

	if ($zc['sitemap']) {
	  	if (file_exists($zc['cache_dir_public_abs'].'/'.$zc['sitemap_file'])) {
			$funcs['cron']['sitemap'] = zl(zt('Sitemap: Delete cached file'), $path, 'l=33');
		} else {
			$funcs['cron']['sitemap'] = zl(zt('Sitemap: View and generate cached file'), $path, 'l=51');
		}
	}

	$funcs['adv']['getlang'] = zl(zt('Get language phrases for translation or modifications'),$path,'l=32');

	if ($zc['database']) {
		$pls = zina_get_playlists_custom();
		if (!empty($pls)) {
			$funcs['adv']['importpls'] = zl(zt('Import Old Format Playlists'),$path,'l=67');
		}
	}

	$funcs['help']['help'] = zl(zt('Help and Support Information'),$path,'l=64');

	if ($zc['lastfm'] && file_exists($zc['cache_dir_private_abs'].'/scrobbler_queue_'.$zc['user_id'].'.txt')) {
		$funcs['general']['lastfm_queue'] = zl(zt('Manually submit last.fm queue'), $path, 'l=19');
	}

	$lang = array(
		'install' => array(
			'title' => zt('Install & Updates'),
			'desc' => zt('These should be run immediately'),
		),
		'general' => array(
			'title' => zt('General'),
		),
		'cron' => array(
			'title' => zt('Cron'),
			'desc' => zt('All these functions will be run automatically if your CMSes cron functionality is setup, or if the cron.php file in the zina root directly is setup to run regularly (via cron?).  If not, you may manually run them here.'),
		),
		'adv' => array(
			'title' => zt('Advanced'),
			'desc' => zt('These should not be needed very often.'),
		),
		'help' => array(
			'title' => zt('Help and Support'),
		),
	);

	$output = '<table><tr>';
	$class = 'zina-list';

	$row = 0;
	foreach($lang as $func => $item) {
		if ($row == 0) $output .= '<td width="50%" valign="top" style="padding-right:10px;">';
		if (isset($funcs[$func])) {
			$output .= '<h3 style="font-weight:bold;font-size:1.1em;">'.$item['title'].'</h3>';
			if (isset($item['desc'])) $output .= '<span>'.$item['desc'].'</span>';

			$output .= '<ul class="zina-list">';
			foreach ($funcs[$func] as $key => $i) {
				$output .= '<li class="zina-list">'.$i;
				if (isset($help[$key])) $output .= '<br/><small>'.$help[$key].'</small>';
				$output .= '</li>';
			}
			$output .= '</ul>';

			unset($funcs[$func]);
		}
		$row = ++$row % 3;
		if ($row == 0) $output .= '</td>';
	}
	if ($row != 0) $output .= '</td>';
	$output .= '</tr></table>';

	$row1 = array('label'=>-1, 'item'=> ztheme('admin_section_header', zt('Administrative Functions'), 'top').'<br/>'.$output);

	array_unshift($rows,$row1);

	$form = array(
		'attr'=>'action="'.zurl($path, 'l=21').'" name="zinaadminform"',
		'rows'=>$rows,
	);

	return ztheme('config',$form);
}

function zina_content_pathlinks($path, $type, $play, $title = false, $id3 = false) {
	global $zc;
	$arr = explode('/', $path);
	$num = sizeof($arr);

	if (sizeof($arr) == 1 && ($arr[0] == '.' || $arr[0] == '/' || $arr[0] == '\\')) {
		return zl(zt($zc['main_dir_title']), '');
	}

	$titles = array();
	$ppath = '';

	for ($i=0; $i < $num; $i++) {
		$part = $arr[$i];
		if ($ppath != '') $ppath = $ppath.'/';
		if (zvalidate_utf8($part)) $part = utf8_decode($part);
		$ppath .= $part;
		if ($i == $num - 1) {
			if ($type == 's') {
				# Sloooowww... (not used)
				if ($id3) {
					$song = $zc['mp3_dir'].'/'.$path;
					$ft = zina_content_song_title($song, true);
				} else {
					if ($title)
						$ft = zina_content_song_title($title);
					else
						$ft = zina_content_song_title($path);
				}

				$titles[] = ($play) ? zl($ft,$ppath,'l=8&amp;m=1', null, false, 'class="zinamp"') : $ft;
			} else {
				if ($part == '.lp') continue;
				$titles[] = zl(ztheme('title',$part),$ppath);
			}
		} else {
			$titles[] = zl(ztheme('title',$part),$ppath);
		}
	}
	return ztheme('search_song_titles', $titles);
}

function zina_content_form_helper($name, $field, $setting, $disable = false) {
	$type = $field['type'];
	$foo = false;
	$value = ($field['def'] == $setting) ? $field['def'] : $setting;
	$disabled = ($disable) ? ' disabled="disabled"' : '';
	$disabled = ($disable) ? ' disabled="disabled" class="disabled"' : '';
	$id = (isset($field['id'])) ? ' id="'.$field['id'].'"' : '';

	$i = 0;
	$item = '';
	if ($type == 'textarea') {
		$cols = (isset($field['cols'])) ? $field['cols'] : 50;
		$rows = (isset($field['rows'])) ? $field['rows'] : 8;
		$item = '<textarea name="'.$name.'" cols="'.$cols.'" rows="'.$rows.'"'.$id.'>'.$value.'</textarea>';
	} elseif ($type == 'radio') {
		$opts = (is_array($field['opts'])) ? $field['opts'] : call_user_func($field['opts']);
		foreach($opts as $key=>$val) {
			$checked = '';
			if ($value == $key) $checked = ' checked="checked"';
			$item .= '<input type="radio" name="'.$name.'" value="'.$key.'"'.$checked.$disabled.$id.' /> '.$val.' ';
		}
	} elseif ($type == 'textfield') {
		$size = (isset($field['size'])) ? $field['size'] : 30;
		if(!empty($value)) {
			if (is_array($value)) $value = implode(',',$value);

			$len = strlen($value);
			if (!isset($field['size'])) {
				$len = strlen($value);
				if ($len < 30) $size = $len+2;
				elseif ($len < 60) $size = $len+10;
				else $size = 60;
			} else {
				if ($len > $size) $size = $len+10;
			}
			if (isset($field['max']) && $size > $field['max']) $size = $field['max'];
		}
		$item = '<input type="text" name="'.$name.'" value="'.$value.'" size="'.$size.'"'.$disabled.$id.'/>';
		if (isset($field['suf'])) $item .= ' '.zt($field['suf']);
	} elseif ($type == 'select') {
		$tmp = $value;
		$opts = (is_array($field['opts'])) ? $field['opts'] : call_user_func($field['opts']);
		$item = '<select name="'.$name.'"'.$disabled.$id.'>';
		foreach($opts as $key=>$val) {
			$sel = '';
			if ($value == $key) $sel = ' selected="selected"';
			$item .= '<option value="'.$key.'"'.$sel.'>'.$val.'</option>';
		}
		$item .= '</select>';
	} elseif ($type == 'password_adm') {
		$item = '<input type="password" name="'.$name.'"'.$disabled.' />';
	} elseif ($type == 'password') {
		$item = '<input type="password" name="'.$name.'" value="'.$value.'"'.$disabled.'/>';
	} elseif ($type == 'checkboxes') {
		$opts = (is_array($field['opts'])) ? $field['opts'] : call_user_func($field['opts']);
		foreach($opts as $key=>$val) {
			$checked = '';
			if (in_array($key, $value)) $checked = ' checked="checked"';
			$item .= '<input type="checkbox" name="'.$name.'[]" value="'.$key.'"'.$checked.$disabled.$id.' /> '.$val.' ';
		}
	} elseif ($type == 'hidden') {
		#todo: use ztheme('form_hidden', $name, $value); #also for all other hiddens
		$item .= '<input type="hidden" name="'.$name.'" value="'.$value.'"'.$id.'/>';
	} elseif ($type == 'checkbox') {
		$checked = (!empty($value)) ? ' checked="checked"' : '';
		$onclick = (isset($field['onclick'])) ? " onClick='".$field['onclick']."'" : '';
		$item .= '<input type="checkbox" name="'.$name.'" '.$checked.$disabled.$id.$onclick.' />';
	}

	# only text, radio, password utilize...
	if ($disable) {
		$field['type'] = 'hidden';
		$item .= zina_content_form_helper($name, $field, $setting);
	}

	return $item;
}

function zina_content_subdir_opts(&$item, $path, $checkbox, $opts=null) {
	global $zc;

	if ($zc['play']) {
		if (strpos($path, '/') !== false) {
			$item['opts']['play'] = array('path'=>$path, 'query'=>'l=8&amp;m=0');
		} elseif ($zc['play_rec_rand']) {
			$item['opts']['play_rec_rand'] = array('path'=>$path, 'query'=>'l=8&amp;m=10&amp;c');
		}
	}
	if ($zc['low'] && ($zc['resample'] || (isset($opts['lofi']) && !empty($opts['lofi'])))) {
		$item['opts']['play_lofi'] = array('path'=>$path, 'query'=>'l=8&amp;m=0&amp;lf');
	}
	if ($zc['cmp_sel']) {
		if (!(isset($item['type']) && $item['type'] == 'artist')) {
			$item['opts']['download'] = array('path'=>$path.'/.lp', 'query'=>'l=5');
		}
	}
	if ($checkbox) {
		$item['checkbox'] = array('name'=>'mp3s[]', 'value'=>zrawurlencode($path).'/.lp', 'checked'=>false);
	}
}

/*
 * $song must be full path
 */
function zina_content_song_title($song, $id3 = false) {
	global $zc;

	if ($id3) {
		$mp3 = zina_get_file_info($song, false);
		if ($mp3->tag) return ztheme('song_title', $mp3->title, true);
	}

	$ext = ($zc['remote']) ? $zc['ext_mus'].'|'.$zc['remote_ext'] :  $zc['ext_mus'];
	$ext = ($zc['fake']) ? $ext.'|'.$zc['fake_ext'] :  $ext;
	$song = preg_replace('/\.('.$ext.')$/i', '', basename($song));

	return ztheme('song_title', $song);
}

function zina_content_playlist_form($item) {
	global $zc;

	$rows[] = array('label'=>zt('Title'),
		'item'=>zina_content_form_helper('title', array('type'=>'textfield', 'def'=>'', 'size'=>30, 'v'=>array('req')), htmlentities($item['title'])));
	$rows[] = array('label'=>zt('Description'),
		'item'=>zina_content_form_helper('description', array('type'=>'textarea', 'def'=>''), htmlentities($item['description'])),
		'help'=>zt('HTML tags allowed: @tags', array('@tags' => $zc['pls_tags'])));


	if ($zc['genres']) {
		$genres = zdbq_assoc_list("SELECT id, genre FROM {genres} ORDER BY genre");
		if (!empty($genres)) {
			$row = '<select name="genre_id">'.
				'<option value="">'.zt('None').'</option>';
			foreach($genres as $key => $genre) {
				$selected = ($key == $item['genre_id']) ? ' selected="selected"' : '';
				$row .= '<option value="'.$key.'"'.$selected.'>'.htmlentities($genre).'</option>';
			}
			$row .= '</select>';
			$rows[] = array('label'=>zt('Genre'),'item'=>$row);
		}
	}

	#TODO: if art/tit
	$dirs = zdbq_assoc_list("SELECT id, path FROM {dirs} WHERE level = 1 AND path != '.' ORDER by path");
	if (!empty($dirs)) {
		$row = '<select name="dir_id">';
		$row .= '<option value="">'.zt('None').'</option>';
		foreach($dirs as $key=> $dir) {
			$selected = ($key == $item['dir_id']) ? ' selected="selected"' : '';
			$row .= '<option value="'.$key.'"'.$selected.'>'.htmlentities($dir).'</option>';
		}
		$row .= '</select>';
		$rows[] = array('label'=>zt('Arist'),'item'=>$row);
	}

	$row = '<select name="image_type">';
	$types = array(0=>zt('None'), 1=>zt('Artist'));
	if ($zc['genres']) $types[2] = zt('Genre');

	foreach($types as $key=> $type) {
		$selected = ($key == $item['image_type']) ? ' selected="selected"' : '';
		$row .= '<option value="'.$key.'"'.$selected.'>'.$type.'</option>';
	}
	$row .= '</select>';
	$rows[] = array('label'=>zt('Show Image'),'item'=>$row);

	if ($zc['pls_public']) {
		$rows[] = array('label'=>zt('Public'),
			'item'=>zina_content_form_helper('visible', array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>true), $item['visible']));
	}
	return $rows;
}
#TODO: move
#$type = insert or update
function zina_playlist_form_submit($type, $pls_id = null) {
	global $zc;

	if ($type == 'update') {
		$user_id = zdbq_single("SELECT user_id FROM {playlists} WHERE id = $pls_id");
	} else {
		$user_id = $zc['user_id'];
	}

	if (!zina_cms_access('edit_playlists', $user_id)) return zina_access_denied();

	$visible = (isset($_POST['visible'])) ? (int)$_POST['visible'] : 1;

	$where = (!$zc['is_admin']) ? ' AND user_id = %d' : '';

	$dir_id = $genre_id = null;

	if (!empty($_POST['dir_id'])) {
		$exists = zdbq_single("SELECT 1 FROM {dirs} WHERE id = %d", array($_POST['dir_id']));
		if ($exists) $dir_id = $_POST['dir_id'];
	}
	if (!empty($_POST['genre_id'])) {
		$exists = zdbq_single("SELECT 1 FROM {genres} WHERE id = %d", array($_POST['genre_id']));
		if ($exists) $genre_id = $_POST['genre_id'];
	}
	$image_type = (in_array($_POST['image_type'], array(0,1,2))) ? $_POST['image_type'] : 0;

	$title = strip_tags($_POST['title']);
	$desc = strip_tags($_POST['description'], $zc['pls_tags']);
	$time = time();

	if ($type == 'update') {
		if (zdbq("UPDATE {playlists} SET title = '%s', description = '%s', dir_id = %d, genre_id = %d, image_type = %d, visible = %d, mtime = %d ".
			"WHERE id = %d $where",
			array($title, $desc, $dir_id, $genre_id, $image_type, $visible, $time, $pls_id, $zc['user_id']))) {
			return true;
		}
	} else {
		if (zdbq("INSERT {playlists} (title, description, user_id, date_created, visible, mtime, dir_id, genre_id, image_type) ".
				"VALUES ('%s', '%s', %d, %d, %d, %d, %d, %d, %d)",
				array($title, $desc, $zc['user_id'], $time, $visible, $time, $dir_id, $genre_id, $image_type))) {
			return zdb_last_insert_id('playlists', 'id');
		}
	}
	zina_set_message(zt('Cannot @type playlist.', array('@type'=>$type)), 'warn');
	return false;
}

function zina_content_playlist_form_opts($form, $playlist_sel='', $type='l a x p r q v') {
	global $zc;

	$options = array_flip(explode(' ', $type));
	$sess = (isset($_SESSION['z_sp']) && strlen($_SESSION['z_sp']) > 0) ? 1 : 0;
	$lang_no = zt('No playlist selected.');

	$opts_url = array(
		'd' => "javascript:selectCheck('$form','".zurl(null,'l=43')."', '".zt('Nothing to delete.')."', $sess, 'view');",
		'a' => "javascript:selectCheckAjax('".zurl(null,'l=40')."', '$form', 'zina_messages', '".zt('Nothing to add to playlist.')."');",
		'v' => "javascript:selectCheck('$form','".zurl(null,'l=2')."','".zt('Nothing to view.')."', $sess, 'view');",
		'p' => "javascript:selectCheck('$form','".zurl(null,'l=8&amp;m=3')."','".$lang_no."', $sess, 'play');",
		'r' => "javascript:selectCheck('$form','".zurl(null,'l=8&amp;m=3&amp;rand')."','".$lang_no."', $sess, 'play');",
		'q' => "javascript:selectCheck('$form','".zurl(null,'l=8&amp;m=3&amp;lf')."','".$lang_no."', $sess, 'play');",
		'l' => 'l=2'
	);

	if (!$zc['play']) unset($options['p']);
	if (!$zc['low']) unset($options['q']);
	if (!$zc['database']) unset($options['r']);

	if ($zc['database']) {
		unset($options['d']); # let them do it through playlist interface
		$playlists = array();
		$access = false;
		if (zina_cms_access('edit_playlists', $zc['user_id'])) {
			$playlists = zdbq_array("SELECT id, title FROM {playlists} WHERE user_id = %d ORDER BY title", array($zc['user_id']));
			$access = true;
		}

		$display = ($zc['is_admin'] || $zc['session_pls'] || $zc['pls_user'] || !empty($playlists));

		if (!$zc['is_admin'] && !$zc['session_pls'] && !$zc['pls_user']) unset($options['a']);

		$options = array_flip($options);

		$new = ($access && strstr($type,'a'));
	} else {
		$playlists = zina_get_playlists_custom();
		$display = (!$zc['is_admin'] && (empty($playlists) && !$zc['session_pls'])) ? false : true;

		if (!$zc['is_admin'] || !$zc['cache']) {
			unset($options['d']);
			if (!$zc['session_pls']) unset($options['a']);
		}
		$options = array_flip($options);

		$new = ($zc['is_admin'] && strstr($type,'a') && $zc['cache']);
	}
	$pl = ztheme('playlist_form_select', 'playlist', $playlists, $playlist_sel, $new, $zc['session_pls'], $zc['database']);

	return ztheme('playlist_form_elements', $display, $form, $options, $opts_url, $pl);
}

function zina_content_song_list_opts($custom_pls, $lofi, $check_boxes, $form, $search = false) {
	global $zc;
	$items = array();
	if (!$search && $zc['is_admin'] && $zc['cache']) {
		$items['submit']['js'] = 'SubmitForm(\''.$form.'\',\'?l=42\', \'add\');';
		$items['submit']['exists'] = $custom_pls;
	}

	if ($check_boxes) {
		$items['checkboxes'] = true;
		$err = zt('At least one song must be selected.');

		if ($zc['play_sel'] || $zc['cmp_sel']) {
			if ($zc['play'] && $zc['play_sel']) {
				$items['opts']['play'] = array('path'=>'javascript:CheckIt(\''.$form.'\',\''.zurl('','l=8&amp;m=7').'\',\''.$err.'\',\'play\');',
					'query'=>null, 'attr'=>null);
			}
			if ($zc['play'] && $zc['play_rec_rand']) {
				$items['opts']['play_rec_rand'] = array('path'=>'javascript:CheckIt(\''.$form.'\',\''.zurl('','l=8&amp;m=8').'\',\''.$err.'\',\'play\');',
					'query'=>null, 'attr'=>null);
			}
			if ($lofi) {
				$items['opts']['play_lofi'] = array('path'=>'javascript:CheckIt(\''.$form.'\',\''.zurl('','l=8&amp;m=7&amp;lf').'\',\''.$err.'\',\'play\');',
					'query'=>null, 'attr'=>null);
			}
			if ($zc['cmp_sel']) {
				$items['opts']['download'] = array('path'=>'javascript:CheckIt(\''.$form.'\',\''.zurl('','l=3').'\',\''.$err.'\',\'download\');',
					'query'=>null, 'attr'=>null);
			}
		}
	}
	$items['playlist_form_elements'] = zina_content_playlist_form_opts($form);
	return $items;
}

#TODO: ADD type = search, genre, year ???
function zina_search_pager_query($term, $query, &$count, &$navigation, $sql = array(), $genre=false, &$selected = null) {
	global $zc;

	$cstart = 0;
	$per_page = $zc['search_pp'];

	if (!empty($sql)) {
		$type_opts = array_keys(zina_search_opts_type());
		$order_opts = array_keys(zina_search_opts_order());
		$sort_opts = array_keys(zina_search_opts_sort());
		$per_page_opts = array_keys(zina_search_opts_per_page());

		if (isset($_POST['type'])) {
			$type = $_POST['type'];
		} elseif (!empty($_SESSION['zina']['search_type'])) {
			$type = $_SESSION['zina']['search_type'];
		}
		if (isset($_POST['order'])) {
			$order = $_POST['order'];
		} elseif (!empty($_SESSION['zina']['search_order'])) {
			$order = $_SESSION['zina']['search_order'];
		}
		if (isset($_POST['sort'])) {
			$sort = $_POST['sort'];
		} elseif (!empty($_SESSION['zina']['search_sort'])) {
			$sort = $_SESSION['zina']['search_sort'];
		}
		if (isset($_POST['per_page'])) {
			$per_page = $_POST['per_page'];
		} elseif (!empty($_SESSION['zina']['search_per_page'])) {
			$per_page = $_SESSION['zina']['search_per_page'];
		}
		if (!isset($sort) || !in_array($sort, $sort_opts)) $sort = 'title';
		if (!isset($order) || !in_array($order, $order_opts)) $order = 'asc';
		if (!isset($per_page) || !in_array($per_page, $per_page_opts)) $per_page = $zc['search_pp'];
		if (!isset($type) || !in_array($type, $type_opts)) $type = 'album';

		$selected['order'] = $_SESSION['zina']['search_order'] = $order;
		$selected['sort'] = $_SESSION['zina']['search_sort'] = $sort;
		$selected['per_page'] = $_SESSION['zina']['search_per_page'] = $per_page;
		$selected['type'] = $_SESSION['zina']['search_type'] = $type;

		if ($sort != 'title') {
			$pre = (in_array($sort, array('mtime', 'sum_votes', 'sum_rating'))) ? '' : 'i.';
			$sort = "$pre$sort $order, i.title";
			$order = '';
		}
		$sql['orderby'] = "$sort $order";
		$sql['type'] = '';
		if ($_GET['l'] != 4) {
			$sql['type'] = "i.type = '$type' AND ";
		}
		$count = $cend = zdbq_single("SELECT count(*) FROM {search_index} AS i WHERE ".$sql['type'].$sql['where'], array($term));
	} elseif ($genre) {
		$genres = zina_core_cache('genre');
		$found = isset($genres[$term]) ? $genres[$term] : array();
		$count = $cend = sizeof($found);
	} else {
		$results = zina_search_cache($term);
		$count = $cend = count($results);
	}

	if ($count > $per_page) {
		$page = isset($_GET['page']) ? $_GET['page'] : 1;
		$pages_total = ceil($count/$per_page);
		if (!zina_validate('int', $page) || $page < 1 || $page > $pages_total) $page = 1;
		$navigation = ztheme('category_pages', null, $page, $pages_total, $query.'&amp;pl='.rawurlencode($term).'&amp;');
		$cstart = ($page - 1) * $per_page;
		if ($cstart > $count) $cstart = 0;

		$cend = $per_page;
	}

	if (!empty($sql)) {
		return zdbq_array("SELECT i.path, i.type, i.title, i.context, i.genre, i.year, i.file_mtime as mtime, ".
			"if(i.type='song', f.sum_votes, d.sum_votes) as sum_votes, ".
			"if(i.type='song', f.sum_rating, d.sum_rating) as sum_rating, f.id3_info ".
			",if(i.type='playlist', p.image_type, FALSE) as image_type ".
			",if(i.type='playlist', pd.path, FALSE) as image_path ".
			",if(i.type='playlist', p.id, FALSE) as id ".
			"FROM {search_index} as i ".
			"LEFT OUTER JOIN {files} AS f ON (i.type = 'song' AND i.type_id = f.id) ".
			"LEFT OUTER JOIN {dirs} AS d ON (i.type != 'song' AND i.type !='playlist' AND i.type_id = d.id) ".
			"LEFT OUTER JOIN {playlists} AS p ON (i.type = 'playlist' AND i.type_id = p.id) ".
			"LEFT OUTER JOIN {dirs} AS pd ON (i.type = 'playlist' AND i.type_id = p.id AND p.dir_id = pd.id) ".
			"WHERE ".$sql['type'].$sql['where']." ORDER BY ".$sql['orderby']." LIMIT %d OFFSET %d",
			array($term, $cend, $cstart));
	} elseif ($genre) {
		$results = array();
		$found = array_slice($found, $cstart, $cend);
		foreach($found as $path) $results[] = zina_search_dir_helper($path);
		return $results;
	} else {
		return array_slice($results, $cstart, $cend);
	}
}

function zina_playlist_pager_query($query, &$count, &$navigation, &$selected = null, $dir_id = false) {
	global $zc;

	$cstart = 0;
	$per_page = $zc['search_pp'];
	$where = '';
	$terms  = array();

	if ($zc['pls_user']) {
		$all_opts = array_keys(zina_playlist_opts_all());
		if (isset($_POST['all'])) {
			$all = $_POST['all'];
		} elseif (!empty($_SESSION['zina']['playlist_all'])) {
			$all = $_SESSION['zina']['playlist_all'];
		}
		if (!isset($all) || !in_array($all, $all_opts)) $all = 'all';
		$selected['all'] = $_SESSION['zina']['playlist_all'] = $all;

		if ($all == 'all') {
			if (!$zc['is_admin']) {
				if ($zc['pls_public']) {
					$where = 'p.user_id = %d OR p.visible = 1';
					$terms[] = $zc['user_id'];
				} else {
					$where = 'p.user_id = %d';
					$terms[] = $zc['user_id'];
				}
			}
		} else { #single
			$where = 'p.user_id = %d';
			$terms[] = $zc['user_id'];
		}
	} else { # no user playlists
		if ($zc['pls_public']) {
			$where = 'visible = 1';
			if (!$zc['is_admin']) return array();
		}
	}

	$sort_opts = array_keys(zina_playlist_opts_sort());
	$order_opts = array_keys(zina_search_opts_order());

	if (isset($_POST['sort'])) {
		$sort = $_POST['sort'];
	} elseif (!empty($_SESSION['zina']['playlist_sort'])) {
		$sort = $_SESSION['zina']['playlist_sort'];
	}

	if (isset($_POST['order'])) {
		$order = $_POST['order'];
	} elseif (!empty($_SESSION['zina']['playlist_order'])) {
		$order = $_SESSION['zina']['playlist_order'];
	}

	if (!isset($sort) || !in_array($sort, $sort_opts)) $sort = 'playlist';
	if (!isset($order) || !in_array($order, $order_opts)) $order = 'asc';

	$selected['sort'] = $_SESSION['zina']['playlist_sort'] = $sort;
	$selected['order'] = $_SESSION['zina']['playlist_order'] = $order;

/*
	$per_page_opts = array_keys(zina_search_opts_per_page());

	if (isset($_POST['per_page'])) {
		$per_page = $_POST['per_page'];
	} elseif (!empty($_SESSION['zina']['search_per_page'])) {
		$per_page = $_SESSION['zina']['search_per_page'];
	}
	if (!isset($per_page) || !in_array($per_page, $per_page_opts)) $per_page = $zc['search_pp'];

	$selected['per_page'] = $_SESSION['zina']['search_per_page'] = $per_page;

	if ($sort != 'title') {
		$pre = (in_array($sort, array('mtime', 'sum_votes', 'sum_rating'))) ? '' : 'i.';
		$sort = "$pre$sort $order, i.title";
		$order = '';
	}
	$sql['orderby'] = "$sort $order";
 */
	if ($dir_id) {
		$where = "WHERE (dd.id = %d OR f.dir_id = %d)". ((!empty($where)) ? " AND ($where)" : '');
		array_unshift($terms, $dir_id);
		array_unshift($terms, $dir_id);

		$count = $cend = zdbq_single("SELECT count(DISTINCT p.id) FROM {playlists} AS p ".
			"INNER JOIN {playlists_map} as pm ON (p.id = pm.playlist_id) ".
			"LEFT OUTER JOIN {files} AS f ON (pm.type = 'song' AND pm.type_id = f.id) ".
			"LEFT OUTER JOIN {dirs} AS dd ON (pm.type = 'album' AND pm.type_id = dd.id) ".
			"$where GROUP BY p.id", $terms);
	} else {
		$joins = '';
		$where = (!empty($where)) ? "WHERE ".$where : '';
		$count = $cend = zdbq_single("SELECT count(*) FROM {playlists} AS p ".$where, $terms);
	}

	if ($sort != 'playlist') {
		if ($sort == 'genre') {
			$sort = "g.genre IS NULL, g.genre $order, playlist";
		} else {
			$sort = "$sort $order, playlist";
		}
		$order = '';
	}
	$orderby = "$sort $order";

	if ($count > $per_page) {
		$page = isset($_GET['page']) ? $_GET['page'] : 1;
		$pages_total = ceil($count/$per_page);
		if (!zina_validate('int', $page) || $page < 1 || $page > $pages_total) $page = 1;
		$navigation = ztheme('category_pages', null, $page, $pages_total, $query.'&amp;');
		$cstart = ($page - 1) * $per_page;
		if ($cstart > $count) $cstart = 0;

		$cend = $per_page;
	}

	if ($dir_id) {
		$sql = "SELECT p.title as playlist, p.*, d.path AS image_path, g.genre ".
			"FROM {playlists} as p ".
			"INNER JOIN {playlists_map} as pm ON (p.id = pm.playlist_id) ".
			"LEFT OUTER JOIN {dirs} AS d ON (p.dir_id = d.id) ".
			"LEFT OUTER JOIN {genres} AS g ON (p.genre_id = g.id) ".
			"LEFT OUTER JOIN {files} AS f ON (pm.type = 'song' AND pm.type_id = f.id) ".
			"LEFT OUTER JOIN {dirs} AS dd ON (pm.type = 'album' AND pm.type_id = dd.id) ".
			"$where ".
			"GROUP BY p.id ".
			"ORDER BY $orderby LIMIT %d OFFSET %d";
	} else {
		$sql = "SELECT p.title as playlist, p.*, d.path AS image_path, g.genre ".
			"FROM {playlists} AS p ".
			"LEFT OUTER JOIN {dirs} AS d ON (p.dir_id = d.id) ".
			"LEFT OUTER JOIN {genres} AS g ON (p.genre_id = g.id) ".
			$where." ORDER BY ".$orderby." LIMIT %d OFFSET %d";
	}

	$terms[] = $cend;
	$terms[] = $cstart;

	return zdbq_array($sql, $terms);
}

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * GET functions
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

/*
 * Almost all of the information for directories and files
 * is done through this function
 *
 * @dir is full path relative to mp3_dir
 * @category is bool
 */

#todo: could potentially use db for category as opt?
function zina_get_directory($root, $category = false, $opts = array()) {
	global $zc;
	$opts += array(
		'cat_images' => false,
		'get_files' => true,
		'dir_tags' => $zc['dir_tags']
	);

	$current_full_path = $zc['mp3_dir'].(!empty($root) ? '/'.$root : '');
	$current_path = (!empty($root)) ? $root.'/' : '';

	if (!$result = @scandir($current_full_path)) {
		zina_debug(zt('Could not read directory'));
		return array();
	}

	$mp3_dir = $zc['mp3_dir'];
	$ext_mus = $zc['ext_mus'];
	$dir_skip = $zc['dir_skip'];
	$dir_tags = $opts['dir_tags'];

	$low_look = ($zc['low_lookahead'] && !$category);
	$id3 = ($zc['mp3_id3'] || $zc['mp3_info']);
	$check_boxes = (($zc['is_admin'] && $zc['cache']) || ($zc['playlists'] && $zc['session_pls']) || $zc['play_sel'] || $zc['cmp_sel']);
	$rating = ($zc['database'] && $zc['rating_files']);

	$dir['lofi'] = false;
	$dir['images'] = $dir['subdirs'] = $dir['files'] = $dir['captions'] = $dir = array();
	$dir['image'] = null;
	$dir['dir_write'] = $dir_write = ($zc['is_admin'] && is_writeable($zc['cur_dir']));
	$dir['dir_edit'] = $dir_edit = (zina_cms_access('editor') && ($zc['database'] || ($zc['is_admin'] && $dir_write)));

	$subdirs = &$dir['subdirs'];
	$files = &$dir['files'];
	$db_subdirs = array();
	$db_dir = array();
	$db_files = array();
	$db = false;
	$file_dir_info = false;


	$now = time();
	$diff = $zc['new_time']*86400;
	#todo: cat_sort is overridable...?
	$get_mtime = ($zc['new_highlight'] || $zc['cat_sort']);

	if ($zc['database']) {
		$db_path = (empty($root)) ? '.' : $root;
		$db_dir = zdbq_array("SELECT * FROM {dirs} WHERE path = '%s'", $db_path);
		if (!empty($db_dir)) {
			$db = true;
			$dir = array_merge($dir, $db_dir[0]);
			$dir_other = unserialize_utf8($dir['other']);
			if (!empty($dir_other)) {
				$dir = array_merge($dir, $dir_other);
			}

			if ($opts['get_files']) {
				$db_files = zdbq_assoc('full_path',
					"SELECT *, IF(path != '.', CONCAT(path, IF(ISNULL(path), '', '/'), file), file) AS full_path ".
					"FROM {files} WHERE dir_id = %d", $dir['id']);
			}

			if (!isset($dir['various'])) {
				$dir['various'] = ($zc['various'] && zina_is_various($current_full_path, $root));
			} else {
				$dir['various'] = ($dir['various'] && $zc['various']);
			}
#TODO: get_files???
			if (!$category || ($dir_tags && $dir['various']) || $opts['cat_images']) {
				$db_subdirs = zdbq_assoc('path',
					"SELECT d.*, f.id3_info FROM {dirs} AS d LEFT OUTER JOIN {files} AS f ON d.id = f.dir_id ".
					"WHERE  d.parent_id = %d", $dir['id']);
			} else {
				$db_subdirs = zdbq_assoc('path', "SELECT * FROM {dirs} WHERE parent_id = %d", $dir['id']);
			}
		}

		#todo: logic is old...
		if ($opts['get_files'] && (($zc['rating_files'] && $zc['rating_files_public']) || $zc['is_admin'])) {
			$ratings = true;
			if ($zc['user_id'] > 0) {
				$user_ratings = zdb_get_song_user_ratings($root, $zc['user_id']);
			}
		}
	}

	if (!isset($dir['various'])) {
		$dir['various'] = ($zc['various'] && zina_is_various($current_full_path, $root));
	}
	#TODO: zc['cat_split']?  wha about dir overrides???
	$various_lookahead = ($category && $zc['various'] && $zc['cat_various_lookahead'] && ($zc['cat_split'] == 2 || $zc['cat_split'] == 3));

	# Custom Playlists
	$c_pls = $zc['cache_pls_dir'].'/'.str_replace('/',' - ',$root).'.m3u';
	if ($zc['cache'] && $opts['get_files'] && file_exists($c_pls)) {
		$c_pls_arr = zunserialize_alt(file_get_contents($c_pls));
	} else {
		$c_pls_arr = array();
	}

	# will probably fuck up on non-Art/Dir structures?  test!
	if ($dir_tags) {
		if (isset($dir['title']) && !$category) {
			if (strpos($root, '/') === false) $dir['title'] = null;
		} else {
			$dir['title'] = null;
		}
	} else {
		$dir['title'] = null;
	}

	foreach($result as $filename) {
		#$dir_tags = $opts['dir_tag'];
		if ($filename == '.' || $filename == '..') continue;
		$full_path = $current_full_path.'/'.$filename;
		$path = $current_path.$filename;
		$path_enc = utf8_encode($path);

		if (isset($db_subdirs[$path_enc]) || is_dir($full_path)) {
	  		if ($filename[0] == $dir_skip) continue;
			$subdir = &$subdirs[$path];

			$subdir = array(
				'image' => null,
				'query' => null,
				'path' => $path,
				'new' => false,
				'various' => null,
				'person' => false,
			);

			if (isset($db_subdirs[$path_enc]['other'])) {
				$other = unserialize_utf8($db_subdirs[$path_enc]['other']);

				if (isset($other['image'])) {
					if ($other['image']) $subdir['image'] = $other['image'];
					$subdir['various'] = $other['various'];
					$subdir['category'] = $other['category'];
					if (isset($other['person'])) $subdir['person'] = $other['person'];
				}
			}

			if (empty($subdir['image']) && (!$category || $opts['cat_images'])) {
				$subdir['image'] = zina_get_dir_item($full_path,'/\.('.$zc['ext_graphic'].')$/i');
			}

			if (is_null($subdir['various']) && $various_lookahead){
				$subdir['various'] = file_exists($full_path.'/'.$zc['various_file']);
			}
/*
 * TODO: XXX QQQ try to simply below...
 */
			if (!$category && $id3) {
				if (isset($db_subdirs[$path_enc]['id3_info'])) {
					$subdir['info'] = unserialize_utf8($db_subdirs[$path_enc]['id3_info']);
				} else {
					$subdir_file = zina_get_dir_item($full_path,'/\.('.$ext_mus.')$/i');
					if (!empty($subdir_file)) {
						$subdir['info'] = zina_get_file_info($full_path.'/'.$subdir_file, false, true, $zc['genres']);
					}
				}

				if (isset($db_subdirs[$path_enc]) && !empty($db_subdirs[$path_enc]['title'])) {
				#if ($dir_tags && isset($db_subdirs[$path]) && !empty($db_subdirs[$path]['title'])) {
					$subdir['title'] = $db_subdirs[$path_enc]['title'];
				} else if ($dir_tags && isset($subdir['info']->album)) {
					$subdir_title = trim($subdir['info']->album);
					if (!empty($subdir_title)) $subdir['title'] = $subdir_title;
				}
			} elseif ($dir_tags && $id3) {
				if (isset($db_subdirs[$path_enc]) && !empty($db_subdirs[$path_enc]['title'])) {
					$subdir['title'] = $db_subdirs[$path_enc]['title'];
				} #elseif (isset('id3_onfo? ??? for backward compat?

				if ($dir['various'] && isset($db_subdirs[$path_enc]['id3_info'])) {
					$subdir['info'] = unserialize_utf8($db_subdirs[$path_enc]['id3_info']);
				}
			}

			if (!isset($subdir['title'])) {
				$subdir['title'] = ztheme('title',$filename);
			}

			if ((!$category || $opts['cat_images']) && (empty($subdir['image']) || $subdir['image'] == 'cover_id3_zina.jpg') && isset($subdir['info']->image)) {
				$subdir['image'] = 'zina_id3_zina.jpg';
			}

			if ($low_look) {
				$subdir['lofi'] = zina_get_dir_item($full_path, '/('.$zc['low_suf'].')\.('.$zc['ext_mus'].')$/i');
			}
			if ($get_mtime) {
				$subdir['mtime'] = $mtime = (isset($db_subdirs[$path_enc]['mtime'])) ? $db_subdirs[$path_enc]['mtime'] : filemtime($full_path);
				$subdir['new'] = ($zc['new_highlight'] && $now - $mtime < $diff);
			}
			#TODO: see if can be used elsewhere...e.g. other zina_get_dir()s
		} elseif ($opts['get_files']) { # file
			if (preg_match('/\.('.$ext_mus.')$/i', $filename, $matches)) {
				$file_ext = $matches[1];
				$extras = array();
				if ($zc['low'] && preg_match('/('.$zc['low_suf'].')\.('.$ext_mus.')$/i', $filename)) {
					$lofi_path = $path;
					$path = preg_replace('/('.$zc['low_suf'].')\.('.$ext_mus.')$/i', '.$2', $path);
					$dir['lofi'] = $files[$path]['lofi'] = true;
					$files[$path]['lofi_path'] = $lofi_path;
					continue;
				}
				$file = &$files[$path];
				$file['fake'] = $file['remote'] = false;
			} elseif ($filename == $zc['dir_file']) {
				if (!isset($dir['description'])) $dir['description'] = file_get_contents($full_path);
			} elseif (preg_match('/\.('.$zc['ext_graphic'].')$/i', $filename)) {
				$dir['images'][] = $filename;
				if ($zc['image_captions']) {
					if (file_exists($full_path.'.txt')) {
						$dir['captions'][$filename] = rtrim(file_get_contents($full_path.'.txt'),"\r\n");
					} else {
						$dir['captions'][$filename] = '';
					}
				}
			} elseif ($zc['remote'] && preg_match('/\.('.$zc['remote_ext'].')$/i', $filename, $matches)) {
				$file = &$files[$path];
				$file['remote'] = true;
				$file['fake'] = false;
				$file_ext = $matches[1];
			} elseif ($zc['fake'] && preg_match('/\.('.$zc['fake_ext'].')$/i', $filename, $matches)) {
				$file = &$files[$path];
				$file['fake'] = true;
				$file['remote'] = false;
				$file_ext = $matches[1];
			} elseif ($zc['mm'] && preg_match('/\.('.$zc['mm_ext'].')$/i', $filename, $matches)) {
				#todo: consider putting mm in files...
				$mm = &$dir['mm'][$path];
				$mm['full_path'] = $path;
				$mm['path'] = $root;
				$mm['file'] = $filename;
				$mm['filesize'] = filesize($full_path);
				$mm['ext'] = $ext = strtolower($matches[1]);
				$mm['description'] = zina_get_file_desc($zc['song_blurbs'], $full_path.'.txt');
				$mm['new'] = false;
				if ($get_mtime) {
					$mm['mtime'] = $mtime = filemtime($full_path);
					$mm['new'] = ($zc['new_highlight'] && $now - $mtime < $diff);
				}
				$mm['checkbox'] = false;
				$mm['title'] = ztheme('song_title', substr($filename,0,-(strlen($ext)+1)));
				if (isset($zc['mm_types'][$ext]['player'])) {
					$mm['opts']['play'] = array('path'=>$path, 'query'=>'l=24&amp;m=1');
					$mm['opts']['play']['local'] = (!$zc['stream_int'] || preg_match('#^'.$_SERVER['DOCUMENT_ROOT'].'#i',$zc['mp3_dir']));
					$mm['opts']['play']['full_path'] = $full_path;
				}
				if ($zc['mm_down']) $mm['opts']['download'] = array('path'=>$path, 'query'=>'l=25');
				if ($dir_write) $mm['opts_edit']['edit'] = array('path'=>$path, 'query'=>'l=22&amp;m=4');
			}

			if (isset($files[$path])) {
				$db_filepath = zcheck_utf8($path, false);
				#$db_filepath = utf8_encode($path);;

				if (isset($db_files[$db_filepath])) {
					$file = array_merge($file, $db_files[$db_filepath]);
					$file['info'] = unserialize_utf8($files[$path]['id3_info']);
					$extras = unserialize_utf8($files[$path]['other']);
				}
				$file['full_path'] = $path;
				$file['path'] = $root;
				$file['file'] = $filename;
				$file['new'] = $file['checkbox'] = $file['ratings'] = false;

				if ($get_mtime) {
					$file['mtime'] = filemtime($full_path);
					$file['new'] = ($zc['new_highlight'] && $now - $file['mtime'] < $diff);
				}

				if (!isset($file['info']) && ($id3 || $file['remote'])) {
					$file['info'] = zina_get_file_info($full_path, $zc['mp3_info'], ($zc['mp3_id3']|| $file['remote']), $zc['genres']);
				}

				if (!isset($file['description'])) {
					$file['description'] = zina_get_file_desc($zc['song_blurbs'], substr($full_path, 0, -strlen($file_ext)).'txt');
				}
				if (isset($file['description'])) {
					$file['description'] = zina_url_filter($file['description']);
				}

				if (isset($file['info']) && !$file_dir_info && !$db) {
					$dir['info'] = $file['info'];
					if (isset($dir['info']->year)) $dir['year'] = $dir['info']->year;
					if (isset($dir['info']->genre)) $dir['genre'] = $dir['info']->genre;
					$file_dir_info = true;
				}

				if (isset($file['info']->title)) {
					$file['title'] = ztheme('song_title', $file['info']->title, true);
				} else {
					$file['title'] =  zina_content_song_title($filename);
				}

				if ($zc['song_extras']) {
					foreach ($zc['song_es_exts'] as $ext) {
						if (isset($extras[$ext]) || file_exists(substr($full_path, 0, -strlen($file_ext)).$ext) || (isset($zc['third_'.$ext]) && $zc['third_'.$ext])) {
							if ($zc['song_es'][$ext]['type'] == 'page_internal') {
								$href = $path;
								$query = 'l=29&amp;m='.$ext;
								$attr = '';
							} elseif ($zc['song_es'][$ext]['type'] == 'page_popup') {
								$href = 'javascript: void 0;';
								$query = NULL;
								$attr = " onclick=\"WindowOpen('".zurl($path, 'l=29&amp;m='.$ext)."','lyrics',".($zc['song_es'][$ext]['page_width']+20).",".$zc['song_es'][$ext]['page_height'].",'no');\"";
							} elseif ($zc['song_es'][$ext]['type'] == 'external') {
								$href = 'javascript: alert(\''.zt('Error').'\');';
								$query = $attr = null;
								$extra_file = substr($full_path, 0, -strlen($file_ext)).$ext;
								$content = trim(file_get_contents($extra_file));
								if (!empty($content)) $href = $content;
							} else {
								$href = 'javascript: alert(\''.zt('Error').'\');';
								$query = $attr = null;
							}
							$file['extras'][$ext] = array('path'=>$href, 'query'=>$query, 'attr'=>$attr,
								'text'=>$zc['song_es'][$ext]['name'] );

						}

						if ($dir_edit) { #edit
							$file['extras_edit'][$ext] = array('path'=>$path,
								'query'=>'l=22&amp;m='.$ext,
								'attr'=>null, 'text'=>zt('Edit @extra', array('@extra'=>$zc['song_es'][$ext]['name']))
							);
						}
					}
				} # end extras

				if ($zc['play'] && !$file['fake'] && (!$file['remote'] || isset($file['info']->url))) {
					$file['title_link'] = $file['opts']['play'] = array('path'=>$path, 'query'=>'l=8&amp;m=1', 'attr'=>' class="zinamp"');
				}

				if ($check_boxes && !$file['fake'] && (!$file['remote'] || isset($file['info']->url))) {
					$file['checkbox'] = array('name'=>'mp3s[]', 'value'=>zrawurlencode($path), 'checked'=>(!empty($c_pls_arr) && in_array($path, $c_pls_arr)));
				}

				if (isset($file['lofi'])) {
					$file['opts']['play_lofi'] = array('path'=>$file['lofi_path'], 'query'=>'l=8&amp;m=1');
				} elseif ($zc['resample'] && preg_match('/\.('.$zc['ext_enc'].')$/i', $filename)) {
					$file['opts']['play_lofi'] = array('path'=>$path, 'query'=>'l=8&amp;m=1&amp;lf');
				}

				if ($zc['download']) {
					if ($file['remote']) {
					  	if (isset($file['info']->download)) $file['opts']['download'] = array('path'=>$file['info']->download, 'query'=>null);
					} elseif (!$file['fake']) {
						$file['opts']['download'] = array('path'=>$path, 'query'=>'l=12');
					}
				}

				if ($dir_edit) $file['opts_edit']['edit'] = array('path'=>$path, 'query'=>'l=22&amp;m=3');
				if ($dir_write) $file['delete'] = array('path'=>$path, 'query'=>'l=75');

				$file['ratings'] = ($rating && !$file['fake']);
				if (!isset($file['sum_votes'])) {
					$file['sum_votes'] = 0;
					$file['sum_rating'] = 0;
					$file['sum_plays'] = 0;
					$file['sum_downloads'] = 0;
				}

				$file['user_rating'] = (isset($user_ratings[$db_filepath])) ? $user_ratings[$db_filepath]['rating'] : 0;

			} # end isset(file)
		} #end file
	} #end loop

	if (isset($dir['description'])) $dir['description'] = zina_url_filter(nl2br($dir['description']));

	if (empty($dir['images']) && isset($file['info']->image)) {
		$dir['images'][] = 'zina_id3_zina.jpg';
	}

	return $dir;
}

function zina_url_filter($input) {
	return preg_replace('/\{internal:(.*?)\}/ie', 'zurl("$1");', $input);
}

function zina_get_file_desc($allow, $file) {
	return ($allow && file_exists($file)) ? file_get_contents($file) : false;
}

function zina_get_form_token() {
	global $zc;
	return ztheme('form_hidden', 'token', zina_token_sess($zc['user_id']));
}

function zina_get_tmpl_cache_file($path) {
	if (empty($path)) {
		$cache_file = md5($_SERVER['REQUEST_URI']).'_'.zina_get_page_opt($path).zina_get_catsort_opt($path);
	} else {
		$cache_file = md5($path);
	}
	return $cache_file;
}

function zina_get_catsort_opt($path) {
	global $zc;
	$cat_sort = $zc['cat_sort_default'];
	if ($zc['cat_sort']) {
		if (isset($_GET['zs']) && in_array($_GET['zs'], array('a','ad','d','dd'))) {
			$cat_sort = $_SESSION['zina']['catsort'][$path] = $_GET['zs'];
		} elseif (isset($_SESSION['zina']['catsort'][$path])) {
			$cat_sort = $_SESSION['zina']['catsort'][$path];
		}
	}
	return $cat_sort;
}

function zina_get_page_opt($path='') {
	#todo: cat_splits might not honor overrides...
	global $zc;
	$page = ($zc['cat_split'] == 2) ? 'A' : 1;
	if ($zc['cat_split']) {
		if (isset($_GET['page']) && zina_validate('alpha_numeric',$_GET['page'])) {
			$page = $_SESSION['zina'][$path]['page'] = $_GET['page'];
		} elseif (isset($_GET['page']) && $zc['cat_split'] >= 2 && $zc['cat_various_lookahead'] && !strstr($path,'..') && is_dir($zc['mp3_dir'].'/'.$_GET['page'])) {
			$page = $_SESSION['zina'][$path]['page'] = $_GET['page'];
		} elseif (isset($_SESSION['zina'][$path]['page'])) {
			$page = $_SESSION['zina'][$path]['page'];
		}
	}
	return $page;
}

function zina_get_genres_list($genres = array(), $image_type = 'genre') {
	global $zc;
	$items = array();

	if (empty($genres)) {
		if ($zc['database']) {
			#TODO:XXX...ordering options?  obsolete?  integrate? make opt?
			# cant be by weight or cat split wont work?
			$genres = zdbq_array_list("SELECT genre FROM {genres} AS g INNER JOIN {genre_tree} as gt ON g.id=gt.id ".
				"WHERE pid = 0 ORDER BY genre");
		} else {
			$genres = zina_core_cache('genres');
		}
	}

	if (!empty($genres)) {
		foreach ($genres as $g) {
			$items[$g] = array(
				'path' => null,
				'query' => 'l=13&amp;pl='.rawurlencode($g),
				'title' => zcheck_utf8($g),
				'new' => false,
			);
			if ($zc['genres_images']) {
				$path = zina_get_genre_image_path($g, $image_type);
				$items[$g]['image_raw'] = $image_raw = ztheme('image', $path, $g, null, 'class="genre-image"');
				$items[$g]['image'] = zl($image_raw, null, $items[$g]['query']);
			}
		}
	}
	return $items;
}

function zina_get_genre_image_path($g, $type = 'genre') {
	global $zc;
	$genre_file = ztheme('image_genre', $g);

	if ($zc['res_genre_img']) {
		$path = zurl(null,'l=7&amp;it='.$type.'&amp;img='.rawurlencode($g), null);
	} elseif (file_exists($zc['theme_path_abs'].'/images/'.$genre_file)) {
		$path = zpath_to_theme().'/images/'.$genre_file;
	} else {
		$path = zpath_to_theme().'/images/'.ztheme('missing_image','genre');
	}
	return $path;
}

#todo: your using this to get images!
function zina_get_dir_list(&$dirs, $sort = true) {
	global $zc;

	$checkbox = ($zc['is_admin'] || ($zc['playlists'] && $zc['session_pls']));
	$sort = ($zc['dir_list_sort'] && $sort);

	if ($sort) {
		if ($zc['dir_list_sort_asc']) {
			$sort = SORT_ASC;
			$empty = 9000;
		} else {
			$sort = SORT_DESC;
			$empty = 0;
		}
	}

	foreach ($dirs as $key=>$opts) {
		if (empty($key)) {

		} elseif (substr_count($key, '/') < 1) {
			#if ($zc['play'] && $zc['play_rec'] && $subdir_num > 1) {
			#	$zina['dir_opts']['play_rec'] = array('path'=>$path, 'query'=>'l=8&amp;m=10');
			#}
			if ($zc['play'] && $zc['play_rec_rand']) {
				$dirs[$key]['opts']['play_rec_rand'] = array('path'=>$opts['path'], 'query'=>'l=8&amp;m=10&amp;c');
			}
		} else {
			zina_content_subdir_opts($dirs[$key], $opts['path'], $checkbox, $opts);
		}
		if (!isset($opts['title'])) {
			$dirs[$key]['title'] = ztheme('title',$opts['path']);
		}
		$dirs[$key]['image_raw'] = $image_raw = ztheme('image', zina_get_image_url($opts['path'], $opts['image'],'sub'), $dirs[$key]['title'], null, 'class="sub-image"');
		$dirs[$key]['image'] = zl($image_raw, $opts['path']);

		if ($sort) {
			$year[$key] = (isset($dirs[$key]['info']->year)) ? $dirs[$key]['info']->year : $empty;
		}
	}

	if ($sort) array_multisort($year, $sort, $dirs);
}

function zina_get_image_url($path, $image, $type, $absolute = false) {
	global $zc;
	if (!in_array($type, array('sub','dir','full','search'))) $type = 'dir';

	if (empty($image)) {
		if ($zc['res_'.$type.'_img'] && $zc[$type.'_img_txt']) {
			return zurl($path,'l=7&amp;it='.$type, null, $absolute);
		} else {
			return zpath_to_theme().'/images/'.ztheme('missing_image',$type);
		}
	} else {
		if ($zc['res_'.$type.'_img'] && preg_match('/\.('.$zc['resize_types'].')$/i', $image)) {
			if ($zc['cache_imgs']) {
				$res_out_type = ($zc['res_out_type'] == 'jpeg') ? 'jpg' : $zc['res_out_type'];
				$cache_img = $type.md5($zc['mp3_dir'].'/'.$path.'/'.$image).'.'.$res_out_type;
				if (file_exists($zc['cache_imgs_dir'].'/'.$cache_img)) {
					if ($absolute) {
						$base_root = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
						$base_url = $base_root .= '://'.$zc['auth']. preg_replace('/[^a-z0-9-:._]/i', '', $_SERVER['HTTP_HOST']);
						return $base_url.$zc['zina_dir_rel'].'/'.$zc['cache_imgs_dir_rel'].'/'.$cache_img;
					} else {
						return $zc['zina_dir_rel'].'/'.$zc['cache_imgs_dir_rel'].'/'.$cache_img;
					}
				}
			}
			return zurl($path,'l=7&amp;img='.rawurlencode($image).'&amp;it='.$type, null, $absolute);
		} elseif ($zc['stream_int']) {
			return zurl($path,'l=11&amp;img='.rawurlencode($image), null, $absolute);
		} else { #NORMAL
			return zurl($path.'/'.$image,NULL,NULL,$absolute,TRUE);
		}
	}
}

function zina_get_vote_url($path) {
	return zurl($path,'l=16').'&n=';
}

function zina_get_page_title($array=null) {
	static $title;
	if ($array) $title = $array;
	return $title;
}

function zina_get_current_dir($current=null) {
	static $dir;
	if ($current) $dir = $current;
	return $dir;
}

function zina_get_breadcrumb($path, $alt_title = null, $full = false, $links = array()) {
	global $zc;

	if (empty($links)) {
		$tmp_path = '';
		$crumbs = explode('/',$path);
		$size = sizeof($crumbs);
		for($i=0; $i < $size; $i++) {
			if ($tmp_path != '') $tmp_path = $tmp_path.'/';
			$tmp_path .= $crumbs[$i];
			$title = ztheme('title',$crumbs[$i]);
			$links[] = zl($title,$tmp_path);
			$titles[] = $title;
		}

		if (empty($alt_title)) {
			zina_get_current_dir(ztheme('title',$crumbs[$size-1]));
			zina_get_page_title($titles);
		} else {
			array_pop($titles);
			$titles[] = $alt_title;
			if ($full) {
				array_pop($links);
				$links[] = zl($alt_title, $path);
			}
			zina_get_current_dir(ztheme('title',$alt_title));
			zina_get_page_title($titles);
		}

		if (!$full)	array_pop($links);
	}

	$home[] = (!empty($path) || isset($_GET['l'])) ? zl(zt($zc['main_dir_title']),'') : zt($zc['main_dir_title']);
	if ($zc['genres'])
		$home[] = (isset($_GET['l']) && $_GET['l'] == '14') ? zt('Genres') : zl(zt('Genres'),null,'l=14');
	if ($zc['playlists'] && ($zc['pls_public'] || $zc['is_admin']))
		$home[] = zl(zt('Playlists'),null,'l=2');
	if ($zc['database'] && $zc['stats'] && ($zc['is_admin'] || $zc['stats_public']))
		$home[] = (isset($_GET['l']) && $_GET['l'] == '15') ? zt('Statistics') : zl(zt('Statistics'),'','l=15');

	array_unshift($links,ztheme('breadcrumb_home', $home));

	return $links;
}

/*
 * @path must be full
 */
function zina_get_file_info($path, $info=true, $id3=true, $genre=false, $image = false) {
	global $zc;
	$mp3 = false;

	if ($zc['remote'] && preg_match('/\.('.$zc['remote_ext'].')$/i', $path)) {
		$mp3 = new remoteFile($path, $info, $id3);
	} else {
		$mp3 = new mp3($path, $info, $id3, $zc['mp3_info_faster'], $genre, $image);
	}
	return $mp3;
}

function zina_get_file_xml($path) {
	global $zc;

	$output = '<?xml version="1.0" encoding="UTF-8"?><track>';
	$mp3 = null;
	$result = array();

	if ($zc['database']) {
		$result = zdbq_array_single("SELECT * FROM {files} WHERE path = '%s' AND file = '%s'", dirname($path), basename($path));
		if (!empty($result)) {
			/*
			foreach ($result as $key => $val) {
				if (in_array($key, array('file', 'path', 'id3_info', 'other'))) continue;
				$output .= xml_field($key, $val);
			}
			 */
			$other = unserialize_utf8($result['other']);
			unset($result['other']);
			$mp3 = unserialize_utf8($result['id3_info']);
			unset($result['id3_info']);
			if ($zc['user_id'] > 0) {
				$result['user_rating'] = zdbq_single("SELECT rating FROM {file_ratings} WHERE file_id = %d AND user_id = %d LIMIT 1", array($result['id'], $zc['user_id']));
				$output .= xml_field('user_rating', $result['user_rating']);
			}
		}
		if ($zc['rating_files']) {
			$output .= xml_field('vote_url', zurl($path,'l=16',null,true).'&n=');
		}
	}

	if (empty($mp3)) {
		$mp3 = zina_get_file_info($zc['mp3_dir'].'/'.$path,true,true,true);
	}

	$result = array_merge($result, get_object_vars($mp3));
	if (empty($result['title'])) $result['title'] = ztheme('song_title', preg_replace('/\.('.$zc['ext_mus'].')$/i', '', $path));

	$items = array('bitrate', 'frequency', 'sum_rating', 'stereo');
	foreach ($items as $item) {
		if (isset($result[$item]) && !empty($result[$item])) {
			$output .= xml_field($item, $result[$item]);
		}
	}

	$img_path = dirname($path);
	$img = zina_get_dir_item($zc['mp3_dir'].'/'.$img_path,'/\.('.$zc['ext_graphic'].')$/i');
	if (empty($img) && isset($mp3->image)) $img = 'zina_id3_zina.jpg';

	#todo: make 'sub' configable?
	$img_url = zina_get_image_url($img_path, $img, 'sub', true);
	$output .= xml_field('image', $img_url);

	if ($zc['zinamp'] && $zc['lastfm']) {
		zina_zinamp_start($path);

		$output .= xml_field('complete_url', zurl($path,'l=56',null,true).'&n=');
		$output .= xml_field('start_url', zurl($path,'l=66',null,true));
	}
	$result['path'] = $path;
	$parts = explode('/', $path);
	if (sizeof($parts) == 3) {
		$result['artist_url'] = zurl($parts[0], null, null, true);
		$result['album_url'] = zurl($parts[0].'/'.$parts[1], null, null, true);
		$output .= xml_field('artist_url', $result['artist_url']);
		$output .= xml_field('album_url', $result['album_url']);
	}
	#todo: genre url?
	if ($zc['song_extras'] && in_array('lyr', $zc['song_es_exts'])) {
		$output .= xml_field('lyric_url', zurl($path,'l=57&m=lyr&pl=zinamp',null,true));
	}

	$output .= "<info>\n<![CDATA[".ztheme('zinamp_song_info',$result)."]]>\n</info>";

	return $output.'</track>';
}

function zina_zinamp_start($path) {
	$now = time();

	$_SESSION['zinamp_track'][$path] = $now;
	if (sizeof($_SESSION['zinamp_track']) > 2) {
		foreach($_SESSION['zinamp_track'] as $track => $time) {
			if ($now > $time + (60*60*1)) {
				unset($_SESSION['zinamp_track'][$track]);
			}
		}
	}
}

function zina_get_dir_item($path, $regex) {
	global $zc;

	@set_time_limit($zc['timeout']);
	if ($results = @scandir($path)) {
		foreach($results as $file) {
			if (preg_match($regex, $file)) return $file;
		}
	}
	return false;
/*

	@set_time_limit($zc['timeout']);
	if (is_dir($path) && $dh = opendir($path)) {
		while (($file = readdir($dh)) !== false) {
			if (preg_match($regex, $file)) {
				closedir($dh);
				return $file;
			}
		}
		closedir($dh);
	}
	return false;
 */
}

function zina_get_title_playlist($path, $custom = false, $low = false) {
	global $zc;
	$dirs = zina_core_cache('files_assoc', $path, array('low'=>$low,'file_sort'=>$zc['files_sort'],'custom'=>$custom,'force'=>(empty($path))));

	$files = array();
	if (isset($dirs[$path])) {
		foreach($dirs[$path] as $file) {
			$files[] = (empty($path)) ? $file : $path.'/'.$file;
		}
	}
	return zina_get_playlist($files, $low);
}

function zina_get_song_url($file, $resample = false) {
	global $zc;
	static $count = 0;
	$format = $zc['playlist_format'];

	$remote = ($zc['remote'] && preg_match('/\.('.$zc['remote_ext'].')$/i', $file));

	#if (!$zc['pos'] && !$zc['play_local']) { ???
	if ($zc['stream_int'] || $resample || $remote) {
		if ($remote || ($zc['stream_extinf'] && $zc['mp3_id3'] && $count++ < $zc['stream_extinf_limit'])) {
			$mp3 = zina_get_file_info($zc['mp3_dir'].'/'.$file);
			if ($mp3->tag) {
				$at = ztheme('artist_song', $mp3->title, $mp3->artist);
			} else {
				$at = zina_get_file_artist_title($file, false);
			}
			$length = isset($mp3->length) ? $mp3->length : -1;
		} else {
			$mp3 = false;
			$at = zina_get_file_artist_title($file, false);
			$length = -1;
		}

		$query = ($resample && !$remote) ? 'l=6' : 'l=10';
		if (isset($zc['token'])) $query .= '&'.$zc['token'];
		$url = zurl($file, $query, null, true);
		if ($format == 'm3u') {
			$at = utf8_decode(zdecode_entities($at));
			$playlist = ($zc['stream_extinf']) ? "#EXTINF:$length,$at\n" : '';
			$ext = '';
			if (!$zc['clean_urls']) {
				# winamp
				$match = strtolower(substr($file, strrpos($file, '.')));
				if ($match != '.mp3') $ext = '&ext='.$match;
			}
			return $playlist.$url.$ext."\n";
		} elseif ($format == 'asx') {
			$at = utf8_decode(zdecode_entities($at));
			return '<entry><title>'.$at.'</title><ref href="'.$url.'"><STARTTIME VALUE="00:00:00.0" /></ref></entry>'."\n";
		} elseif ($format == 'xspf') {
			if ($mp3 && $mp3->tag) {
				$meta = '';
				if (isset($mp3->artist) && !empty($mp3->artist)) {
					$meta .= xml_field('creator', $mp3->artist);
					$meta .= xml_field('title', $mp3->title);
				} else {
					$meta .= xml_field('title', $at);
				}
				if ($length > 0) $meta .= xml_field('duration', ($length*1000));
			} else {
				$meta = xml_field('title', $at);
			}
			$meta .= xml_field('link', zurl($file,'l=54',NULL,TRUE));
			$meta .= xml_field('meta', 'audio');
			return '<track><location>'.$url.'</location>'.$meta.'</track>'."\n";
		}
	} else {
		return zurl($file,NULL,NULL,TRUE,TRUE)."\n";
	}
}

function zina_get_playlist($songs, $low, $c_pls = false) {
	global $zc;
	static $pls_ids = array();

	$playlist = '';
	if (!empty($songs)) {
		if ($zc['pos'] || $zc['play_local']) {
			foreach($songs as $song) {
				$song = zrawurldecode($song);
				if (preg_match('/\.lp$/i', $song)) {
					$song = preg_replace('/\/\.lp$/i', '', $song);
					$playlist .= zina_get_title_playlist($song, $zc['honor_custom'], $low);
				} elseif (preg_match('/\.pls/i', $song)) {
					$song = preg_replace('/\.pls/i', '', $song);
					if (in_array($song, $pls_ids)) continue '';
					$pls_ids[] = $song;
					$playlist .= zina_get_playlist(zina_get_playlist_custom($song, $zc['honor_custom'], $low), $low);
				} else {
					$playlist .= $zc['mp3_dir'].'/'.$song."\n";
				}
			}
		} else { # NOT POS
			foreach($songs as $song) {
				$song = zrawurldecode($song);
				if (preg_match('/\.lp$/i', $song)) {
					$song = preg_replace('/\/\.lp$/i', '', $song);
					$playlist .= zina_get_title_playlist($song, $zc['honor_custom'], $low);
				} elseif (preg_match('/\.pls$/i', $song)) {
					$song = preg_replace('/\.pls/i', '', $song);
					if (in_array($song, $pls_ids)) continue '';
					$pls_ids[] = $song;
					$playlist .= zina_get_playlist(zina_get_playlist_custom($song, $zc['honor_custom'], $low), $low);
				} elseif ($zc['remote'] && preg_match('/\.('.$zc['remote_ext'].')$/i', $song)) {
					$playlist .= zina_get_song_url($song);
				} elseif ($low) {
					$song_lofi = preg_replace('/(\.'.$zc['ext_mus'].')$/i', $zc['low_suf'].'$1', $song);

					if ($c_pls && file_exists($zc['mp3_dir'].'/'.$song_lofi)) {
						$playlist .= zina_get_song_url($song_lofi);
					} elseif (preg_match('/('.$zc['low_suf'].')\.('.$zc['ext_mus'].')$/i', $song)) {
						$playlist .= zina_get_song_url($song);
					} elseif($zc['resample'] && preg_match('/\.('.$zc['ext_enc'].')$/i', $song)) {
						$playlist .= zina_get_song_url($song, true);
					} else {
						$playlist .= zina_get_song_url($song);
					}
				} else {
					$playlist .= zina_get_song_url($song);
				}
			}
		}
	}
	return $playlist;
}

function zina_get_playlists_custom() {
	global $zc;
	$custom_playlists = null;

	if (file_exists($zc['cache_pls_dir'])) {
		$d = dir($zc['cache_pls_dir']);

		while($entry = $d->read()) {
			#use last for insteadh of preg
			if (substr($entry,0,6) == '_zina_' && preg_match('/\.m3u$/i', $entry) ) {
				$entry = preg_replace('/^_zina_/i','',$entry);
				$custom_playlists[] = preg_replace('/\.m3u$/i','',$entry);
			}
		}
		$d->close();

		if (!empty($custom_playlists)) natcasesort($custom_playlists);
	}
	return $custom_playlists;
}

function zina_core_cache($type, $path = '', $opts = array()) {
	global $zc;
	$serial = true;
	if ($type == 'dirs') {
		$cache_file = $zc['cache_dirs_file'];
	} elseif ($type == 'files') {
		$files = array();
		$files_assoc = zina_core_cache('files_assoc',$path, $opts);

		foreach($files_assoc as $dir=> $array) {
			foreach ($array as $file) {
				$files[] = (empty($dir)) ? $file : $dir.'/'.$file;
			}
		}
		return $files;
	} elseif ($type == 'files_assoc') {
		$cache_file = $zc['cache_files_assoc_file'];
		if (isset($opts['low']) && $opts['low']) $cache_file = preg_replace('/(\.gz)$/i', $zc['low_suf'].'$1', $cache_file);
	} elseif ($type == 'genre') {
		#[genre]=>array(albums)
		$cache_file = $zc['cache_genre_file'];
	} elseif ($type == 'genres') {
		# genre list
		if ($zc['database']) {
			$keys = zdbq_array_list("SELECT genre FROM {genres} ORDER BY genre");
			if (empty($keys)) $keys[] = zt('No genres found.');
		} else {
			$genre = zina_core_cache('genre');
			if (!empty($genre)) {
				$keys = array_keys($genre);
				natcasesort($keys);
			} else {
				$keys[] = zt('No genres found.');
			}
		}
		return $keys;
	} else {
		return array();
	}

	if ($zc['cache'] && !(isset($opts['force']) && $opts['force']) && empty($path) && file_exists($cache_file)) {
		if ($serial) {
			return unserialize(implode('', gzfile($cache_file)));
		} else {
			return gzfile($cache_file);
		}
	}
	$result_array = zina_scandir($type, $path, $opts);

	if ($result_array) {
		if ($zc['cache'] && empty($path)) {
			if ($serial) {
				$result = serialize($result_array);
			} else {
				$result = implode("\n", $result_array);
			}
			if (zina_check_directory(dirname($cache_file),1)) {
				$fp = gzopen($cache_file, 'w1');
				gzwrite($fp,$result);
				gzclose($fp);
			} else {
				zina_debug(zt('Cache directories do not exist. Create them under "Settings."'));
			}
		}
		return $result_array;
	}
	return array();
}

/*

 */
function zina_scandir($type, $root='', $opts = array()) {
	global $zc;
	@set_time_limit($zc['timeout']);

	$root = $zc['mp3_dir'] . ((!empty($root)) ? '/'.$root : '');
	if(!is_dir($root)) return false;

	if ($type == 'genre') {
		$genres = array();
		$files = zina_core_cache('files_assoc');
		$dwm = array_keys($files);

		foreach ($dwm as $dir) {
			$file = $files[$dir][0];
			$path = (!empty($dir)) ? $dir.'/'.$file : $file;
			$path_full = $zc['mp3_dir'].'/'.$path;
			if (file_exists($path_full)) {
				$mp3 = zina_get_file_info($path_full, false, true, true);
				$genre = ($mp3->tag && isset($mp3->genre)) ? $mp3->genre : zt('Unknown');
				$genres[$genre][] = $dir;
			}
		}
		return $genres;
	}
	$dirs_get = ($type == 'dirs');
	$files_get_assoc = ($type == 'files_assoc');

	if (!($dirs_get || $files_get_assoc)) return false;

	$dir_sort = (isset($opts['dir_sort'])) ? $opts['sort'] : 1;
	$file_sort = (isset($opts['file_sort'])) ? $opts['file_sort'] : 0;
	$custom = (isset($opts['custom'])) ? $opts['custom'] : $zc['honor_custom'];
	$low = (isset($opts['low']) && $opts['low']);

	$dirs_all = $files = array();
	$dirs_all[] = '';

	$start = strlen($zc['mp3_dir'])+1;

	$dirs = array($root);
	while(($dir = array_pop($dirs)) !== null) {
		if ($result = @scandir($dir)) {
			@set_time_limit($zc['timeout']);
			$custom_check = false;

			foreach($result as $filename) {
				if ($filename == '.' || $filename == '..') continue;
				$path = $dir . '/' . $filename;
				if (is_dir($path)) {
					if ($filename[0] == $zc['dir_skip']) continue;
					$dirs[] = $path;
					if ($dirs_get) {
						$dirs_all[] = substr($path, $start);
					}
				} elseif ($files_get_assoc && (preg_match('/\.('.$zc['ext_mus'].')$/i', $filename, $matches) ||
							($zc['remote'] && preg_match('/\.('.$zc['remote_ext'].')$/i', $filename)))) {
					if ($files_get_assoc) {
						$directory = substr($dir, $start);
						if (empty($directory)) $directory = '';

						if ($custom && !$custom_check) {
							$custom_file = $zc['cache_pls_dir'].'/'.str_replace('/', ' - ', $directory).'.m3u';

							if (file_exists($custom_file)) {
								$custom_files = zunserialize_alt(file_get_contents($custom_file));

								foreach($custom_files as $cf) {
									if ($low) {
										$lofi_file = preg_replace('/\.('.$zc['ext_mus'].')$/i', $zc['low_suf'].'.$1', $cf);
										if (file_exists($zc['mp3_dir'].'/'.$lofi_file)) {
											$files[$directory][] = basename($lofi_file);
											if ($file_sort > 1) {
												$mtimes[$directory][] = filemtime($zc['mp3_dir'].'/'.$lofi_file);
											}
											continue;
										}
									}
									$files[$directory][] = basename($cf);
									if ($file_sort > 1) {
										$mtimes[$directory][] = filemtime($zc['mp3_dir'].'/'.$cf);
									}
								}
								continue 2; # go to next dir
							}
							$custom_check = true;
						}
						if ($low) {
							$lofi_file = preg_replace('/\.('.$zc['ext_mus'].')$/i', $zc['low_suf'].'.$1', $filename);
							$lofi_path = (empty($directory)) ? $lofi_file : $directory.'/'.$lofi_file;
							if (file_exists($zc['mp3_dir'].'/'.$lofi_path)) {
								$files[$directory][] = $lofi_file;
								$files_assoc_uniq[$directory] = true;
								continue;
							}
						}
						$files[$directory][] = $filename;

						if ($file_sort > 1) {
							$mtimes[$directory][] = filemtime($path);
						}
					}
				}
			}
		} else {
			zina_debug(zt('Could not read directory: @dir'),array('@dir'=>$dir));
		}
	}

	if ($files_get_assoc) {
		if (!empty($files)) {
			if ($low) {
				foreach ($files_assoc_uniq as $directory => $x) {
					$files[$directory] = array_unique($files[$directory]);
				}
			}

			if ($file_sort) {
				foreach($files as $dir => $contents) {
					if ($file_sort == 1) {
						rsort($files[$dir]);
					} elseif ($file_sort == 2) {
						array_multisort($mtimes[$dir], SORT_ASC, $files[$dir], SORT_DESC);
					} elseif ($file_sort == 3) {
						array_multisort($mtimes[$dir], SORT_DESC, $files[$dir]);
					}
				}
			}

			if ($dir_sort) ($zc['dir_sort_ignore']) ? uksort($files, 'zsort_ignore') : uksort($files, 'strnatcasecmp');
			return $files;
		}
	} else {
		$result = &$dirs_all;

		if (!empty($result)) {
			if ($low) $result = array_unique($result);
			#todo: ???
			#($zc['dir_sort_ignore']) ? uksort($files, 'zsort_ignore') : uksort($files, 'strnatcasecmp');
		  	if ($dir_sort) natcasesort($result);
		}
	}

	return $result;
}

/*
 * only called when generating playlists & stat blocks
 * otherwise, info should already be available
 */
function zina_get_file_artist_title($file, $id3, &$result = null) {
	global $zc;
	if ($id3) {
		$mp3 = zina_get_file_info($file, false, true);
		if ($mp3->tag) {
			$result['artist'] = $mp3->artist;
			$result['title'] = $mp3->title;
			return ztheme('artist_song', $mp3->title, $mp3->artist);
		}
	}
	$x = explode('/', $file);
	$len = sizeof($x);
	$song = zina_content_song_title($x[$len - 1]);
	if ($len > 2) {
		$artist =  ztheme('title',$x[$len - 3]);
	} elseif (!empty($x[$len-2])) {
		$artist = ztheme('title',$x[$len - 2]);
	} else {
		$artist = false;
	}
	$result['artist'] = $artist;
	$result['title'] = $song;
	return ztheme('artist_song', $song, $artist);
}

function zina_get_themes() {
	global $zc;
	$dir = $zc['zina_dir_abs'].'/themes';
	if ($d = @dir($dir)) {
		while($entry = $d->read()) {
			if ($entry == '.' || $entry == '..') continue;
			$opts[$entry] = $entry;
		}
		$d->close();
		return $opts;
	}
	zina_set_message(zt('Cannot read themes directory'),'error');
	return array('zinaGarland'=>'zinaGarland');
}

function zina_get_languages() {
	global $zc;
	if ($zc['cache']) {
		$lang_file = $zc['cache_dir_private_abs'].'/languages.txt';
		if (file_exists($lang_file)) {
			return unserialize(file_get_contents($lang_file));
		}
	}

	$dir = $zc['zina_dir_abs'].'/lang';
	if ($d = @dir($dir)) {
		while($entry = $d->read()) {
			if ($entry == '.' || $entry == '..') continue;
			$base = basename($entry,'.php');
			if (substr($base,0,1) != '.') {
				$contents = file_get_contents($dir.'/'.$entry);
				if (preg_match('/\$language\s+=\s+[\'"](.*?)[\'"]/si',$contents, $matches)) {
					$opts[$base] = zt('@lang', array('@lang'=> $matches[1]));
				} else {
					$opts[$base] = zt('Unknown: @code', array('@code'=>$base));
				}
			}
		}
		$d->close();
		asort($opts);
		if ($zc['cache']) {
			file_put_contents($lang_file, serialize($opts));
		}
		return $opts;
	}
	zina_debug(zt('Cannot read languages directory'),'error');
	return array('en'=>zt('English'));
}

function zina_get_setting($setting, $default = false) {
	global $zc;
	return (isset($zc[$setting])) ? $zc[$setting] : $default;
}

function zina_playlist_opts_all() {
	return array(
		'all'=>zt('All'),
		'solo'=>zt('Just Mine')
	);
}

function zina_playlist_opts_sort() {
	global $zc;
	$opts = array(
		'date_created'=> zt('Created Date'),
		'genre'       => zt('Genre'),
		'sum_items'   => zt('Items'),
		'sum_plays'   => zt('Plays'),
		'sum_rating'  => zt('Rating'),
		'playlist'    => zt('Title'),
		'sum_views'   => zt('Views'),
		'sum_votes'   => zt('Votes'),
	);
	if (!$zc['genres']) unset($opts['genre']);
	if (!$zc['pls_ratings']) {
		unset($opts['sum_votes']);
		unset($opts['sum_rating']);
	}
	return $opts;
}

function zina_search_opts_sort() {
	return array(
		'title'      => zt('Title'),
		#'mtime'      => zt('File Date'),
		'genre'      => zt('Genre'),
		'sum_rating' => zt('Rating'),
		'type'       => zt('Type'),
		'sum_votes'  => zt('Votes'),
		'year'       => zt('Year'),
	);
}


function zina_search_opts_order() {
	return array(
		'asc'  => zt('Ascending'),
		'desc' => zt('Descending'),
	);
}

function zina_search_opts_type($type = false) {
	$types = array(
		'artist'  => zt('Artists'),
		'album' => zt('Albums'),
		'song' => zt('Songs'),
		'playlist' => zt('Playlists'),
	);
	if ($type == 'year') {
		unset($types['playlist']);
		unset($types['artist']);
	}
	return $types;
}

function zina_search_opts_per_page() {
	global $zc;
	$array = explode(',', $zc['search_pp_opts']);
	return array_combine($array, $array);
}

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * SEND functions
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function zina_send_playlist($content, $store = false) {
	#$mem1=memory_get_usage();$time_start=microtime(true);
	#$time=microtime(true)-$time_start;$mem2=memory_get_peak_usage();printf("<pre>db dir info\nMax memory: %0.2f kbytes\nRunning time: %0.3f s</pre>",($mem2-$mem1)/1024.0, $time);
	#zdbg($content,1);
	global $zc;
	if ($zc['pos']) {
		$tmpfname = tempnam('/tmp', 'zina-');
		file_put_contents($tmpfname, $content);
		$temp_file = $tmpfname.'.m3u';
		if (file_exists($temp_file)) unlink($temp_file);
		rename($tmpfname, $temp_file);
		if ($zc['pos_kill']) exec($zc['pos_kill_cmd']);
		$pos_cmd = str_replace('%TEMPFILENAME%', $tmpfname, $zc['pos_cmd']);
		@session_write_close();
		exec($pos_cmd);
		#sleep(2);
		#unlink("$tmpfname.m3u");
		while(@ob_end_clean());
		header('Location: '.$_SERVER['HTTP_REFERER']);
		exit;
	} else {
		$format = $zc['playlist_format'];

		if ($zc['other_media_types']) {
			$content = preg_replace('#.*?://(.*\.)('.$zc['ext_mus'].')(\n|$)#ie', "\$zc['media_types'][strtolower('$2')][\"protocol\"].\"://\".'$1'.'$2'.'$3'", $content);
			if (preg_match_all('/\.('.$zc['ext_mus'].')(\n|$|\?|&)/i', $content, $ext) && isset($zc['media_types'][$ext[1][0]])) {
				$ext = $ext[1][0];
				$playlist_ext = $zc['media_types'][$ext]['playlist_ext'];
				if ($format == 'asx' && $playlist_ext == 'asx') {
					$content = "<ASX VERSION=\"3.0\">\n<title>".zt('Playlist')."</title>\n".$content.'</ASX>';
				} elseif ($playlist_ext == 'm3u' && $zc['stream_extinf']) {
					$content = "#EXTM3U\n".$content;
				} elseif ($playlist_ext == 'xspf') {
					$content = '<?xml version="1.0" encoding="UTF-8"?>'.
						'<playlist version="1" xmlns="http://xspf.org/ns/0/">'.
						xml_field('title', zt('Playlist')).
						'<trackList>'.$content.'</trackList></playlist>';
				}
				while(@ob_end_clean());
				header('Content-type: '.$zc['media_types'][$ext]['playlist_mime']);
				header('Content-Disposition: inline; filename=playlist.'.$playlist_ext);
			} else {
				zina_debug('Bad other media type');
			}
		} else {
			if ($format == 'm3u') {
				if ($zc['stream_extinf']) $content = "#EXTM3U\n".$content;
			} elseif ($format == 'asx') {
				$content = "<ASX VERSION=\"3.0\">\n<title>".zt('Playlist')."</title>\n".$content.'</ASX>';
			} elseif ($format == 'xspf') {
				$content = '<?xml version="1.0" encoding="UTF-8"?>'.
					'<playlist version="1" xmlns="http://xspf.org/ns/0/">'.
					xml_field('title', zt('Playlist')).
					'<trackList>'.$content.'</trackList></playlist>';
			}
		}
		if ($store) {
			$_SESSION['zina_store'] = array('type'=>$format, 'content'=>$content);
			zina_send_playlist_content('store', zurl('','l=74&rand='.time()));
		} else {
			zina_send_playlist_content($format, $content);
		}
	}
	exit;
}

function zina_send_playlist_content($type, $content) {
	while(@ob_end_clean());
	if ($type == 'm3u') {
		header("Content-type: audio/mpegurl", true, 200);
		header('Content-Disposition: inline; filename=playlist.m3u');
	} elseif ($type == 'asx') {
		header('Content-type: application/vnd.ms-asx', true, 200);
		header('Content-Disposition: inline; filename=playlist.asx');
	} elseif ($type == 'xspf') {
		header('Content-type: application/xspf+xml', true, 200);
		header('Content-Disposition: inline; filename=playlist.xml');
	}

	header('Cache-control: private'); #IE seems to need this.
	echo $content;
	exit;
}

function zina_send_playlist_song($path, $low = false) {
	zina_send_playlist(zina_get_playlist(array($path), $low, false));
}

function zina_send_playlist_title($path, $custom, $low = false) {
	zina_send_playlist(zina_get_title_playlist($path, $custom, $low));
}

function zina_send_playlist_selected($songs, $low = false, $store = false) {
	zina_send_playlist(zina_get_playlist($songs, $low, true), $store);
}

# hack central...fix this
function zina_send_playlist_selected_random($items, $low = false, $store = false) {
	global $zc;

	$items = zina_get_playlist($items, $low, true);

	if ($zc['stream_extinf']) {
		if ($zc['playlist_format'] == 'xspf') {
			$delim = '<track>';
		} else {
			$delim = '#EXTINF';
		}
		$x = explode($delim, $items);
		array_shift($x);
		zo_shuffle($x);
		$songs = $delim.implode($delim, $x);
	} else {
		$delim = "\n";
		$x = explode($delim, $items);
		array_pop($x);
		zo_shuffle($x);
		$songs = implode($delim, $x).$delim;
	}
	zina_send_playlist($songs, $store);
}

function zina_get_playlist_custom($pls_id) {
	global $zc;

	$where = 'p.id = %d';
	if (!$zc['is_admin']) {
		if ($zc['pls_public']) {
			$where .= ' AND (p.user_id = %d OR p.visible = 1)';
		} else {
			$where .= ' AND p.user_id = %d';
		}
	}

	$sql = "SELECT ".
		"CASE pm.type ".
			"WHEN 'song' THEN IF(f.path!='.' && f.path!='',CONCAT(f.path,IF(ISNULL(f.path), '','/'),f.file), f.file) ".
			"WHEN 'album' THEN CONCAT(d.path,'/.lp') ".
			"WHEN 'playlist' THEN CONCAT(pm.type_id,'.pls') ".
		"END AS path ".
		"FROM {playlists_map} as pm ".
		"INNER JOIN {playlists} as p ON (pm.playlist_id = p.id) ".
		"LEFT OUTER JOIN {files} AS f ON pm.type = 'song' AND pm.type_id = f.id ".
		"LEFT OUTER JOIN {dirs} AS d ON pm.type = 'album' AND pm.type_id = d.id ".
		"WHERE $where ".
		"ORDER BY weight";

	return zdbq_array_list($sql, array($pls_id, $zc['user_id']));
}

function zina_send_playlist_custom($playlist, $low, $random = false) {
	global $zc;
	$items = array();

	if($zc['session_pls'] && $playlist == 'zina_session_playlist') {
		if ($zc['database']) {
			$results = zina_get_session_playlist();
			foreach($results as $item) {
				if ($item['type'] == 'album') {
					$items[] = $item['path'].'/.lp';
				} elseif ($item['type'] == 'playlist') {
					$items[] = $item['type_id'].'.pls';
				} else {
					$items[] = $item['path'];
				}
			}
			if (!empty($items) && $random) zina_send_playlist_selected_random($items);
		} else {
			if (isset($_SESSION['z_sp'])) $items = unserialize_utf8($_SESSION['z_sp']);
		}
	} else {
		if ($playlist != 'new_zina_list') {
			if ($zc['database']) {

				$items = zina_get_playlist_custom($playlist);
				if (!empty($items)) {
					zdb_log_stat_playlist($playlist, 'plays');
					if ($random) zina_send_playlist_selected_random($items);
				}
			} else {
				$filename = $zc['cache_pls_dir'].'/_zina_'.str_replace('/',' - ',$playlist).'.m3u';
				if (file_exists($filename)) {
					$items = zunserialize_alt(file_get_contents($filename));
				}
			}
		}
	}
	zina_send_playlist(zina_get_playlist($items, $low, true));
}

/*
 * Sends various playlists via cache
 *  - random by albums, song
 *  - directory recursive
 *  - directory recursive random
 *  - random by song ratings
 *  - random by album ratings
 *  - random by genre
 *  Also, non-cached
 *   - above minus genre and db stuff
 *
 * type:
 *  't' => title,
 *  's' => song
 *  's' && num ==0 => resursive & random recursive ($rand = true)
 *  'tt' => songs via rated songs
 *  'artist' => 'songs via rated albums
 */
function zina_send_playlist_random($num, $type, $low, $rand = true, $path = null, $genre = null, $year = false) {
	global $zc;
	@set_time_limit($zc['timeout']);

	$opts = explode(',',$zc['rating_random_opts']);
	$rating = isset($_POST['rating']) ? $_POST['rating'] : (isset($_GET['rating']) ? $_GET['rating'] : null);
	if ($zc['database'] && $zc['rating_random'] && in_array($rating, $opts)) {
		$random_rating = true;
	} else {
		$random_rating = false;
		if ($type == 'tt' && $rating == 0) $type = 's';
	}

	if ($year) {
		$array = array();
		if ($zc['db_search']) {
			$total = zdbq_single('SELECT COUNT(*) FROM {files} WHERE year = %d', array($year));
			if ($zc['random_least_played'] && $total > $num) {
				$array = zina_least_played($num, 'year = %d AND', array($year));
			} else {
				$array = zdbq_array_list("SELECT IF(path!='.',CONCAT(path,IF(ISNULL(path), '','/'),file),file) as path ".
					"FROM {files} ".
					"WHERE year = %d", array($year));
			}
			#TODO:
			if ($type == 't') {
			} elseif ($type == 'artist') {
			} else { #if ($type == 's') {
			}
		}
	} elseif ($zc['genres'] && !empty($genre) && $genre != 'zina') {

		if ($zc['database']) {
			$array = array();
			$children = zdb_genres_get_children($genre);
			$children[] = $genre;
			$in = str_repeat("'%s',", sizeof($children)-1)."'%s'";
			$vars = $children;
			$vars[] = $num;
			if ($type == 's' || $type == 'artist') {
				$rand = false;
				$array = zdbq_array_list("SELECT CONCAT(path,IF(ISNULL(path), '','/'),file) ".
					"FROM {files} WHERE genre IN ($in) ORDER BY RAND() LIMIT %d", $vars);
			} else {
				$rand = false;
				$array = zdbq_array_list("SELECT path ".
					"FROM {dirs} WHERE genre IN ($in) ORDER BY RAND() LIMIT %d", $vars);
			}
			# probably could make one sql stmt w/ above
			if ($random_rating) { #dir and files?
				#todo: opt to have random honor user_id? pass user_id???
				$files_rated = zdb_get_random_by_rating($type, $rating);
				$array = array_values(array_intersect($array, $files_rated));
			}
		} else {
			$genres = zina_core_cache('genre');
			$array = $genres[$genre];

			if ($type == 's' || $type == 'artist') {
				#todo: could be better artist stuff
				$files = array();
				$dirs = zina_core_cache('files_assoc', $path, array('low'=>$low));
				foreach($array as $title) {
					foreach($dirs[$title] as $file) {
						$files[] = $title.'/'.$file;
					}
				}
				$array = $files;
			}
		}
	} elseif ($random_rating) {
		# artist or tt or s
		if ($type == 't' || $type == 'tt')
			$dirs = zina_core_cache('files_assoc', $path, array('low'=>$low));

		$array = zdb_get_random_by_rating($type, $rating, $num);

		if ($type == 'artist' && $zc['honor_custom']) {
			#todo: by passing $num above && least_played, might return < $num
			$files = zina_core_cache('files', $path, array('low'=>$low));
			$array = array_values(array_intersect($array, $files));
		}
	} else { # normal
		if ($type == 't') {
			$sort = ($rand) ? 0 : $zc['files_sort'];
			$dir_sort = ($rand) ? 0 : 1;
			$dirs = zina_core_cache('files_assoc', $path, array('low'=>$low, 'file_sort'=>$sort, 'dir_sort'=>$dir_sort));
			$array = array_keys($dirs);
		} else {
			$type = 's';

			if ($zc['database'] && $zc['random_least_played']) {
				#todo: doesn't honor "custom"
				$total = zdbq_single('SELECT COUNT(*) FROM {files}');
				if ($total <= $num || $num == 0) {
					$array = zina_core_cache('files', $path, array('low'=>$low));
				} else {
					$array = zina_least_played($num);
				}
			} else {
				$array = zina_core_cache('files', $path, array('low'=>$low));
			}
		}
	}

	# Songs via Rated Albums
	if ($type == 'tt') {
		foreach($array as $title) {
			foreach($dirs[$title] as $file) {
				$files[] = $title.'/'.$file;
			}
		}
		$array = $files;
	}

	if ($rand) zo_shuffle($array);

	$total = sizeof($array);
	if ($num == 0 || $num > $total) $num = $total;
	$array = array_slice($array,0,$num);

	if ($type == 't') {
		foreach($array as $title) {
			foreach($dirs[$title] as $file) {
				$files[] = $title.'/'.$file;
			}
		}
		$array = $files;
	}
	zina_send_playlist(zina_get_playlist($array, $low));
}

#TODO: move
function zina_least_played($total, $where = '', $where_vars = array()) {
	global $zc;

	$result = array();
	$count = 0;
	$floor = $zc['random_lp_floor'];
	$limit = $least = ceil($total*$zc['random_lp_perc']/100);

	while ($count < $least) {
		#todo: by user_id?
		$vars = $where_vars;
		$vars[] = $floor;
		$vars[] = $limit;

		$result += zdbq_assoc_list("SELECT id, CONCAT(path,IF(ISNULL(path), '','/'),file) ".
			"FROM {files} WHERE $where sum_plays = %d ORDER BY RAND() LIMIT %d", $vars);

		$count = sizeof($result);

		if ($count < $least) {
			$limit = $least - $count;
			$floor++;
		}
	}

	if ($count < $total) {
		$vars = $where_vars;
		$vars[] = $floor;
		$vars[] = implode(',', array_keys($result));
		$vars[] = $total - $count;

		$result = array_merge($result, zdbq_array_list("SELECT CONCAT(path,IF(ISNULL(path), '','/'),file) ".
			"FROM {files} WHERE $where sum_plays >= %d AND id NOT IN(%s) ORDER BY RAND() LIMIT %d", $vars));
	}

	return $result;
}

function zina_send_zip_selected($songs, $lofi = false, $filename = 'selectedmusic') {
	global $zc;
	#todo: lowfi, baby

	if ($zc['cmp_sel']) {
		$zipfile = $zc['cache_zip_dir'] .'/'. md5(serialize($songs)).'.zip';
		$files = null;

		if ($zc['cmp_sel'] == 1) { #EXTERNAL
			#todo: make opt to limit on number of files??? or dirs???  or opt on search pages?

			foreach ($songs as $song) {
				$song = zrawurldecode($song);
				if ($zc['database'] && $zc['stats']) zdb_log_stat('down', dirname($song), basename($song));
				$files .= '"'.$zc['mp3_dir'].'/'.$song.'" ';
			}
			if ($zc['cmp_cache']) {
				if (zina_send_zip_helper($zipfile, $zc['cmp_mime'], $filename.'.'.$zc['cmp_extension']))
					exit;
			} else {
				$zipfile = '-';
			}

			$opts = str_replace(array('%FILE%','%FILELIST%'), array($zipfile, $files), $zc['cmp_set']);
			$passthru = $zc['cmp_pgm'].' '.$opts;

			zina_send_zip_helper($zipfile, $zc['cmp_mime'], $filename.'.'.$zc['cmp_extension'], $passthru, $zc['cmp_cache']);
		} else { # internal
			if ($zc['cmp_cache']) {
				if (zina_send_zip_helper($zipfile, 'application/zip', $filename.'.zip'))
					exit;
			}

			$zip = new ZipArchive;
			if (($result = $zip->open($zipfile,ZIPARCHIVE::CREATE)) === TRUE) {
				foreach ($songs as $song) {
					$song = zrawurldecode($song);
					if ($zc['database'] && $zc['stats']) zdb_log_stat('down', dirname($song), basename($song));
					$file = $zc['mp3_dir'].'/'.$song;
					$zip->addFile($file, $song);
				}
				$zip->close();
			} else {
				#todo: might be able to redirect...
				zina_debug(zt('Could not open archive file: @err', array('@err'=>$result)), 'error');
				exit;
			}
			zina_send_zip_helper($zipfile, 'application/zip', $filename.'.zip');
			if (!$zc['cmp_cache'] && file_exists($zipfile)) {
				@unlink($zipfile);
			}
		}
	}
	exit;
}

function zina_send_zip_selected_dir($dir, $custom, $low) {
	$path = preg_replace('/\/\.lp$/i', '', $dir);

	if (empty($path)) $path = false;
	$dirs = zina_core_cache('files_assoc', $path, array('low'=>$low,'custom'=>$custom,'force'=>(empty($path))));

	$files = array();
	$filename = 'not found';

	if (isset($dirs[$path])) {
		foreach($dirs[$path] as $file) {
			$files[] = (empty($path)) ? $file : $path.'/'.$file;
		}
		$filename = str_replace('/', ' - ', $path);
	}
	zina_send_zip_selected($files, $low, $filename);
}

function zina_send_zip_helper($file, $mime, $filename, $passthru = false, $cache = true) {
	zina_set_header('Content-type: '.$mime);
	zina_set_header('Content-Disposition: inline; filename="'.$filename.'"');

	@session_write_close();
	if ($passthru) {
		if (!$cache) {
			while(@ob_end_clean());
			zina_get_headers();
			passthru($passthru);
			return;
		} else {
			passthru($passthru);
		}
	}
	if (file_exists($file)) {
		while(@ob_end_clean());
		header('Content-Length: '.filesize($file));
		if (zina_send_file($file)) {
			return true;
		} else {
			zina_debug(zt('Cannot stream compressed downloads'), 'error');
		}
	}
	return false;
}

function zina_send_file_music($path, $resample = false) {
	global $zc;

	if (preg_match('/\.('.$zc['ext_mus'].')$/i', $path, $exts)) {
		$result = false;
		$ext = strtolower($exts[1]);
		$file = $zc['mp3_dir'].'/'.$path;
		if (($zc['lastfm'] || $zc['twitter']) && $zc['user_id'] > 0) {
			if ($zc['lastfm']) require_once($zc['zina_dir_abs'].'/extras/scrobbler.class.php');
			if ($zc['twitter']) require_once($zc['zina_dir_abs'].'/extras/twitter.class.php');

			$mp3 = zina_get_file_info($file);

			if ($mp3->tag && $mp3->info) {
				$icy = ztheme('artist_song', $mp3->title, $mp3->artist);
				$mp3->timestamp = time();
				if (!isset($mp3->track)) $mp3->track = '';
				if (!isset($mp3->album)) $mp3->album = '';
				if ($zc['lastfm']) {
					$scrobbler = new scrobbler($zc['lastfm_username'],$zc['lastfm_password']);
					if (!$scrobbler->now_playing($mp3)) {
						zina_debug('Zina Error: scrobbler cannot submit now playing: '.$scrobbler->error_msg);
					}
				}
				if ($zc['twitter']) {
					$twitter = new twitter($zc['twitter_username'], $zc['twitter_password']);
					if (!$twitter->set_status('Listening to '.$icy)) {
						zina_debug('Zina Error: twitter cannot set status: '.$scrobbler->error_msg);
					}
				}
			} else {
				$icy = zina_get_file_artist_title($path, false);
			}
		}

		if (!isset($icy)) $icy = zina_get_file_artist_title($file, $zc['mp3_id3']);

		if ($resample) {
			if (!preg_match('/\.('.$zc['ext_enc'].')$/i', $path, $exts)) {
				zina_debug('Zina Error: Bad song sent to resample function.');
				exit;
			}
			$type = $zc['encoders'][$ext]['mime'];

		} else {
			$type = $zc['media_types'][$ext]['mime'];
			$filesize = filesize($file);
		}

		$sapi_type = php_sapi_name();
		if (substr($sapi_type, 0, 3) == 'cgi') {
			zina_set_header('HTTP/1.0 200 OK');
		} else {
			zina_set_header('ICY 200 OK');
		}

		zina_set_header('icy-name: '.$icy);
		zina_set_header('Connection: close');
		zina_set_header('Content-type: '.$type);
		zina_set_header('Content-Disposition: inline; filename='.$icy);

		if ($resample) {
			@set_time_limit(0);
			@session_write_close();
			$opts = str_replace('%FILE%', '"'.$file.'"', $zc['encoders'][$ext]['opts']);
			$begin = time();
			while(@ob_end_clean());
			zina_get_headers();
			passthru($zc['encoders'][$ext]['encoder'].' '.$opts);
			if (isset($scrobbler)) {
				$result = (time() - $begin > $mp3->length / 2);
			}
		} else {
			zina_set_header('Content-Length: '.$filesize);
			if ($end = zina_send_file($file)) {
				$result = ($end > $filesize / 3 * 2);
				if ($zc['zinamp'] && isset($scrobbler)) {
					$result = false;
					$_SESSION['zinamp_track'][$path] = time();
				}
			}
		}

		if (isset($scrobbler) && $result) {
			zina_scrobbler_submit($scrobbler, $mp3);
		}
	}
	exit;
}

function zina_play_complete($path, $length) {
	global $zc;
	if (preg_match('/\.('.$zc['ext_mus'].')$/i', $path)) {
		if ($zc['lastfm'] && $zc['user_id'] > 0) {
			require_once($zc['zina_dir_abs'].'/extras/scrobbler.class.php');
			if (isset($_SESSION['zinamp_track'][$path])) {
				$mp3 = zina_get_file_info($zc['mp3_dir'].'/'.$path);

				if ($mp3->tag && $mp3->info) {
					$icy = ztheme('artist_song', $mp3->title, $mp3->artist);
					$mp3->timestamp = $_SESSION['zinamp_track'][$path];
					$mp3->length = $length;
/*
					#TODO: when last.fm allows Love submissions...
					if ($zc['database'] && $zc['stats']) {
						$rating = zdbq_single("SELECT r.rating ".
							"FROM {files} as f INNER JOIN {file_ratings} AS r ON (f.id = r.file_id) ".
							"WHERE file = '%s' AND r.user_id = %d", array($path, $zc['user_id']));
						if (!empty($rating) && $rating == 5) $mp3->love = true;
					}
 */

					$scrobbler = new scrobbler($zc['lastfm_username'],$zc['lastfm_password']);
					zina_scrobbler_submit($scrobbler, $mp3);
					unset($_SESSION['zinamp_track'][$path]);
				}
			} else {
				zina_debug(zt('scrobbler session not set: @path', array('@path'=>$path)), 'error');
			}
		}
	}
}

function zina_scrobbler_submit(&$scrobbler, $mp3) {
	$scrobbler->queued_tracks = zina_set_scrobbler_queue();
	if (!isset($mp3->track)) $mp3->track = '';
	if (!isset($mp3->album)) $mp3->album = '';

	if ($scrobbler->submit_track($mp3)) {
		zina_set_scrobbler_queue(array(), true);
	} else {
		zina_debug('scrobbler cannot submit tracks: '.$scrobbler->error_msg);
		zina_set_scrobbler_queue($scrobbler->queued_tracks);
	}
}

function zina_send_file($file) {
	@set_time_limit(0);
	@session_write_close();
	$fp = @fopen($file, 'rb');
	if ($fp !== false) {
		ignore_user_abort(true);

		# various embeds
		while(@ob_end_clean());

		zina_get_headers();

		while(!feof($fp)) {
			echo fread($fp, 8192);
			flush();
			#usleep(20000); # XXX
			if (connection_status() != 0) break;
		}
		$end = ftell($fp);
		fclose($fp);
		return $end;
	}
	return false;
}

function zina_send_image_resized($source, $type, $txt = null, $cache_file = false, $string = false) {
	global $zc;
	$types = array(1=>'gif', 2=>'jpeg', 3=>'png', 7=>'tiff', 8=>'tiff');

	if ($string || $info = getimagesize($source)) {
		if ($string) {
			$org_image = imagecreatefromstring($string['data']);
			$org_x = imagesx($org_image);
			$org_y = imagesy($org_image);
			$org_type = ($string['type'] == 'jpg') ? 'jpeg' : $string['type'];
		} else {
			$org_type = $types[$info[2]];
			$org_image = call_user_func('imagecreatefrom'.$org_type,$source);
			$org_x = $info[0];
			$org_y = $info[1];
		}

		$new_x = $zc['res_'.$type.'_x'];
		$quality = $zc['res_'.$type.'_qual'];
		$res_out_type = $zc['res_out_type'];

		if (!$zc['res_out_x_lmt'] || $org_x > $new_x) {
			$new_y = intval($org_y * $new_x / $org_x);
			$new_image = imagecreatetruecolor($new_x, $new_y);
			imagecopyresampled($new_image, $org_image, 0, 0, 0, 0, $new_x, $new_y, $org_x, $org_y);
		} else {
			$res_out_type = $org_type;
			$new_image = $org_image;
			$new_x = $org_x;
			$new_y = $org_y;
		}
		$font = (isset($zc[$type.'_img_txt_font'])) ? $zc[$type.'_img_txt_font'] : false;

		if (!empty($txt) && $font && file_exists($font) && function_exists('imagettftext')) {
			$clr_str = $zc[$type.'_img_txt_color'];
			$font_size = $zc[$type.'_img_txt_font_size'];

			$txt = wordwrap(html_entity_decode($txt), $zc[$type.'_img_txt_wrap'], "\r\n");
			$box = imagettfbbox($font_size, 0, $font, $txt);
			$rgb = explode(',',$clr_str);
			$color = imagecolorallocate($new_image, $rgb[0], $rgb[1], $rgb[2]);
			$bx = $box[2] - $box[0];
			$by = $box[5] - $box[3];
			$mod = ($type == 'search') ? 1 : .5;
			$vert = 2 + (substr_count($txt, "\r\n") * $mod);
			$txt_x = ($new_x - $bx)/2;
			$txt_y = ($new_y - $by)/$vert;
			imagettftext($new_image, $font_size, 0, $txt_x, $txt_y, $color, $font, $txt);
		}

		imageinterlace($new_image, true);

		if (function_exists('image'.$res_out_type)) {
			if ($res_out_type == 'png') $quality = round(abs(($quality - 100) / 11.111111));

			if ($zc['cache_imgs'] && $cache_file && is_writeable($zc['cache_imgs_dir'])) {
				call_user_func('image'.$res_out_type,$new_image,$cache_file,$quality);
			}
			while(@ob_end_clean());
			Header('Content-type: image/'.$res_out_type);
			call_user_func('image'.$res_out_type,$new_image,null,$quality);
		}

		@imagedestroy($new_image);
		@imagedestroy($org_image);
	} else {
		zina_debug(zt('Zina Error: Could not open image file: @file', array('@file'=>$source)));
	}
}

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * WRITE/UPDATE/DELETE functions
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

function zina_write_playlist($songs, $filename, $type = '') {
	global $zc;
	$files = array();
	if (!empty($songs)) {
		foreach ($songs as $song) {
			$files[] = zrawurldecode($song);
		}
	}


	if($zc['session_pls'] && strstr($filename,'zina_session_playlist')) {
		if (empty($files)) {
			unset($_SESSION['z_sp']);
		} else { # new/additions
			if ($type == 'a' && isset($_SESSION['z_sp'])) {
				$existing = unserialize_utf8($_SESSION['z_sp']);
				$files = array_merge($existing, $files);
			}
			if (sizeof($files) > $zc['pls_length_limit']) {
				$files = array_slice($files, 0, 100);
				zina_set_message(zt('Session playlists limited to @num items.', array('@num'=>$zc['pls_length_limit'])), 'warn');
			}
			$_SESSION['z_sp'] = utf8_encode(serialize($files));
		}
	} else {
		$content = serialize($files);
		$filename = $zc['cache_pls_dir'].'/'.$filename;
		if ($type == 'a' && file_exists($filename)) {
			$existing = zunserialize_alt(file_get_contents($filename));
			if (!empty($existing)) $content = serialize(array_merge($existing, $files));
		}
		file_put_contents($filename, $content);
		if (empty($files) && ($type == 't') && file_exists($filename)) unlink($filename);
	}
}

function zina_delete_playlist_custom($pls_name) {
	global $zc;
	if ($pls_name == 'zina_session_playlist') {
		unset($_SESSION['z_sp']);
	} elseif ($pls_name != 'new_zina_list') {
		$filename = $zc['cache_pls_dir'].'/_zina_'.$pls_name.'.m3u';
		if (file_exists($filename)) unlink($filename);
	}
}

function zina_delete_tmpl_file($file) {
	global $zc;
	if (strstr($file,'..')) return false;
	$cache_file = $zc['cache_tmpl_dir'].'/'.$file;
	if (file_exists($cache_file)) return unlink($cache_file);
	return true;
}

function zina_delete_files($path) {
	if (file_exists($path) && is_dir($path) && is_writeable($path)) {
		if ($d = @dir($path)) {
			while($entry = $d->read()) {
				if ($entry != '.' && $entry != '..') unlink($path.'/'.$entry);
			}
			$d->close();
			return true;
		}
	}
	zina_set_message(zt('Cannot open directory to delete files.'));
	return false;
}

function zina_write_settings() {
	global $zc;
	$fields = zina_get_settings('cfg');
	$lang = zina_get_settings('lang');
	$titles = &$lang['titles'];
	$errors = false;

	if (!zina_token_sess_check()) {
		zina_debug(zt('Session token does not match.'));
		return false;
	}

	$config = "<?php\n";
	$settings = array();

	foreach($fields as $cat => $x) {
		if (($zc['embed'] != 'standalone') && ($cat == 'auth' || $cat == 'integration' ||
				($cat == 'db' && (!isset($POST['db']) || !$POST['db'])))) {
			continue;
		}
		foreach($x as $name => $field) {
			if (isset($_POST[$name])) {
				$input = $_POST[$name];
			} else {
				$input = null;
			}

			if ($input != $field['def']) {
				if ($name == 'adm_pwd') {
					if (!empty($input)) {
						if ($input == $_POST['adm_pwd_con']) {
							if (zina_check_password($zc['adm_name'], $_POST['adm_pwd_old'])) {
								$config .= "\$adm_pwd = '".zina_get_hash($_POST['adm_pwd'])."';\n";
							} else {
								$errors = true;
								zina_set_message(zt('Old password is incorrect.').' '.zt('Password not changed'),'warn');
							}
						} else {
							$errors = true;
							zina_set_message(zt('New password and confirmation password do not match.').' '.zt('Password not changed'),'warn');
						}
					} else {
						$config .= "\$adm_pwd = \"".$zc['adm_pwd']."\";\n";
					}
				} else {
					if ($name == 'main_dir_title' && $input == zt('Artists')) continue;

					if (isset($field['v'])) {
						foreach($field['v'] as $key => $type) {
							$opts = null;
							if (is_array($type)) {
								$opts = $type;
								$type = $key;
							}

							if (!zina_validate($type, $input, $opts)) {
								$errors = true;
								$title = $titles[$name];
								if ($opts) $type = current($opts);
								zina_validate_error_message($type, $title, $input);
								continue 2;
							}
						}
					}
					$quote = '"';
					if (is_array($input)) {
						$input = serialize($input);
						$quote = "'";
					}
					$arr = array("\n"=>"\\n","\t"=>"\\t","\r"=>"\\r","\t"=>"\\t");
					$config .= "\$$name = $quote". str_replace(array_keys($arr),array_values($arr),$input)."$quote;\n";
					$settings[$name] = $input;

				}
			}
		}
	}

	$config .= "?>";

	if (!$errors) {
		$file = $zc['zina_dir_abs'].'/zina.ini.php';

		if ($zc['embed'] != 'standalone' && $zc['database'] && (!isset($_POST['db']) || !$_POST['db'])) {
			if (!zvar_set('settings', $settings)) {
				$errors = true;
				zina_set_message(zt('Cannot save settings to cms database'),'error');
			}
		} elseif ((file_exists($file) && is_writeable($file)) || (!file_exists($file) && is_writeable($zc['zina_dir_abs']))) {
			if (!file_put_contents($file, $config)) {
				$errors = true;
				zina_set_message(zt('Cannot write config file'),'error');
			}
		} else {
			$errors = true;
			zina_set_message(zt('Config file or directory is not writeable.'),'error');
		}

		if ($errors) {
			zina_set_message(
				'<p>'.zt('You may manually put the following in %cfg', array('%cfg'=>$zc['zina_dir_abs'].'/zina.ini.php')).
				'<form><textarea cols="80" rows="20">'.$config.'</textarea></form>'
				,'warn'
			);
		}
	}
	return !$errors;
}

function zina_validate_error_message($type, $title, $input) {
	if ($type == 'req') {
		zina_set_message(zt('@title: This option is required', array('@title'=>$title)),'warn');
	} elseif ($type == 'tf') {
		zina_set_message(zt('@title: Must be true or false (this should not happen)', array('@title'=>$title)),'warn');
	} elseif ($type == 'int') {
		zina_set_message(zt('@title: Must be an integer', array('@title'=>$title)),'warn');
	} elseif ($type == 'int_split') {
		zina_set_message(zt('@title: Must be a string of integers separated by ",".', array('@title'=>$title)),'warn');
	} elseif ($type == 'file_exists') {
		zina_set_message(zt('@title: File @file does not exists', array('@file'=>$input, '@title'=>$title)),'warn');
	} elseif ($type == 'dir_exists') {
		zina_set_message(zt('@title: Directory @dir does not exists', array('@dir'=>$input, '@title'=>$title)),'warn');
	} elseif ($type == 'path_relative') {
		zina_set_message(zt('@title: Directory @dir must exist and be relative to the zina directory', array('@dir'=>$input, '@title'=>$title)),'warn');
	} else {
		zina_set_message(zt('Unknown validation error[@type][@title][@input]', array('@type'=>$type, '@title'=>$title,'@input'=>$input)),'warn');
	}
}

function zina_set_scrobbler_queue($queue = array(), $clear = false) {
	global $zc;
	if ($zc['cache']) {
		$que_file = $zc['cache_dir_private_abs'].'/scrobbler_queue_'.$zc['user_id'].'.txt';
		if (empty($queue)) {
			if ($clear) {
				if (file_exists($que_file)) unlink($que_file);
			} elseif (file_exists($que_file)) {
				return unserialize(file_get_contents($que_file));
			}
		} else {
			return file_put_contents($que_file, serialize($queue));
		}
	}
	return array();
}

function zina_rename_playlist($old, $new) {
	global $zc;
	$filename = $zc['cache_pls_dir'].'/_zina_'.$new.'.m3u';

	if ($new == '') return false;
	if ($new == $old) return true;
	if (file_exists($filename)) return false;
	if (!rename($zc['cache_pls_dir'].'/_zina_'.$old.'.m3u', $filename)) return false;

	return true;
}

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * SEARCH functions
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function zina_search_embed($term, $limit = 0) {
	global $zc;
	if ($zc['db_search']) {
		$results = zdbq_array("SELECT path, type, title, context FROM {search_index} WHERE title LIKE '%%%s%%' ORDER BY title LIMIT %d",
	  		array($term, $limit));
	} else {
		$results = zina_search_cache($term, $limit);
	}
	zina_content_search_list($results, false);
	return $results;
}

function zina_search_cache($term, $limit = false) {
	$results = array();
	$num = 0;

	$results = array_merge($results, zina_search_dirs($term, $limit));
	if ($limit) {
		$limit -= sizeof($results);
		if (!$limit) return $results;
	}
	$results = array_merge($results, zina_search_files($term, $limit));
	if (!empty($results)) usort($results, 'zsort_title');

	return $results;
}

function zina_search_dirs($term, $limit = false) {
	$found = array();

	$dirs = zina_core_cache('dirs');
	$num = 0;

	if (!empty($dirs)) {
		foreach ($dirs as $path) {
			if (stristr($path, $term)) {
				$found[] = zina_search_dir_helper($path);
				if ($limit && ++$num >= $limit) break;
			}
		}
	}
	return $found;
}

function zina_search_dirs_for_missing_images($limit = false) {
	global $zc;
	$missing = array();

	$dirs = zina_core_cache('dirs');
	$num = 0;

	if (!empty($dirs)) {
		foreach ($dirs as $path) {
			if (empty($path)) continue;
			$full_path = $zc['mp3_dir'].(!empty($path) ? '/'.$path : '');
			if (!zina_get_dir_item($full_path,'/\.('.$zc['ext_graphic'].')$/i')) {
				$missing[$path] = $path;
			}
		}
	}
	return $missing;
}

function zina_search_dir_helper($path) {
	global $zc;
	$type = 'directory';
	$context = null;
	$count = substr_count($path, '/');
	if ($zc['search_structure']) {
		if ($count == 0) {
			$type = 'artist';
		} elseif ($count == 1) {
			$type = 'album';
			$context = ztheme('title',substr($path, 0, strpos($path,'/')));
		}
	}
	$title = ($count > 0) ? substr($path, strrpos($path,'/')+1) : $path;

	# order is important
	return array(
		'title' => ztheme('title',$title),
		'type' => zt($type),
		'context' => $context,
		'id' => $path,
		'path' => $path,
		'genre' => null,
		'image' => null,
		'year' => null,
		'mtime' => null,
	);
}

function zina_search_files($term, $limit = false) {
	global $zc;
	$found = array();
	$num = 0;

	$files = zina_core_cache('files');

	if (!empty($files)) {
		foreach ($files as $path) {
			$file = basename($path);
			if (stristr($file, $term)) {
				$x = explode('/', $path);
				$len = sizeof($x);
				if ($len == 2)
					$context = ztheme('title',$x[0]);
				elseif ($len > 2)
					$context = ztheme('title',$x[$len - 3]);
				else
					$context = null;

				# order is important
				$found[] = array(
					'title' => ztheme('song_title', preg_replace('/\.('.$zc['ext_mus'].')$/i', '', $file)),
					'type' => 'song',
					'context' => $context,
					'id' => $path,
					'path' => $path,
					'genre' => null,
					'image' => null,
					'year' => null,
					'mtime' => null,
				);
				if ($limit && ++$num >= $limit) break;
			}
		}
	}
	return $found;
}

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * MISC functions
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
#todo: incorp timelimit?
#todo: private not implemented or used...
#type sitemap
function zina_cache($type, $func, $args = null,  $gz, $public = true, $force = false) {
	global $zc;

	$cache = $zc[$type.'_cache'];
	$file = $zc[$type.'_file'];
	$cache_file = (($public) ?  $zc['cache_dir_public_abs'] : $zc['cache_dir_private_abs']).'/'.$file;

	if ($cache && !$force) {
		if (file_exists($cache_file)) {
			if ($public) {
				while(@ob_end_clean());
				header('Location: '.$zc['zina_dir_rel'].'/'.$zc['cache_dir_public_rel'].'/'.$file);
				exit;
			} else {
				return implode("\n", gzfile($cache_file));
			}
		}
	}
	$output = call_user_func_array($func, $args);
	if (!$output) return false;

	if ($cache || $force) {
		if ($gz) {
			$gz = gzopen($cache_file,'w1');
			gzwrite($gz, $output);
			gzclose($gz);
		} else {
			file_put_contents($cache_file, $output);
		}
	}
	return $output;
}

function zina_reorder_playlist($songs, $order) {
	$new = $neworder = array();

	$count = sizeof($songs);
	if ($count == 0) return null;
	for ($i = 0; $i < $count; $i++){
		if (empty($order[$i])) continue; # 0 == delete
			$j = (zina_validate('int', $order[$i])) ? $order[$i] : 99;
			$new[$j][] = $songs[$i];
	}
	ksort($new);
	$songs = array();

	foreach($new as $neworder) {
		foreach($neworder as $song) $songs[] = $song;
	}
	return $songs;
}

function zina_is_admin() {
	global $zc;

	if (isset($zc['conf']['is_admin'])) return $zc['conf']['is_admin'];

	if (isset($_SESSION['za-'.ZINA_VERSION]) && $_SESSION['za-'.ZINA_VERSION] == true) return true;

	if ($zc['adm_ip'] && strstr($zc['adm_ips'], $_SERVER['REMOTE_ADDR'])) return true;

	if ($zc['loc_is_adm'] && $_SERVER['SERVER_ADDR'] == $_SERVER['REMOTE_ADDR'])  return true;

	if ($zc['session'] && isset($_COOKIE['ZINA_SESSION'])) {
		$sess_file = $zc['cache_dir_private_abs'].'/sess_'.zcheck_plain($_COOKIE['ZINA_SESSION']);
		if (file_exists($sess_file) && filemtime($sess_file) + (60*60*$zc['session_lifetime']) > time()) {
			@touch($sess_file);
			$_SESSION['za-'.ZINA_VERSION] = true;
			return true;
		}
	}

	if (!$_SESSION && isset($_GET['zid']) && isset($_GET['l']) && ($_GET['l'] == '6' || $_GET['l'] == '10')) {
		return (zina_token('verify', $_GET['zid']));
	}

	return false;
}

function zina_is_various($cur_dir, $path) {
	global $zc;
	if ($zc['various']) {
		if (file_exists($cur_dir.'/'.$zc['various_file'])) return true;
		if ($zc['various_above'] && !empty($path)) {
			$parts = explode('/', $path);
			$size = sizeof($parts);
			if ($size > 1) {
				$dir_above = preg_replace('/'.preg_quote($parts[($size-1)]).'$/i', '', $cur_dir);
				if (file_exists($dir_above.$zc['various_file'])) return true;
			}
		}
	}
	return false;
}

function zina_is_category($path) {
	global $zc;

	$cat_file = $path.'/'.$zc['cat_file'];
	$cat_file_exists = $category = file_exists($cat_file);

	if (!$category && $zc['cat_auto']) {
		$num = $zc['cat_auto_num'];
		$dir_skip = $zc['dir_skip'];
		$i = 1;
		if ($result = scandir($path)) {
			foreach($result as $file) {
				if ($file== "." || $file== '..' || $file[0] == $dir_skip) continue;
				if (is_dir($path.'/'.$file)) {
					if (++$i > $num) {
						$category = true;
						break;
					}
				}
			}
		}
	}

	if ($category) {
		$cat['images'] = $zc['cat_images'];
		$cat['cols'] = $zc['cat_cols'];
		$cat['truncate'] = $zc['cat_truncate'];
		$cat['split'] = $zc['cat_split'];
		$cat['sort'] = $zc['cat_sort'];
		$cat['pp'] = $zc['cat_pp'];

		if ($cat_file_exists) {
			$cat_xml = file_get_contents($cat_file);
			if (!empty($cat_xml)) {
				$cat_settings = xml_to_array($cat_xml, 'category');
				if (!empty($cat_settings)) {
					$cat_settings = $cat_settings[0];
					$cat['images'] = isset($cat_settings['images']) ? $cat_settings['images'] : $cat['images'];
					$cat['cols'] = isset($cat_settings['columns']) ? $cat_settings['columns'] : $cat['cols'];
					$cat['truncate'] = isset($cat_settings['truncate']) ? $cat_settings['truncate'] : $cat['truncate'];
					$cat['split'] = isset($cat_settings['split']) ? $cat_settings['split'] : $cat['split'];
					$cat['pp'] = isset($cat_settings['per_page']) ? $cat_settings['per_page'] : $cat['pp'];
					$cat['sort'] = isset($cat_settings['sort']) ? $cat_settings['sort'] : $cat['sort'];
				}

				$settings = xml_to_array($cat_xml, 'override');
				if (!empty($settings)) {
					$settings = $settings[0];
					foreach($settings as $key => $val) {
						if (in_array($key, array('dir_tags'))) {
							$zc[$key] = $val;
							#$cat['override'][$key] = $val;
						}
					}
				}
			}
		}
		return $cat;

	}
	return false;
}

function zina_check_password($un, $up) {
	global $zc;
	$hash = base64_decode($zc['adm_pwd']);
	$new_hash = pack("H*", sha1($up.substr($hash,20)));
	return ($zc['adm_name'] == $un && substr($hash,0,20) == $new_hash);
}

function zina_get_hash($password) {
	$salt = substr(pack("H*", sha1( substr(pack('h*', sha1(mt_rand())), 0, 8).$password)), 0, 4);
	return base64_encode( pack("H*", sha1($password.$salt)).$salt);
}

function zina_cron_run($opts = array()) {
	global $zc;
	$semaphore = $zc['cache_dir_private_abs'].'/.cron';

	if (file_exists($semaphore)) {
		if (time() - filemtime($semaphore) > 3600) {
			zina_debug(zt('Cron has been running for more than an hour and is most likely stuck.'));
			@unlink($semaphore);
		} else {
			zina_debug(zt('Attempting to re-run cron while it is already running.'));
		}
	} else {
		touch($semaphore);

		if ($zc['database']) {
			require_once($zc['zina_dir_abs'].'/batch.php');
			$operations = array();
			foreach(array('dirs', 'files_assoc', 'genre') as $type) {
				$cache_file = $zc['cache_'.$type.'_file'];
				$mtime = (file_exists($cache_file)) ? filemtime($cache_file) : 0;
				if (time() - $mtime > $zc['cache_expire'] * 86400 ) {
					@set_time_limit($zc['timeout']);
					$operations[] = array('zina_core_cache_batch', array($type, '', array('force'=>true)));
				}
			}

			if ($zc['low']) {
				foreach(array('files_assoc') as $type) {
					@set_time_limit($zc['timeout']);
					$cache_file = $zc['cache_'.$type.'_file'];
					$mtime = (file_exists($cache_file)) ? filemtime($cache_file) : 0;
					if (time() - $mtime > $zc['cache_expire'] * 86400 ) {
						$operations[] = array('zina_core_cache_batch', array($type, '', array('force'=>true, 'low'=>true)));
					}
				}
			}
			zdb_cron_run($operations);

			if ($zc['sitemap']) {
				$cache_file = $zc['cache_dir_public_abs'].'/'.$zc['sitemap_file'];
				$mtime = (file_exists($cache_file)) ? filemtime($cache_file) : 0;
				if (time() - $mtime > $zc['sitemap_cache_expire'] * 86400 ) {
					$operations[] = array('zina_cache', array('sitemap', 'zina_content_sitemap', null, ($zc['sitemap'] == 2), true, true));
				}
			}

			$batch = array(
				'title' => zt('Cron Run'),
				'finished_message' => zt('Cron completed successfully.'),
				'operations' => $operations,
				'finished' => 'zina_cron_finished',
  			);

			zbatch_set($batch);

			$redirect_path = (isset($opts['redirect_path'])) ? $opts['redirect_path'] : null;
			$redirect_query = (isset($opts['redirect_query'])) ? $opts['redirect_query'] : null;
			zbatch_process(null, $redirect_query, $opts);

		} else { # NO DATABASE

			foreach(array('dirs', 'files_assoc', 'genre') as $type) {
				$cache_file = $zc['cache_'.$type.'_file'];
				$mtime = (file_exists($cache_file)) ? filemtime($cache_file) : 0;
				if (time() - $mtime > $zc['cache_expire'] * 86400 ) {
					@set_time_limit($zc['timeout']);
					zina_core_cache($type, '', array('force'=>true));
				}
			}

			if ($zc['low']) {
				foreach(array('files_assoc') as $type) {
					@set_time_limit($zc['timeout']);
					$cache_file = $zc['cache_'.$type.'_file'];
					$mtime = (file_exists($cache_file)) ? filemtime($cache_file) : 0;
					if (time() - $mtime > $zc['cache_expire'] * 86400 ) {
						zina_core_cache($type, '', array('force'=>true,'low'=>true));
					}
				}
			}

			if ($zc['sitemap']) {
				$cache_file = $zc['cache_dir_public_abs'].'/'.$zc['sitemap_file'];
				$mtime = (file_exists($cache_file)) ? filemtime($cache_file) : 0;
				if (time() - $mtime > $zc['sitemap_cache_expire'] * 86400 ) {
					zina_cache('sitemap', 'zina_content_sitemap', null, ($zc['sitemap'] == 2), true, true);
				}
			}
		}

		@unlink($semaphore);
	}
}

function zina_cron_feature($cron) {
	global $zc;
	$now = getdate();
	#$now = getdate(mktime ( 8, 45, 0, 12, 29, 2003));
	$n[0] = $now['wday']; # 0-6; 0 = sun
	$n[1] = $now['mon']; # 1-12
	$n[2] = $now['mday']; # 0-31
	$n[3] = ($now['hours'] * 60) + $now['minutes']; #0-23:0-59

	foreach($cron as $feature=>$crontabs) {
		foreach($crontabs as $key=>$crontab) {
			$zc[$feature] = zina_cron_feature_check($crontab, $n);
		}
	}
}

#crontab = 'WDAY MON MDAY HH:MM';
function zina_cron_feature_check($crontab, $now) {
	$tab = explode(' ',$crontab);
	$size = sizeof($tab);
	if ($size != 4) {
		zina_debug(zt('Bad zina_cron_feature_check option'),'warn');
		return false;
	}

	for($i=0; $i<4; $i++) {
		$cur = $tab[$i];
		$n = $now[$i];
		if($cur[0] == '*') continue;

		if(strpos($cur,',') !== false) { #multi
			$multi = explode(',',$cur);
			if (!in_array($n, $multi)) return false;
		} elseif (strpos($cur,'-') !== false) { #range
			$range = explode('-',$cur);
			if ($i == 3) {
				$hm = explode(':',$range[0]);
				$r0 = ($hm[0] * 60) + $hm[1];
				$hm = explode(':',$range[1]);
				$r1 = ($hm[0] * 60) + $hm[1];
			} else {
				$r0 = $range[0];
				$r1 = $range[1];
			}
			if ($r0 < $r1) {
				if($n < $r0 || $n > $r1) return false;
			} else {
				if($n < $r0 && $n > $r1) return false;
			}
		} else { #single
			if ($n != $cur) return false;
		}
	}
	return true;
}

class remoteFile {
	function remoteFile($file, $info=false, $tag=false) {
		$this->tag = $this->info = false;
		$this->filesize = 0;
		$this->remote = true;
		$this->genre = 'Unknown';

		$content = file_get_contents($file);
		$arr = xml_to_array($content);
		$arr = (empty($arr)) ? get_meta_tags($file) : $arr[0];
		if (empty($arr)) return;

		$this->url = $arr['url'];
		$this->download = isset($arr['download']) ? $arr['download'] : null;

		if ($info) {
			if (isset($arr['time'])) {
				$this->time = $arr['time'];
				$this->filesize = isset($arr['filesize']) ? $arr['filesize'] : 0;
				$this->bitrate = isset($arr['bitrate']) ? $arr['bitrate'] : 0;
				$this->frequency = isset($arr['frequency']) ? $arr['frequency'] : 0;
				$this->length = isset($arr['length']) ? $arr['length'] : 0;
				$this->stereo = isset($arr['stereo']) ? $arr['stereo'] : 1;
				$this->info = true;
			}
		}
		if ($tag) {
			if (isset($arr['title'])) {
				$this->title = $arr['title'];
				$this->artist = isset($arr['artist']) ? $arr['artist'] : '';
				$this->album = isset($arr['album']) ? $arr['album'] : '';
				$this->year = isset($arr['year']) ? $arr['year'] : '';
				$this->genre = isset($arr['genre']) ? $arr['genre'] : 'Unknown';
				$this->tag = true;
			}
		}
	}
}

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * SETTINGS & INIT
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

function zina_init($conf) {
	global $zc, $z_dbc, $zina_lang_code, $zina_lang_path;

	if ($conf['embed'] == 'standalone') {
		ini_set('arg_separator.output',     '&amp;');
		ini_set('magic_quotes_runtime',     0);
		ini_set('magic_quotes_sybase',      0);
		ini_set('session.cache_expire',     259200); # 3 days
		ini_set('session.cache_limiter',    'none');
		ini_set('session.cookie_lifetime',  1209600); # 14 days
		ini_set('session.gc_maxlifetime',   259200);
		ini_set('session.use_only_cookies', 1);
		ini_set('session.use_trans_sid',    0);
		ini_set('url_rewriter.tags',        '');
		session_start();
		$zc['login'] = true;
	} else {
		$zc['login'] = false;
	}

	# FOR IIS
	if (!isset($_SERVER['DOCUMENT_ROOT'])) {
		if (isset($_SERVER['SCRIPT_FILENAME'])) {
			$_SERVER['DOCUMENT_ROOT'] = str_replace( '\\', '/', substr($_SERVER['SCRIPT_FILENAME'], 0, 0-strlen($_SERVER['PHP_SELF'])));
		} elseif (isset($_SERVER['PATH_TRANSLATED'])) {
			$_SERVER['DOCUMENT_ROOT'] = str_replace( '\\', '/', substr(str_replace('\\\\', '\\', $_SERVER['PATH_TRANSLATED']), 0, 0-strlen($_SERVER['PHP_SELF'])));
		}
	}

	$zc['conf'] = $conf;
	$zc['database'] = $zc['db_cms'] = $zc['is_admin'] = $zc['play_local'] = false;
	$zc['charset'] = (isset($conf['charset'])) ? $conf['charset'] : 'utf-8'; #needed for blocks

	$zc['windows'] = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
	$zc['zina_dir_abs'] = (($zc['windows']) ? str_replace("\\",'/',$conf['index_abs']) : $conf['index_abs']).'/zina';

	# for modules
	if (isset($conf['index_rel'])) {
		$zc['index_rel'] = $conf['index_rel'];
	}

	$settings = zina_get_settings('cfg');

	require_once($zc['zina_dir_abs'].'/common.php');
	require_once($zc['zina_dir_abs'].'/theme.php');
	require_once($zc['zina_dir_abs'].'/mp3.class.php');

	$ini = $zc['zina_dir_abs'].'/zina.ini.php';
	if (file_exists($ini)) require($ini);

	if ($conf['embed'] != 'standalone') {
		if ($z_dbc = zina_get_active_db($db_opts)) {
			if (in_array($db_opts['type'], zina_get_db_types())) {
				$zc['db_pre'] = $db_opts['prefix'].'zina_';
				$zc['db_type'] = $db_opts['type'];
				require_once($zc['zina_dir_abs'].'/database-'.$zc['db_type'].'.php');
				require_once($zc['zina_dir_abs'].'/database.php');
				$zinaini = zvar_get('settings', null);
				if (!empty($zinaini)) extract($zinaini, EXTR_OVERWRITE);
				$zc['database'] = $zc['db_cms'] = true;
			} else {
				zina_debug(zt('Unsupported CMS database type: @type', array($db_opts['type'])));
			}
		}
	}

	if (isset($login)) $zc['login'] = $login;

	foreach($settings as $section => $cat) {
		# dont allow defaults to override cms db settings
		if ($section == 'db' && $zc['db_cms']) continue;
		foreach($cat as $key => $val) {
			if ($key == 'res_in_types') {
				$zc[$key] = isset($$key) ? unserialize($$key) : $val['def'];
			} else {
				$zc[$key] = isset($$key) ? $$key : $val['def'];
			}
			if ($key == 'media_types_cfg') $media_types_default = $val['def'];
		}
	}

	$zina_lang_code = $zc['lang'];

	#TODO: get confirmation this works
	if (!empty($zc['locale'])) {
		if (($locale = setlocale(LC_ALL, $zc['locale'])) === false) {
			if ($_GET['l'] != 21) {
				zina_set_message(zt('Locale setting not supported by your system.  Locale is set to @locale.', array('@locale'=> setlocale(LC_ALL,null))),'warn');
				$zc['locale'] = setlocale(LC_ALL,0);
			}
		}
	}

	@date_default_timezone_set($zc['timezone']);
	@set_time_limit($zc['timeout']);

	zpath_to_theme($zc['zina_dir_rel'].'/themes/'.$zc['theme']);
	$zc['theme_path_abs'] = $zc['zina_dir_abs'].'/themes/'.$zc['theme'];

	$zina_lang_path = false;
	$lang_user = $zc['theme_path_abs'].'/'.$zina_lang_code.'.php';
	if (file_exists($lang_user)) {
		$zina_lang_path = $lang_user;
	} elseif (file_exists($zc['zina_dir_abs'].'/lang/'.$zina_lang_code.'.php')) {
		$zina_lang_path = $zc['zina_dir_abs'].'/lang/'.$zina_lang_code.'.php';
	}

	if (function_exists('mb_strlen')) {
		$zc['multibyte'] = true;
		mb_internal_encoding($zc['charset']);
  		mb_language('uni');
	} else {
		$zc['multibyte'] = false;
		zina_debug(zt('No multibyte support.'));
	}

	#todo: still needed with new error checking???
	if (!is_dir($zc['mp3_dir'])) {
		$default_music = $zc['zina_dir_abs'].'/demo';
		if (!is_dir($default_music)) $default_music = $zc['zina_dir_abs'];
		zina_set_message(zt('Your music directory does not exist!'),'error');
		$zc['mp3_dir'] = $default_music;
	}
	if ($zc['stream_int'] || !preg_match('#^'.$_SERVER['DOCUMENT_ROOT'].'#i',$zc['mp3_dir'])) {
		$zc['stream_int'] = true;
	} else {
		$zc['www_path'] = preg_replace('#^'.$_SERVER['DOCUMENT_ROOT'].'#i', '', $zc['mp3_dir']);
	}

	$zc['cache_dir_public_abs'] = $zc['zina_dir_abs'].'/'.$zc['cache_dir_public_rel'];
	$zc['cache_imgs_dir'] = $zc['zina_dir_abs'].'/'.$zc['cache_imgs_dir_rel'];
	$zc['cache_tmpl_dir'] = $zc['cache_dir_private_abs'].'/tmpl';
	$zc['cache_pls_dir'] = $zc['cache_dir_private_abs'].'/playlist';
	$zc['cache_zip_dir'] = $zc['cache_dir_private_abs'].'/zip';

	$md5 = md5($zc['mp3_dir']);
	$zc['cache_dirs_file'] = $zc['cache_dir_private_abs'].'/'.$md5.'_dirs.gz';
	$zc['cache_files_assoc_file'] = $zc['cache_dir_private_abs'].'/'.$md5.'_files_assoc.gz';
	$zc['cache_genre_file'] = $zc['cache_dir_private_abs'].'/'.$md5.'_genre.gz';

	$zc['sitemap_file'] = ($zc['sitemap'] == 2) ? 'sitemap.xml.gz' : 'sitemap.xml';

	$zc['encoders'] = zina_parse_ini_string($zc['enc_arr']);
	$zc['ext_enc'] = implode('|',array_keys($zc['encoders']));

	$types = ($zc['other_media_types']) ? $zc['media_types_cfg'] : $media_types_default;
	$zc['media_types'] = zina_parse_ini_string($types);
	$zc['ext_mus'] = implode('|',array_keys($zc['media_types']));
	$zc['ran_opts'] = explode(',', $zc['ran_opts']);
	$zc['auth'] = ($zc['apache_auth'] && isset($_SERVER['PHP_AUTH_USER'])) ? $_SERVER['PHP_AUTH_USER'].':'.$_SERVER['PHP_AUTH_PW'].'@' : '';

	$zc['resize_types'] = implode('|',$zc['res_in_types']);

	if ($zc['mm']) {
		$zc['mm_types'] = zina_parse_ini_string($zc['mm_types_cfg']);
		$zc['mm_ext'] = implode('|',array_keys($zc['mm_types']));
	}

	if ($zc['cron']) {
		$zc['cron_options'] = zina_parse_ini_string($zc['cron_opts']);
		if (is_array($zc['cron_options'])) $zc['cron_options'] = current($zc['cron_options']);
	}

	if ($zc['song_extras']) {
		$zc['song_es'] = zina_parse_ini_string($zc['song_extras_opt']);
		$zc['song_es_exts'] = array_keys($zc['song_es']);
	}

	$zc['embed'] = (isset($conf['embed'])) ? $conf['embed'] : 'standalone';
	if (isset($conf['clean_urls'])) {
		$zc['clean_urls'] = $conf['clean_urls'];
		if (isset($conf['clean_urls_hack'])) $zc['clean_urls_hack'] = $conf['clean_urls_hack'];
	}
	if (isset($conf['url_query'])) $zc['url_query'] = $conf['url_query'];
	if (isset($conf['lastfm'])) {
		$zc['lastfm'] = $conf['lastfm'];
		$zc['lastfm_username'] = $conf['lastfm_username'];
		$zc['lastfm_password'] = $conf['lastfm_password'];
	}
	if (isset($conf['twitter'])) {
		$zc['twitter'] = $conf['twitter'];
		$zc['twitter_username'] = $conf['twitter_username'];
		$zc['twitter_password'] = $conf['twitter_password'];
	}

	$theme_file = $zc['theme_path_abs'].'/index.php';
	if (file_exists($theme_file)) require_once($theme_file);

	if (isset($zc['db']) && $zc['db'] && in_array($zc['db_type'], zina_get_db_types())) {
		require_once($zc['zina_dir_abs'].'/database-'.$zc['db_type'].'.php');
		if ($z_dbc = zdb_connect($zc['db_host'], $zc['db_name'], $zc['db_user'], $zc['db_pwd'], ($conf['embed'] != 'standalone'))) {
			require_once($zc['zina_dir_abs'].'/database.php');
			$zc['database'] = true;
		}
	}

	$zc['search'] = ($zc['search'] || $zc['db_search']);
	$zc['db_search'] = ($zc['db_search'] && $zc['search'] && $zc['database']);

	if ($zc['clean_urls'] && $zc['clean_urls_hack']) {
		$zc['index_rel'] = (isset($zc['index_rel'])) ? $zc['index_rel'].'/'.$zc['clean_urls_index'] : $zc['clean_urls_index'];
		if (isset($_GET['p']) && !(strpos($_GET['p'],$zc['clean_urls_index'])===false)) {
			$_GET['p'] = substr($_GET['p'],strlen($zc['index_rel'])+1);
		}
	}

	$zc['session'] = ($zc['cache'] && $zc['embed'] == 'standalone' && $zc['session']);

	$zc['is_admin'] = zina_is_admin();

	if ($_SERVER['SERVER_ADDR'] == $_SERVER['REMOTE_ADDR'] || $zc['pos']) {
		if ($zc['local_path'] || $zc['pos']) {
			$zc['stream_int'] = false;
			$zc['play_local'] = true;
		}
	}

	if (!$zc['is_admin'] && $zc['cron'] && !empty($zc['cron_options'])) zina_cron_feature($zc['cron_options']);

	$zc['user_id'] = (isset($conf['user_id']) && is_numeric($conf['user_id'])) ? (int)$conf['user_id'] : (int)($zc['is_admin']);

	if ($zc['user_id'] > 0 && isset($_SESSION)) {
		if (function_exists('zina_token')) {
			$token_value = (isset($conf['token_value'])) ? $conf['token_value'] : $zc['user_id'];
			if ($token = zina_token('get', $token_value)) $zc['token'] = 'zid='.$token;
		}
	}

	if ($zc['zinamp']) {
		$xml = file_get_contents($zc['zina_dir_abs'].'/zinamp/skins/'.$zc['zinamp_skin'].'/skin.xml');
		$mp_cfg = xml_to_array($xml,'skin');
		$zc['zinamp_width'] = $mp_cfg[0]['width'];
		$zc['zinamp_height'] = $mp_cfg[0]['height'];
		$zc['zinamp_url'] = zurl('','l=55', null, true);
	}
}

function zina_get_opt_zip() {
	$opts[0] = zt('None');
	$opts[1] = zt('External');

	if (function_exists('zip_open')) {
		$opts[2] = zt('Internal PHP Zip Library');
	}
	return $opts;
}

function zina_get_opt_playlist_format() {
	return array(
		'm3u' => zt('m3u'),
		'asx' => zt('asx'),
		'xspf' => zt('xspf'),
	);
}

function zina_get_opt_search() {
	return array(
		'browse' => zt('Browse'),
		'play' => zt('Play'),
	);
}

function zina_get_opt_filesort() {
	return array(
		0 => zt('Alphabetically'),
		1 => zt('Alphabetically Descending'),
		2 => zt('By Date'),
		3 => zt('By Date Descending'),
		4 => zt('ID3 Track Number'),
	);
}

# currently only for lyr
function zina_get_extras_opts($type) {
	#TODO: get from directory... extras/extras_'.$m.'_*
	#$opts = array('lyricwiki', 'lyricsfly');
	return array('lyricwiki', 'chartlyrics', 'lyricsmania');
}

function zina_get_extras_lyr() {
	return implode(',', zina_get_extras_opts('lyr'));
}

function zina_get_extras_images_opts() {
	#TODO: get from directory...
	#$opts = array( 'amazon' => array('num'=>2, 'artist'=>false, 'album'=> true, 'order'=>0)??, 'lastfm' = > 1);
	return array('amazon', 'amazon', 'lastfm', 'google', 'google', 'google');
}

function zina_get_opt_catsplit() {
	return array(
		0 => zt('Full Page'),
		3 => zt('Full Page (split Alphabetically)'),
		1 => zt('Split by Number Per Page'),
		2 => zt('Split Alphabetically'),
	);
}

function zina_get_opt_sitemap() {
	return array(
		0 => zt('None'),
		1 => zt('sitemap.xml'),
		2 => zt('sitemap.xml.gz'),
	);
}

function zina_get_cat_sorts() {
	return array(
		'a' => zt('Alphabetical Ascending'),
		'ad' => zt('Alphabetical Descending'),
		'd' => zt('Date Ascending'),
		'dd' => zt('Date Descending'),
	);
}

function zina_get_zinamp() {
	return array(
		0 => zt('None'),
		1 => zt('Inline'),
		2 => zt('Pop-up'),
	);
}

function zina_get_zinamp_skins() {
	global $zc;
	$dir = $zc['zina_dir_abs'].'/zinamp/skins';
	if ($d = @dir($dir)) {
		while($entry = $d->read()) {
			if ($entry == '.' || $entry == '..' || !is_dir($dir.'/'.$entry)) continue;
			$opts[$entry] = $entry;
		}
		$d->close();
		return $opts;
	}
	zina_set_message(zt('Cannot read skins directory'),'error');
	return array('WinampClassic'=>'WinampClassic');
}

function zina_get_opt_tf() {
	return array(1=>zt('True'), 0=>zt('False'));
}
function zina_get_db_types() {
	return array('mysql'=>'mysql','mysqli'=>'mysqli');
}

function zina_get_settings($type = '') {
	global $zc;

	if ($type == 'cfg') {
		$len = strlen($_SERVER['DOCUMENT_ROOT']);
		$zina_dir_rel = ($len < strlen($zc['zina_dir_abs'])) ? str_replace('\\','/',substr($zc['zina_dir_abs'], $len)) : '';

		if (function_exists('gd_info')) {
			$gd = gd_info();
			$img_resize = true;
			$freetype = (bool)$gd['FreeType Support'];
			#$freetype = function_exists('imagettftext');

			if ((isset($gd['JPG Support']) && $gd['JPG Support']) ||
				(isset($gd['JPEG Support']) && $gd['JPEG Support'])) {
				$img_ins['jpg'] = 'jpg';
				$img_ins['jpeg'] = 'jpeg';
				$img_outs['jpeg'] = 'jpeg';
				$img_out_def = 'jpeg';
			}
			if ($gd['PNG Support']) {
				$img_ins['png'] = 'png';
				$img_outs['png'] = 'png';
			}
			if ($gd['GIF Read Support']) {
				$img_ins['gif'] = 'gif';
			}
			if ($gd['GIF Create Support']) {
				$img_outs['gif'] = 'gif';
			}
			$img_ins_def = $img_ins;
		} else {
			$img_resize = false;
			$freetype = false;
			$gdno = (function_exists('zt')) ? zt('GD not detected') : 'GD not detected';
			$img_ins = array('none'=>$gdno);
			$img_ins_def = array('none'=>'none');
			$img_outs['none'] = $gdno;
			$img_out_def = 'none';
		}

		if ($zc['conf']['embed'] == 'standalone') {
			$default_theme = 'zinaGarland';
			$cat_cols = 4;
			$cat_pp = 100;
			$clean_url_hack = ($zc['windows']) ? true : false;
		} else {
			$default_theme = 'zinaEmbed';
			$cat_cols = 3;
			$cat_pp = 99;
			$clean_url_hack = false;
		}

		$lyrics = zina_get_extras_lyr();

		return array(
			'config' => array(
				'mp3_dir' => array('type'=>'textfield', 'size'=>40, 'def'=>$zc['zina_dir_abs'].'/demo', 'v'=>array('dir_exists')),
				'clean_urls' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
			),
			'auth' => array(
				'adm_name' => array('type'=>'textfield', 'def'=>'admin', 'v'=>array('req')),
				'adm_pwd' => array('type'=>'password_adm', 'def'=>'1GiuvuvgdFGQwfQkIiZ04Ro6K7s1VWnm'),
				'loc_is_adm' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'adm_ip' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'adm_ips' => array('type'=>'textfield', 'def'=>'', 'v'=>array('if'=>array('adm_ip'=>'req'))),
				'apache_auth' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'session' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'session_lifetime' => array('type'=>'textfield', 'suf'=>'hours', 'def'=>336, 'v'=>array('if'=>array ('session'=>'int'))),
			),
			'db' => array(
				'db' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'db_type' => array('type'=>'select', 'opts'=>'zina_get_db_types', 'def'=>'mysql', 'v'=>array('if'=>array('db'=>'req'))),
				'db_host' => array('type'=>'textfield', 'def'=>'localhost', 'v'=>array('if'=>array('db'=>'req'))),
				'db_name' => array('type'=>'textfield', 'def'=>'zina', 'v'=>array('if'=>array('db'=>'req'))),
				'db_user' => array('type'=>'textfield', 'def'=>'username', 'v'=>array('if'=>array('db'=>'req'))),
				'db_pwd' => array('type'=>'password', 'def'=>'password', 'v'=>array('if'=>array('db'=>'req'))),
				'db_pre' => array('type'=>'textfield', 'def'=>'z_'),
			),
			'caches' => array(
				'cache' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'cache_dir_abs' => array('type'=>'textfield', 'def'=>$zc['zina_dir_abs'].'/cache', 'v'=>array('if'=>array('cache'=>'dir_writeable'))),
				'cache_expire' => array('type'=>'textfield', 'def'=>7, 'suf'=> 'days', 'v'=>array('if'=>array('cache'=>'int')),
					'break'=>1),
				'cache_tmpl' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'cache_tmpl_expire' => array('type'=>'textfield', 'def'=>21, 'suf'=>'days', 'v'=>array('if'=>array('cache_tmpl'=>'int'))) ,
			),
			'general' => array(
				'theme' => array('type'=>'select', 'opts'=>'zina_get_themes', 'def'=>$default_theme, 'v'=>array('req')),
				'lang' => array('type'=>'select', 'opts'=>'zina_get_languages', 'def'=>'en', 'v'=>array('req')),
				'main_dir_title' => array('type'=>'textfield', 'def'=>'Artists'),
				'amg' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'play_sel' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf'),
					'break'=>1),
				'random' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'ran_opts' => array('type'=>'textfield', 'def'=>'1,5,10,25,50,0', 'v'=>array('if'=>array('random'=>'int_split'))),
				#todo: val && in ran_opts...
				'ran_opts_def' => array('type'=>'textfield', 'def'=>25, 'v'=>array('if'=>array('random'=>'int'))),
				'play_rec' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'play_rec_rand' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'honor_custom' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf'),
					'break'=>1),

				#todo: make a random section???
				'random_least_played' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'random_lp_floor' => array('type'=>'textfield', 'def'=>0, 'v'=>array('if'=>array('random_least_played'=>'int'))),
				'random_lp_perc' => array('type'=>'textfield', 'def'=>75, 'v'=>array('if'=>array('random_least_played'=>'int')), 'suf'=>'%',
					'break'=>1),

				'new_highlight' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'new_time' => array('type'=>'textfield', 'suf'=>'days', 'def'=>14, 'v'=>array('if'=>array('new_highlight'=>'int'))),
			),
			'dirs' => array(
				'dir_file' => array('type'=>'textfield', 'def'=>'index.txt', 'v'=>array('req')),
				'ext_graphic' => array('type'=>'textfield', 'def'=>'jpg|gif|png|jpeg', 'v'=>array('req')),
				'image_captions' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				#todo: val if (not empty) strlen == 1
				'dir_skip' => array('type'=>'textfield', 'def'=>'_'),
				'dir_sort_ignore' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'dir_si_str' => array('type'=>'textfield', 'def'=>'the|a|an', 'v'=>array('if'=>array('dir_sort_ignore'=>'req')),
					'break'=>1),

				'dir_list' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'subdir_truncate' => array('type'=>'textfield', 'def'=>25, 'suf'=>'characters', 'v'=>array('int')),
				#TODO: make drop Down title ASC, title DESC, year ASC, year DESC
				'dir_list_sort' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'dir_list_sort_asc' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'subdir_images' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'subdir_cols' => array('type'=>'textfield', 'def'=>3, 'v'=>array('int'),
					'break'=>1),

				'alt_dirs' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'alt_file' => array('type'=>'textfield', 'def'=>'zina_alt', 'v'=>array('if'=>array('alt_dirs'=>'req'))),
			),
			'categories' => array(
				'cat_auto' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'cat_auto_num' => array('type'=>'textfield', 'def'=>20, 'v'=>array('if'=>array('cat_auto'=>'int'))),
				'cat_file' => array('type'=>'textfield', 'def'=>'zina_category', 'v'=>array('req')),
				#todo: val: if cat_auto || cat_file
				'cat_split' => array('type'=>'select', 'opts'=>'zina_get_opt_catsplit', 'def'=>1, 'v'=>array('req')),
				'cat_various_lookahead' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				#todo val: if cat_split == 1
				'cat_cols' => array('type'=>'textfield', 'def'=>$cat_cols, 'v'=>array('int')),
				'cat_pp' => array('type'=>'textfield', 'def'=>$cat_pp, 'v'=>array('if'=>array('cat_split'=>'int'))),
				'cat_sort' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'cat_sort_default' => array('type'=>'select', 'opts'=>'zina_get_cat_sorts', 'def'=>'a', 'v'=>array('req')),
				'cat_truncate' => array('type'=>'textfield', 'def'=>25, 'suf'=>'characters', 'v'=>array('int')),
				'cat_images' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
			),
			'files' => array(
				'play' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'download' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'stream_int' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')) ,
				'playlist_format' => array('type'=>'select', 'opts'=>'zina_get_opt_playlist_format', 'def'=>'m3u', 'v'=>array('req'),
					'break'=>1),
				# todo: use xml_field for more crap...?  must test though =(
				'zinamp' => array('type'=>'radio', 'opts'=>'zina_get_zinamp', 'def'=>0, 'v'=>array('req')),
				'zinamp_skin' => array('type'=>'select', 'opts'=>'zina_get_zinamp_skins', 'def'=>'WinampClassic', 'v'=>array('req'),
					'break'=>1),
				'files_sort' => array('type'=>'select', 'opts'=>'zina_get_opt_filesort', 'def'=>0, 'v'=>array('req')),
				'mp3_id3' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'dir_tags' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'mp3_info' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'mp3_info_faster' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'stream_extinf' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1,'v'=>array('tf')),
				'stream_extinf_limit' => array('type'=>'textfield', 'def'=>100, 'v'=>array('if'=>array('stream_extinf'=>'int')),'suf'=>'items'),
				'song_blurbs' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf'), 'break'=>1),
				'song_extras' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'song_extras_opt' => array('type'=>'textarea', 'def'=>"[lyr]\r\nname=Lyrics\r\ntype=page_internal\r\n",
					'rows'=>5, 'v'=>array('if'=>array('song_extras'=>'req')),
					'break'=>1),

				'playlists' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'session_pls' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'pls_ratings' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'pls_length_limit' => array('type'=>'textfield', 'def'=>50, 'suf'=>'items', 'v'=>array('int')),
				'pls_limit' => array('type'=>'textfield', 'def'=>10, 'suf'=>'playlists', 'v'=>array('int')),
				'pls_public' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'pls_included' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'pls_included_limit' => array('type'=>'textfield', 'def'=>5, 'suf'=>'playlists', 'v'=>array('if'=>array('pls_included'=>'int')),
					'break'=>1),

				'various' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'various_file' => array('type'=>'textfield', 'def'=>'zina_various', 'v'=>array('if'=>array('various-'>'req'))),
				'various_above' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf'),
					'break'=>1),

				'low' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'low_suf' => array('type'=>'textfield', 'def'=>'.lofi', 'v'=>array('if'=>array('low'=>'req'))),
				'low_lookahead' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf'),
					'break'=>1),

				'resample' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'enc_arr' => array('type'=>'textarea', 'def'=>
					"[mp3]\r\nencoder = lame\r\nopts = --mp3input -f -b56 --lowpass 12.0 --resample 22.05 -S %FILE% -\r\nmime = audio/mpeg\r\n\r\n".
					"[wav]\r\nencoder = lame\r\nopts = -f -b56 --lowpass 12.0 --resample 22.05 -S %FILE% -\r\nmime = audio/mpeg",
					'v'=>array('if'=>array('resample'=>'req')),
					'break'=>1),

				'remote' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'remote_ext' => array('type'=>'textfield', 'def'=>'rem', 'v'=>array('if'=>array('remote'=>'req')),
					'break'=>1),

				'fake' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'fake_ext' => array('type'=>'textfield', 'def'=>'fake', 'v'=>array('if'=>array('fake'=>'req'))),
			),
			'tags' => array(
				'tags_cddb_server' => array('type'=>'textfield', 'def'=>'freedb.freedb.org', 'v'=>array('req')),
				'tags_format' => array('type'=>'textfield', 'def'=>'UTF-8'),
				'tags_keep_existing_data' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'tags_filemtime' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'tags_cddb_auto_start' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
			),
			'search' => array(
				'search' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'search_images' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'search_min_chars' => array('type'=>'textfield', 'def'=>3, 'v'=>array('if'=>array('search'=>'int'))),
				'search_live_limit' => array('type'=>'textfield', 'def'=>12, 'v'=>array('if'=>array('db_search'=>'int'))),
				'search_default' => array('type'=>'select', 'opts'=>'zina_get_opt_search', 'def'=>'browse', 'v'=>array('req')),
				'search_structure' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'search_pp' => array('type'=>'textfield', 'def'=>20, 'v'=>array('if'=>array('search'=>'int')),
					'break'=>1),
				'db_search' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'search_pp_opts' => array('type'=>'textfield', 'def'=>'20,50,100', 'v'=>array('if'=>array('search'=>'int_split'))),
			),
			'genres' => array(
				'genres' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'genres_custom' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'dir_genre_look' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'genres_split' => array('type'=>'select', 'opts'=>'zina_get_opt_catsplit', 'def'=>0, 'v'=>array('req')),
				'genres_cols' => array('type'=>'textfield', 'def'=>4, 'v'=>array('int')),
				'genres_pp' => array('type'=>'textfield', 'def'=>200, 'v'=>array('if'=>array('genres_split'=>'int'))),
				'genres_truncate' => array('type'=>'textfield', 'def'=>30, 'suf'=>'characters', 'v'=>array('int')),
				'genres_images' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
			),
			'compress' => array(
				#todo: val cmp_sel value
				'cmp_sel' => array('type'=>'select', 'opts'=>'zina_get_opt_zip', 'def'=>0, 'v'=>array('int')),
				'cmp_cache' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'cmp_pgm' => array('type'=>'textfield', 'def'=>'C:\cygwin\bin\zip', 'v'=>array('if'=>array('cmp_sel'=>'req'))),
				'cmp_set' => array('type'=>'textfield', 'def'=>'-0 -j -q %FILE% %FILELIST%', 'v'=>array('if'=>array('cmp_sel'=>'req'))),
				'cmp_extension' => array('type'=>'textfield', 'def'=>'zip', 'v'=>array('if'=>array('cmp_sel'=>'req'))),
				'cmp_mime' => array('type', 'type'=>'textfield', 'def'=>'application/zip', 'v'=>array('if'=>array('cmp_sel'=>'req'))),
			),

			'podcasts' => array(
				#todo: val: if sitemap 1 or 2 tf
				'sitemap' => array('type'=>'radio', 'opts'=>'zina_get_opt_sitemap', 'def'=>1, 'v'=>array('int')),
				'sitemap_cache' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('if'=>array('sitemap'=>'tf'))),
				'sitemap_cache_expire' => array('type'=>'textfield', 'def'=>21, 'suf'=>'days', 'v'=>array('if'=>array('sitemap_cache'=>'int')),
					'break'=>1),
				'rss' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'rss_podcast' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'rss_file' => array('type'=>'textfield', 'def'=>'rss.xml', 'v'=>array('if'=>array('rss'=>'req'))),
				'rss_mm' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
			),
			#todo: could loop dir, sub, genre
			'images' => array(
				'res_in_types' => array('type'=>'checkboxes', 'opts'=>$img_ins, 'def'=>$img_ins_def, 'v'=>array('req')),
				'res_out_type' => array('type'=>'radio', 'opts'=>$img_outs, 'def'=> $img_out_def, 'v'=>array('req')),
				'res_out_x_lmt' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'cache_imgs' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf'),
					'break' =>1),
				'res_dir_img' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>$img_resize, 'v'=>array('tf')),
				'res_dir_qual' => array('type'=>'textfield', 'def'=>75, 'v'=>array('int')),
				'res_dir_x' => array('type'=>'textfield', 'def'=>300, 'v'=>array('if'=>array('res_dir_img'=>'int')),'suf'=>'pixels'),
				'dir_img_txt' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>$freetype, 'v'=>array('tf')),
				'dir_img_txt_color' => array('type'=>'textfield', 'def'=>'255,255,255', 'v'=>array('if'=>array('dir_img_txt'=>'int_split')),'suf'=>'(R,G,B)'),
				'dir_img_txt_wrap' => array('type'=>'textfield', 'def'=>20, 'v'=>array('if'=>array('dir_img_txt'=>'int'))),
				'dir_img_txt_font' => array('type'=>'textfield', 'def'=>$zc['zina_dir_abs'].'/extras/LiberationSans-Bold.ttf',
					'v'=>array('if'=>array('dir_img_txt'=>'file_exists'))
				),
				'dir_img_txt_font_size' => array('type'=>'textfield', 'def'=>12, 'v'=>array('if'=>array('dir_img_txt'=>'int')), 'suf'=>'pt',
					'break'=>1
				),
				'res_sub_img' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>$img_resize, 'v'=>array('tf')),
				'res_sub_qual' => array('type'=>'textfield', 'def'=>75, 'v'=>array('int') ),
				'res_sub_x' => array('type'=>'textfield', 'def'=>200, 'v'=>array('int'),'suf'=>'pixels'),
				'sub_img_txt' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>$freetype, 'v'=>array('tf')),
				'sub_img_txt_color' => array('type'=>'textfield', 'def'=>'0,0,0', 'v'=>array('if'=>array('sub_img_txt'=>'int_split')),'suf'=>'(R,G,B)'),
				'sub_img_txt_wrap' => array('type'=>'textfield', 'def'=>20, 'v'=>array('if'=>array('sub_img_txt'=>'int'))),
				'sub_img_txt_font' => array('type'=>'textfield', 'def'=>$zc['zina_dir_abs'].'/extras/LiberationSans-Bold.ttf',
					'v'=>array('if'=>array('sub_img_txt'=>'file_exists'))
				),
				'sub_img_txt_font_size' => array('type'=>'textfield', 'def'=>12, 'v'=>array('if'=>array('sub_img_txt'=>'int')),'suf'=>'pt',
					'break'=>1
				),
				'res_full_img' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>$img_resize, 'v'=>array('tf')),
				'res_full_qual' => array('type'=>'textfield', 'def'=>75, 'v'=>array('int') ),
				'res_full_x' => array('type'=>'textfield', 'def'=>780, 'v'=>array('int'),'suf'=>'pixels',
					'break'=>1),
				'res_genre_img' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>$img_resize, 'v'=>array('tf')),
				'res_genre_qual' => array('type'=>'textfield', 'def'=>75, 'v'=>array('int') ),
				'res_genre_x' => array('type'=>'textfield', 'def'=>200, 'v'=>array('int'),'suf'=>'pixels'),
				'genre_img_txt' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>$freetype, 'v'=>array('tf')),
				'genre_img_txt_color' => array('type'=>'textfield', 'def'=>'255,255,255', 'v'=>array('if'=>array('genre_img_txt'=>'int_split')),'suf'=>'(R,G,B)'),
				'genre_img_txt_wrap' => array('type'=>'textfield', 'def'=>16, 'v'=>array('if'=>array('genre_img_txt'=>'int'))),
				'genre_img_txt_font' => array('type'=>'textfield', 'def'=>$zc['zina_dir_abs'].'/extras/LiberationSans-Bold.ttf',
					'v'=>array('if'=>array('sub_img_txt'=>'file_exists'))),
				'genre_img_txt_font_size' => array('type'=>'textfield', 'def'=>14, 'v'=>array('if'=>array('genre_img_txt'=>'int')),'suf'=>'pt',
					'break'=>1),
				'res_search_img' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>$img_resize, 'v'=>array('tf')),
				'res_search_qual' => array('type'=>'textfield', 'def'=>100, 'v'=>array('int') ),
				'res_search_x' => array('type'=>'textfield', 'def'=>65, 'v'=>array('int'),'suf'=>'pixels'),
				'search_img_txt' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>$freetype, 'v'=>array('tf')),
				'search_img_txt_color' => array('type'=>'textfield', 'def'=>'0,0,0', 'v'=>array('if'=>array('search_img_txt'=>'int_split')),'suf'=>'(R,G,B)'),
				'search_img_txt_wrap' => array('type'=>'textfield', 'def'=>12, 'v'=>array('if'=>array('search_img_txt'=>'int'))),
				'search_img_txt_font' => array('type'=>'textfield', 'def'=>$zc['zina_dir_abs'].'/extras/LiberationSans-Bold.ttf',
					'v'=>array('if'=>array('sub_img_txt'=>'file_exists'))),
				'search_img_txt_font_size' => array('type'=>'textfield', 'def'=>8, 'v'=>array('if'=>array('search_img_txt'=>'int')),'suf'=>'pt'),
			),
			'pos' => array(
				'pos' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'pos_cmd' => array('type'=>'textfield', 'def'=>'bgrun.exe C:\Progra~1\Winamp\winamp.exe %TEMPFILENAME%.m3u >NUL',
					'v'=>array('if'=>array('pos'=>'req'))),
				'pos_kill' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'pos_kill_cmd' => array('type'=>'textfield', 'def'=>'killall mpg123', 'v'=>array('if'=>array('pos_kill'=>'req'))),
			),
			'other_media' => array(
				'other_media_types' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'media_types_cfg' => array('type'=>'textarea', 'def'=>
					"[mp3]\r\nmime = audio/mpeg\r\nprotocol = http\r\nplaylist_ext = m3u\r\nplaylist_mime = audio/mpegurl\r\n\r\n".
					"[ogg]\r\nmime = audio/ogg\r\nprotocol = http\r\nplaylist_ext = m3u\r\nplaylist_mime = audio/mpegurl\r\n\r\n".
					"[m4a]\r\nmime = audio/m4a\r\nprotocol = http\r\nplaylist_ext = m3u\r\nplaylist_mime = audio/mpegurl\r\n\r\n".
					"[wav]\r\nmime = audio/x-wav\r\nprotocol = http\r\nplaylist_ext = m3u\r\nplaylist_mime = audio/mpegurl\r\n\r\n".
					"[wma]\r\nmime = audio/x-ms-wma\r\nprotocol = http\r\nplaylist_ext = m3u\r\nplaylist_mime = audio/mpegurl",
					'v'=>array('if'=>array('other_media_types'=>'req'))
				),
			),
			'mm' => array(
				'mm' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'mm_down' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'mm_types_cfg' => array('type'=>'textarea', 'def'=>
					"[pdf]\r\nmime = application/pdf\r\ndisposition = inline\r\n\r\n".
					"[avi]\r\nmime = video/msvideo\r\nplayer  = WMP\r\n\r\n[mpg]\r\nmime = video/mpeg\r\nplayer = WMP\r\n\r\n".
					"[mpeg]\r\nmime = video/mpeg\r\nplayer = WMP\r\n\r\n[asf]\r\nmime = video/x-ms-asf\r\nplayer = WMP\r\n\r\n".
					"[wmv]\r\nmime = video/x-ms-wmv\r\nplayer = WMP\r\n\r\n[mov]\r\nmime = video/quicktime\r\nplayer = QT",
					'v'=>array('if'=>array('mm'=>'req'))
				),
			),
			'cron' => array(
				'cron' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'cron_opts' => array('type'=>'textarea', 'rows'=>5, 'def'=>'', 'v'=>array('if'=>array('cron'=>'req'))),
			),
			'cms' => array(
				'cms_editor' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'cms_tags' => array('type'=>'textfield', 'def'=>'<a><strong><em><ul><ol><li><cite><code>',
					'break'=>1),
				'pls_user' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'pls_tags' => array('type'=>'textfield', 'def'=>'<b><i>'),
			),
			'integration' => array(
				'lastfm' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'lastfm_username' => array('type'=>'textfield', 'def'=>'', 'v'=>array('if'=>array('lastfm'=>'req'))),
				'lastfm_password' => array('type'=>'password', 'def'=>'', 'v'=>array('if'=>array('lastfm'=>'req')),
					'break'=>1),
				'twitter' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'twitter_username' => array('type'=>'textfield', 'def'=>'', 'v'=>array('if'=>array('twitter'=>'req'))),
				'twitter_password' => array('type'=>'password', 'def'=>'', 'v'=>array('if'=>array('twitter'=>'req')),
					'break'=>1),
			),
			'third' => array(
				'third_images' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'third_amazon_private' => array('type'=>'textfield', 'def'=>''),
				'third_amazon_public' => array('type'=>'textfield', 'def'=>''),
				'third_amazon_region' => array('type'=>'textfield', 'def'=>'com',
					'break'=>1),

				'third_lyr' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'third_lyr_order' => array('type'=>'textfield', 'def'=>$lyrics),
				'third_lyr_save' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf'),
					'break'=>1),
				'third_addthis' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'third_addthis_id' => array('type'=>'textfield', 'def'=>'YOUR-ACCOUNT-ID'),
				'third_addthis_options' => array('type'=>'textfield', 'def'=>'email, favorites, digg, delicious, facebook, google, live, myspace, twitter, more'),
			),
			'stats' => array(
				'stats' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'stats_public' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'stats_images' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'stats_rss' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'stats_limit' => array('type'=>'textfield', 'def'=>'10', 'v'=>array('if'=>array('stats'=>'int'))),
				'stats_to' => array('type'=>'textfield', 'def'=>'90', 'suf'=>'seconds', 'v'=>array('if'=>array('stats'=>'int'))),
				'stats_org' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf'),
					'break'=>1),

				'rating_dirs' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'rating_dirs_public' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'rating_files' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'rating_files_public' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				'rating_limit' => array('type'=>'textfield', 'def'=>'1,1,1,1,1', 'v'=>array('if'=>array('stats'=>'int_split'))),

				'rating_random' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>1, 'v'=>array('tf')),
				#todo: val -> if float_split
				'rating_random_opts' => array('type'=>'textfield', 'def'=>'5,4.5,4', 'v'=>0,
					'break'=>1),

				'cache_stats' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'cache_stats_expire' => array('type'=>'textfield', 'def'=>3, 'suf'=>'hours', 'v'=>array('if'=>array('cache_stats'=>'int'))),
			),
			'advanced' => array(
				#todo: val->path_exists? try path_relative?
				'zina_dir_rel' => array('type'=>'textfield', 'def'=>$zina_dir_rel),
				'local_path' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'timeout' => array('type'=>'textfield', 'def'=>120, 'v'=>array('int')),
				'charset' => array('type'=>'textfield', 'def'=>'utf-8', 'v'=>array('req')),
				'locale' => array('type'=>'textfield', 'def'=> setlocale(LC_ALL,0)),
				'timezone' => array('type'=>'textfield', 'def'=>@date_default_timezone_get()),
				'debug' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf'),
					'break'=>1),
				'settings_override' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'settings_override_file' => array('type'=>'textfield', 'def'=>'zina_settings.xml', 'v'=>array('if'=>array('settings_override'=>'req')),
					'break'=>1),
				'cache_dir_private_abs' => array('type'=>'textfield', 'def'=>$zc['zina_dir_abs'].'/cache/private', 'v'=>array('if'=>array('cache'=>'dir_writeable'))),
				'cache_dir_public_rel' => array('type'=>'textfield', 'def'=>'cache/public', 'v'=>array('if'=>array('cache'=>'dir_writeable'))),
				'cache_imgs_dir_rel' => array('type'=>'textfield', 'def'=>'cache/public/images', 'v'=>array('if'=>array('cache_imgs'=>'path_relative')),
					'break'=>1),
				'clean_urls_hack' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>$clean_url_hack, 'v'=>array('tf')),
				'clean_urls_index' => array('type'=>'textfield', 'def'=>'music', 'v'=>array('if'=>array('clean_urls_hack'=>'req'))),
			),
		);
	} elseif ($type == 'lang') {
		require_once('lang-cfg.php');
		return array('cats' => $cats, 'titles' => $titles, 'subs' => $subs);
	} elseif ($type == 'directory') {
		return array(
			'directory_opts' => array(
				'person' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'various' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
			),
			'categories' => array(
				'category' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>0, 'v'=>array('tf')),
				'split' => array('type'=>'select', 'opts'=>'zina_get_opt_catsplit', 'def'=>$zc['cat_split'], 'v'=>array('req')),
				'cols' => array('type'=>'textfield', 'def'=>$zc['cat_cols'], 'v'=>array('if'=>array('category'=>'int'))),
				'pp' => array('type'=>'textfield', 'def'=>$zc['cat_pp'], 'v'=>array('if'=>array('split'=>'int'))),
				'sort' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>$zc['cat_sort'], 'v'=>array('tf')),
				'sort_default' => array('type'=>'select', 'opts'=>'zina_get_cat_sorts', 'def'=>'a', 'v'=>array('req')),
				'truncate' => array('type'=>'textfield', 'def'=>$zc['cat_truncate'], 'suf'=>'characters', 'v'=>array('if'=>array('category'=>'int'))),
				'images' => array('type'=>'radio', 'opts'=>'zina_get_opt_tf', 'def'=>$zc['cat_images'], 'v'=>array('tf')),
			),
		);
	}
}
?>
