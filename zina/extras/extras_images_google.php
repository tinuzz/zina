<?php
if (!function_exists('str_getcsv')) {
    function str_getcsv($input, $delimiter = ",", $enclosure = '"', $escape = "\\") {
        $MBs = 1 * 1024 * 1024;
        $fp = fopen("php://temp/maxmemory:$MBs", 'r+');
        fputs($fp, $input);
        rewind($fp);

        $data = fgetcsv($fp, 1000, $delimiter, $enclosure); //  $escape only got added in 5.3.0

        fclose($fp);
        return $data;
    }
}

function zina_extras_images_google($artist, $album = null, $num = 3, $size = 'huge') {
	if (empty($artist)) return false;

	$url = 'http://images.google.com/images?q='.urlencode($artist).((!empty($album)) ? '+'.urlencode($album) : '').
		'&gbv=2&hl=en&safe=on&sa=G';

	#huge, xxlarge, medium|large|xlarge
	if (empty($album)) { # ARTIST
		#$url .= '&imgsz=huge|xxlarge';
		#$url .= '&imgtype=face';
	} else {
		#$url .= '&imgsz=large|xlarge';
	}

	$result = false;
	if ($html = file_get_contents($url)) {
		$split = preg_split('/"\/imgres/is', $html);
		if (!empty($split) && sizeof($split) >= $num) {
			array_shift($split);
			for($i=0; $i < $num; $i++) {
				$parts = str_getcsv($split[$i]);
				if (is_array($parts)) {
				$result[] = array(
					'image_url' => $parts[3],
					'source_url' => $url,
					'size' => $parts[9],
					'thumbnail_url' => $parts[14].'?q=tbn:'.$parts[2].$parts[3],
				);
				}
			}
		}
	}

	return $result;
}
?>
