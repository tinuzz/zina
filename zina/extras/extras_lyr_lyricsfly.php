<?php
/*
 * lyricsfly.com
 *
 * You need to keep the link to lyricsfly.com if you use this.
 */
function zina_extras_lyr_lyricsfly($artist, $title) {
	$fly_id = 'b57a2b68919f11738-temporary.API.access';
	if ($xml = file_get_contents('http://lyricsfly.com/api/api.php?i='.$fly_id.'&a='.rawurlencode($artist).'&t='.rawurlencode($title))) {
		if (preg_match('#<tx>(.*?)</tx>#is', $xml, $matches)) {
			$result['output'] = str_replace('[br]','</br>', $matches[1]);
			$source = '<a href="http://www.lyricsfly.com" class="zina_lyr_source">lyricsfly.com™</a>';
			$result['source'] = zt('Lyrics provided by !url', array('!url'=>$source));
			return $result;
		}
	} 
	return false;
}
?>
