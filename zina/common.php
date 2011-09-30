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
 * common.php
 *  - common functions (many modified/inspired from Drupal)
 *
 *  TODO:
 *   - normalize function names
 *   - organize file
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

function zt($string, $args = array()) {
	global $zina_lang_code, $zina_lang_path;
	static $lang;

	if (!isset($lang[$zina_lang_code]) && $zina_lang_path) {
		$lang[$zina_lang_code] = array();
		include($zina_lang_path);
	}
	if (isset($lang[$zina_lang_code][$string])) $string = $lang[$zina_lang_code][$string];

	if (empty($args) || !is_array($args)) return $string;

	// Transform arguments before inserting them.
	foreach ($args as $key => $value) {
		switch ($key[0]) {
			case '@':
				// Escaped only.
				$args[$key] = zcheck_plain($value);
			break;
			case '%':
			default:
				// Escaped and placeholder.
				$args[$key] = ztheme('placeholder', $value);
			break;
			case '!':
				// Pass-through.
		}
	}
	return strtr($string, $args);
}

function zcheck_plain($text) {
	if (zvalidate_utf8($text)) {
		return htmlspecialchars($text, ENT_QUOTES);
	} else {
		global $zc;
		return ((strtolower($zc['charset']) == 'utf-8') ? utf8_encode($text) : '');
	}
}

function zcheck_utf8($text, $xml=true) {
	global $zc;
	# Best so far???
	return (!(preg_match('/^./us', $text) == 1) && strtolower($zc['charset']) == 'utf-8') ? utf8_encode($text) : (($xml) ? zxml_encode($text) : $text);
	#return (!zvalidate_utf8($text) && strtolower($zc['charset']) == 'utf-8') ? utf8_encode($text) : (($xml) ? zxml_encode($text) : $text);
}

function zvalidate_utf8($text) {
	if (strlen($text) == 0) return true;
	return (preg_match('/^./us', $text) == 1);
}

function zina_set_message($message = NULL, $type = 'status', $repeat = TRUE) {
	if ($message) {
		if (!isset($_SESSION['messages'])) $_SESSION['messages'] = array();

		if (!isset($_SESSION['messages'][$type])) $_SESSION['messages'][$type] = array();

		if ($repeat || !in_array($message, $_SESSION['messages'][$type])) {
			$_SESSION['messages'][$type][] = $message;
		}
	}

	// messages not set when DB connection fails
	return isset($_SESSION['messages']) ? $_SESSION['messages'] : NULL;
}

function zina_get_messages($type = NULL, $clear_queue = TRUE) {
	if ($messages = zina_set_message()) {
		if ($type) {
			if ($clear_queue) unset($_SESSION['messages'][$type]);
			if (isset($messages[$type])) {
				return array($type => $messages[$type]); }
		} else {
			if ($clear_queue) unset($_SESSION['messages']);
			return $messages;
		}
	}
	return array();
}

function ztheme() {
	global $zc;
	$args = func_get_args();
	$function = array_shift($args);

	if (function_exists($zc['theme'].'_'.$function)) {
		return call_user_func_array($zc['theme'].'_'.$function, $args);
	} elseif (function_exists('ztheme_'. $function)){
		return call_user_func_array('ztheme_'.$function, $args);
	}
}

#todo: change to drupal 6? arr of $opts??
function zurl($path = NULL, $query = NULL, $fragment = NULL, $absolute = FALSE, $direct = FALSE) {
	global $zc;
	if (isset($fragment)) $fragment = '#'. $fragment;

	// Return an external link
	if (strpos($path, ':') !== FALSE) {
		// Split off the fragment
		if (strpos($path, '#') !== FALSE) {
			list($path, $old_fragment) = explode('#', $path, 2);
			if (isset($old_fragment) && !isset($fragment)) $fragment = '#'. $old_fragment;
		}
		if (isset($query)) $path .= (strpos($path, '?') !== FALSE ? '&' : '?') . $query;
		return $path.$fragment;
	}

	#todo: base_path not set?
	static $script, $base_url, $base_path, $base_url2, $base_path2, $www_path;

	if (!isset($script)) {
		$script = (strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') === FALSE && !$zc['clean_urls']) ? 'index.php' : '';
	}

	if (!isset($base_url)) {
	 	$base_root = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
		$base_url = $base_root .= '://'.$zc['auth']. preg_replace('/[^a-z0-9-:._]/i', '', $_SERVER['HTTP_HOST']);
	}

	if ($direct) {
		$base = ($absolute) ? $base_url : '';
		if (!isset($www_path)) {
			$tmp = preg_replace("#^${_SERVER['DOCUMENT_ROOT']}#i", '', $zc['mp3_dir'], -1, $count);
			$www_path = ($count > 0) ? $tmp : '';
		}
		return $base.$www_path.'/'.zrawurlencode($path);
	} else {
		if (!isset($base_url2)) {
			if ($dir = trim(dirname($_SERVER['SCRIPT_NAME']), '\,/')) {
				$base_path2 = "/$dir";
				$base_url2 = $base_url.$base_path2;
				$base_path2 .= '/';
			} else {
				$base_path2 = '/';
				$base_url2 = $base_url;
	 		}
			if (isset($zc['index_rel'])) {
				$base_url2 .= '/'.$zc['index_rel'];
				$base_path2 .= $zc['index_rel'] .'/';
			}
		}
		$base = ($absolute) ? $base_url2 . '/' : $base_path2;
	}

	if (isset($zc['url_query'])) {
		$url_query = implode('&',$zc['url_query']);
		$query .= (empty($query)) ? $url_query : '&'.$url_query;
	}

	if (!empty($path)) {
		if ($zc['clean_urls']) {
			$path = str_replace(array('%2F', '%26', '%23', '//', '%2B'), array('/', '%2526', '%2523', '/%252F', '%252B'), rawurlencode($path));
			return $base.$path.((isset($query)) ? '?'.$query:'').$fragment;
		} else {
			$path = str_replace('%2F', '/', rawurlencode($path));
			return $base.$script.'?p='.$path.(isset($query)?'&'.$query:'').$fragment;
		}
	} else {
		return $base.(isset($query)?$script.'?'.$query:'').$fragment;
	}
}

function zrawurlencode($path) {
	return str_replace(array('%2F', '%26', '%23'), array('/', '%2526', '%2523'), rawurlencode($path));
}

function zrawurldecode($x) {
	return str_replace(array('%2F', '%26', '%23'), array('/', '&', '#'), rawurldecode($x));
}

function zpath_to_theme($path_rel = null) {
	static $theme_dir;
	if (!empty($path_rel)) $theme_dir = $path_rel;
	return $theme_dir;
}

/*
#TODO: redo ala D6
function zl($text, $path, $options = array()) {
  $options += array(
      'attributes' => array(),
      'html' => FALSE,
	);

	if (isset($options['attributes']['title']) && strpos($options['attributes']['title'], '<') !== FALSE) {
		$options['attributes']['title'] = strip_tags($options['attributes']['title']);
	}

	return '<a href="' . check_plain(url($path, $options)) . '"' . drupal_attributes($options['attributes']) . '>' . ($options['html'] ? $text : check_plain($text)) . '</a>';
}
 */
function zl($text, $path, $query = NULL, $fragment = NULL, $absolute = FALSE, $attributes = '') {
	if (!empty($attributes)) $attributes = ' '.$attributes;
	return '<a href="'.zurl($path, $query, $fragment, $absolute).'"'.$attributes.'>'.$text.'</a>';
}

function zina_get_headers() {
	foreach(zina_set_header() as $header) {
		header($header);
	}
}

function zina_set_header($header = NULL) {
	static $stored_headers = array();

	if (strlen($header)) {
		$stored_headers[] = $header;
	}
	return $stored_headers;
}

function zina_set_html_head($header = NULL) {
	static $headers = array();

	if (strlen($header)) {
		$headers[$header] = true;
	}
	return $headers;
}

function zina_get_html_head() {
	return implode("\n", array_keys(zina_set_html_head()));
}

# type = inline or file
function zina_set_css($type = null, $css = null) {
	global $zc;
	static $files = array(), $inline = '';
	if (empty($files)) {
		$files['themes/'.$zc['theme'].'/common.css'] = true;
	}

	if (empty($type)) {
		$output = '';
		$files = array_reverse($files);
		foreach ($files as $file => $nothing) {
			$output .= '<link rel="stylesheet" href="'.$zc['zina_dir_rel'].'/'.$file.'" type="text/css" />'."\n";
		}
		if (!empty($inline)) $output .= '<style type="text/css">'.$inline.'</style>'."\n";

		return $output;
	} else {
		if ($type == 'file')
			$files[$css] = true;
		elseif ($type == 'inline')
			$inline .= $css;
	}
}

function zina_get_js() {
	global $zc;
	$js = zina_set_js();

	$output = '';
	foreach($js['file'] as $file=> $relative) {
		$path = ($relative) ? $zc['zina_dir_rel'].'/' : '';
		$output .= '<script type="text/javascript" src="'.$path.$file.'"></script>'."\n";
	}
	#for validation
	$prefix = "\n<!--//--><![CDATA[//><!--\n";
	$suffix = "\n//--><!]]>\n";

	if (!empty($js['inline']) || !empty($js['jquery']) || !empty($js['vars'])) {
		$output .= '<script type="text/javascript">'.$prefix;
		if (!empty($js['vars'])) $output .= implode('', $js['vars']);

		if (!empty($js['jquery'])) {
			$noconflict = (!in_array($zc['embed'], array('standalone', 'drupal'))) ? 'jQuery.noConflict();' : '';
			$output .= $noconflict.'jQuery(document).ready(function($){'.implode('', $js['jquery']).'});';
		}
		if (!empty($js['inline'])) $output .= implode('', $js['inline']);

		$output .= $suffix.'</script>';
	}

	return $output;
}

# type = inline or file
function zina_set_js($type = null, $js = null, $relative = true) {
	static $javascript = array();

	if (empty($files)) $javascript['file']['zina.js'] = true;

	if ($type == 'file')
		$javascript['file'][$js] = $relative;
	elseif ($type == 'vars')
		$javascript['vars'][] = $js;
	elseif ($type == 'jquery')
		$javascript['jquery'][] = $js;
	else
		$javascript['inline'][] = $js;

	return $javascript;
}

function zo_shuffle(&$array){
	$last = count($array);
	while ($last > 0){
		$last--;
		$random = mt_rand(0, $last);
		$temp = $array[$random];
		$array[$random] = $array[$last];
		$array[$last] = $temp;
	}
	return 1;
}

/*
 * Gets serialized content or converts from older format
 */
function zunserialize_alt($content) {
	if (($array = @unserialize($content)) === false) {
		$array = explode("\n", rtrim($content,"\r\n"));
		foreach($array as $key=>$val) {
			$array[$key] = rtrim($val,"\r\n");
		}
	}
	return $array;
}

function unserialize_utf8($text) {
	if (empty($text)) return false;

	$output = @unserialize(utf8_decode($text));
	if ($output === false) {
		$output = unserialize(preg_replace('!s:(\d+):"(.*?)";!se', "'s:'.strlen('$2').':\"$2\";'", $text));
	}
	return $output;
}

function zina_goto($path = '', $query = null, $fragment = null, $absolute = false, $direct = false) {
	$url = zurl($path, $query, $fragment, $absolute, $direct);
	$url = str_replace(array("\n", "\r"), '', $url);
	@session_write_close();
	while(@ob_end_clean());
	header('Location: '. $url, TRUE, 302);
	exit();
}

function zina_debug($error, $level='warn') {
	global $zc;
	if ($zc['debug']) {
		zina_set_message($error, $level);
		error_log('Zina '.$level.': '.$error);
	}
}

function zina_check_directory($directory, $create = 0) {
	global $zc;
	$directory = rtrim($directory, '/\\');

	if (!is_dir($directory)) {
		if ($create) {
		  	if (@mkdir($directory)) {
				zina_set_message(zt('The directory %directory has been created.', array('%directory' => $directory)));
				@chmod($directory, 0775); // Necessary for non-webserver users.
			} else {
				zina_set_message(zt('Cannot create directory %directory.', array('%directory' => $directory)),'error');
				return FALSE;
			}
		} else {
			zina_set_message(zt('Directory does not exist %directory.', array('%directory' => $directory)),'error');
			return FALSE;
		}
	}

	// Check to see if the directory is writable.
	if (!is_writable($directory)) {
		if ($mode && @chmod($directory, 0775)) {
			zina_set_message(zt('The permissions of directory %directory have been changed to make it writable.', array('%directory' => $directory)));
		} else {
			zina_set_message(zt('The directory %directory is not writeable.', array('%directory' => $directory)),'error');
			return FALSE;
		}
	}

	if ($zc['cache_dir_private_abs'] == $directory && !is_file("$directory/.htaccess")) {
		$htaccess_lines = "Options None\nOrder Deny,Allow\nDeny from all";
		if (($fp = fopen("$directory/.htaccess", 'w')) && fputs($fp, $htaccess_lines)) {
			fclose($fp);
			chmod($directory .'/.htaccess', 0664);
		} else {
			$variables = array('%directory' => $directory, '!htaccess' => '<br />'. nl2br(zcheck_plain($htaccess_lines)));
			zina_set_message(zt("Security warning: Couldn't write .htaccess file. Please create a .htaccess file in your %directory directory which contains the following lines: <code>!htaccess</code>", $variables),'warn');
		}
	}

	return true;
}

function zina_strlen($text) {
	global $zc;
	return ($zc['multibyte']) ? mb_strlen($text) : strlen(preg_replace("/[\x80-\xBF]/", '', $text));
}

function zina_substr($text, $start, $length = NULL) {
	global $zc;
	if ($zc['multibyte']) {
		return $length === NULL ? mb_substr($text, $start) : mb_substr($text, $start, $length);
	} else {
		# NOT a work-around
		return $length === NULL ? substr($text, $start) : substr($text, $start, $length);
	}
}

function zina_strtoupper($text) {
	global $zc;
	# NO NON-MB work-around
	return ($zc['multibyte']) ? mb_strtoupper($text) : strtoupper($text);
}

function zina_date($format, $timestamp) {

  $max = strlen($format);
  $date = '';
  for ($i = 0; $i < $max; $i++) {
    $c = $format[$i];
    if (strpos('AaDlM', $c) !== FALSE) {
      $date .= zt(gmdate($c, $timestamp));
    } else if ($c == 'F') {
      // Special treatment for long month names: May is both an abbreviation
      // and a full month name in English, but other languages have different abbreviations.
      $date .= trim(zt('!long-month-name '. gmdate($c, $timestamp), array('!long-month-name' => '')));
    } else if (strpos('BdgGhHiIjLmnsStTUwWYyz', $c) !== FALSE) {
      $date .= gmdate($c, $timestamp);
    } else if ($c == 'r') {
      $date .= format_date($timestamp - $timezone, 'custom', 'D, d M Y H:i:s O', $timezone, $langcode);
    } else if ($c == 'O') {
      $date .= sprintf('%s%02d%02d', ($timezone < 0 ? '-' : '+'), abs($timezone / 3600), abs($timezone % 3600) / 60);
    } else if ($c == 'Z') {
      $date .= $timezone;
    } else if ($c == '\\') {
      $date .= $format[++$i];
    } else {
      $date .= $c;
    }
  }

  return $date;
}

function zina_delete_file($file) {
	global $zc;
	if (!file_exists($file) || !zfile_check_location($file, $zc['mp3_dir'])) {
		zina_set_message(zt('File does not exist: @file', array('@file'=>$file)));
		return false;
	}
	$full_path = substr($file, strlen($zc['mp3_dir'])+1);
	$path = dirname($full_path);
	$file_name = basename($full_path);

	if (unlink($file)) {
		if ($zc['database']) {
			$id = zdbq_single("SELECT id FROM {files} WHERE path = '%s' AND file = '%s'", array($path, $file_name));
			if (!empty($id)) zdb_remove('file', $id);
		}

		$exts = $zc['song_es_exts'];
		$exts[] = $zc['remote_ext'];
		$exts[] = $zc['fake_ext'];
		$exts[] = 'txt';

		foreach($exts as $ext) {
			$aux_files[] = preg_replace('/'.$zc['ext_mus'].'$/i', $ext, $file_name);
		}
		foreach($aux_files as $aux) {
			$aux_file = $path.'/'.$aux;
			if (file_exists($aux_file)) unlink($aux_file);
		}

		return $path;
	} else {
		zina_set_message(zt('Could not delete file: @file', array('@file'=>$file)));
		return false;
	}
}

function zina_delete_directory($dir) {
	if (!file_exists($dir)) return true;
	if (!is_dir($dir) || is_link($dir)) return unlink($dir);

	foreach (scandir($dir) as $path) {
		if ($path == '.' || $path == '..') continue;
		$item = $dir.'/'.$path;
		if (!zina_delete_directory($item)) {
			chmod($item, 0777);
			if (!zina_delete_directory($item)) return false;
		}
	}
	return rmdir($dir);
}

function zina_token_sess($value) {
	static $key;
	if (empty($key)) {
		global $zc;
		$keyfile = $zc['cache_dir_private_abs'].'/sitekey.txt';
		if (file_exists($keyfile)) {
			$key = file_get_contents($keyfile);
		} else {
			zina_debug(zt('Sitekey file does not exist'),'warn');
			return false;
		}
	}
	return md5(session_id() . $value . $key);
}

function zina_token_sess_check() {
	global $zc;
	if (isset($_POST['token']) && !empty($_POST['token']) && $_POST['token'] == zina_token_sess($zc['user_id'])) {
		return true;
	}
	return false;
}

function zsort_date($a, $b) {
	if ($a['mtime'] == $b['mtime']) return 0;
	return ($a['mtime'] < $b['mtime']) ? -1 : 1;
}

function zsort_date_desc($a, $b) {
	if ($a['mtime'] == $b['mtime']) return 0;
	return ($a['mtime'] > $b['mtime']) ? -1 : 1;
}

function zsort_trackno($a, $b) {
	if ($a['info']->track == $b['info']->track) return 0;
	return ((int)$a['info']->track < (int)$b['info']->track) ? -1 : 1;
}

function zsort_ignore($a, $b) {
	global $zc;
	#return strnatcasecmp(preg_replace('/(^|\/)('.$zc['dir_si_str'].') /i','$1',$a), preg_replace('/(^|\/)('.$zc['dir_si_str'].') /i','$1',$b));

	if (strpos($a, '/') || strpos($b, '/')) {
		return strnatcasecmp(preg_replace('/^('.$zc['dir_si_str'].') /i','',basename($a)), preg_replace('/^('.$zc['dir_si_str'].') /i','',basename($b)));
	} else {
		if (strpos($a, ' ') || strpos($b, ' ')) {
			return strnatcasecmp(preg_replace('/^('.$zc['dir_si_str'].') /i','',$a), preg_replace('/^('.$zc['dir_si_str'].') /i','',$b));
		} else {
			return strnatcasecmp($a, $b);
		}
	}
}

function zsort_title_ignore($a, $b) {
	if ($a['person'] || $b['person']) {
		$a1 = $a['title'];
		$b1 = $b['title'];
		if ($a['person'] && ($pos = strripos($a['title'], ' ')) !== false) {
			$a1 = substr($a['title'], $pos+1).','.substr($a['title'], 0, $pos);
		}
		if ($b['person'] && ($pos = strripos($b['title'], ' ')) !== false) {
			$b1 = substr($b['title'], $pos+1).','.substr($b['title'], 0, $pos);
		}
		
		return zsort_ignore($a1, $b1);
	} else {
		return zsort_ignore($a['title'], $b['title']);
	}
}

function zsort_title($a, $b) {
	if ($a['title'] == $b['title']) return 0;
	return ($a['title'] < $b['title']) ? -1 : 1;
}

function zina_validate($type, $value, $opts = null) {
	if ($type == 'req') {
		if (is_array($value)) {
			return (!empty($value));
		} else {
			return strlen($value) > 0;
		}
	} elseif ($type == 'tf') {
		return ($value == 0 || $value == 1);
	} elseif ($type == 'int') {
		return (is_numeric($value) ? intval($value) == $value : false);
	} elseif ($type == 'int_split') {
		$ints = explode(',', $value);
		foreach($ints as $int) {
			if (!zina_validate('int', $int)) return false;
		}
		return true;
	} elseif ($type == 'file_exists') {
		return (file_exists($value));
	} elseif ($type == 'alpha_numeric') {
		return (!preg_match('/\W/', $value));
	} elseif ($type == 'path_relative') {
		global $zc;
		return zina_check_directory($zc['zina_dir_abs'].'/'.$value,1);
	} elseif ($type == 'dir_exists') {
		return (file_exists($value) && is_dir($value));
	} elseif ($type == 'dir_writeable') {
		return zina_check_directory($value,1);
	} elseif ($type == 'if') {
		$key = key($opts);
		if (isset($_POST[$key]) && $_POST[$key] == 1) {
			return zina_validate(current($opts), $value);
		}
		return true;
	}
	return false;
}

/*
 * Thanks to: http://tim-ryan.com/labs/parseINI/
 */
function zina_parse_ini_string($ini_string) {
	$data = array();
	$currentSection = '';
	foreach (preg_split('/\r\n?|\r?\n/', $ini_string) as $line) {
		if (preg_match('/^\s*\[\s*(.*)\s*\]\s*$/', $line, $matches)) {
			$currentSection = $matches[1];
		} else if (preg_match('/^\s*([^;\s].*?)\s*=\s*([^\s].*?)$/', $line, $matches)) {
			$key = preg_replace('/\[\]$/', '', $matches[1]);
			$isArray = preg_match('/\[\]$/', $matches[1]);

			preg_match('/^"(?:\\.|[^"])*"|^\'(?:[^\']|\\.)*\'|^[^;]+?\s*(?=;|$)/', $matches[2], $matches);
			$value = preg_replace('/^(["\'])(.*?)\1?$/', '\2', stripslashes($matches[0]));
			if (is_numeric($value))
				$value = (float) $value;
			else if (strtolower($value) == 'true')
				$value = true;
			else if (strtolower($value) == 'false')
				$value = false;

			$section =& $data[$currentSection];
			foreach (explode('.', $key) as $level) {
				if (isset($section[$level]) && !is_array($section[$level])) {
					$section[$level] = array();
				}

				$section =& $section[$level];
			}
			$isArray ? $section[] = $value : $section = $value;
		}
	}
	return $data;
}

function xml_to_array($data, $type = 'file') {
	$parser = xml_parser_create();
	xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
	xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
	xml_parse_into_struct($parser, $data, $values, $tags);
	xml_parser_free($parser);

	$items = array();

	foreach ($tags as $key=>$val) {
		if ($key == $type) {
			$pairs = $val;
			$num = count($pairs);
			if ($num > 1) {
				$item = null;

				for ($i=0; $i < $num; $i+=2) {
					$offset = $pairs[$i] + 1;
					$len = $pairs[$i + 1] - $offset;
					$vs =  array_slice($values, $offset, $len);
					for ($j=0; $j < count($vs); $j++) {
						if (isset($vs[$j]["value"])) {
							$item[$vs[$j]["tag"]] = trim($vs[$j]["value"]);
						}
					}
					$items[] = $item;
				}
			}
		} else {
			continue;
		}
	}
	return $items;
}

function zfile_check_location($source, $directory = '') {
  $check = realpath($source);
  if ($check) {
    $source = $check;
  } else {
    // This file does not yet exist
    $source = realpath(dirname($source)) .'/'. basename($source);
  }
  $directory = realpath($directory);
  if ($directory && strpos($source, $directory) !== 0) {
    return 0;
  }
  return $source;
}

function zina_add_tabledrag($table_id, $action, $relationship, $group, $subgroup = NULL, $source = NULL, $hidden = TRUE, $limit = 0) {
	if (function_exists('drupal_add_tabledrag')) {
		return drupal_add_tabledrag($table_id, $action, $relationship, $group, $subgroup, $source, $hidden, $limit);
	}

	static $js_added = false;
	if (!$js_added) {
		global $zc;
		zina_set_js('file', 'extras/tabledrag.js');
		$js_added = TRUE;
		if (isset($zc['index_rel'])) {
			$data[] = array('basePath' => '/'.$zc['index_rel'].'/');
		}

		$trans['Drag to re-order'] = zt('Drag to re-order');
		$trans['Changes made in this table will not be saved until the form is submitted.'] = zt('Changes made in this table will not be saved until the form is submitted.');
		$test = "Drupal.locale={'strings':". zina_to_js($trans) ."};";
		zina_set_js('vars', $test);
	}

  // If a subgroup or source isn't set, assume it is the same as the group.
  $target = isset($subgroup) ? $subgroup : $group;
  $source = isset($source) ? $source : $target;
  $settings['tableDrag'][$table_id][$group][] = array(
    'target' => $target,
    'source' => $source,
    'relationship' => $relationship,
    'action' => $action,
    'hidden' => $hidden,
    'limit' => $limit,
  );
  $data[] = $settings;
  zina_set_js('vars', 'jQuery.extend(Drupal.settings, ' . zina_to_js(call_user_func_array('array_merge_recursive', $data)) . ");");
}

function zina_to_js($var) {
	if (function_exists('drupal_to_js')) return drupal_to_js($var);

  switch (gettype($var)) {
    case 'boolean':
      return $var ? 'true' : 'false'; // Lowercase necessary!
    case 'integer':
    case 'double':
      return $var;
    case 'resource':
    case 'string':
      return '"'. str_replace(array("\r", "\n", "<", ">", "&"),
                              array('\r', '\n', '\x3c', '\x3e', '\x26'),
                              addslashes($var)) .'"';
    case 'array':
      // Arrays in JSON can't be associative. If the array is empty or if it
      // has sequential whole number keys starting with 0, it's not associative
      // so we can go ahead and convert it as an array.
      if (empty ($var) || array_keys($var) === range(0, sizeof($var) - 1)) {
        $output = array();
        foreach ($var as $v) {
          $output[] = zina_to_js($v);
        }
        return '[ '. implode(', ', $output) .' ]';
      }
      // Otherwise, fall through to convert the array as an object.
    case 'object':
      $output = array();
      foreach ($var as $k => $v) {
        $output[] = zina_to_js(strval($k)) .': '. zina_to_js($v);
      }
      return '{ '. implode(', ', $output) .' }';
    default:
      return 'null';
  }
}

function xml_field($name, $value, $decode = true) {
	return '<'.$name.'>'.zxml_encode($value).'</'.$name.'>'."\n";
}

function zxml_encode($x) {
	return htmlspecialchars(zdecode_entities($x));
}

function zdecode_entities($text, $exclude = array()) {
  static $table;
  if (!isset($table)) {
    $table = array_flip(get_html_translation_table(HTML_ENTITIES));
    $table = array_map('utf8_encode', $table);
    $table['&apos;'] = "'";
  }
  $newtable = (!empty($exclude)) ? array_diff($table, $exclude) : $table;
  return preg_replace('/&(#x?)?([A-Za-z0-9]+);/e', '_zdecode_entities("$1", "$2", "$0", $newtable, $exclude)', $text);
}

/**
 * Helper function for decode_entities
 */
function _zdecode_entities($prefix, $codepoint, $original, &$table, &$exclude) {
  if (!$prefix) {
    if (isset($table[$original])) {
      return $table[$original];
    }
    else {
      return $original;
    }
  }
  if ($prefix == '#x') {
	  $codepoint = base_convert($codepoint, 16, 10);
  } else {
    $codepoint = preg_replace('/^0+/', '', $codepoint);
  }
  if ($codepoint < 0x80) {
    $str = chr($codepoint);
  } else if ($codepoint < 0x800) {
    $str = chr(0xC0 | ($codepoint >> 6))
         . chr(0x80 | ($codepoint & 0x3F));
  } else if ($codepoint < 0x10000) {
    $str = chr(0xE0 | ( $codepoint >> 12))
         . chr(0x80 | (($codepoint >> 6) & 0x3F))
         . chr(0x80 | ( $codepoint       & 0x3F));
  } else if ($codepoint < 0x200000) {
    $str = chr(0xF0 | ( $codepoint >> 18))
         . chr(0x80 | (($codepoint >> 12) & 0x3F))
         . chr(0x80 | (($codepoint >> 6)  & 0x3F))
         . chr(0x80 | ( $codepoint        & 0x3F));
  }
  // Check for excluded characters
  if (in_array($str, $exclude)) {
    return $original;
  } else {
    return $str;
  }
}

function zina_sitemap_frequency($interval) {
  $frequencies = array(
    'always' => 3600,
    'hourly' => 86400,
    'daily' => 604800,
    'weekly' => 2419200,
    'monthly' => 29030400,
    'yearly' => 100000000,
    'never' => 0,
  );

  foreach ($frequencies as $frequency => $value)
    if ($interval <= $value || $frequency == 'never') break;

  return $frequency;
}

function zina_filter_html($text, $tags) {
	global $zc;
	$allowed_tags = preg_split('/\s+|<|>/', $tags, -1, PREG_SPLIT_NO_EMPTY);
	$text = zina_filter_xss($text, $allowed_tags);
	return trim($text);
}

function zina_filter_xss($string, $allowed_tags) {
 if (!zvalidate_utf8($string)) return '';
 
  // Store the input format
  zina_filter_xss_split($allowed_tags, TRUE);
  // Remove NUL characters (ignored by some browsers)
  $string = str_replace(chr(0), '', $string);
  // Remove Netscape 4 JS entities
  $string = preg_replace('%&\s*\{[^}]*(\}\s*;?|$)%', '', $string);

  // Defuse all HTML entities
  $string = str_replace('&', '&amp;', $string);
  // Change back only well-formed entities in our whitelist
  // Decimal numeric entities
  $string = preg_replace('/&amp;#([0-9]+;)/', '&#\1', $string);
  // Hexadecimal numeric entities
  $string = preg_replace('/&amp;#[Xx]0*((?:[0-9A-Fa-f]{2})+;)/', '&#x\1', $string);
  // Named entities
  $string = preg_replace('/&amp;([A-Za-z][A-Za-z0-9]*;)/', '&\1', $string);

  return preg_replace_callback('%(<(?=[^a-zA-Z!/])|<[^>]*(>|$)|>)%x', 'zina_filter_xss_split', $string);
}

function zina_filter_xss_split($m, $store = false) {
  static $allowed_html;

  if ($store) {
    $allowed_html = array_flip($m);
    return;
  }

  $string = $m[1];

  if (substr($string, 0, 1) != '<') {
    return '&gt;';
  } else if (strlen($string) == 1) {
    return '&lt;';
  }

  if (!preg_match('%^<\s*(/\s*)?([a-zA-Z0-9]+)([^>]*)>?$%', $string, $matches)) {
    // Seriously malformed
    return '';
  }

  $slash = trim($matches[1]);
  $elem = &$matches[2];
  $attrlist = &$matches[3];

  if (!isset($allowed_html[strtolower($elem)])) return '';

  if ($slash != '') return "</$elem>";

  // Is there a closing XHTML slash at the end of the attributes?
  // In PHP 5.1.0+ we could count the changes, currently we need a separate match
  $xhtml_slash = preg_match('%\s?/\s*$%', $attrlist) ? ' /' : '';
  $attrlist = preg_replace('%(\s?)/\s*$%', '\1', $attrlist);

  // Clean up attributes
  $attr2 = implode(' ', zina_filter_xss_attributes($attrlist));
  $attr2 = preg_replace('/[<>]/', '', $attr2);
  $attr2 = strlen($attr2) ? ' '. $attr2 : '';

  return "<$elem$attr2$xhtml_slash>";
}

function zina_filter_xss_attributes($attr) {
  $attrarr = array();
  $mode = 0;
  $attrname = '';

  while (strlen($attr) != 0) {
    // Was the last operation successful?
    $working = 0;

    switch ($mode) {
      case 0:
        // Attribute name, href for instance
        if (preg_match('/^([-a-zA-Z]+)/', $attr, $match)) {
          $attrname = strtolower($match[1]);
          $skip = ($attrname == 'style' || substr($attrname, 0, 2) == 'on');
          $working = $mode = 1;
          $attr = preg_replace('/^[-a-zA-Z]+/', '', $attr);
        }

        break;

      case 1:
        // Equals sign or valueless ("selected")
        if (preg_match('/^\s*=\s*/', $attr)) {
          $working = 1; $mode = 2;
          $attr = preg_replace('/^\s*=\s*/', '', $attr);
          break;
        }

        if (preg_match('/^\s+/', $attr)) {
          $working = 1; $mode = 0;
          if (!$skip) {
            $attrarr[] = $attrname;
          }
          $attr = preg_replace('/^\s+/', '', $attr);
        }

        break;

      case 2:
        // Attribute value, a URL after href= for instance
        if (preg_match('/^"([^"]*)"(\s+|$)/', $attr, $match)) {
          $thisval = zina_filter_xss_bad_protocol($match[1]);

          if (!$skip) {
            $attrarr[] = "$attrname=\"$thisval\"";
          }
          $working = 1;
          $mode = 0;
          $attr = preg_replace('/^"[^"]*"(\s+|$)/', '', $attr);
          break;
        }

        if (preg_match("/^'([^']*)'(\s+|$)/", $attr, $match)) {
          $thisval = zina_filter_xss_bad_protocol($match[1]);

          if (!$skip) {
            $attrarr[] = "$attrname='$thisval'";;
          }
          $working = 1; $mode = 0;
          $attr = preg_replace("/^'[^']*'(\s+|$)/", '', $attr);
          break;
        }

        if (preg_match("%^([^\s\"']+)(\s+|$)%", $attr, $match)) {
          $thisval = zina_filter_xss_bad_protocol($match[1]);

          if (!$skip) {
            $attrarr[] = "$attrname=\"$thisval\"";
          }
          $working = 1; $mode = 0;
          $attr = preg_replace("%^[^\s\"']+(\s+|$)%", '', $attr);
        }

        break;
    }

    if ($working == 0) {
      // not well formed, remove and try again
      $attr = preg_replace('/
        ^
        (
        "[^"]*("|$)     # - a string that starts with a double quote, up until the next double quote or the end of the string
        |               # or
        \'[^\']*(\'|$)| # - a string that starts with a quote, up until the next quote or the end of the string
        |               # or
        \S              # - a non-whitespace character
        )*              # any number of the above three
        \s*             # any number of whitespaces
        /x', '', $attr);
      $mode = 0;
    }
  }

  // the attribute list ends with a valueless attribute like "selected"
  if ($mode == 1) {
    $attrarr[] = $attrname;
  }
  return $attrarr;
}

function zina_filter_xss_bad_protocol($string, $decode = TRUE) {
  static $allowed_protocols;
  if (!isset($allowed_protocols)) {
    $allowed_protocols = array_flip(array('http', 'https', 'ftp', 'mailto', '{internal'));
  }

  // Get the plain text representation of the attribute value (i.e. its meaning).
  if ($decode) $string = zdecode_entities($string);

  // Iteratively remove any invalid protocol found.

  do {
    $before = $string;
    $colonpos = strpos($string, ':');
    if ($colonpos > 0) {
      // We found a colon, possibly a protocol. Verify.
      $protocol = substr($string, 0, $colonpos);
      // If a colon is preceded by a slash, question mark or hash, it cannot
      // possibly be part of the URL scheme. This must be a relative URL,
      // which inherits the (safe) protocol of the base document.
      if (preg_match('![/?#]!', $protocol)) {
        break;
      }
      // Per RFC2616, section 3.2.3 (URI Comparison) scheme comparison must be case-insensitive
      // Check if this is a disallowed protocol.
      if (!isset($allowed_protocols[strtolower($protocol)])) {
        $string = substr($string, $colonpos + 1);
      }
    }
  } while ($before != $string);
  return zcheck_plain($string);
}

function zdbg($x, $exit = false, $phpinfo = false) {
	echo '<pre>';
	print_r($x);
	echo '</pre>'."\n";
	if ($phpinfo) phpinfo(32);
	if ($exit) exit;
}
?>
