<?php
/*
 * lyrics.wikia.com
 */
#$lyrics = zina_extras_lyr_lyricwiki('New Order', 'Love Vigilantes');
#$lyrics = zina_extras_lyr_lyricwiki('Kinks, The', 'Fancy');
#$lyrics = zina_extras_lyr_lyricwiki('Modern Lovers', 'Dance with Me');

#function zxml_encode($x) { return utf8_encode($x); }
#function zt($x, $y) {return $x.$y;}
#print_r($lyrics);

function zina_extras_lyr_lyricwiki($artist, $song) {
	$url = parse_url('http://lyrics.wikia.com/server.php');
	
	$soap = '<?xml version="1.0" encoding="UTF-8" standalone="no"?><SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:si="http://soapinterop.org/xsd" xmlns:tns="urn:LyricWiki" xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/" xmlns:wsdl="http://schemas.xmlsoap.org/wsdl/" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"><SOAP-ENV:Body><mns:getSongResult xmlns:mns="urn:LyricWiki" SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"><artist xsi:type="xsd:string">'.
		zxml_encode($artist).'</artist><song xsi:type="xsd:string">'.zxml_encode($song).'</song></mns:getSongResult></SOAP-ENV:Body></SOAP-ENV:Envelope>';
	$socket_timeout = 2;
	$as_socket = @fsockopen($url['host'], 80, $errno, $errstr, $socket_timeout);

	if(!$as_socket) {
		zina_debug('cannot open socket (lyricwiki.org): '.$errstr);
		return false;
	}

	$action = "POST /".$url['path']." HTTP/1.1\r\n";
	fwrite($as_socket, $action);
	fwrite($as_socket, "Host: ".$url['host']."\r\n");
	fwrite($as_socket, "Content-type: application/soap+xml; charset=\"utf-8\"\r\n");
	fwrite($as_socket, "Content-length: ".strlen($soap)."\r\n\r\n");
	fwrite($as_socket, $soap."\r\n\r\n");
	
	$buffer = '';
	while(!feof($as_socket)) {
		$buffer .= fread($as_socket, 8192);
	}
	fclose($as_socket);
	
	$context = stream_context_create(array(
		'http' => array(
			'timeout' => 5,
			'user_agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:x.x.x) Gecko/20041107 Firefox/3.2',
		),
	));

	if (preg_match('/<lyrics.*?>(.*?)<\/lyrics/is', $buffer, $matches) && $matches[1] != 'Not found') {
		if (preg_match('/<url.*?>(.*?)<\/url/is', $buffer, $matches)) {
			if ($page = file_get_contents($matches[1], false, $context)) {
				if (preg_match('/<div class=[\'"]lyricbox.*?>(.*?)<!--/is', $page, $lyrics)) {
					$source = '<a href="http://lyricwiki.org" class="zina_lyr_source">lyricwiki.org</a>';
					$lyric = preg_replace('/^.*?<\/div>/i','', $lyrics[1]);
					$lyric = html_entity_decode(strip_tags($lyric,'<br>'));
					if (strstr($lyric, 'not licensed to display')) return false;
					$result['output'] = str_replace(array('<br />','<br>', '<br/>'), "\n", $lyric);
					$result['source'] = zt('Lyrics provided by !url', array('!url'=>$source));
					return $result;
				}
			}
		}
	}
	return false;
}
?>
