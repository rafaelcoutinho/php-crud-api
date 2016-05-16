<?php
include 'DBCrud.php';
include 'connInfo.php';

// Using Autoload all classes are loaded on-demand

// Adjust to your timezone
date_default_timezone_set ( 'America/Sao_Paulo' );
class NotificatorGCM extends MySQL_CRUD_API {
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
				syslog ( LOG_INFO, "Falhou results " . $result );
				syslog ( LOG_INFO, "Erro  " . $jsonResults->results [0]->error );
				
				if (strcmp ( $jsonResults->results [0]->error, "NotRegistered" ) == 0) {
					syslog ( LOG_INFO, "Token invalido" );
					$this->query ( $db, "delete from MsgDevice where token=?", array (
							$deviceToken 
					) );
				}
			}
		} else {
			syslog ( LOG_INFO, "Falhou results " . $result );
		}
		
		return $result;
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
		
		$resposta = $this->sendGCMMsg ( $_POST ["body"], $_POST ["title"], $_POST ["token"], $_POST ["image"], $_POST ["action"], $db );
		
		$this->startOutput ( null );
		echo $resposta;
		$this->endOutput ( null );
	}
}

$api = new NotificatorGCM ( $configArray );
$api->GCM_AUTH_KEY = $GCM_AUTH_KEY;

$api->configArray = $configArray;

$api->executeCommand ();
?>