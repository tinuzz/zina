<?php
/*
 * api.leoslyrics.php
 * http://www.leoslyrics.com/posts/list/15869.page
 */

#$lyrics = zina_extras_lyr_leoslyrics('New Order', 'Love Vigilantes');
#print_r($lyrics);
#function zina_debug($x){print_r($x);}

function zina_extras_lyr_leoslyrics($artist, $song) {
	/*
	$url = parse_url('http://api.leoslyrics.com/api_search.php?auth=duane&artist='.rawurlencode($artist).'&songtitle='.rawurlencode($song));
	print_r($url);

	$socket_timeout = 2;
	$as_socket = @fsockopen($url['host'], 80, $errno, $errstr, $socket_timeout);

	if (!$as_socket) {
		echo "NO SOCKET!!!<br/>\n";
		zina_debug('cannot open socket ('.$url['host'].'): '.$errstr);
		return false;
	}

	$action = "GET ".$url['path'];
	if (isset($url['query'])) $action .= '?'.$url['query'];
	
	fwrite($as_socket, $action." HTTP/1.1\r\n");
	fwrite($as_socket, "Host: ".$url['host']."\r\n");
	 */
	#fwrite($as_socket, "Accept: */*\r\n\r\n");
/*
	$buffer = '';
	while(!feof($as_socket)) {
		$buffer .= fread($as_socket, 4096);
	}
	fclose($as_socket);

	return false;
	 */
	if (($xml = @simplexml_load_file('http://api.leoslyrics.com/api_search.php?auth=duane&artist='.rawurlencode($artist).'&songtitle='.rawurlencode($song))) !== FALSE) {
		if (isset($xml->response) && $xml->response == 'SUCCESS') {
			$attr = $xml->searchResults->result->attributes();
			if ($attr['exactMatch'] == 'true') {
				if ($xml2 = @simplexml_load_file('http://api.leoslyrics.com/api_lyrics.php?auth=duane&hid='.$attr['hid'])) {
					if (isset($xml2->response) && $xml2->response == 'SUCCESS') {
						$source = '<a href="http://leoslyrics.com" class="zina_lyr_source">leoslyrics.com</a>';
						$result['output'] = strip_tags((string)$xml2->lyric->text, '<br><p>');
						$result['source'] = zt('Lyrics provided by !url', array('!url'=>$source));
						return $result;
					}
				}
			}
		}
	}
	return false;
}
?>
