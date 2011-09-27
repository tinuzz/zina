<?php
/*
 Copyright (c) 2001 - 2007 Ampache.org
 All rights reserved.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License v2
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.


 Modified by Ryan Lathouwers <ryanlath@pacbell.net>
 - Added Now Playing
 - Upgraded to current protocol

 
 Connects to the scrobbler servers and submits "Now Playing" & "Track Summissions" 
  - Last.fm Submissions Protocol v1.2.1
*/

class scrobbler {
	public $error_msg;
	public $username;
	public $password;
	public $session_id;
	public $now_playing_url;
	public $submission_url;
	public $queued_tracks;
	#TODO: reset_handshake isn't used ?
	public $reset_handshake = false; 
	/**
	 * This is the constructer it takes a username and password
	 */
	public function __construct($username, $password) {

		$this->error_msg = '';
		$this->username = trim($username);
		$this->password = trim($password);
		$this->session_id = null;
		$this->now_playing_url = null; 
		$this->submission_url = null; 
		$this->queued_tracks = array();
		$this->handshake_socket_timeout = 1;
		$this->submit_socket_timeout = 2;

	} // scrobbler

	public function get_error_msg() {
		return $this->error_msg;
	}

	public function get_queue_count() {
		return count($this->queued_tracks);
	}

	/**
	 * handshake with the audioscrobber server
	 */
	public function handshake() {

		$as_socket = fsockopen('post.audioscrobbler.com', 80, $errno, $errstr, $this->handshake_socket_timeout);
		if(!$as_socket) {
			$this->error_msg = 'handshake cannot open socket: '.$errstr;
			return false;
		}

		$username	= rawurlencode($this->username);
		$timestamp	= time(); 
		$auth_token	= rawurlencode(md5(md5($this->password) . $timestamp)); 
		
		$get_string = "GET /?hs=true&p=1.2&c=zin&v=1.0&u=$username&t=$timestamp&a=$auth_token HTTP/1.1\r\n";
		
		fwrite($as_socket, $get_string);
		fwrite($as_socket, "Host: post.audioscrobbler.com\r\n");
		fwrite($as_socket, "Accept: */*\r\n\r\n");

		$buffer = '';
		while(!feof($as_socket)) {
			$buffer .= fread($as_socket, 4096);
		}
		fclose($as_socket);
		$split_response = preg_split("/\r\n\r\n/", $buffer);

		if(!isset($split_response[1])) {
			$this->error_msg = 'Did not receive a valid response';
			return false;
		}

		$response = explode("\n", $split_response[1]);

		if ($response[0] == 'OK') {
			$this->session_id = $response[1];
			$this->now_playing_url = $response[2];
			$this->submission_url = $response[3];
			return true;
		} else {
			# BANNED BADAUTH BADTIME FAILED <reason>
			$this->error_msg = $response;
			return false;
		}
	} // handshake

	/**
	 * queue_track 
	 * This queues the LastFM track by storing it in this object
	 * $track is object containing 'artist', 'album', 'title','track','length', 'timestamp'
	 */
	public function queue_track($track) {
		if(empty($track) || !is_object($track)) {
			$this->error_msg = "No track to submit to queue";
		} elseif ($track->length < 30 || time() - $track->timestamp < 30) {
			$this->error_msg = "Track too short, not queing";
		} elseif (time() - $track->timestamp < $track->length/2) {
			$this->error_msg = "Track not played long enough, not queing";
		} else {
			$this->queued_tracks[$track->timestamp] = $track;
			return true;
		}	
		return false;
	} // queue_track

	public function submit_track($track) {
		if (!empty($this->session_id) || $this->handshake()) {
			if ($this->queue_track($track)) {
				return $this->submit_tracks();
			}
		}
		return false;
	}
	/**
	 * submit_tracks
	 * Generates the "submit" query string for queued tracks then calls submit()
	 */ 
	public function submit_tracks() {

		// Check and make sure that we've got some queued tracks
		if (!count($this->queued_tracks)) {
			$this->error_msg = "No tracks to submit";
			return false;
		}

		if (empty($this->session_id) && !$this->handshake()) {
			return false; # handshake sets the error
		}

		//sort array by timestamp
		ksort($this->queued_tracks); 

		// build the query string
		$query_str = 's='.rawurlencode($this->session_id).'&';

		$i = 0;

		foreach ($this->queued_tracks as $track) {
			 $query_str .= 
				 "a[$i]=".rawurlencode($track->artist).
				 "&t[$i]=".rawurlencode($track->title).
				 "&b[$i]=".rawurlencode($track->album).
				 "&m[$i]=&l[$i]=".rawurlencode($track->length).
				 "&i[$i]=".rawurlencode($track->timestamp).
				 "&n[$i]=".rawurlencode($track->track) . 
				 "&o[$i]=P&r[$i]="; 
			 if (isset($track->love)) $query_str .= 'L';
			 $query_str .= '&';

			#TODO: have r be set optionally L (love) or B ban when last.fm supports it
			$i++;
		}

		return $this->submit($this->submission_url, $query_str);
	} // submit_tracks

	/**
	 * now_playing
	 * Generates the "now playing" query string for a track then calls submit()
	 *  - track is an object
	 */ 
	public function now_playing($track) {

		// Check and make sure that we've got some queued tracks
		if(empty($track) || !is_object($track)) {
			$this->error_msg = "No track to submit to now playing";
			return false;
		}

		if (empty($this->session_id) && !$this->handshake()) {
			return false;
		}

		// build the query string
		$query_str = 's='.rawurlencode($this->session_id).
				 "&a=".rawurlencode($track->artist).
				 "&t=".rawurlencode($track->title).
				 "&b=".rawurlencode($track->album).
				 "&l=".rawurlencode($track->length).
				 "&n=".rawurlencode($track->track) . 
				 "&m=";

		return $this->submit($this->now_playing_url, $query_str);
	} // now_playing

	/**
	 * submit
	 * Does the actual connecting to scrobbler server
	 */ 
	private function submit($submit_url, $query_str) {
		$url = parse_url($submit_url);
		if (!isset($url['host']) || !isset($url['port']) || !isset($url['path'])) {
			$this->error_msg = 'Bad now_playing_url';
			$this->reset_handshake = true; 
			return false;
		}

		$as_socket = fsockopen($url['host'], intval($url['port']), $errno, $errstr, $this->submit_socket_timeout);

		if(!$as_socket) {
			$this->error_msg = 'submit cannot open socket: '.$errstr;
			$this->reset_handshake = true; 
			return false;
		}

		$action = "POST ".$url['path']." HTTP/1.0\r\n";
		fwrite($as_socket, $action);
		fwrite($as_socket, "Host: ".$url['host']."\r\n");
		fwrite($as_socket, "Accept: */*\r\n");
		fwrite($as_socket, "User-Agent: Zina/2.0\r\n");
		fwrite($as_socket, "Content-type: application/x-www-form-urlencoded\r\n");
		fwrite($as_socket, "Content-length: ".strlen($query_str)."\r\n\r\n");

		fwrite($as_socket, $query_str."\r\n\r\n");
		#TODO: test
		stream_set_timeout($as_socket, $this->submit_socket_timeout); 
		$info = stream_get_meta_data($as_socket);

		$buffer = '';
		while(!feof($as_socket) && !$info['timed_out']) {
			$buffer .= fread($as_socket, 4096);
			$info = stream_get_meta_data($as_socket);
		}
		fclose($as_socket);

		$split_response = preg_split("/\r\n\r\n/", $buffer);
		if(!isset($split_response[1])) {
			$this->error_msg = 'Did not receive a valid response: '.$buffer;
			$this->reset_handshake = true; 
			return false;
		}

		$response = explode("\n", $split_response[1]);

		if(!isset($response[0])) {
			$this->error_msg = 'Unknown error submitting tracks: '.$buffer;
			$this->reset_handshake = true; 
			return false;
		}
		if ($response[0] == 'OK') {
			return true;
		} else {
			$this->error_msg = $response[0];
			$this->reset_handshake = true; 
			return false;
		}
	} // submit
} // end audioscrobbler class
?>
