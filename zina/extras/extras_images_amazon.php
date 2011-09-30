<?php
function zina_extras_images_amazon($artist, $album = null, $num = 2, $size = 'LargeImage') {
	global $zc;
	if (empty($artist) || empty($album) || empty($zc['third_amazon_private']) || empty($zc['third_amazon_public'])) return false;

	$host = 'ecs.amazonaws.'.((empty($zc['third_amazon_region'])) ? 'com' : $zc['third_amazon_region']);
	$uri = '/onca/xml';

	$query = 
		'AWSAccessKeyId='.rawurlencode($zc['third_amazon_public']).'&'.
		'Artist='.rawurlencode($artist).'&'.
		'Operation=ItemSearch&'.
		'ResponseGroup=Images&'.
		'SearchIndex=Music&'.
		'Service=AWSECommerceService&'.
		'Timestamp='.rawurlencode(gmdate("Y-m-d\TH:i:s\Z")).'&'.
		((!empty($album)) ? 'Title='.rawurlencode($album).'&' : '').
		'Version=2009-03-31';

	$string_to_sign = "GET\n$host\n$uri\n$query";
	$signature = str_replace("%7E", "~", rawurlencode(base64_encode(hash_hmac("sha256", $string_to_sign, $zc['third_amazon_private'], true))));
    
	if ($xml = file_get_contents("http://$host$uri?$query&Signature=".$signature)) {
/*
 	#WON'T WORK AFTER Aug 09...
	if ($xml = file_get_contents('http://webservices.amazon.com/onca/xml?Service=AWSECommerceService&Operation=ItemSearch'.
		'&AWSAccessKeyId=0F8G6H70GCY1ECK6X6G2'.
		'&SearchIndex=Music&ResponseGroup=Images'.
		'&Artist='.rawurlencode($artist).((!empty($album)) ? '&Title='.rawurlencode($album) : ''))) {
 */
		if (preg_match('/<IsValid>(.*?)</i', $xml, $matches) && $matches[1] == 'True') {
			$source = null;
			if (preg_match('/<ASIN>(.*?)</i', $xml, $matches)) {
				#$result['asin'] = $matches[1];
				$source = 'http://www.amazon.com/gp/product/'.$matches[1];
			}
			$array = xml_to_array($xml, $size);

			if (!empty($array)) {
				for($i=0; $i<$num && isset($array[$i]); $i++) {
					#if ($i > 0 && ($array[$i]['URL'] == $array[0]['URL'] || $array[$i]['Width'] < $array[0]['Width'])) {
					if ($i > 0 && $array[$i]['URL'] == $array[0]['URL']) {
						$num++;
					} else {
						$result[] = array(
							'image_url' => $array[$i]['URL'],
							'source_url' => $source,
							'size' => $array[$i]['Width'].' x '.$array[$i]['Height'],
						);
					}
				}
				return $result;
			}
		}
	} else {
		zina_debug(zt('Amazon error: @err', array('@err'=>$xml)));
	}

	return false;
}
?>
