<?php
/*
 * Fills up the database with data (for testing)
 *  - dirs: views, votes
 *  - file: plays, downloads, votes
 *
 * Instructions:
 *  - Zina's database should already be setup and configured
 *  - Move this file to root directory
 *  - Set options
 *  - Comment 'exit' below
 *  - load 'er up
 */

exit;

# total number of entries to generate
$total = 1000;

# percentage of users that are anonymous (vs authenticated)
$anon_perc = 85;

# percentage of request that are directories (vs files)
$dirs_perc = 25; 

# number of different random authenticated users
$users = 100;

# period of time (years)
$time_period = 2 * (60*60*24*365); 

/*
 * End of easily configurable stuff... 
 */

$features = array(
	'dirs' => array(
		'view' => '',
		'vote' => '',
	),
	'files' => array(
		'play' => '',
		'down' => '',
		'vote' => '',
	),
);

$conf['time'] = microtime(true);
$conf['embed'] = 'standalone';
$conf['index_abs'] = dirname(__FILE__);
require_once('zina/index.php');
zina_init($conf);

$dirs = zina_core_cache('dirs');
$files = zina_core_cache('files');
$dirs_num = count($dirs) - 1;
$files_num = count($files) - 1;

$zc['debug'] = true;
$zc['stats_to'] = 0;

@set_time_limit(0);

echo "<p>BEGIN<br/>";
for($i = 0; $i < $total; $i++) {
	if (mt_rand(0,100) <= $anon_perc) {
		$user_id = 0;
	} else {
		$user_id = mt_rand(1,$users);
	}
	$x = mt_rand(1,100);
	$type = ($x <= $dirs_perc) ? 'dirs' : 'files';
	$feature = array_rand($features[$type]);

	if ($type == 'dirs') {
		$path = $dirs[mt_rand(0,$dirs_num)];
		$file = null;
	} else {
		$entry = $files[mt_rand(0,$files_num)];
		$path = dirname($entry);
		$file = basename($entry);
	}
	$rating = ($feature == 'vote') ? mt_rand(1,5) : null;
	$timestamp = time() - mt_rand(0,$time_period);
	echo ". ";
	if ($i > 0 && $i % 1000 == 0) echo $i;
	flush();

	#zdbg("$feature, $path, $file, $rating, false, array('user_id'=>$user_id, 'timestamp' => $timestamp)");
	zdb_log_stat($feature, $path, $file, $rating, false, array('user_id'=>$user_id, 'timestamp' => $timestamp));

}
echo "<p><b>".$i." Records Generated in: ".round((microtime(true) - $conf['time'])/60,1)." minutes</b>";
echo ztheme('messages');
?>
