<?php
include 'DBCrud.php';
include 'connInfo.php';
// Using Autoload all classes are loaded on-demand

// Adjust to your timezone
date_default_timezone_set ( 'America/Sao_Paulo' );
class NotificatorApi extends MySQL_CRUD_API {
	protected function sendIOSMsg($notification, $device) {
		$ctx = stream_context_create ();
		
		stream_context_set_option ( $ctx, 'ssl', 'local_cert', 'pushcert.pem' );
		stream_context_set_option ( $streamContext, 'ssl', 'passphrase', $this->APN_Password );
		
		$fp = stream_socket_client ( 'ssl://gateway.push.apple.com:2195', $err, $errstr, 60, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $ctx );
		
		if (! $fp) {
			// Handle Error
		}
		
		$body ['aps'] = array (
				'alert' => $notification,
				'sound' => 'default' 
		);
		
		$payload = json_encode ( $body );
		
		$deviceToken = $device;
		$msg = chr ( 0 ) . pack ( 'n', 32 ) . pack ( 'H*', $deviceToken ) . pack ( 'n', strlen ( $payload ) ) . $payload;
		$result = fwrite ( $fp, $msg, strlen ( $msg ) );
		fclose ( $fp );
	}
	protected function sendGCMMsg($body, $title, $deviceToken) {
		$registrationIds = array (
				$deviceToken 
		);
		$msg = array (
				'body' => $body,
				'sound' => 'default',
				'vibrate' => true,
				'title' => $title 
		);
		$fields = array (
				'to' => $deviceToken,
				
				'notification' => $msg 
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
		syslog ( LOG_INFO, "resultS " . $result );
		
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
		syslog ( LOG_INFO, "data " . $data->to );
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
			}
		} else {
			$this->exitWith ( "Sem destinatarios", 404, 2 );
		}
		$resp = array ();
		if ($result) {
			while ( $row = $this->fetch_assoc ( $result ) ) {
				syslog ( LOG_INFO, "Enviando para user id " . $row ["idUser"] . " em " . $row ["platform"] );
				if (strcmp ( "android", $row ["platform"] ) == 0) {
					
					$resposta = $this->sendGCMMsg ( $data->notification->body, $data->notification->title, $row ["token"] );
					syslog ( LOG_INFO, $resposta );
				} else {
					$this->sendIOSMsg ( $data->notification->body, $row ["token"] );
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