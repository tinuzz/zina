<?php
/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * ZINA (Zina is not Andromeda)
 *
 * Zina is a graphical interface to your MP3 collection, a personal
 * jukebox, an MP3 streamer. It can run on its own, embeded into an
 * existing website, or as a Drupal/Joomla/Wordpress/etc. module.
 *
 * http://www.pancake.org/zina
 * Author: Ryan Lathouwers <ryanlath@pacbell.net>
 * Support: http://sourceforge.net/projects/zina/
 * License: GNU GPL2 <http://www.gnu.org/copyleft/gpl.html>
 *
 * MP3/OGG/WMA/M4A file info and tags
 *
 * hacked pretty heavily from...
 * MP3::Info by Chris Nandor <http://sf.net/projects/mp3-info/>
 * class.id3.php by Sandy McArthur, Jr. <http://Leknor.com/code/>
 * getID3() [ogg stuff] by James Heinrich <http://www.silisoftware.com>
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
#TODO: propbably should be renamed
#todo: make opts array instead of list of opts...
class mp3 {
	function mp3($file, $info=false, $tag=false, $faster=true, $genre = false, $image = false) {
		$this->file = $file;
		$this->tag = 0;
		$this->info = 0;
		$this->faster = $faster;
		$this->get_image = $image;
		#TODO: get fh once???

		if (stristr(substr($file,-3),'mp3')) {
 			if ($info) $this->getMP3Info();
			if ($tag) $this->getID3Tag($genre);
		} elseif (stristr(substr($file,-3),'ogg')) {
			if ($info || $tag) $this->getOgg($info, $tag);
		} elseif (strtolower(substr($file,-3)) == 'm4a' || strtolower(substr($file,-3)) == 'mp4') {
			$this->getM4A();
		} elseif (strtolower(substr($file,-3)) == 'wma') {
			$this->getWMA();
		} else {
			$this->getBadInfo();
		}
	}
	function getID3Tag($genre) {
		$this->tag = 0;
		$v2h = null;

		if (!($fh = @fopen($this->file, 'rb'))) { return 0; }

		$v2h = $this->getV2Header($fh);
		$this->track = 0;

		if (!empty($v2h) && !($v2h->major_ver < 2)) {
			$hlen = 10; $num = 4;

			if ($v2h->major_ver == 2) { $hlen = 6; $num = 3; }

			$off = 10; #TODO ext_header?
			$size = null;
			$map = array(
				'2'=>array('TT2'=>'title', 'TAL'=>'album', 'TP1'=>'artist', 'TYE'=>'year', 'TCO'=>'genre', 'TRK'=>'track', 'ULT'=>'lyrics'),
				'3'=>array('TIT2'=>'title', 'TALB'=>'album', 'TPE1'=>'artist', 'TYER'=>'year', 'TCON'=>'genre', 'TRCK'=>'track','USLT'=>'lyrics'),
				);
			if ($this->get_image) {
				$map[2]['PIC'] = 'image_raw';
				$map[3]['APIC'] = 'image_raw';
			}
			$fs = sizeof($map[2]);
			$this->title = $this->artist = null;

			while($off < $v2h->tag_size) {
				$arr = $id = null;
				$found = 0;
				fseek($fh, $off);
				$bytes = fread($fh, $hlen);
				if (preg_match("/^([A-Z0-9]{".$num."})/", $bytes, $arr)) {
					$id = $arr[0];
					$size = $hlen;
					$bytes = array_reverse(unpack("C$num",substr($bytes,$num,$num)));
					for ($i=0; $i<($num - 1); $i++) {
						$size += $bytes[$i] * pow(256,$i);
					}
				} else { break; }
				fseek($fh, $off + $hlen);
				if ($size > $hlen) {
					$bytes = fread($fh, $size - $hlen);
					if (isset($map[$v2h->major_ver][$id])) {
						if ($id == 'APIC' || $id == 'PIC') {
							$value = $bytes;
						} else {
							if (ord($bytes[0]) == 0) { # lang enc 0:ISO | 1,2,3:UTF variants
								$value = zcheck_utf8(trim($bytes), false);
							} else {
								$value = zcheck_utf8(str_replace("\0",'',substr($bytes, 3)), false);
							}
						}
						$this->$map[$v2h->major_ver][$id] = $value;
						
						$this->tag = 1;
						if (++$found == $fs) break;
					}
				}
				$off += $size;
			}

			if ($this->tag) $this->tag_version = "ID3v2.".$v2h->major_ver;

			if (isset($this->lyrics)) {
				#todo: lang encoding???
				$this->lyrics = substr($this->lyrics,4);
				if (empty($this->lyrics)) unset($this->lyrics);
			}

			if ($this->get_image && isset($this->image_raw)) {
				$frame_offset = 0;
				$frame_textencoding = ord(substr($this->image_raw, $frame_offset++, 1));

				if ($v2h->major_ver == 2) {
					$frame_mimetype = substr($this->image_raw, $frame_offset, 3);
					if (strtolower($frame_mimetype) == 'ima') { # ima hack (NOT tested)
						$frame_terminatorpos = @strpos($this->image_raw, "\x00", $frame_offset);
						$frame_mimetype = substr($this->image_raw, $frame_offset, $frame_terminatorpos - $frame_offset);
						$frame_offset = $frame_terminatorpos + strlen("\x00");
					} else {
						$frame_offset += 3;
					}
				} elseif ($v2h->major_ver > 2) {
					$frame_terminatorpos = @strpos($this->image_raw, "\x00", $frame_offset);
					$frame_mimetype = substr($this->image_raw, $frame_offset, $frame_terminatorpos - $frame_offset);
					if (ord($frame_mimetype) === 0) {
						$frame_mimetype = '';
					}
					$frame_offset = $frame_terminatorpos + strlen("\x00");
				}

				$terminators = array(0=>"\x00", 1=>"\x00\x00", 2=>"\x00\x00", 3=>"\x00", 255=>"\x00\x00");

				$terminator = @$terminators[$frame_textencoding];
				$frame_picturetype = ord(substr($this->image_raw, $frame_offset++, 1));
				$frame_terminatorpos = @strpos($this->image_raw, $terminator, $frame_offset);

				if (ord(substr($this->image_raw, $frame_terminatorpos + strlen($terminator), 1)) === 0) {
					$frame_terminatorpos++; // @strpos() fooled because 2nd byte of Unicode chars are often 0x00
				}
				$frame_description = substr($this->image_raw, $frame_offset, $frame_terminatorpos - $frame_offset);
				if (ord($frame_description) === 0) $frame_description = '';
				$image_data = substr($this->image_raw, $frame_terminatorpos + strlen($terminator));
				if (strlen($image_data) > 0 && @imagecreatefromstring($image_data)) {
					$bytes = strlen($image_data);

					$image_type = strtolower(str_replace('image/', '', strtolower($frame_mimetype)));
					if ($image_type == 'jpeg') $image_type = 'jpg';

					$this->image = array(
						'type' => $image_type,
						'data' => $image_data,
						'bytes' => $bytes,
						'kbytes' => round($bytes/1024,2),
					);
				}
				unset($this->image_raw);
			}
		}

		#if v2 not found look for v1
		if (!$this->tag) {
			if (fseek($fh, -128, SEEK_END) == -1) { return 0; }
			$tag = fread($fh, 128);

			if (substr($tag,0,3) == "TAG") {
				if ($tag[125] == Chr(0) and $tag[126] != Chr(0)) {
					$format = 'a3TAG/a30title/a30artist/a30album/a4year/a28comment/x1/C1track/C1genre';
					$this->tag_version = "ID3v1.1";
				} else {
					$format = 'a3TAG/a30title/a30artist/a30album/a4year/a30comment/C1genre';
					$this->tag_version = "ID3v1";
				}
				$id3tag = unpack($format, $tag);
				foreach ($id3tag as $key=>$value) {
					$this->$key = zcheck_utf8(trim($value), false);
				}
				unset($this->TAG);
				$this->tag = 1;
			}
		}
		fclose($fh);

		if ($genre) $this->getGenre();
		#$this->track = (int)$this->track;
		return $this->tag;
	}

	function getGenre() {
		$genres = array(
0=>'Blues',1=>'Classic Rock',2=>'Country',3=>'Dance',4=>'Disco',
5=>'Funk',6=>'Grunge',7=>'Hip-Hop',8=>'Jazz',9=>'Metal',10=>'New Age',
11=>'Oldies',12=>'Other',13=>'Pop',14=>'R&B',15=>'Rap',16=>'Reggae',
17=>'Rock',18=>'Techno',19=>'Industrial',20=>'Alternative',21=>'Ska',
22=>'Death Metal',23=>'Pranks',24=>'Soundtrack',25=>'Euro-Techno',
26=>'Ambient',27=>'Trip-Hop',28=>'Vocal',29=>'Jazz+Funk',30=>'Fusion',
31=>'Trance',32=>'Classical',33=>'Instrumental',34=>'Acid',35=>'House',
36=>'Game',37=>'Sound Clip',38=>'Gospel',39=>'Noise',40=>'Alternative Rock',
41=>'Bass',42=>'Soul',43=>'Punk',44=>'Space',45=>'Meditative',46=>'Instrumental Pop',
47=>'Instrumental Rock',48=>'Ethnic',49=>'Gothic',50=>'Darkwave',
51=>'Techno-Industrial',52=>'Electronic',53=>'Pop-Folk',54=>'Eurodance',
55=>'Dream',56=>'Southern Rock',57=>'Comedy',58=>'Cult',59=>'Gangsta',
60=>'Top 40',61=>'Christian Rap',62=>'Pop/Funk',63=>'Jungle',64=>'Native US',
65=>'Cabaret',66=>'New Wave',67=>'Psychadelic',68=>'Rave',69=>'Showtunes',
70=>'Trailer',71=>'Lo-Fi',72=>'Tribal',73=>'Acid Punk',74=>'Acid Jazz',
75=>'Polka',76=>'Retro',77=>'Musical',78=>'Rock & Roll',79=>'Hard Rock',
80=>'Folk',81=>'Folk-Rock',82=>'National Folk',83=>'Swing',84=>'Fast Fusion',
85=>'Bebob',86=>'Latin',87=>'Revival',88=>'Celtic',89=>'Bluegrass',90=>'Avantgarde',
91=>'Gothic Rock',92=>'Progressive Rock',93=>'Psychedelic Rock',94=>'Symphonic Rock',
95=>'Slow Rock',96=>'Big Band',97=>'Chorus',98=>'Easy Listening',99=>'Acoustic',
100=>'Humour',101=>'Speech',102=>'Chanson',103=>'Opera',104=>'Chamber Music',
105=>'Sonata',106=>'Symphony',107=>'Booty Bass',108=>'Primus',109=>'Porn Groove',
110=>'Satire',111=>'Slow Jam',112=>'Club',113=>'Tango',114=>'Samba',115=>'Folklore',
116=>'Ballad',117=>'Power Ballad',118=>'Rhytmic Soul',119=>'Freestyle',120=>'Duet',
121=>'Punk Rock',122=>'Drum Solo',123=>'Acapella',124=>'Euro-House',125=>'Dance Hall',
126=>'Goa',127=>'Drum & Bass',128=>'Club-House',129=>'Hardcore',130=>'Terror',
131=>'Indie',132=>'BritPop',133=>'Negerpunk',134=>'Polsk Punk',135=>'Beat',
136=>'Christian Gangsta Rap',137=>'Heavy Metal',138=>'Black Metal',139=>'Crossover',
140=>'Contemporary Christian',141=>'Christian Rock',142=>'Merengue',143=>'Salsa',
144=>'Trash Metal',145=>'Anime',146=>'Jpop',147=>'Synthpop',255=>'Unknown'
);

		if ($this->tag && !empty($this->genre)) {
			$this->genre = (preg_match("/\((.*?)\)/",$this->genre, $match)) ? $match[1] : ucfirst(trim($this->genre));
			if (is_numeric($this->genre)) {
				$this->genre = (isset($genres[$this->genre])) ? $genres[$this->genre] : 'Unknown';
			}
		} else {
			$this->genre = 'Unknown';
		}
	}

	function getMP3Info() {
		$file = $this->file;

		if (!($f = @fopen($file, 'rb'))) {
			error_log(zt('Zina: mp3.class: getMP3Info(): Cannot open file: @file', array('@file'=>$file)));
			return false;
		}

		$this->filesize = filesize($file);
		$frameoffset = 0;
		$total = 4096;

		if ($frameoffset == 0) {
			if ($v2h = $this->getV2Header($f)) {
				$total += $frameoffset += $v2h->tag_size;
				fseek($f, $frameoffset);
			} else {
				fseek($f, 0);
			}
		}

		if ($this->faster) {
			do {
				while (fread($f,1) != Chr(255)) { // Find the first frame
					if (feof($f)) { return false; }
				}
				fseek($f, ftell($f) - 1); // back up one byte
				$frameoffset = ftell($f);
				$r = fread($f, 4);

				$bits = decbin($this->unpackHeader($r));
				#64bit machines...
				if (PHP_INT_SIZE == 8) $bits = substr($bits,32);
				if ($frameoffset > $total) { return $this->getBadInfo(); }
			} while (!$this->isValidMP3Header($bits));
		} else { #more accurate with some VBRs
			$r = fread($f, 4);
			$bits = decbin($this->unpackHeader($r));
			if (PHP_INT_SIZE == 8) $bits = substr($bits,32);

			while (!$this->isValidMP3Header($bits)) {
				if ($frameoffset > $total) { return $this->getBadInfo(); }
				fseek($f, ++$frameoffset);
				$r = fread($f, 4);
				$bits = decbin($this->unpackHeader($r));
				if (PHP_INT_SIZE == 8) $bits = substr($bits,32);
			}
		}

		#$this->bits = $bits;
		$this->header_found = $frameoffset;
		$this->vbr = 0;
		$vbr = $this->getVBR($f, $bits[12], $bits[24] + $bits[25], $frameoffset);
		fclose($f);

		#TODO: vbr file size

		if ($bits[11] == 0) {
			$mpeg_ver = "2.5";
			$bitrates = array(
				'1'=>array(0, 32, 48, 56, 64, 80, 96, 112, 128, 144, 160, 176, 192, 224, 256, 0),
				'2'=>array(0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160, 0),
				'3'=>array(0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160, 0),
				);
		} else if ($bits[12] == 0) {
			$mpeg_ver = "2";
			$bitrates = array(
				'1'=>array(0, 32, 48, 56, 64, 80, 96, 112, 128, 144, 160, 176, 192, 224, 256, 0),
				'2'=>array(0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160, 0),
				'3'=>array(0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160, 0),
				);
		} else {
			$mpeg_ver = "1";
			$bitrates = array(
				'1'=>array(0, 32, 64, 96, 128, 160, 192, 224, 256, 288, 320, 352, 384, 416, 448, 0),
				'2'=>array(0, 32, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, 384, 0),
				'3'=>array(0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, 0),
				);
		}

		$layers = array(array(0,3), array(2,1),);
		$layer = $layers[$bits[13]][$bits[14]];
		if ($layer == 0) return $this->getBadInfo();

		$bitrate = 0;
		if ($bits[16] == 1) $bitrate += 8;
		if ($bits[17] == 1) $bitrate += 4;
		if ($bits[18] == 1) $bitrate += 2;
		if ($bits[19] == 1) $bitrate += 1;
		if ($bitrate == 0) return $this->getBadInfo();

		$this->bitrate = $bitrates[$layer][$bitrate];

		$frequency = array(
			'1'=>array(
				'0'=>array(44100, 48000),
				'1'=>array(32000, 0),
				),
			'2'=>array(
				'0'=>array(22050, 24000),
				'1'=>array(16000, 0),
				),
			'2.5'=>array(
				'0'=>array(11025, 12000),
				'1'=>array(8000, 0),
				),
			 );
		$this->frequency = $frequency[$mpeg_ver][$bits[20]][$bits[21]];
		$mfs = $this->frequency / ($bits[12] ? 144000 : 72000);
		if ($mfs == 0) return $this->getBadInfo();
		$frames = (int)($vbr && $vbr['frames'] ? $vbr['frames'] : $this->filesize / $this->bitrate / $mfs);

		if ($vbr) {
			$this->vbr = 1;
			if ($vbr['scale']) $this->vbr_scale = $vbr['scale'];
			$this->bitrate = (int)($this->filesize / $frames * $mfs);
			if (!$this->bitrate) return $this->getBadInfo();
		}

		$s = -1;
		if ($this->bitrate != 0) {
			$s = ((8*($this->filesize))/1000) / $this->bitrate;
		}
		$this->stereo = ($bits[24] == 0);
		$this->length = (int)$s;
		$this->time = sprintf('%.2d:%02d',floor($s/60),floor($s-(floor($s/60)*60)));
		$this->info = 1;
	}

	function getV2Header($fh) {
		fseek($fh, 0);
		$bytes = fread($fh, 3);

		if ($bytes != 'ID3') return false;

		#$bytes = fread($fh, 3);
		#get version
		$bytes = fread($fh, 2);
		$ver = unpack("C2",$bytes);

		$h = (object) array('major_ver' => $ver[1]);
		$h->minor_ver = $ver[2];

		#get flags
		$bytes = fread($fh, 1);

		#get ID3v2 tag length from bytes 7-10
		$tag_size = 10;
		$bytes = fread($fh, 4);
		$temp = array_reverse(unpack("C4", $bytes));
		for($i=0; $i<=3; $i++) {
			$tag_size += $temp[$i] * pow(128,$i);
		}
		$h->tag_size = $tag_size;
		return $h;
	}

	function getVBR($fh, $id, $mode, &$offset) {
		$offset += 4;

		if ($id) {
			$offset += $mode == 2 ? 17 : 32;
		} else {
			$offset += $mode == 2 ? 9 : 17;
		}

		$bytes = $this->Seek($fh, $offset);

		if ($bytes != "Xing") return 0;

		$bytes = $this->Seek($fh, $offset);

		$vbr['flags'] = $this->unpackHeader($bytes);

		if ($vbr['flags'] & 1) {
			$bytes = $this->Seek($fh, $offset);
			$vbr['frames'] = $this->unpackHeader($bytes);
		}

		if ($vbr['flags'] & 2) {
			$bytes = $this->Seek($fh, $offset);
			$vbr['bytes'] = $this->unpackHeader($bytes);
		}

		if ($vbr['flags'] & 4) {
			$bytes = $this->Seek($fh, $offset, 100);
		}

		if ($vbr['flags'] & 8) {
			$bytes = $this->Seek($fh, $offset);
			$vbr['scale'] = $this->unpackHeader($bytes);
		} else {
			$vbr['scale'] = -1;
		}

		return $vbr;
	}

	function isValidMP3Header($bits) {
		if (strlen($bits) != 32) return false;
		if (substr_count(substr($bits,0,11),'0') != 0) return false;
		if ($bits[16] + $bits[17] + $bits[18] + $bits[19] == 4) return false;
		return true;
	}

	function getOgg($info, $tag) {
		$fh = fopen($this->file, 'rb');

		// Page 1 - Stream Header
		$h = null;
		if (!$this->getOggHeader($fh, $h)) { return $this->getBadInfo(); }

		if ($info) {
			$this->filesize = filesize($this->file);

			$data = fread($fh, 23);
			$offset = 0;

			$this->frequency = implode('',unpack('V1', substr($data, 5, 4)));
			$bitrate_average = 0;

			if (substr($data, 9, 4) !== chr(0xFF).chr(0xFF).chr(0xFF).chr(0xFF)) {
				$bitrate_max = implode('',unpack('V1', substr($data, 9, 4)));
			}
			if (substr($data, 13, 4) !== chr(0xFF).chr(0xFF).chr(0xFF).chr(0xFF)) {
				$bitrate_nominal = implode('',unpack('V1', substr($data, 13, 4)));
			}
			if (substr($data, 17, 4) !== chr(0xFF).chr(0xFF).chr(0xFF).chr(0xFF)) {
				$bitrate_min = implode('',unpack('V1', substr($data, 17, 4)));
			}
		}

		if ($tag) {
			// Page 2 - Comment Header
			if (!$this->getOggHeader($fh, $h)) { return $this->getBadInfo(); }
			$data = fread($fh, 16384);
			$offset = 0;
			$vendorsize = implode('',unpack('V1', substr($data, $offset, 4)));
			$offset += (4 + $vendorsize);
			$totalcomments = implode('',unpack('V1', substr($data, $offset, 4)));
			$offset += 4;

			for ($i = 0; $i < $totalcomments; $i++) {
				$commentsize = implode('',unpack('V1', substr($data, $offset, 4)));
				$offset += 4;
				$commentstring = substr($data, $offset, $commentsize);
				$offset += $commentsize;
				$comment = explode('=', $commentstring, 2);
				$comment[0] = strtolower($comment[0]);
				$this->$comment[0] = $comment[1];
			}
			$this->tag_version = "ogg";
			$this->tag = 1;
		}

		if ($info) {
			// Last Page - Number of Samples
			fseek($fh, max($this->filesize - 16384, 0), SEEK_SET);
			$LastChunkOfOgg = strrev(fread($fh, 16384));
			if ($LastOggSpostion = strpos($LastChunkOfOgg, 'SggO')) {
				fseek($fh, 0 - ($LastOggSpostion + strlen('SggO')), SEEK_END);
				if (!$this->getOggHeader($fh, $h)) { return $this->getBadInfo(); }
				$samples = $h->pcm;
				$bitrate_average = ($this->filesize * 8) / ($samples / $this->frequency);
			}

			if ($bitrate_average > 0) {
				$this->bitrate = $bitrate_average;
			} else if (isset($bitrate_nominal) && ($bitrate_nominal > 0)) {
				$this->bitrate = $bitrate_nominal;
			} else if (isset($bitrate_min) && isset($bitrate_max)) {
				$this->bitrate = ($bitrate_min + $bitrate_max) / 2;
			}

			$this->bitrate = (int) ($this->bitrate / 1000);
			$s = -1;
			if (isset($this->bitrate)) {
				$s = (float) (($this->filesize * 8) / $this->bitrate / 1000);
			}
			$this->length = (int)$s;
			$this->time = sprintf('%.2d:%02d',floor($s/60),floor($s-(floor($s/60)*60)));
			$this->info = 1;
		}
		return true;
	}

	function getOggHeader(&$fh, &$h) {
		$baseoffset = ftell($fh);
		$data = fread($fh, 16384);
		$offset = 0;
		while ((substr($data, $offset++, 4) != 'OggS')) {
			if ($offset >= 10000) { return FALSE; }
		}

		$offset += 5;
		$h->pcm = implode('',unpack('V1', substr($data, $offset)));
		$offset += 20;
		$segments = implode('',unpack('C1', substr($data, $offset)));
		$offset += ($segments + 8);
		fseek($fh, $offset + $baseoffset, SEEK_SET);

		return true;
	}

	function parseAtom($fh, $offset, $end) {
		while ($offset < $end && $offset < $this->filesize) {
			fseek($fh, $offset);
			if (ftell($fh) + 8 > $end) return $atoms;
			$bytes = fread($fh, 4);
			$atom_size = $this->toUInt($bytes);
			$atom_type = fread($fh, 4);

			if ($atom_size == 1) {
				if (ftell($fh) + 8 > $end) return $atoms;
				$bytes = fread($fh, 8);
				$atom_size = implode('',unpack("N*", $bytes));
			}

			if ($atom_size < 0 || $offset + $atom_size > $end) {
				return $atoms;
			}

			if (in_array($atom_type, array('moov', 'udta','meta'))) {
				if ($atom_type == "meta") fseek($fh, 4, SEEK_CUR);
				$atoms[] = array(
					'type'=>$atom_type,
					'size'=>$atom_size,
					'children' => $this->parseAtom($fh, ftell($fh), $offset+$atom_size)
				);
			} else {
				if (in_array($atom_type, array('mvhd', 'ilst'))) {
					$atoms[] = array(
						'type'=>$atom_type,
						'size'=>$atom_size,
						'data'=> $this->atomSpecific($atom_type, $atom_size, $fh)
					);
				}
			}

			if ($atom_size == 0) {
				$offset = filesize($this->file);
			} else {
				$offset += $atom_size;
			}

			if ($atom_type == 'udta') $offset += 4;
		}

		return $atoms;
	}

	function getTime($s) {
		$secs = (int).5+$s;
		$mm = (int) $s/60;
		$ss = (int) $s - ($mm*60);
		$ms = (int).5 +(1000*($s - (int) $s));
		$this->length = (int) $s;
		$this->time = sprintf('%.2d:%02d',floor($s/60),floor($s-(floor($s/60)*60)));
		$this->bitrate = ceil(0.5 + $this->filesize / (($mm*60+$ss+$ms/1000)*128));
	}

	function atomSpecific($atom_type, $atom_size, $fh) {
		switch ($atom_type) {
			case 'mvhd' :
				fseek($fh, 12, SEEK_CUR);
				$bytes = fread($fh, 4);
				$this->frequency = implode('',unpack("N*", $bytes));
				$bytes = fread($fh, 4);
				$duration = implode('',unpack("N*", $bytes));
				$s =  $duration/$this->frequency;
				$this->getTime($s);
				/*
				$secs = (int).5+$s;
				$mm = (int) $s/60;
				$ss = (int) $s - ($mm*60);
				$ms = (int).5 +(1000*($s - (int) $s));
				$this->length = (int) $s;
				$this->time = sprintf('%.2d:%02d',floor($s/60),floor($s-(floor($s/60)*60)));
				$this->bitrate = ceil(0.5 + $this->filesize / (($mm*60+$ss+$ms/1000)*128));
				 */
				$this->info = true;
				break;

			case 'ilst' :
				$this->tag = true;

				$end = ftell($fh) + $atom_size;
				while(ftell($fh) + 8 < $end) {
					$tag_size = $this->toUInt(fread($fh, 4));
					$tag = fread($fh, 4);
					$next = ftell($fh) + $tag_size - 8;

					$data_size = $this->toUInt(fread($fh, 4));
					$data_type = fread($fh, 4);
					$c = chr(0xa9);
					if ($data_type == 'data' && in_array($tag, array($c.'nam', 'trkn', $c.'ART', $c.'alb', 'gnre', $c.'day'))) {
						$type = fread($fh, 8);
						$type = implode('',unpack("N", $type));

						$bytes = fread($fh, $data_size - 16);
						if ($type == 0) {
							$value = implode('',unpack("n*", $bytes));
						} else {
							$value = $bytes;
						}
						switch ($tag) {
							case $c.'nam' :
								$this->title = $value;
								break;
							case $c.'ART' :
								$this->artist = $value;
								break;
							case $c.'alb' :
								$this->album = $value;
								break;
							case $c.'day' :
								$this->year = $value;
								break;
							case 'gnre' :
								$this->genre = $value;
								$this->getGenre();
								break;
							case 'trkn' :
								if (strlen($value) % 2 == 0) {
									$value = substr($value,0, strlen($value)/2);
								}
								$this->track = $value;
								break;
						}
					} else {
						fseek($fh, $next, SEEK_SET);
					}
				}
				break;
			case 'mdat' :
				return 'mdat is data';
				break;
			default:
				#return fread($fh, $atom_size - 8);
				break;
		}
	}
		function parseWMA($fh) {
		fseek($fh, 0);
		$guid = $this->bin2guid(fread($fh,16));
		$size = implode('',unpack("VV", fread($fh, 8)));
		$number = implode('',unpack("V", fread($fh, 4)));
		fseek($fh, 2, SEEK_CUR);

		if ($guid != '75B22630-668E-11CF-A6D9-00AA0062CE6C') return $this->getBadInfo();

		for($i=0; $i < $number; $i++) {
			$guid = $this->bin2guid(fread($fh,16));
			$size = implode('',unpack("VV", fread($fh, 8)));
			$offset = ftell($fh)-24+$size;
			if ($guid == '8CABDCA1-A947-11CF-8EE4-00C00C205365') { //ASF_File_Properties_Objecy
				fseek($fh, 16+8+8+8, SEEK_CUR);
				$duration = implode('',unpack("VV", fread($fh, 8)));
				$duration = ($duration < 0) ? $duration += 4294967296 : $duration;
				fseek($fh, 8, SEEK_CUR);
				$pre_roll = implode('',unpack("VV", fread($fh, 8)));
				$s = $duration/10000000 - $pre_roll/1000;
				$this->getTime($s);
				fseek($fh, 4+4+4,SEEK_CUR);
				$max_bitrate = implode('',unpack("V", fread($fh, 4)));
				$this->bitrate = (int) ($max_bitrate/1000);

			} elseif ($guid == 'D2D0A440-E307-11D2-97F0-00A0C95EA850') { //ASF_Extended_Content_Description
				$count = implode('',unpack("v", fread($fh, 2)));
				$this->tag = true;
				for ($j = 0; $j < $count; $j++) {
					$name_length = implode('',unpack("v", fread($fh, 2)));
					$name = $this->denull(implode('',unpack("a*", fread($fh, $name_length))));
					$data_type = implode('',unpack("v", fread($fh, 2)));
					$data_length = implode('',unpack("v", fread($fh, 2)));
					$data = fread($fh, $data_length);

					if ($name == 'WM/Genre') {
						$this->genre = $this->getValueByType($data_type, $data);
						#TODO $this->getGenre();
					} elseif ($name == 'WM/AlbumTitle') {
						$this->album = $this->getValueByType($data_type, $data);
					} elseif ($name == 'WM/AlbumArtist') {
						$this->artist = $this->getValueByType($data_type, $data);
					} elseif ($name == 'WM/Year') {
						$this->year = $this->getValueByType($data_type, $data);
					} elseif ($name == 'WM/TrackNumber') {
						$this->track = $this->getValueByType($data_type, $data);
					}
				}

			} elseif ($guid == '75B22633-668E-11CF-A6D9-00AA0062CE6C' && !isset($this->title)) { //ASF_Content_Description
				$title_length = implode('',unpack("v", fread($fh, 2)));
				$artist_length = implode('',unpack("v", fread($fh, 2)));
				fseek($fh, 6, SEEK_CUR);
				if (!empty($title_length)) {
					$this->title = $this->denull(implode('',unpack("a*", fread($fh, $title_length))));
					$this->tag = true;
				}
				if (!empty($artist_length)) {
					$this->artist = $this->denull(implode('',unpack("a*", fread($fh, $artist_length))));
				}
			} elseif ($guid == 'B7DC0791-A9B7-11CF-8EE6-00C00C205365') { // ASF_Stream_Properties
				$stream_type = $this->bin2guid(fread($fh,16));
				fseek($fh, 16+8, SEEK_CUR);
				$stream_length = implode('',unpack("V", fread($fh, 4)));
				$error_length = implode('',unpack("V", fread($fh, 4)));
				fseek($fh, 2+4, SEEK_CUR);
				if ($stream_type == 'F8699E40-5B4D-11CF-A8FD-00805F5C442B') { // ASF_Audio_Media
					fseek($fh, 2, SEEK_CUR);
					$channels = implode('',unpack("v", fread($fh, 2)));
					$this->stereo = ($channels > 1);
					$this->frequency = implode('',unpack("V", fread($fh, 4)));
				}
			}
			fseek($fh, $offset);
		}
	}

	function denull($str) {
		return str_replace("\0",'', $str);
	}

	# wma types
	function getValueByType($data_type, $data) {
		if ($data_type == 0) {
			return $this->denull(implode('', unpack('a*', $data)));
		} elseif ($data_type == 1) {
			return $data;
		} elseif ($data_type == 2 || $data_type == 5) {
			return implode('', unpack('v', $data));
		} elseif ($data_type == 3) {
			return implode('', unpack('V', $data));
		} elseif ($data_type == 4) {
			return implode('', unpack('VV', $data));
		} elseif ($data_type == 6) {
			return $this->bin2guid($data);
		}
	}

	function bin2guid($hs) {
	   if (strlen($hs) == 16) {
	      $hexstring = bin2hex($hs);
			return strtoupper(substr( $hexstring, 6,2).substr( $hexstring, 4,2).
				substr( $hexstring, 2,2).substr( $hexstring, 0,2).'-'.substr( $hexstring, 10,2).substr( $hexstring, 8,2).'-'.
				substr( $hexstring, 14,2).substr( $hexstring, 12,2).'-'.substr( $hexstring, 16,4).'-'.substr( $hexstring, 20,12));
	   } else {
	      return false;
	   }
	}

	function getM4A() {
		$fh = fopen($this->file, 'rb');
		$this->filesize = filesize($this->file);
		$this->parseAtom($fh, 0, $this->filesize);
		$this->stereo = true;
		if (!$this->info) $this->getBadInfo();
		fclose($fh);
	}

	function getWMA() {
		$fh = fopen($this->file, 'rb');
		$this->filesize = filesize($this->file);
		$this->parseWMA($fh);
		fclose($fh);
	}

	function getBadInfo() {
		$this->time = $this->bitrate = $this->frequency = 0;
		$this->filesize = filesize($this->file);
		return false;
	}

	function toUInt($bytes) {
		return implode('',unpack("N*", $bytes));
	}

	function Seek($fh, &$offset, $n = 4) {
		fseek($fh, $offset);
		$bytes = fread($fh, $n);
		$offset += $n;
		return $bytes;
	}

	function unpackHeader($byte) {
		return implode('', unpack('N', $byte));
	}
}

function cddb_disc_id($track_offsets, $disc_length) {
	$n = 0;
	$count = count($track_offsets);
	for ($i = 0; $i < $count; $i++) {
		$n = $n + cddb_sum($track_offsets[$i] / 75);
	}
	// The $disc_length - 2 accounts for the 150 frame offset the RedBook standard uses... I think...
	return dechex(($n % 0xff) << 24 | ($disc_length - 2) << 8 | $count);
}

function cddb_sum($n) {
	$ret = 0;
	while ($n > 0) {
		$ret = $ret + ($n % 10);
		$n = (int) ($n / 10);
	}
	return $ret;
}
?>
