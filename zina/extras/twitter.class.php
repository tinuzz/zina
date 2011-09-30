<?php
class twitter {
	public $error;
	public $username;
	public $password;
	public $host;
	public $port;
	public $url;

	public function __construct($username, $password) {
		$this->error= '';
		$this->username = $username;
		$this->password = $password;
		$this->host = 'twitter.com';
		$this->port = 80;
		$this->path = '/statuses/update.json';
		$this->socket_timeout = 1;
	}

	public function set_status($status) {
		$as_socket = fsockopen($this->host, intval($this->port), $errno, $errstr, $this->socket_timeout);

		if(!$as_socket) {
			$this->error= 'submit cannot open socket: '.$errstr;
			return false;
		}

		$query_str = 'status='.urlencode($status).'&source=zina';

		$action = "POST ".$this->path." HTTP/1.1\r\n";

		fwrite($as_socket, $action);
		fwrite($as_socket, "Host: ".$this->host."\r\n");
		fwrite($as_socket, "Accept: */*\r\n");
		#fwrite($as_socket, "Source: zina\r\n");
		fwrite($as_socket, "Content-type: application/x-www-form-urlencoded\r\n");
		fwrite($as_socket, "Authorization: Basic ".base64_encode("{$this->username}:{$this->password}")."\r\n");
		fwrite($as_socket, "Content-length: ".strlen($query_str)."\r\n\r\n");

		fwrite($as_socket, $query_str."\r\n\r\n");
		
		$buffer = '';
		while(!feof($as_socket)) {
			$buffer .= fread($as_socket, 8192);
		}
		fclose($as_socket);

		$split_response = preg_split("/\r\n\r\n/", $buffer);

		if(!isset($split_response[1])) {
			$this->error= 'Did not receive a valid response: '.$buffer;
			return false;
		}

		$response = explode("\n", $split_response[1]);

		if(!isset($response[0])) {
			$this->error = 'Unknown error: '.$buffer;
			return false;
		} 
		$json = json_decode($response[0], true);	
		
		if (isset($json['text']) && $json['text'] == $status) {
			return true;
		}

		if (isset($json['error'])) 
			$this->error = $json['error'];
		else
			$this->error = $response[0]; 

		return false;
	}
} // end class
?>
