<?php

function zina_content_edit_tags(&$zina, $path, $opts) {
	global $zc;

	if (!is_writeable($zc['cur_dir'])) {
		zina_set_message(zt('Directory is not writeable: @dir', array('@dir'=>$zc['cur_dir'])),'error');
		zina_goto($path);
	}

	$dir = zina_get_directory($path);
	zina_content_breadcrumb($zina, $path, $dir['title'], true);

	require_once($zc['zina_dir_abs'].'/extras/getid3/getid3.php');

	$getID3 = new getID3;
	$getID3->setOption(array('encoding'=>$zc['tags_format']));
	getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'write.php', __FILE__, true);

	if (isset($_POST['write_tags'])) { # WRITE TAG
		if (!zina_token_sess_check()) return zina_page_main($path);
		if (empty($_POST['files'])) {
			zina_set_message(zt('No files to tag'), 'error');
			return zina_page_main($path);
		}

		$changed = 0;

		foreach($_POST['files'] as $key => $file) {
			$file = zrawurldecode($file);
			$full_path = $zc['mp3_dir'].'/'.((empty($path) || $path == '.') ? $file : $path.'/'.$file);
			if (file_exists($full_path) && zfile_check_location($full_path, $zc['mp3_dir']) && is_writeable($full_path)) {
				$getID3 = new getID3;
				$info = $getID3->analyze($full_path);
				getid3_lib::CopyTagsToComments($info);
				$mtime = filemtime($full_path);

				if (isset($_POST['tag_format_override'])) {
					$tag_formats_to_write = (isset($_POST['tag_format'])) ? $_POST['tag_format'] : array();
					if (empty($tag_formats_to_write)) {
						zina_set_message(zt('No global tag formats selected'), 'error');
						break;
					}
				} else {
					$tag_formats_to_write = (isset($_POST['tag_formats'][$key])) ? $_POST['tag_formats'][$key] : array();
					if (empty($tag_formats_to_write)) {
						zina_set_message(zt('No individual tag formats selected'), 'warn');
						continue;
					}
				}

				$tags = array();
				$common = array('title','artist','album','year','comment','genre');

				foreach($common as $keynew) {
					if (isset($_POST[$keynew.'_override'])) {
						$tags[$keynew][] = getid3_lib::SafeStripSlashes($_POST[$keynew]);
					} else {
						$tags[$keynew][] = getid3_lib::SafeStripSlashes($_POST[$keynew.'s'][$key]);
					}
				}

				$tags['tracknumber'][] = getid3_lib::SafeStripSlashes($_POST['tracks'][$key]);

				if ($zc['tags_keep_existing_data'] && isset($info['comments'])) {
					foreach($info['comments'] as $keyold=>$valold) {
						if (!isset($tags[$keyold])) {
							$tags[$keyold] = $valold;
						}
					}
					# for array compare
					if (!isset($info['comments']['comment'])) $info['comments']['comment'] = array('');
					if (!isset($info['comments']['tracknumber'])) $info['comments']['tracknumber'] = array('');
				}
				
				if (isset($info['tags'])) {
					$existing_tags = array_keys($info['tags']);
					foreach($existing_tags as $key => $t) {
						if ($t == 'id3v2') {
							$existing_tags[$key] = $t.'.'.$info[$t]['majorversion'];
						}
					}
				}

				if (isset($info['comments']) && $info['comments'] == $tags && $existing_tags == $tag_formats_to_write) {
					if (isset($info['comments']['tracknumber']) && isset($tags['tracknumber'])) {
						if ($info['comments']['tracknumber'] === $tags['tracknumber']) continue;
					} else {
						continue;
					}
				}

				$tagwriter = new getid3_writetags;
				$tagwriter->filename       = $full_path;
				$tagwriter->tagformats     = $tag_formats_to_write;
				$tagwriter->overwrite_tags = !$zc['tags_keep_existing_data'];
				$tagwriter->tag_encoding   = $zc['tags_format'];
				if (!empty($_POST['remove_other_tags'])) $tagwriter->remove_other_tags = true;
				if (!empty($_POST['skip_errors'])) $tagwriter->skip_errors = true;

				$tagwriter->tag_data = $tags;

				if ($tagwriter->WriteTags()) {
					zina_set_message(zt('Successfully wrote tag: @file', array('@file'=>$file)));
					#TODO: genre update?
					if ($zc['tags_filemtime']) @touch($full_path, $mtime);
					zdb_log_stat('insertonly', $path, $file, null, true);
					$changed++;
				} else {
					zina_set_message(zt('Failed to write tags for @file.', array('@file'=>$full_path)), 'error');
					if (!empty($tagwriter->errors)) {
						zina_set_message(implode('<BR><BR>', $tagwriter->errors), 'error');
					}
				}
				if (!empty($tagwriter->warnings)) {
					zina_set_message(implode('<BR><BR>', $tagwriter->warnings), 'warn');
				}
			} else {
				zina_set_message(zt('File does not exist or is not writeable: @file', array('@file'=>$full_path)), 'error');
			}
		} # end loop

		if ($changed > 0) zdb_genre_populate(time(), $context);

		zina_set_message(zt('@changed of @total files tags updated', array('@changed'=>$changed, '@total'=>sizeof($_POST['files']))));
		if ($zc['embed'] != 'standalone') zina_goto($path, 'l=71');
	} # END POST

	$length = 0;
	$theme_opts = array();
	$tag_types = array();

	if (!empty($dir['files'])) {
		$i = 0;
		foreach($dir['files'] as $key => $file) {
			$full_path = $zc['mp3_dir'].'/'.((empty($file['path']) || $file['path'] == '.') ? $file['file'] : $file['path'].'/'.$file['file']);

			$getID3 = new getID3;
			$file_info = $getID3->analyze($full_path);
			if (!isset($file_info['fileformat'])) {
				zina_set_message(zt('File format not supported: @file', array('@file'=>$full_path)), 'warn');
				if (isset($getID3->info['error']) && !empty($getID3->info['error'])) {
					foreach($getID3->info['error'] as $error) {
						zina_set_message(zt('@error', array('@error'=>$error)), 'error');
					}
				}
				if (isset($getID3->info['warning']) && !empty($getID3->info['warning'])) {
					foreach($getID3->info['warning'] as $warning) {
						zina_set_message(zt('@error', array('@error'=>$warning)), 'warn');
					}
				}
				continue;
			}

			getid3_lib::CopyTagsToComments($file_info);
			$offsets[] = 150+round(($length*75));
			$length += $file_info['playtime_seconds'];

			$ValidTagTypes = zina_extras_tags_formats($file_info['fileformat'], @$file_info['audio']['dataformat']);

			if (isset($file_info['error'])) {
				if (is_array($file_info['error'])) {
					foreach($file_info['error'] as $error) {
						zina_set_message(zt('Error reading tag: @error', array('@error'=>$error)), 'warn');
					}
				} else {
					zina_set_message(zt('Error reading tag: @error', array('@error'=>$file_info['error'])), 'warn');
				}
			}
			$tag_types = array_merge($tag_types, $ValidTagTypes);

			$track = FixTextFields(@implode(', ',$file_info['comments']['tracknumber']));
			$artist = FixTextFields(@implode(', ',$file_info['comments']['artist']));
			$album = FixTextFields(@implode(', ',$file_info['comments']['album']));
			$title = FixTextFields(@implode(', ',$file_info['comments']['title']));
			$genre = FixTextFields(@implode(', ',$file_info['comments']['genre']));
			$year = FixTextFields(@implode(', ',$file_info['comments']['year']));
			$comment = FixTextFields(@implode(', ',$file_info['comments']['comment']));

			$artists[$artist] = (isset($artists[$artist])) ? ++$artists[$artist] : 1;
			$albums[$album] = (isset($albums[$album])) ? ++$albums[$album] : 1;
			$years[$year] = (isset($years[$year])) ? ++$years[$year] : 1;
			$genres[$genre] = (isset($genres[$genre])) ? ++$genres[$genre] : 1;
			$comments[$comment] = (isset($comments[$comment])) ? ++$comments[$comment] : 1;
			if (empty($title)) $theme_opts['missing_tags'] = true;

			$rows[] = array(
				'cols' => array(
					'track'=> array('size'=>2, 'value'=>$track),
					'title'=> array('size'=>30, 'max'=>50, 'value' => (empty($title) ? ztheme('song_title', $file['file']) : $title)),
					'artist'=> array('size'=>30, 'max'=>30, 'value'=>$artist),
					'album'=> array('size'=>20, 'max'=>40, 'value'=>$album),
					'genre'=> array('size'=>20, 'max'=>30, 'value'=>$genre),
					'year'=> array('size'=>6, 'max'=>6, 'value'=>$year),
					'comment'=> array('size'=>20, 'max'=>40, 'value'=>$comment),
				),
				'valid_tag_types' => $ValidTagTypes,
				'tags' => (isset($file_info['tags'])) ? array_keys($file_info['tags']) : array(),
				'file' => $file['file'],
				'hidden' => zina_content_form_helper('files[]', array('type'=>'hidden', 'def'=>''), zrawurlencode($file['file'])),
				);
			$i++;
		}
		$theme_opts['tag_types'] = array_unique($tag_types);

		if (!isset($_POST['write_tags'])) {
			$length = round($length);
			$disc_id = cddb_disc_id($offsets, $length);
			$url = "http://".$zc['tags_cddb_server']."/~cddb/cddb.cgi?cmd=cddb+query+$disc_id+".count($offsets)."+".implode('+', $offsets)."+".$length.
				"&hello=anonymous+unknown+zina+".ZINA_VERSION."&proto=6";

			zina_set_js('file', 'extras/jquery.js');

			$url = zurl($path, 'l=72&pl='.zrawurlencode($url).'&rand='.time());

			$js = 'function zinaTagSearch(){'.
				'jQuery("#zina-matches").html("'.zt('Loading...').'");'.
				'jQuery("#zina-matches").load("'.$url.'",function(data,status,xml){'.
				'if(status=="error"){'.
					'jQuery("#zina-matches").html(\'Error: \'+msg+status+data);'.
				'}else{'.
					'jQuery("#zina-matches").addClass("messages status");'.
				'}'.
			'});}';

			zina_set_js('inline', $js);
		}
	}
	$theme_opts['auto_start'] = $zc['tags_cddb_auto_start'];

	if (sizeof($artists) > 1) arsort($artists);
	if (sizeof($albums) > 1) arsort($albums);
	if (sizeof($years) > 1) arsort($years);
	if (sizeof($genres) > 1) arsort($genres);
	if (sizeof($comments) > 1) arsort($comments);

	$artist = key($artists);
	$album = key($albums);
	$year = key($years);
	$genre = key($genres);
	$comment = key($comments);
	$theme_opts['various'] = $dir['various'];

	$m['artist'] = array('label'=>"Artist", 'value'=>$artist, 'over'=>(!$dir['various']));
	$m['album'] = array('label'=>"Album", 'value'=>$album, 'over'=>1);
	$m['genre'] = array('label'=>"Genre", 'value'=>$genre, 'over'=>1);
	$m['year'] = array('label'=>"Year", 'value'=>$year, 'over'=>1);
	$m['comment'] = array('label'=>"Comment", 'value'=>$comment, 'over'=>1);

	$form_id = 'zina-tags';
	$attr = 'action="'.zurl($path, 'l=71').'" id="'.$form_id.'"';
	$zina['content'] = ztheme('tag_editor', $m, $rows, $attr, $form_id, $theme_opts);
}

function ztheme_tag_editor($global, $rows, $form_attr, $form_id, $opts) {
	$output = '<h3>'.zt('Edit Tags').'</h3><br/>';
	if (!isset($_POST['write_tags'])) {
		$txt = '';
		if ($opts['auto_start']) {
			$txt = '<script type="text/javascript">zinaTagSearch();</script>';
		} else {
			$txt = '<a href="javascript: void 0;" onclick="zinaTagSearch();">'.zt('Search for tags').'</a>';
		}
		$output .= '<div id="zina-matches" class="zina_messages">'.$txt.'</div>';
	}

	$output .= '<table border=0>'.
		'<tr><td class="search-header" colspan="3"><strong>'.zt('Global Settings').
		'</strong></td></tr>';

	foreach($global as $key => $item) {
		$output .= '<tr><td valign="top">'.zt($item['label']).'</td>'.
			'<td valign="top">'.zina_content_form_helper($key, array('type'=>'textfield', 'id'=>'zina-'.$key, 'def'=>'', 'size'=>30, 'max'=>50, 'v'=>array('req')), $item['value']);
			if ($key == 'comment') {
				$output .= ' <small><a href="javascript: void 0;" onclick="zClearField(\''.$form_id.'\',\''.$key.'\');">'.zt('Clear').'</a>';
			}
			$output .= '<br/><div id="zina-'.$key.'-orig" class="small">'.$item['value'].'</div></td>'.
			'<td valign="top">'.zina_content_form_helper($key.'_override', array('type'=>'checkbox', 'def'=>'', 'onclick'=>'TagFieldToggle("'.$form_id.'","'.$key.'s[]");'), $item['over']).
				' '.zt('Override/set values in files below').'</td></tr>';
	}

	$output .= '<tr><td>'.zt('Write tag formats').'</td><td>'.
		zina_content_tags_format($opts['tag_types'], array(), 'tag_format[]').
		'</td><td>'.
		zina_content_form_helper('tag_format_override', array('type'=>'checkbox', 'def'=>'', 'onclick'=>'TagFieldToggle("'.$form_id.'","tag_formats");'), 1).' '.zt('Override/set values in files below').
		'</td></tr>'.
	 	'<tr><td colspan="2">'.
		zina_content_form_helper('remove_other_tags', array('type'=>'checkbox', 'def'=>''), 1).' '.zt('Remove non-selected tag formats when writing new tag').' '.
		zina_content_form_helper('skip_errors', array('type'=>'checkbox', 'def'=>''), 0).' '.zt('Ignore frame errors').
		'</td></tr>'.
		'</table>';

	$output .= '<p style="text-align:center;"><input type="submit" name="write_tags" value="'.zt('Submit').'"/></p>';

	$js = '';

$SMALL_WORDS = '(a|an|and|as|at|but|by|en|for|if|in|of|on|or|the|to|v[.]?|via|vs[.]?)';

$foo = <<<FOO
/*
 * The list of "small words" which are not capped comes from
 * the New York Times Manual of Style, plus "vs" and "v".
 *
 * Original Perl version by:
 *   John Gruber
 *   http://daringfireball.net/
 *   10 May 2008
 *
 * Adapted to JavaScript by:
 *   Marshall Elfstrand
 *   http://vengefulcow.com/
 *   21 May 2008
 *
 * License: http://www.opensource.org/licenses/mit-license.php
 */
var TitleCase = {};

TitleCase.SPLIT_PATTERN = /([:.;?!][ ]|(?:[ ]|^)["“])/;
TitleCase.BASE_PATTERN = /\b([A-Za-z][a-z.'’]*)\b/g;
TitleCase.INLINE_DOT_PATTERN = /[A-Za-z][.][A-Za-z]/;
TitleCase.SMALL_WORD_PATTERN = /\b$SMALL_WORDS\b/ig;
TitleCase.SMALL_WORD_FIRST_PATTERN = /^(\W*)$SMALL_WORDS\b/ig;
TitleCase.SMALL_WORD_LAST_PATTERN = /\b$SMALL_WORDS(\W*)$/ig;
TitleCase.VS_PATTERN = / V(s?)\. /g;
TitleCase.POSSESSIVE_PATTERN = /(['’])S\b/g;

TitleCase.capitalize = function(w) {
    return w.substr(0, 1).toUpperCase() + w.substr(1).toLowerCase();
}

TitleCase.capitalizeUnlessInlineDot = function(w) {
    if (TitleCase.INLINE_DOT_PATTERN.test(w)) return w;
    else return TitleCase.capitalize(w);
}

TitleCase.capitalizeFirstWord = function(match, leadingPunctuation, smallWord) {
    return leadingPunctuation + TitleCase.capitalize(smallWord);
}

TitleCase.capitalizeLastWord = function(match, smallWord, trailingPunctuation) {
    return TitleCase.capitalize(smallWord) + trailingPunctuation;
}

TitleCase.toLowerCase=function(w){return w.toLowerCase();}
TitleCase.toUpperCase=function(w){return w.toUpperCase();}

TitleCase.toTitleCase = function(input) {
    var result = "";

    var tokens = input.split(TitleCase.SPLIT_PATTERN);
    for (var i = 0; i < tokens.length; i++) {
        var s = tokens[i];
        s = s.replace(TitleCase.BASE_PATTERN, TitleCase.capitalizeUnlessInlineDot);
        s = s.replace(TitleCase.SMALL_WORD_PATTERN, TitleCase.toLowerCase);
        s = s.replace(TitleCase.SMALL_WORD_FIRST_PATTERN, TitleCase.capitalizeFirstWord);
        s = s.replace(TitleCase.SMALL_WORD_LAST_PATTERN, TitleCase.capitalizeLastWord);
        result += s;
    }
    
    // Handle special cases.
    result = result.replace(TitleCase.VS_PATTERN, ' v$1. ');
    result = result.replace(TitleCase.POSSESSIVE_PATTERN, '$1s');
 
    return result;
}

String.prototype.toTitleCase = function() {
    return TitleCase.toTitleCase(this);
};
FOO;

	$js .= $foo;
	$js .= 'function FillTrackNumbers(formid){'.
		'var e=document.forms[formid].elements["tracks[]"];'.
		'for(var i=0;i<e.length;i++){'.
			'e[i].value = i+1;'.
		'}'.
	'}';
	$js .= 'function zClearField(formid, field){'.
		'document.forms[formid].elements[field].value="";'.
	'}';
	
	$js .= 'function zTitleCase(formid, field){'.
		'var e=document.forms[formid].elements[field+"[]"];'.
		'for(var i=0;i<e.length;i++){'.
			'e[i].value = e[i].value.toTitleCase();'.
		'}'.
	'}';

	$js .= 'function zStripLast(formid,field){'.
		'var e=document.forms[formid].elements[field+"[]"];'.
		'for(var i=0;i<e.length;i++){'.
			'var len = e[i].value.length;'.
			'if (len > 4) e[i].value = e[i].value.slice(0,len-4);'.
		'}'.
	'}';


	$js .= 'function TagFieldToggle(formid, element) {'.
	'var $j = jQuery;'.
		'if(element=="tag_formats"){'.
			'var length=document.forms[formid].elements["tracks[]"].length;'.
			'for(var i=0;i<length;i++){'.
				'var e=document.forms[formid].elements["tag_formats["+i+"][]"];'.
				'if (!e) continue;'. # unsupported tag format
				'if (e.length){'.
					'for(var j=0;j<e.length;j++){'.
						'e[j].disabled = !e[j].disabled;'.
					'}'.
				'}else{'. # singular
					'e.disabled = !e.disabled;'.
				'}'.
			'}'.
		'}else{'.
			'var e=document.forms[formid].elements[element];'.
			'for(var i=0;i<e.length;i++){'.
				'e[i].disabled = !e[i].disabled;'.
				'e[i].style.backgroundColor = (e[i].disabled) ? "#CCC" : "";'.
			'}'.
		'}'.
	'}';

	# IE doesnt grey out disabled inputs =(
	$js2 = 'var cols = new Array("artists[]", "albums[]", "genres[]", "years[]", "comments[]");'.
			'for(j=0;j<cols.length;j++){'.
				'var e=document.forms["'.$form_id.'"].elements[cols[j]];'.
				'for(var i=0;i<e.length;i++){'.
					'e[i].style.backgroundColor = (e[i].disabled) ? "#CCC" : "";'.
				'}'.
			'}';


	$js2 = '<script>'.$js2.'</script>';
	zina_set_js('inline', $js);

	$header = array(zt('Track'), zt('Title'), zt('Artist'), zt('Album'), zt('Genre'), zt('Year'), zt('Comment'));

	$output .= '<a href="javascript: void 0;" onclick="FillTrackNumbers(\''.$form_id.'\');">'.zt('Fill Track Numbers').'</a>';
	$output .= ' | <a href="javascript: void 0;" onclick="zTitleCase(\''.$form_id.'\',\'titles\');">'.zt('Title Case').'</a>';
	if ($opts['various'])
		$output .= ' | <a href="javascript: void 0;" onclick="zTitleCase(\''.$form_id.'\',\'artists\');">'.zt('Artist Case').'</a>';
	if (isset($opts['missing_tags']))
		$output .= ' | <a href="javascript: void 0;" onclick="zStripLast(\''.$form_id.'\',\'titles\');">'.zt('Strip Last Four Letters').'</a>';

	$output .= '<table>'.
		'<tr><td class="search-header"><strong>'.
		implode('</strong></td><td class="search-header"><strong>', $header).
		'</strong></td></tr>';

	$color = 0;
	foreach($rows as $i=>$row) {
		$output .= '<tr>';
		foreach($row['cols'] as $key => $item) {
			$field = array('type'=>'textfield', 'id'=>'zina-'.$key.'-'.$i, 'def'=>'', 'size'=>$item['size']);
			if (isset($item['max'])) $field['max'] = $item['max'];
			$disabled = (isset($global[$key]['over']) && $global[$key]['over']);
			$output .= '<td class="row'.$color.'" valign="top">'.zina_content_form_helper($key.'s[]', $field, $item['value'], $disabled).
				'<br/><div id="zina-'.$key.'-'.$i.'-orig" class="small">'.$item['value'].'</div></td>';
		}

		$output .= '</tr>'."\n".
			'<tr><td class="small row'.$color.'">'.$row['hidden'].'</td><td colspan="3" class="small row'.$color.'" valign="top">'.zt('File: @file', array('@file'=>$row['file'])).'</td>'.
			'<td colspan="'.(sizeof($row['cols'])-4).'" class="small row'.$color.'" valign="top">'.zt('Write tag formats').': '.
			zina_content_tags_format($row['valid_tag_types'], $row['tags'], 'tag_formats['.$i.'][]', true).
			'</td></tr>';
		$color = ++$color % 2;
	}

	$output .= '</table>'.
		'<p style="text-align:center;"><input type="submit" name="write_tags" value="'.zt('Submit').'"/></p>';

	return ztheme('form', $form_attr, $output).$js2;
}

function zina_extras_tags_formats($format, $dataformat) {
	$types = array();

	if (in_array($format, array('mp3','mp2','mp1'))) {
		$types = array('id3v1', 'id3v2.3', 'ape');
	} elseif ($format == 'mpc') {
		$types = array('ape');
	} elseif ($format == 'ogg') {
		$types = ($dataformat == 'flac') ? array() : array('vorbiscomment');
	} elseif ($format == 'flac') {
		$types = array('metaflac');
	} elseif ($format == 'real') {
		$types = array('real');
	}

	return $types;
}

function zina_content_tags_format($ValidTagTypes, $tags, $name, $disable = false) {
	$output = '';
	$disabled = ($disable) ? ' disabled="disabled"' : '';
	$checked = ' checked="checked"';
	foreach ($ValidTagTypes as $type) {
		$output .= '<INPUT TYPE="CHECKBOX" NAME="'.$name.'" VALUE="'.$type.'"';
		if (count($ValidTagTypes) == 1) {
			$output .= $checked;
		} else {
			switch ($type) {
				case 'id3v1':
					$output .= $checked;
					break;
				case 'id3v2.3':
					$output .= $checked;
					break;
				default:
					if (isset($tags[$type])) $output .= $checked;
					break;
			}
		}
		$output .= $disabled.' />'.$type;
	}
	return $output;
}

/*
 * freedb.org
 * http://ftp.freedb.org/pub/freedb/latest/CDDBPROTO
 *
 */

function zina_extras_tags_freedb_matches($url) {
	global $zc;
	if (!(bool)ini_get('allow_url_fopen')) {
		return zt("PHP setting 'allow_url_fopen' must be enabled for CDDB look-up to work.");
	}
	$context = stream_context_create(array(
			'http' => array(
			'timeout' => 5,
		),
	));
	$codes = _zina_freedb_codes();

	if (($results = @file_get_contents($url, false, $context)) !== false) {
		$items = explode("\n", $results);
		$item = explode(" ", array_shift($items));
		$response = (int)substr($results,0,3);
		if (!isset($codes[$response])) return zt('Freedb.org query: unknown response');

		if ($response == 200 || $response == 210 || $response == 211) {
			$output = '';
			$js =
'function zinaFreedb(cat, discid){'.
	'var url = "'.zurl('','l=73&rand='.time()).'&cat="+cat+"&discid="+discid;'.
	'var $j = jQuery;'.
	'$j("#zina_messages").html("'.zt('Loading...').'");'.
	'$j("#zina_messages").addClass("messages status");'.
	'$j.getJSON(url,function(data,status){'.
		'if(status=="error"){'.
			'$j("#zina_messages").html(\'Error: \'+msg+status+data);}'.
		'else{'.
		'if (data.error) {'.
			'$j("#zina_messages").html(data.error);'.
			'$j("#zina_messages").addClass("messages warn");'.
		'}else{'.
			'$j("#zina_messages").html("'.zt('Tags Found').'");'.
			'$j("#zina_messages").addClass("messages status");'.

			'var items = new Array("artist", "album", "genre", "year");'.
			'for(i=0;i<items.length;i++){'.
				'var col = items[i];'.
				'var item = $j("input#zina-"+col);'.
				'var orig = $j("div#zina-"+col+"-orig");'.

				'if (item.val() != orig.html()) {'.
					'item.val(orig.html());'.
					'item.css("background-color","");'.
				'}'.

				'if (data[col] != item.val()) {'.
					'item.val(data[col]);'.
					'item.css("background-color","#FFF380");'.
				'}'.
			'}'.

			'var cols = new Array("track", "title", "artist", "album", "genre", "year", "comment");'.
			'for(i=0;i<data.tracks.length;i++){'.
				'for(j=0;j<cols.length;j++){'.
					'var col = cols[j];'.
					'var item = $j("input#zina-"+col+"-"+i);'.
					'var orig = $j("div#zina-"+col+"-"+i+"-orig");'.

					'if (item.val() != orig.html()) {'.
						'item.val(orig.html());'.
						'item.css("background-color","");'.
					'}'.
					'if (data.tracks[i][col] && data.tracks[i][col] != item.val()) {'.
						'item.val(data.tracks[i][col]);'.
						'var color = (item.attr("disabled")) ? "#C9BE62" : "#FFF380";'.
						'item.css("background-color",color);'.
					'}'.
				'}'.
			'}'.

		'}'.
		'}'.
	'});'.
	'}';

			$output .= '<script type="text/javascript">'.$js.'</script>';

			if ($response == 200) { #exact
				$cat = $item[1];
				$discid = $item[2];
				array_shift($item);
				array_shift($item);
				array_shift($item);
				$title = implode('', $item);

				$output .= '<h3>'.zt('Exact Match').'</h3><ul id="zina-found" class="zina-list">';
				$output .= '<li class="zina-list"><a href="javascript: void 0;" onclick="zinaFreedb(\''.$cat.'\',\''.$discid.'\');">'.$title.' ('.$discid.') ('.$cat.')</a></li>';
				$output .= '</ul>';
			} else {
				$output .= '<h3>'.zt('Matches').'</h3><ul id="zina-found" class="zina-list">';
				foreach($items as $i) {
					if (trim($i) == '.') break;
					$item = explode(" ", $i);
					$cat = trim($item[0]);
					$discid = trim($item[1]);
					$title = trim(substr($i, strlen($cat)+strlen($discid)+2));
					$output .= '<li class="zina-list"><a href="javascript: void 0;" onclick="zinaFreedb(\''.$cat.'\',\''.$discid.'\');">'.$title.' ('.$discid.') ('.$cat.')</a></li>';
				}
				$output .= '</ul>';
			}

			return $output;
		} elseif ($response == 202) {
			return zt('No matches found');
		} else {
			return zt('Freedb error: @code', array('@code'=>$codes[$response]));
		}
	}
	return zt('Cannot connect to: @server', array('@server'=>$zc['tags_cddb_server']));
}

function zina_extras_tags_freedb_match($cat, $discid) {
	global $zc;
	$context = stream_context_create(array(
			'http' => array(
			'timeout' => 5,
		),
	));

	$codes = _zina_freedb_codes();

	$url = "http://".$zc['tags_cddb_server']."/~cddb/cddb.cgi?cmd=cddb+read+$cat+$discid&hello=anonymous+unknown+zina+".ZINA_VERSION."&proto=6";

	if (($results = file_get_contents($url, false, $context)) !== false) {
		$response = (int)substr($results,0,3);

		if (!isset($codes[$response])) {
			$output = array('error'=>zt('Freedb.org entry: unknown response'));
		}
		if ($response == 210) {
			$output = _zina_freedb_parseRecord($results);
		} else {
			$output = array('error'=>zt('Freedb record error: @code', array('@code'=>$response)));
		}
	} else {
		$output = array('error'=>zt('Freedb: record error'));
	}
	return json_encode($output);
}

function _zina_freedb_codes() {
	return array(
		200 => zt('Found exact match'),
		202 => zt('No match found'),
		210 => zt('OK, CDDB database entry follows (until terminating marker)'),
		211 => zt('Found inexact matches, list follows (until terminating marker)'),
		401 => zt('Specified CDDB entry not found'),
		402 => zt('Server error'),
		403 => zt('Database entry is corrupt'),
		409 => zt('No handshake'),
		500 => zt('Command syntax error: incorrect arg count forhandshake'),
	);
}
/*
 * Keith Palmer <Keith@UglySlug.com>
 *
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */
function _zina_freedb_parseRecord($str, $category = '') {
	$record = array(
		'discid' => '',
		'album' => '',
		'year' => '',
		'genre' => '',
		'category' => trim($category), // Not ever parsed out of a disc record, just here because we need to store it
		'extd' => '',
		'playorder' => '',
		'tracks' => array(),
		'dlength' => 0,
		'revision' => 0,
		'submitted_via' => '',
		'processed_by' => '',
		);
	// Some records seem to use \r and some use \n... convert all to one or the other
	$str = str_replace("\r", "\n", $str);
	$str = str_replace("\r\n", "\n", $str);
	$str = str_replace("\n\n", "\n", $str);

	$lines = explode("\n", $str);

	foreach ($lines as $line_num => $line) {
		if (count($explode = explode('=', $line)) == 2) { // key=value type line
			 $key = trim(strtolower(current($explode)));
			 $value = trim(end($explode));
			 if (substr($key, 0, 6) == 'ttitle') {
					$track_num = (int) substr($key, 6);
					if (!isset($record['tracks'][$track_num])) {
						$record['tracks'][$track_num] = array(
							 'title' => '',
							 'extt' => '',
							 'offset' => '',
							 'length' => 0,
							 );
					}
					$record['tracks'][$track_num]['title'] .= trim($value);
			 } else if (substr($key, 0, 4) == 'extt') {
				$track_num = (int) substr($key, 6);
				if (!isset($record['tracks'][$track_num])) {
					$record['tracks'][$track_num] = array(
						 'title' => '',
						 'extt' => '',
						 'offset' => '',
						 'length' => 0,
						 );
				}
				$record['tracks'][$track_num]['extt'] .= trim($value);
			 } elseif (substr($key, 0, 6) == 'dtitle') {
					$record['album'] .= trim($value);
			 } elseif (substr($key, 0, 7) == 'dartist') {
					$record['artist'] .= trim($value);
			 } elseif (substr($key, 0, 5) == 'dyear') {
					$record['year'] .= (int)trim($value);
			 } elseif (substr($key, 0, 6) == 'dgenre') {
					$record['genre'] .= trim($value);
			 } else {
					$record[$key] .= trim($value);
			 }
		} else { // Other data line
			 if (false !== strpos($line, 'frame offsets')) {
					$track_num = 0;
					$line_num++;
					while ((int) trim(substr($lines[$line_num], 1))) {
						if (!isset($record['tracks'][$track_num])) {
							 $record['tracks'][$track_num] = array(
									'title' => '',
									'extt' => '',
									'offset' => '',
									'length' => 0,
									);
						}
						$record['tracks'][$track_num]['offset'] = (int) trim(substr($lines[$line_num], 1));
						$track_num++;
						$line_num++;
					}
			 } else if (false !== ($pos = strpos($line, 'Disc length:'))) {
					$record['dlength'] = (int) substr($line, $pos + 12);
			 } else if (false !== ($pos = strpos($line, 'Revision:'))) {
					$record['revision'] = substr($line, $pos + 9);
			 } else if (false !== ($pos = strpos($line, 'Submitted via:'))) {
					$record['submitted_via'] = substr($line, $pos + 14);
			 } else if (false !== ($pos = strpos($line, 'Processed by:'))) {
					$record['processed_by'] = substr($line, $pos + 13);
			 }
		}
	}
	// Now, lets seperate artists from titles
	if (count($explode = explode(' / ', $record['album'])) == 2) {
		$record['artist'] = current($explode);
		$record['album'] = end($explode);
	} else {
		$record['artist'] = $record['album'];
	}
	foreach ($record['tracks'] as $key => $track) {
		if (count($explode = explode(' / ', $track['title'])) == 2) {
			 $record['tracks'][$key]['artist'] = current($explode);
			 $record['tracks'][$key]['title'] = end($explode);
		} else {
			 $record['tracks'][$key]['artist'] = $record['artist'];
		}
		$record['tracks'][$key]['album'] = $record['album'];
		$record['tracks'][$key]['genre'] = $record['genre'];
		$record['tracks'][$key]['year'] = $record['year'];
		$record['tracks'][$key]['track'] = $key+1;
	}

	// Calculate the lengths for each of the disc's tracks
	$count = count($record['tracks']);
	if ($count) {
		$start = $record['tracks'][0]['offset']; // Initial disc offset

		for ($i = 1; $i < $count; $i++) {
			 $end = $record['tracks'][$i]['offset'];
			 $record['tracks'][$i - 1]['length'] = round(($end - $start) / 75); // Set track offsets (seconds get rounded)
			 $start = $record['tracks'][$i]['offset'];
		}

		// Set the final track length
		$record['tracks'][$count - 1]['length'] = $record['dlength'] - round($start / 75);
	}

	return $record;
}

function FixTextFields($text) {
	return htmlentities(utf8_decode(getid3_lib::SafeStripSlashes($text)), ENT_QUOTES);
}

?>
