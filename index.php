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

# BY DEFAULT: username is "admin" & password is "password"
# After you login in to Zina, change your password under "Settings"
# All settings are described and set through the GUI.

#$mem1 = memory_get_usage();
$conf['time'] = microtime(true);

#session_start();
#header('P3P: CP="NOI ADM DEV PSAi COM NAV OUR OTRo STP IND DEM"');
$conf['embed'] = 'standalone';
$conf['index_abs'] = dirname(__FILE__);

require_once('zina/index.php');
if ($zina = zina($conf)) {
	echo ztheme('page_complete', $zina);
}

#TODO:
#$mem2 = memory_get_peak_usage();
#printf("<pre>Max memory: %0.2f kbytes\nRunning time: %0.3f s</pre>",($mem2-$mem1)/1024.0, microtime(true) - $conf['time']);


function zina_access_denied() {
	header('HTTP/1.1 403 Forbidden');
	return zina_page_simple(zt('Access denied.'), zt('You are not authorized to access this page.'));
}

function zina_not_found() {
	header('HTTP/1.1 404 Not Found');
	return zina_page_simple(zt('Page not found.'), zt('The requested page could not be found.'));
}

function zina_token($type, $value) {
	global $zc;
	static $sitekey;

	if (empty($sitekey)) {
		$keyfile = $zc['cache_dir_private_abs'].'/sitekey.txt';
		if (file_exists($keyfile)) {
			$sitekey = file_get_contents($keyfile);
		} else {
			zina_debug(zt('Sitekey file does not exist'),'warn');
			return false;
		}
	}
	$sep = '|';
	if ($type == 'get') {
		return $value.$sep.md5($value.$sitekey);
	} elseif ($type == 'verify') {
		$x = explode($sep, $value);
		if (md5($x[0].$sitekey) === $x[1]) {
			return $x[0];
		} else {
			return false;
		}
	}

	return false;
}

function zina_cms_access($type, $type_user_id = null) {
	global $zc;
	
	return $zc['is_admin'];
}

function zina_cms_user($user_id = false) {
	global $zc;
	return false;
/*
	$uid = ($user_id) ? $user_id : $zc['user_id'];

	if ($user_id != 1) return false;

	return array(
      'uid' => $zc['user_id'],
      'name' => zt('Administrator'),
      'profile_url' => false,
	);
 */
}

/*
 * Include various statistic blocks in parts of your website 
 *
 * type = a=artist, t=album, f=song
 * page = zina_get_stats_pages();
 *    stats, rating, votes, views, plays, downloads, latest
 * period = zina_get_stats_periods(); 
 *    all, year, month, week, day
 * number = number of items
 *
 * $results = zina_get_block_stat($type, $page, $period, $number);
 *
 * Example:
 * $results = zina_get_block_stat('t', 'latest', 'all', 10);
 * echo "<h3>Latest Played Albums</h3>";
 * echo ztheme('zina_block', $results, 't');
 */
?>
