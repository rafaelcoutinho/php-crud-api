<?php
include 'DBCrud.php';
include 'connInfo.php';
// Using Autoload all classes are loaded on-demand

// Adjust to your timezone
date_default_timezone_set ( 'America/Sao_Paulo' );
class NotificatorApi extends MySQL_CRUD_API {
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
			
			syslog ( LOG_INFO, 'Response Command:' . $error_response ['command'] . '\nIdentifier:' . $error_response ['identifier'] . '\n' . $error_response ['status_code'] . '\n');
			
			
			return true;
		}else{
			syslog ( LOG_INFO, 'No Error');
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
		syslog ( LOG_INFO, "payload  " . $payload );
		
		$deviceToken = $device;
		$msg = chr ( 0 ) . pack ( 'n', 32 ) . pack ( 'H*', $deviceToken ) . pack ( 'n', strlen ( $payload ) ) . $payload;
		$result = fwrite ( $fp, $msg, strlen ( $msg ) );
		$this->checkAppleErrorResponse($fp);
		fclose ( $fp );
	}
	protected function sendGCMMsg($body, $title, $deviceToken, $img, $action, $db) {
		$registrationIds = array (
				$deviceToken 
		);
		$msg = array (
				'icon' => 'noti',
				'message' => $body,
				'sound' => 'default',
				'vibrate' => true,
				
				'title' => $title 
		);
		if ($img != null && strcmp ( $img, "" ) != 0) {
			$msg ["picture"] = $img;
			$msg ["style"] = 'picture';
			$msg ["summaryText"] = $body;
		}
		if ($action != null && strcmp ( $action, "" ) != 0 && strcmp ( $action, "none" ) != 0) {
			$sql = "select * from Etapa where active=(1)";
			$resp = $this->getEntity ( $db, $sql, array () );
			syslog ( LOG_INFO, "e  " . $resp );
			$msg ["idEtapa"] = json_decode ( $resp )->id;
			
			$msg ["content-available"] = 1;
			$msg ["action"] = $action;
		}
		$fields = array (
				'registration_ids' => array (
						$deviceToken 
				),
				
				'data' => $msg 
		);
		$fields = json_encode ( $fields );
		syslog ( LOG_INFO, "deb3 " . $fields );
		
		$arrContextOptions = array (
				"http" => array (
						"method" => "POST",
						"header" => "Content-type: application/json\r\n" . "Authorization: key=" . $this->GCM_AUTH_KEY . "\r\n",
						"content" => $fields 
				),
				"ssl" => array (
						"verify_peer" => false 
				) 
		);
		$opts = stream_context_create ( $arrContextOptions );
		
		$result = file_get_contents ( 'https://gcm-http.googleapis.com/gcm/send', false, $opts );
		// $result = file_get_contents ( 'http://localhost/northServer/nottest.php', false, $opts );
		if ($result) {
			$jsonResults = json_decode ( $result );
			if ($jsonResults->failure == 1) {
				syslog ( LOG_INFO, "Fahlou results " . $result );
				syslog ( LOG_INFO, "Erro  " . $jsonResults->results [0]->error );
				
				if (strcmp ( $jsonResults->results [0]->error, "NotRegistered" ) == 0) {
					syslog ( LOG_INFO, "Token invalido" );
					$this->query ( $db, "delete from MsgDevice where token=?", array (
							$deviceToken 
					) );
				}
			}
		} else {
			syslog ( LOG_INFO, "Fahlou results " . $result );
		}
		
		return $result;
	}
	public function executeCommand() {
		syslog ( LOG_INFO, "deb2 " . $this->GCM_AUTH_KEY . " / " . $this->APN_Password );
		if (isset ( $_SERVER ['REQUEST_METHOD'] )) {
			header ( 'Access-Control-Allow-Origin: *' );
			$this->headersCommand ( NULL );
		}
		if (strcmp ( $_SERVER ['REQUEST_METHOD'], "OPTIONS" ) == 0) {
			return;
		}
		header ( 'Content-Type: application/json;charset=utf-8', true );
		$request = parse_url ( $_SERVER ['REQUEST_URI'], PHP_URL_PATH );
		
		$request_body = file_get_contents ( 'php://input' );
		syslog ( LOG_INFO, "data " . $request_body );
		$data = json_decode ( $request_body );
		
		$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
		$result = null;
		if ($data->to != null) {
			$result = $this->query ( $db, "select * from MsgDevice where idUser=?", array (
					$data->to 
			) );
		} else if ($data->type != null) {
			if (strcmp ( $data->type, "all" ) == 0) {
				$result = $this->query ( $db, "select * from MsgDevice", array () );
			} else if (strcmp ( $data->type, "pro" ) == 0) {
				$result = $this->query ( $db, "select * from MsgDevice msg, Competidor t where msg.idUser=t.id_Trekker and t.categoria=?", array (
						"Pró" 
				) );
			} else if (strcmp ( $data->type, "grad" ) == 0) {
				$result = $this->query ( $db, "select * from MsgDevice msg, Competidor t where msg.idUser=t.id_Trekker and t.categoria=?", array (
						"Graduados" 
				) );
			} else if (strcmp ( $data->type, "trekker" ) == 0) {
				$result = $this->query ( $db, "select * from MsgDevice msg, Competidor t where msg.idUser=t.id_Trekker and t.categoria=?", array (
						"Trekkers" 
				) );
			} else if (strcmp ( $data->type, "turismo" ) == 0) {
				$result = $this->query ( $db, "select * from MsgDevice msg, Competidor t where msg.idUser=t.id_Trekker and t.categoria=?", array (
						"Turismo" 
				) );
			}if (strcmp ( $data->type, "competidor" ) == 0) {
				$result = $this->query ( $db, "select * from MsgDevice msg where msg.idUser=?", array (
						$data->id_Trekker
				) );
				
			}if (strcmp ( $data->type, "equipe" ) == 0) {
				$result = $this->query ( $db, "select * from MsgDevice msg, Competidor t where msg.idUser=t.id_Trekker and t.id_Equipe=?", array (
						$data->id_Equipe
				) );
			}
		} else {
			$this->exitWith ( "Sem destinatarios", 404, 2 );
		}
		$resp = array ();
		if ($result) {
			while ( $row = $this->fetch_assoc ( $result ) ) {
				syslog ( LOG_INFO, "Enviando para user id " . $row ["idUser"] . " em " . $row ["platform"] );
				if (strcmp ( "android", $row ["platform"] ) == 0) {
					
					$resposta = $this->sendGCMMsg ( $data->notification->body, $data->notification->title, $row ["token"], $data->notification->image, $data->notification->action, $db );
					syslog ( LOG_INFO, $resposta );
				} else {
					$this->sendIOSMsg ( $data->notification->body, $data->notification->title, $row ["token"], $data->notification->action, $db );
				}
				syslog ( LOG_INFO, "Ok para user id " . $row ["idUser"] );
				$resp [] = $row ["idUser"];
			}
			$this->close ( $result );
		} else {
			syslog ( LOG_INFO, "nenhum usuario encontrado '" . $data->to . "'" );
			$this->exitWith ( "Nenhum usuario", 500, 2 );
		}
		
		$this->startOutput ( null );
		echo json_encode ( $resp );
		$this->endOutput ( null );
	}
}

$api = new NotificatorApi ( $configArray );
$api->APN_Password = $APN_Password;
$api->GCM_AUTH_KEY = $GCM_AUTH_KEY;

$api->configArray = $configArray;

$api->executeCommand ();
?>