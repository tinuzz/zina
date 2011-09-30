<?php
function zina_extras_images_lastfm($artist, $album = null, $num = 1, $size = 'extralarge') {
	if (empty($artist)) return false;
	
	$url = parse_url('http://ws.audioscrobbler.com/2.0');
	$method = (empty($album)) ? 'artist' : 'album';
	$query = 'method='.$method.'.getinfo&api_key=43bab901b290bc43b62d0c05e629ef08&artist='.urlencode($artist);
	if (!empty($album)) {
		$query .= '&album='.urlencode($album);
	}

	$socket_timeout = 2;
	$as_socket = @fsockopen($url['host'], 80, $errno, $errstr, $socket_timeout);

	if(!$as_socket) {
		zina_debug('images-lastfm: cannot open socket: '.$errstr);
		return false;
	}

	$action = "POST ".$url['path']." HTTP/1.0\r\n";
	fwrite($as_socket, $action);
	fwrite($as_socket, "Host: ".$url['host']."\r\n");
	fwrite($as_socket, "Content-type: application/x-www-form-urlencoded\r\n");
	fwrite($as_socket, "Content-length: ".strlen($query)."\r\n\r\n");
	fwrite($as_socket, $query."\r\n\r\n");
	
	$buffer = '';
	while(!feof($as_socket)) {
		$buffer .= fread($as_socket, 8192);
	}
	fclose($as_socket);
	
	if (preg_match('/<url>(.*?)<\/url/is', $buffer, $matches) && !empty($matches[1])) {
		$source = $matches[1];
	} else {
		$source = 'http://last.fm';
	}
	if (preg_match('/<summary>(.*?)<\/summary/is', $buffer, $matches) && !empty($matches[1])) {
		$summary = substr($matches[1], 9, strlen($matches[1])-12);
	} else {
		$summary = '';
	}
	
	$result = false;	
	if (preg_match('/<image size="extralarge">(.*?)<\/image/is', $buffer, $matches) && !empty($matches[1])) {
		$result[] = array(
			'image_url' => $matches[1],
			'source_url' => $source,
			'summary' => $summary,
		);
	} elseif (preg_match('/<image size="large">(.*?)<\/image/is', $buffer, $matches) && !empty($matches[1])) {
		$result[] = array(
			'image_url' => $matches[1],
			'source_url' => $source,
			'summary' => $summary,
		);
	}

	return $result;
}
?>
