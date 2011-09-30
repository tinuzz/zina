<?php
/*
 * api.chartlyrics.php
 * http://www.chartlyrics.com/app/api.aspx
 */

#$lyrics = zina_extras_lyr_chartlyrics('3rd Bass', 'The Cactus');
#$lyrics = zina_extras_lyr_chartlyrics('New Order', 'Love Vigilantes');
#$lyrics = zina_extras_lyr_chartlyrics('Kinks, The', 'Fancy');
#$lyrics = zina_extras_lyr_chartlyrics('Modern Lovers', 'Dance with Me');
#function zt($x, $y) {return $x.$y;}
#function zina_debug($x){print_r($x);}
#print_r($lyrics);

function zina_socket_get($url_in, $timeout = 2) {
	$url = parse_url($url_in);
	$socket_timeout = $timeout;

	$as_socket = @fsockopen($url['host'], 80, $errno, $errstr, $socket_timeout);

	if (!$as_socket) {
		zina_debug('chartlyrics: cannot open socket ('.$url['host'].'): '.$errstr);
		return false;
	}

	$action = "GET ".$url['path'];
	if (isset($url['query'])) $action .= '?'.$url['query'];

	fwrite($as_socket, $action." HTTP/1.1\r\n");
	fwrite($as_socket, "Host: ".$url['host']."\r\n");
	fwrite($as_socket, "Connection: Close\r\n\r\n");

	stream_set_blocking($as_socket, FALSE);
	stream_set_timeout($as_socket,$timeout);
	$info = stream_get_meta_data($as_socket);

	$buffer = '';
	while ((!feof($as_socket)) && (!$info['timed_out'])) {
		$buffer .= fread($as_socket, 4096);
		$info = stream_get_meta_data($as_socket);
	}
	fclose($as_socket);

	if ($info['timed_out']) {
		zina_debug('chartlyrics: connection timed out ('.$url['host'].'): '.$errstr);
		return false;
	}
	return $buffer;
}

function zina_extras_lyr_chartlyrics($artist, $song) {
	if (strpos($artist, ',')) {
		$arr = explode(',', $artist);
		$artist = trim(implode(' ', array_reverse($arr)));
	}
	$buffer = zina_socket_get('http://api.chartlyrics.com/apiv1.asmx/SearchLyric?artist='.rawurlencode($artist).'&song='.urlencode($song));
	if (!$buffer) return false;

	$response = preg_split("/\r\n\r\n/", $buffer, 2);

	$code = substr($response[0],0, 15);
	if ($code != 'HTTP/1.1 200 OK' || !isset($response[1])) {
		zina_debug('chartlyric: invalid response ('.$code.')');
		return false;
	}

	if ($xml = simplexml_load_string($response[1])) {
		if (isset($xml->SearchLyricResult->LyricId)) {
			foreach($xml->SearchLyricResult as $res) {
				if (stristr($res->Artist, $artist) && stristr($res->Song, $song)) {
					$buff2 = zina_socket_get('http://api.chartlyrics.com/apiv1.asmx/GetLyric?lyricId='.$res->LyricId.'&lyricCheckSum='.$res->LyricChecksum);
					$resp2 = preg_split("/\r\n\r\n/", $buff2, 2);

					# todo: weak
					if (substr($resp2[0],0, 15) != 'HTTP/1.1 200 OK' || !isset($resp2[1])) {
						zina_debug('chartlyrics: invalid response (2nd connect)');
						return false;
					}

					if (preg_match('/<Lyric>(.*?)<\/Lyric>/si', $resp2[1], $matches)) {
						zdbg($matches[1]);
						$source = '<a href="http://www.chartlyrics.com.org" class="zina_lyr_source">chartlyrics.com</a>';
						$output = strip_tags(trim($matches[1]),'<br>');
						if (strstr($output, "We haven't lyrics")) return false;
						if (strstr($output, 'add these lyrics for other users')) return false;
						$result['output'] = $output;
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
