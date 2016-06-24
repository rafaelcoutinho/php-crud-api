<?php
include 'DBCrud.php';
include 'connInfo.php';

// Using Autoload all classes are loaded on-demand

// Adjust to your timezone
date_default_timezone_set ( 'America/Sao_Paulo' );
class NotificatorGCM extends MySQL_CRUD_API {
	// FUNCTION to check if there is an error response from Apple
	// Returns TRUE if there was and FALSE if there was not
	protected function checkAppleErrorResponse($fp) {
		
		// byte1=always 8, byte2=StatusCode, bytes3,4,5,6=identifier(rowID). Should return nothing if OK.
		$apple_error_response = fread ( $fp, 6 );
		// NOTE: Make sure you set stream_set_blocking($fp, 0) or else fread will pause your script and wait forever when there is no response to be sent.
		
		if ($apple_error_response) {
			// unpack the error response (first byte 'command" should always be 8)
			$error_response = unpack ( 'Ccommand/Cstatus_code/Nidentifier', $apple_error_response );
			
			if ($error_response ['status_code'] == '0') {
				$error_response ['status_code'] = '0-No errors encountered';
			} else if ($error_response ['status_code'] == '1') {
				$error_response ['status_code'] = '1-Processing error';
			} else if ($error_response ['status_code'] == '2') {
				$error_response ['status_code'] = '2-Missing device token';
			} else if ($error_response ['status_code'] == '3') {
				$error_response ['status_code'] = '3-Missing topic';
			} else if ($error_response ['status_code'] == '4') {
				$error_response ['status_code'] = '4-Missing payload';
			} else if ($error_response ['status_code'] == '5') {
				$error_response ['status_code'] = '5-Invalid token size';
			} else if ($error_response ['status_code'] == '6') {
				$error_response ['status_code'] = '6-Invalid topic size';
			} else if ($error_response ['status_code'] == '7') {
				$error_response ['status_code'] = '7-Invalid payload size';
			} else if ($error_response ['status_code'] == '8') {
				$error_response ['status_code'] = '8-Invalid token';
			} else if ($error_response ['status_code'] == '255') {
				$error_response ['status_code'] = '255-None (unknown)';
			} else {
				$error_response ['status_code'] = $error_response ['status_code'] . '-Not listed';
			}
			
			syslog ( LOG_INFO, 'Response Command:' . $error_response ['command'] . ' Identifier:' . $error_response ['identifier'] . ' ' . $error_response ['status_code'] . '' );
			
			return true;
		} else {
			syslog ( LOG_INFO, 'No Error' );
		}
		return false;
	}
	protected function sendIOSMsg($notification, $title, $device, $action, $db) {
		$ctx = stream_context_create ();
		
		stream_context_set_option ( $ctx, 'ssl', 'local_cert', 'pushcert.pem' );
		stream_context_set_option ( $streamContext, 'ssl', 'passphrase', $this->APN_Password );
		
		$fp = stream_socket_client ( 'ssl://gateway.push.apple.com:2195', $err, $errstr, 60, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $ctx );
		stream_set_blocking ( $fp, 0 );
		if (! $fp) {
			// Handle Error
		}
		$alert = array (
				'title' => $title,
				'body' => $notification 
		);
		$aps = array (
				'alert' => $alert,
				'sound' => 'default' 
		);
		if ($action != null && strcmp ( $action, "" ) != 0 && strcmp ( $action, "none" ) != 0) {
			$sql = "select * from Etapa where active=(1)";
			$resp = $this->getEntity ( $db, $sql, array () );
			$aps ['content-available'] = 1;
			$aps ['idEtapa'] = json_decode ( $resp )->id;
			$aps ["action"] = $action;
		}
		$body ['aps'] = $aps;
		
		$payload = json_encode ( $body );
		syslog ( LOG_INFO, "payload     " . $payload );
		syslog ( LOG_INFO, "payload len " . strlen ( $payload ) );
		
		$deviceToken = $device;
		$msg = chr ( 0 ) . pack ( 'n', 32 ) . pack ( 'H*', $deviceToken ) . pack ( 'n', strlen ( $payload ) ) . $payload;
		$result = fwrite ( $fp, $msg, strlen ( $msg ) );
		$this->checkAppleErrorResponse ( $fp );
		fclose ( $fp );
	}
	public function executeCommand() {
		if (strcmp ( $_SERVER ['REQUEST_METHOD'], "OPTIONS" ) == 0) {
			return;
		}
		header ( 'Content-Type: application/json;charset=utf-8', true );
		$request = parse_url ( $_SERVER ['REQUEST_URI'], PHP_URL_PATH );
		
		syslog ( LOG_INFO, $_POST ["body"] );
		syslog ( LOG_INFO, $_POST ["title"] );
		syslog ( LOG_INFO, $_POST ["token"] );
		syslog ( LOG_INFO, $_POST ["image"] );
		syslog ( LOG_INFO, $_POST ["action"] );
		
		$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
		
		$this->sendIOSMsg ( $_POST ["body"], $_POST ["title"], $_POST ["token"], $_POST ["action"], $db );
		
		$this->startOutput ( null );
		echo "";
		$this->endOutput ( null );
	}
}

$api = new NotificatorGCM ( $configArray );
$api->APN_Password = $APN_Password;

$api->configArray = $configArray;

$api->executeCommand ();
?>