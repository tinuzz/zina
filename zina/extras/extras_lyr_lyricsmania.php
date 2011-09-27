<?php
/*
 * lyricsmania.com
 */
#$lyrics = zina_extras_lyr_lyricsmania('New Order', 'Love Vigilantes');
#$lyrics = zina_extras_lyr_lyricsmania('Kinks, The', 'Fancy');
#$lyrics = zina_extras_lyr_lyricsmania('Modern Lovers', 'Dance with Me');
#function zt($x, $y) {return $x.$y;}
#function zina_debug($x){print_r($x);}
#print_r($lyrics);

function zina_extras_lyr_lyricsmania($artist, $title) {
	$context = stream_context_create(array(
		'http' => array(
			'timeout' => 10,
			'user_agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:x.x.x) Gecko/20041107 Firefox/3.2',
		),
	));

	if (strpos($artist, ',')) {
		$arr = explode(',', $artist);
		$artist = trim(implode(' ', array_reverse($arr)));
	}
	$host = 'http://www.lyricsmania.com';
	$url = $host.'/search.php?c=title&k='.urlencode($title);

	if ($html = file_get_contents($url, false, $context)) {
		if (preg_match('#<div id=albums>(.*?)</div>#is', $html, $matches)) {
			$items = explode('<h1>', $matches[1]);
			$exact = $found = false;
			$partial = array();
			
			foreach ($items as $item) {
				if (preg_match('/href="(.*?)".*?>(.*?) - (.*?)<\//si', $item, $parts)) {
					if (strcasecmp($parts[3], $artist) == 0 && strcasecmp($parts[2], $title) == 0) {
						$exact = $parts[1];
						break;
					} else if (stristr((string)$parts[3], $artist) && stristr((string)$parts[2], $title)) {
						$partial[] = $parts;
					}
				}
			}
			if ($exact || !empty($partial)) {
				if ($exact) {
					$lyric_url = $exact;
				} else {
					$lyric_url = $partial[0][1];
				}
				
				if ($lyrics_page = file_get_contents($host.$lyric_url, false, $context)) {
					if (preg_match('/\/strong> :<br \/>(.*?)<br \/>&#91/si', $lyrics_page, $lyrics)) {
						$found = true;
					}
				}
			}

			if ($found) {
				$result['output'] = strip_tags(trim($lyrics[1]));
				#str_replace(array('<br />','<br>', '<br/>'), "\n", $lyric);
				$source = '<a href="http://lyricsmania.com" class="zina_lyr_source">lyricsmania.com</a>';
				$result['source'] = zt('Lyrics provided by !url', array('!url'=>$source));
				return $result;
			}
		}
	} 
	return false;
}
?>
