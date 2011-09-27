<?php
/**
 * See Drupal batch.inc
 */

function zbatch_set($batch_definition) {
  if ($batch_definition) {
    $batch =& zbatch_get();
    // Initialize the batch
    if (empty($batch)) {
      $batch = array(
        'sets' => array(),
      );
    }

    $init = array(
      'sandbox' => array(),
      'results' => array(),
      'success' => FALSE,
		'other' => array(),
    );

    $defaults = array(
      'title' => zt('Processing'),
      'init_message' => zt('Initializing.'),
      'progress_message' => zt('Processing @current of @total.'),
      'error_message' => zt('An error has occurred.'),
    );
    $batch_set = $init + $batch_definition + $defaults;

    // Tweak init_message to avoid the bottom of the page flickering down after init phase.
    $batch_set['init_message'] .= '<br/>&nbsp;';
    $batch_set['total'] = count($batch_set['operations']);

    // If the batch is being processed (meaning we are executing a stored submit handler),
    // insert the new set after the current one.
    if (isset($batch['current_set'])) {
      // array_insert does not exist...
      $slice1 = array_slice($batch['sets'], 0, $batch['current_set'] + 1);
      $slice2 = array_slice($batch['sets'], $batch['current_set'] + 1);
      $batch['sets'] = array_merge($slice1, array($batch_set), $slice2);
    }
    else {
      $batch['sets'][] = $batch_set;
    }
  }
}

/**
 * Process the batch.
 *
 * Unless the batch has been marked with 'progressive' = FALSE, the function
 * issues a drupal_goto and thus ends page execution.
 */
function zbatch_process($redirect_path = NULL, $redirect_query = 'l=20', $opts = array()) {
  $batch =& zbatch_get();

  if (isset($batch)) {
	  // Add process information
    $process_info = array(
      'current_set' => 0,
      'progressive' => TRUE,
      'query' => 'l=65',
      'redirect_path' => $redirect_path,
      'redirect_query' => $redirect_query,
      'nojs' => (isset($opts['nojs'])) ? (bool)$opts['nojs'] : false,
    );

    $batch += $process_info;

	 if (isset($opts['url'])) $batch['custom_url'] = $opts['url'];

    if ($batch['progressive']) {
      zdbq("INSERT INTO {batch} (token, timestamp) VALUES ('', %d)", time());
      $batch['id'] = zdb_last_insert_id('batch', 'bid');

		$batch['error_message'] = zt('Please continue to <a href="@error_url">the error page</a>',
			array('@error_url' => zurl(null, $batch['query'].'&id='.$batch['id'].'&op=finished')));

      zdbq("UPDATE {batch} SET token = '%s', batch = '%s' WHERE bid = %d", zina_token('get', $batch['id']), serialize($batch), $batch['id']);

      $query = $batch['query'].'&op=start&id='. $batch['id'];

		if (isset($batch['custom_url'])) {
			@session_write_close();
			while(@ob_end_clean());
			header('Location: '. $batch['custom_url'].'?'.$query, TRUE, 302);
			exit();
		} else {
      	zina_goto(null, $query, null, true);
		}

      #zina_goto(null, $batch['query'].'&op=start&id='. $batch['id'], null, true);
    } else {
      // Non-progressive execution: bypass the whole progressbar workflow
      // and execute the batch in one pass.
      _zbatch_process();
    }
  }
}

/**
 * Retrieve the current batch.
 */
function &zbatch_get() {
  static $batch = array();
  return $batch;
}

// $Id: batch.inc,v 1.14 2007/12/20 11:57:20 goba Exp $

/**
 * @file Batch processing API for processes to run in multiple HTTP requests.
 */

/**
 * State-based dispatcher for the batch processing page.
 */
function _zbatch_page() {
  $batch =& zbatch_get();
  // Retrieve the current state of batch from db.
  if (isset($_REQUEST['id']) && $data = zdbq_single("SELECT batch FROM {batch} WHERE bid = %d AND token = '%s'", $_REQUEST['id'], zina_token('get', $_REQUEST['id']))) {
		$batch = unserialize($data);
  } else {
    return FALSE;
  }

  // Register database update for end of processing.
  register_shutdown_function('_zbatch_shutdown');

  $op = isset($_REQUEST['op']) ? $_REQUEST['op'] : '';
  $output = NULL;
  switch ($op) {
    case 'start':
      $output = _zbatch_start($batch['nojs']);
      break;

    case 'do': // JS-version AJAX callback.
      _zbatch_do();
      break;

    case 'do_nojs': // Non-JS progress page.
      $output = _zbatch_progress_page_nojs();
      break;

    case 'finished':
      $output = _zbatch_finished();
      break;
  }

  return $output;
}

/**
 * Initiate the batch processing
 */
function _zbatch_start($nojs) {
  // Choose between the JS and non-JS version.
  // JS-enabled users are identified through the 'has_js' cookie set in drupal.js.
  // If the user did not visit any JS enabled page during his browser session,
  // he gets the non-JS version...

  if (!$nojs) {
    return _zbatch_progress_page_js();
  } else {
    return _zbatch_progress_page_nojs();
  }
}

function zbatch_set_title($title = NULL) {
  static $stored_title;

  if (isset($title)) {
    $stored_title = $title;
  }
  return $stored_title;
}

/**
 * Batch processing page with JavaScript support.
 */
function _zbatch_progress_page_js() {
  $batch = zbatch_get();

  // The first batch set gets to set the page title
  // and the initialization and error messages.
  $current_set = _zbatch_current_set();
  zbatch_set_title($current_set['title']);
  zina_set_js('file', 'extras/jquery.js');
  zina_set_js('file', 'extras/drupal.js');
  zina_set_js('file', 'extras/progress.js');

  	$query = $batch['query'].'&id='.$batch['id'];
  if (isset($batch['custom_url'])) {
  	$url = $batch['custom_url'].'?'.$query;
  } else {
  	$url = zurl(null, $query);
  }

  $js_setting = array(
    'batch' => array(
      'errorMessage' => $current_set['error_message'] .'<br/>'. $batch['error_message'],
      'initMessage' => $current_set['init_message'],
      'uri' => $url,
    ),
 );

  $js = 'jQuery.extend(Drupal.settings, '.zina_to_js($js_setting) . ");";

  zina_set_js('inline', $js);
  zina_set_js('file', 'extras/batch.js');

  $output = '<div id="progress"></div>';
  return $output;
}

/**
 * Do one pass of execution and inform back the browser about progression
 * (used for JavaScript-mode only).
 */
function _zbatch_do() {
  // HTTP POST required
  if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    zina_set_message(zt('HTTP POST is required.'), 'error');
    zbatch_set_title(zt('Error'));
    return '';
  }

  // Perform actual processing.
  list($percentage, $message) = _zbatch_process();

  zina_json(array('status' => TRUE, 'percentage' => $percentage, 'message' => $message));
}

/**
 * Batch processing page without JavaScript support.
 */
function _zbatch_progress_page_nojs() {
  $batch =& zbatch_get();
  $current_set = _zbatch_current_set();

  zbatch_set_title($current_set['title']);

  $new_op = 'do_nojs';

  if (!isset($batch['running'])) {
    // This is the first page so we return some output immediately.
    $percentage = 0;
    $message = $current_set['init_message'];
    $batch['running'] = TRUE;
  } else {
    // This is one of the later requests: do some processing first.

    // Error handling: if PHP dies due to a fatal error (e.g. non-existant
    // function), it will output whatever is in the output buffer,
    // followed by the error message.
    ob_start();
    $fallback = $current_set['error_message'] .'<br/>'. $batch['error_message'];

    print $fallback;

    // Perform actual processing.
    list($percentage, $message) = _zbatch_process($batch);
    if ($percentage == 100) {
      $new_op = 'finished';
    }

    // PHP did not die : remove the fallback output.
    ob_end_clean();
  }

	$query = $batch['query'].'&id='.$batch['id'].'&op='.$new_op;
  sleep(1);

	if (isset($batch['custom_url'])) {
		@session_write_close();
		while(@ob_end_clean());
		header('Location: '. $batch['custom_url'].'?'.$query, TRUE, 302);
		exit();
	} else {
  		zina_goto(null, $query);
	}

  #zina_goto(null, $batch['query'].'&id='.$batch['id'].'&op='.$new_op);
}

/**
 * Advance batch processing for 1 second (or process the whole batch if it
 * was not set for progressive execution - e.g forms submitted by drupal_execute).
 */
function _zbatch_process() {
  $batch =& zbatch_get();
  $current_set =& _zbatch_current_set();
  $set_changed = TRUE;

  if ($batch['progressive']) {
    ztimer_start('batch_processing');
  }

  while (!$current_set['success']) {
    // If this is the first time we iterate this batch set in the current
    // request, we check if it requires an additional file for functions
    // definitions.
    if ($set_changed && isset($current_set['file']) && is_file($current_set['file'])) {
      include_once($current_set['file']);
    }

    $finished = 1;
    $task_message = '';
    if ((list($function, $args) = reset($current_set['operations'])) && function_exists($function)) {
      // Build the 'context' array, execute the function call,
      // and retrieve the user message.
		$batch_context = array('sandbox' => &$current_set['sandbox'], 'results' => &$current_set['results'], 'finished' => &$finished,
			'other' => &$current_set['other'], 'message' => &$task_message);
      // Process the current operation.
      call_user_func_array($function, array_merge($args, array(&$batch_context)));
    }

    if ($finished == 1) {
      // Make sure this step isn't counted double when computing $current.
      $finished = 0;
      // Remove the operation and clear the sandbox.
      array_shift($current_set['operations']);
      $current_set['sandbox'] = array();
    }

    // If the batch set is completed, browse through the remaining sets,
    // executing 'control sets' (stored form submit handlers) along the way -
    // this might in turn insert new batch sets.
    // Stop when we find a set that actually has operations.
    $set_changed = FALSE;
    $old_set = $current_set;
    while (empty($current_set['operations']) && ($current_set['success'] = TRUE) && _zbatch_next_set()) {
      $current_set =& _zbatch_current_set();
      $set_changed = TRUE;
    }
    // At this point, either $current_set is a 'real' batch set (has operations),
    // or all sets have been completed.

    // If we're in progressive mode, stop after 1 second.
    if ($batch['progressive'] && ztimer_read('batch_processing') > 1000) {
      break;
    }
  }

  if ($batch['progressive']) {
    // Gather progress information.

    // Reporting 100% progress will cause the whole batch to be considered
    // processed. If processing was paused right after moving to a new set,
    // we have to use the info from the new (unprocessed) one.
    if ($set_changed && isset($current_set['operations'])) {
      // Processing will continue with a fresh batch set.
      $remaining = count($current_set['operations']);
      $total = $current_set['total'];
      $progress_message = $current_set['init_message'];
      $task_message = '';
    }
    else {
      $remaining = count($old_set['operations']);
      $total = $old_set['total'];
      $progress_message = $old_set['progress_message'];
    }

    $current    = $total - $remaining + $finished;
    $percentage = $total ? floor($current / $total * 100) : 100;
    $values = array(
      '@remaining'  => $remaining,
      '@total'      => $total,
      '@current'    => floor($current),
      '@percentage' => $percentage,
      );
    $message = strtr($progress_message, $values) .'<br/>';
    $message .= $task_message ? $task_message : '&nbsp';

    return array($percentage, $message);

  } else {
    // If we're not in progressive mode, the whole batch has been processed by now.
    return _zbatch_finished();
  }

}

/**
 * Retrieve the batch set being currently processed.
 */
function &_zbatch_current_set() {
  $batch =& zbatch_get();
  return $batch['sets'][$batch['current_set']];
}

/**
 * Move execution to the next batch set if any, executing the stored
 * form _submit handlers along the way (thus possibly inserting
 * additional batch sets).
 */
function _zbatch_next_set() {
  $batch =& zbatch_get();
  if (isset($batch['sets'][$batch['current_set'] + 1])) {
    $batch['current_set']++;
    $current_set =& _zbatch_current_set();
    return TRUE;
  }
}

/**
 * End the batch processing:
 * Call the 'finished' callbacks to allow custom handling of results,
 * and resolve page redirection.
 */
function _zbatch_finished() {
  $batch =& zbatch_get();

  // Execute the 'finished' callbacks for each batch set.
  foreach ($batch['sets'] as $key => $batch_set) {
    if (isset($batch_set['finished'])) {
      // Check if the set requires an additional file for functions definitions.
      if (isset($batch_set['file']) && is_file($batch_set['file'])) {
        include_once($batch_set['file']);
      }
      if (function_exists($batch_set['finished'])) {
        $batch_set['finished']($batch_set);
        #$batch_set['finished']($batch_set['success'], $batch_set['results'], $batch_set['operations']);
		}
	 } else {
		if ($batch_set['success']) {
			$message = (isset($batch_set['finished_message'])) ? $batch_set['finished_message'] : zt('Completed successfully.');
		} else {
			$message = zt('Finished with an error.');
		}
		zina_set_message($message);
	 }
  }

  // Cleanup the batch table and unset the global $batch variable.
  if ($batch['progressive']) {
    zdbq("DELETE FROM {batch} WHERE bid = %d", $batch['id']);
  }

  $_batch = $batch;
  $batch = NULL;

  if ($_batch['nojs']) {
	#global $zc;
	#$semaphore = $zc['cache_dir_private_abs'].'/.cron';
	#if (file_exists($semaphore)) @unlink($semaphore);
  } elseif ($_batch['progressive']) {

	if (isset($_batch['custom_url'])) {
		@session_write_close();
		while(@ob_end_clean());
		header('Location: '. $_batch['custom_url'].'?'.$_batch['redirect_query']);
		exit();
	} else {
    	zina_goto($_batch['redirect_path'], $_batch['redirect_query'], null, true);
	}

    #zina_goto($_batch['redirect_path'], $_batch['redirect_query'], null, true);
  }
}

/**
 * Shutdown function: store the batch data for next request,
 * or clear the table if the batch is finished.
 */
function _zbatch_shutdown() {
  if ($batch = zbatch_get()) {
    zdbq("UPDATE {batch} SET batch = '%s' WHERE bid = %d", serialize($batch), $batch['id']);
  }
}

function zina_json($var = NULL) {
	global $zc;
	if (!$zc['debug']) while(@ob_end_clean());
	header('Content-Type: text/javascript; charset=utf-8');
	if (isset($var)) echo zina_to_js($var);
	exit;
}

function ztimer_start($name) {
  global $timers;

  list($usec, $sec) = explode(' ', microtime());
  $timers[$name]['start'] = (float)$usec + (float)$sec;
  $timers[$name]['count'] = isset($timers[$name]['count']) ? ++$timers[$name]['count'] : 1;
}

function ztimer_read($name) {
  global $timers;

  if (isset($timers[$name]['start'])) {
    list($usec, $sec) = explode(' ', microtime());
    $stop = (float)$usec + (float)$sec;
    $diff = round(($stop - $timers[$name]['start']) * 1000, 2);

    if (isset($timers[$name]['time'])) {
      $diff += $timers[$name]['time'];
    }
    return $diff;
  }
}

function zina_core_cache_batch($type, $path = '', $opts = array(), &$context) {
	$context['message'] = zt('Generating @type cache.', array('@type'=>$type));
	zina_core_cache($type, $path, $opts);
	$context['results'][] = zt('@type cache', array('@type'=>$type));
}

function zina_cron_finished(&$batch) {
	global $zc;
	$semaphore = $zc['cache_dir_private_abs'].'/.cron';
	if (file_exists($semaphore)) @unlink($semaphore);
}

function zdb_populate_finished(&$batch) {
	if ($batch['success']) {
		zvar_set('cron_populate_last_run', $batch['other']['runtime']);
		zina_set_message($batch['finished_message']);

		if (!empty($batch['results'])) {
			if (sizeof($batch['results']) > 20) {
				$results = array_slice($batch['results'],0,20);
				$results[] = ' ***SNIP*** ';
				$results += array_slice($batch['results'],-20);
			} else {
				$results = &$batch['results'];
			}
			$output = ztheme('list', $results, "zina-list");
			zina_set_message($output);
		}
		if ($batch['other']['regen']) {
			zdbq("DELETE FROM {search_index} WHERE mtime + 86400 < ".$batch['other']['runtime']);
		}
	} else {
		zina_set_message($batch['operations']['error_message'], 'error');
	}
	unset($_SESSION['zina_batch_dirs']);
	unset($_SESSION['zina_batch_files']);
}

function zdb_populate_batch($regen = false, &$context) {
	if (empty($context['sandbox'])) {
		$context['sandbox']['progress'] = 0;
		$_SESSION['zina_batch_dirs'] = zina_core_cache('dirs');
		$_SESSION['zina_batch_files'] = zina_core_cache('files_assoc');
		$context['sandbox']['max'] = count($_SESSION['zina_batch_dirs']);
		$context['other']['runtime'] = time();
		$context['other']['regen'] = $regen;
		$context['sandbox']['last_update'] = zvar_get('cron_populate_last_run', 0);
	}
	global $zc;

	$path = array_shift($_SESSION['zina_batch_dirs']);

	$dir = $zc['mp3_dir'];
	if (!empty($path)) $dir .= '/'.$path;

	$context['message'] = zcheck_utf8($path);
	$context['sandbox']['progress']++;

	if (file_exists($dir) && is_dir($dir)) {
		if ($regen || filemtime($dir) > $context['sandbox']['last_update']) {
			$context['results'][] = zcheck_utf8($path);
			if (zdb_log_stat('insertonly', $path) !== false) {
				if (isset($_SESSION['zina_batch_files'][$path])) {
					foreach($_SESSION['zina_batch_files'][$path] as $file) {
						zdb_log_stat('insertonly', $path, $file, null, $regen);
					}
				}
			}
			if (function_exists('zina_cms_populate')) {
				zina_cms_populate($path);
			}
		}
	}

	if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
		$context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
	}
}

function zbatch_extract_images(&$context) {
	global $zc;
	if (empty($context['sandbox'])) {
		$context['sandbox']['progress'] = 0;
		$_SESSION['zina_batch_files'] = $files = zina_core_cache('files_assoc');
		$_SESSION['zina_batch_dirs'] = array_keys($files);
		$context['sandbox']['max'] = count($_SESSION['zina_batch_dirs']);
	}

	$limit = 5;
	$count = &$context['sandbox']['progress'];

	if ($count + $limit > $context['sandbox']['max']) {
		$limit = $context['sandbox']['max'] - $count;
	}

	$result = array_slice($_SESSION['zina_batch_dirs'], $count, $limit);

	if (!empty($result)) {
		foreach($result as $dir) {
			$count++;
			$file = $_SESSION['zina_batch_files'][$dir][0];
			$path = (!empty($dir)) ? $dir.'/'.$file : $file;
			$path_full = $zc['mp3_dir'].'/'.$path;
			$context['message'] = $path;

			if (file_exists($path_full)) {
				$mp3 = zina_get_file_info($path_full, false, true, false, true);

				if (isset($mp3->image)) {
					$image_path = $zc['mp3_dir']. ((empty($dir) || $dir == '.') ? '' : '/'.$dir);
					$image_file = $image_path.'/cover_id3_zina.'.$mp3->image['type'];
					if (!file_exists($image_file)) {
						if (is_dir($image_path) && is_writeable($image_path)) {
							if (file_put_contents($image_file, $mp3->image['data'])) {
								$context['results'][] = zcheck_utf8($image_file);
							} else {
								zina_set_message(zt('Could not write image file: @file', array('@file'=>$image_file), 'error'));
							}
						} else {
							zina_set_message(zt('Directory is not writeable: @dir', array('@dir'=>$image_path), 'warn'));
						}
					}
				}
			}
		}
	}

	if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
		$context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
	}
}

function zbatch_extract_images_finished(&$batch) {
	unset($_SESSION['zina_batch_files']);
	unset($_SESSION['zina_batch_dirs']);

	if ($batch['success']) {
		zina_set_message($batch['finished_message']);
		$output = ztheme('list', $batch['results'], "zina-list");
		zina_set_message($output);
	} else {
		zina_set_message($batch['operations']['error_message'], 'error');
	}
}

if (!function_exists('zina_token')) {
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
}
?>
