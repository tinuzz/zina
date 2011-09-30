<?php
function zina_content_help() {
	global $zc;

	$output = '';

	$furl = ini_get('allow_url_fopen');

	$version_level = 'error';
	$version_help = zt('You should be running the latest version of Zina.');

	if (!$furl) {
		$version = zt('Check Manually');
		$version_level = 'warn';
		$version_help = zt('Cannot retrieve latest Zina version because PHP "allow_url_fopen" is set to false.');
	} elseif (($version = file_get_contents('http://pancake.org/zina_version')) === false) {
		$version = 'unknown';
	}

	$tests['zina_version'] = array(
		'test' => zt('Zina version'),
		'reqd' => zt('Latest').' ('.htmlentities($version).')',
		'result' => ZINA_VERSION,
		'passed' => version_compare(ZINA_VERSION, trim($version), '='),
		'level' => $version_level,
		'help' => $version_help,
	);


	$tests['php_version'] = array(
		'test' => zt('PHP version'),
		'reqd' => '>= 5.2.0',
		'result' => PHP_VERSION,
		'passed' => version_compare(PHP_VERSION, '5.2.0', '>='),
		'level' => 'error',
	);

	$tests['cache_dir'] = array(
		'test' => zt('Zina cache directory writeable'),
		'reqd' => 1,
		'result' => 1,
		'passed' => (zina_check_directory($zc['cache_dir_private_abs']) && zina_check_directory($zc['cache_dir_public_abs'])),
		'level' => 'error',
	);

	if ($furl) {
		$zina_dir_rel = zurl($zc['zina_dir_rel'].'/zina.js',null,null,true,true);
		$tests['zina_dir_rel'] = array(
			'test' => zt('Zina Files Directory'),
			'reqd' => zt('Must be correct'),
			'result' => $zc['zina_dir_rel'],
			'passed' => @file_get_contents($zina_dir_rel),
			'level' => 'error',
			'help' => zt('This setting must be correct.').' '.
				zt('It must start with a /.').' '.
				zt('See Zina Settings->Advanced->Zina Files Directory.').'<br/>'.
				zt('Example: The following url must exist: !file', array('!file'=>'<a href="'.$zina_dir_rel.'">'.$zina_dir_rel.'</a>'))
		);
	} else {
		$tests['zina_dir_rel'] = array(
		'test' => zt('Zina Files Directory'),
		'reqd' => zt('Begins with a /'),
		'result' => $zc['zina_dir_rel'],
		'passed' => (substr($zc['zina_dir_rel'], 0, 1) == "/"),
		'level' => 'error',
		'help' => zt('See Zina Settings->Advanced->Zina Files Directory.').' '.
			zt('e.g. http://yoursite.com[/drupal-6.2/sites/all/modules/zina/zina]').' '.
			zt('Settings is in [].'),
		);
	}

	$db_version = "None";
	if ($zc['database']) {
		$db_version = zdbq_single('SELECT VERSION()');
		$tests['db_version'] = array(
			'test' => zt('Mysql version'),
			'reqd' => '>= 4.1',
			'result' => $db_version,
			'passed' => version_compare($db_version, '4.1', '>='),
			'level' => 'error',
		);
		
		$mysql_char_info = '';
		$mysql_coll = zdbq_array("SHOW VARIABLES LIKE 'collation%'");
		$mysql_char = zdbq_array("SHOW VARIABLES LIKE 'character_set%'");

		foreach($mysql_coll as $item) {
			$mysql_char_info .= "   ".$item['Variable_name'] .': '.$item['Value']."\n";
		}
		foreach($mysql_char as $item) {
			$mysql_char_info .= "   ".$item['Variable_name'] .': '.$item['Value']."\n";
		}
	}

	if ($zc['zinamp']) {
		$tests['zinamp'] = array(
			'test' => zt('Flash Player requires XSPF playlist format (Zina setting)'),
			'reqd' => 'xspf',
			'result' => $zc['playlist_format'], 
			'passed' => ($zc['playlist_format'] == 'xspf'),
			'level' => 'error',
		);
	}
	zina_set_js('file', 'extras/jquery.js');
	zina_set_js('jquery', 
		'$("td.zina-test-jquery").html("'.zt('Passed').'");'.
		'$("td.zina-test-jquery").attr("class", "zina-test-status");'.
		'$("td.zina-test-jquery").html("'.zt('Passed').'");'.
		'$("td.zina-result-jquery").html("'.zt('Passed').'");'.
		'$("textarea.zina-test-textarea").val($("textarea.zina-test-textarea").val().replace("jQuery status: Failed","jQuery status: Passed"));'.
		'$("div.zina-help-jquery").css("display", "none");'
		);

	$tests['jquery'] = array(
		'test' => zt('jQuery status'),
		'reqd' => '',
		'result' => 'Failed', 
		'passed' => false,
		'level' => ($zc['zinamp'] || $zc['search']) ? 'error' : 'warn',
		'help' => zt('If this test fails, it is most likely a conflict with mootools.js or a module/component/plugin that uses mootools.').' '.
			zt('Try disabling that module on Zina pages and/or check your browsers JavaScript debugger for an error message.'),
	);

	$memory_limit = ini_get('memory_limit');
	$tests['php_memory_limit'] = array(
		'test' => zt('PHP: memory_limit'),
		'reqd' => zt('>= 128M (recommended)'),
		'result' => $memory_limit,
		'passed' => (intval($memory_limit) >= 128),
		'level' => 'warn',
	);

	$output_buffering = intval(ini_get('output_buffering'));
	$tests['php_output_buffering'] = array(
		'test' => zt('PHP: output_buffering'),
		'reqd' => zt('> 0 (4096 recommended)'),
		'result' => $output_buffering,
		'passed' => ($output_buffering > 0),
		'level' => 'warn',
	);

	$php_items = array( 
		array(
			'item'=>'magic_quotes_gpc',
			'reqd' => 0,
			'level' => 'error',
		),
		array(
			'item'=>'session.auto_start',
			'reqd' => 0,
			'level' => 'warn',
		),
		array(
			'item'=>'allow_url_fopen',
			'reqd' => 1,
			'level' => 'warn',
			'help' => zt('Required for features like lyrics and cddb look-ups.'),
		),
	);

	foreach($php_items as $item) {
		$cfg = $item['item'];
		$version = ini_get($cfg);
		$tests[$cfg] = array(
			'test' => zt('PHP: @cfg', array('@cfg'=>$cfg)),
			'reqd' => $item['reqd'],
			'result' => intval($version),
			'passed' => ($version == $item['reqd']),
			'level' => $item['level'],
		);
		if (isset($item['help'])) $tests[$cfg]['help'] = $item['help'];
	}

	
	zina_set_css('inline', '.zina-test-error{font-weight:bold;background-color:red;font-color:white;font-size:bigger;}');
	zina_set_css('inline', '.zina-test-warn{font-weight:bold;background-color:yellow;font-size:bigger;}');
	zina_set_css('inline', '.zina-test-status{color:green;}');
	
	$output .= '<h3>'.zt('This page will attempt to detect common setup problems.').'</h3>';
	$output .= '<ul><li>'.zt('Any RED error result MUST be fixed.');
	$output .= '<li>'.zt('Any YELLOW warning result SHOULD be fixed and might be necessary depending on your setup.');
	$output .= '</ul>';

	$cfg = '';
	$output .= '<table>';
	$output .= '<thead><tr><th>'.zt('Item').'</th><th>'.zt('Required').'</th><th>'.zt('Your Setup').'</th><th>'.zt('Result').'</th></tr></thead>';
	foreach($tests as $key => $test) {
		$output .= '<tr><td>'.$test['test'];
		if (!$test['passed'] && isset($test['help'])) $output .= '<div class="zina-help-'.$key.'"><small>'.$test['help'].'</small></div>';	
		$output .= '</td><td>'.$test['reqd'].'</td>'.
			'<td class="zina-result-'.$key.'">'.$test['result'].'</td>'.zina_help_passfail($key, $test).'</tr>';
		$cfg .= $test['test'].': '.$test['result']."\n";
	}
	$output .= '</table><br/>';

	zina_set_css('inline', 'div.zina-help-stuff ol, div.zina-help-stuff li {margin-left:1em;padding-left:1em;}');

	$output .= '<div class="zina-help-stuff"><h3>'.zt('If you still have a problem after fixing these settings...').'</h3>';
	$output .= '<ol><li style="list-style-type:upper-roman;">'.'<a href="http://pancake.org/zina/support"><strong>'.zt('Read This First').'</strong></a></li>'.
		'<li style="list-style-type:upper-roman;"><strong>'.zt('Try to find an error message (LOOK IN YOUR WEBSERVERS ERROR LOG or your browsers "Error Console" for Javascript errors) and include it with your post.').'</strong></li>'.
		'<li style="list-style-type:upper-roman;"><strong>'.zt('Copy and paste all the text in the textarea below into your help request/posting.').'</strong></li>'.
		'</ol></div>';


	$zinaini = "Zina.ini does not exist"."\n";
	$output .= '<textarea  rows=20 class="zina-test-textarea" style="width:100%;">';
	if ($zc['embed'] != 'standalone') {
		$info = zina_cms_info();
		$output .= ucfirst($zc['embed'])." ".$info['version']."\n";
		$text = zvar_get('settings', null);
		if (!empty($text)) {
			$zinaini = serialize($text)."\n";
		}
		if (isset($info['modules']) && !empty($info['modules'])) {
			$output .= 'Modules: '.implode(', ',$info['modules'])."\n";
		}
	} else {
		$ini = $zc['zina_dir_abs'].'/zina.ini.php';
		if (file_exists($ini)) {
			$text = file_get_contents($ini);
			$text = preg_replace('/pwd = "(.*?)"/si', 'pwd = "***"', $text);
			$text = preg_replace('/password = "(.*?)"/si', 'pwd = "***"', $text);
			$zinaini = $text ."\n";
		}
	}
	$output .= "\n".$cfg;
	$output .= $_SERVER['SERVER_SOFTWARE']."\n";
	$output .= "UserAgent: ".$_SERVER['HTTP_USER_AGENT']."\n";
	$output .= "Clean URLs: ".intval($zc['clean_urls'])."\n";
	$output .= "PHP locale: ".setlocale(LC_ALL,0)."\n";
	if ($zc['database']) {
		#$output .= "Mysql character info: \n".$mysql_char_info."\n";
	}
	$output .= "\n".$zinaini."\n";
	$output .= '</textarea>';
	return $output;
}

function zina_help_passfail($idx, $test) {
	$output = '<td class="zina-test-'.$idx;
	$output .= ' zina-test-'.(($test['passed']) ? 'status' : $test['level']);
	$output .= '">';
	if ($test['passed']) {
		$output .= zt('Passed');
	} else {
		$output .= (($test['level'] == 'error') ? zt('Failed') : zt('Warning'));
	}
	$output .= '</td>';
	return $output;
}
?>
