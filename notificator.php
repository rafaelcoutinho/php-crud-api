<?php
include 'DBCrud.php';
include 'connInfo.php';
// Using Autoload all classes are loaded on-demand
use google\appengine\api\taskqueue\PushTask;
use google\appengine\api\taskqueue\PushQueue;
// Adjust to your timezone
date_default_timezone_set ( 'America/Sao_Paulo' );
class NotificatorAction extends MySQL_CRUD_API {
	protected function getBody($type, $notification, $row, $db) {
		if (strcmp ( $type, "resultados" ) == 0) {
			$colocacao = $row['colocacao'];
			$pontos_perdidos = $row['pontos_perdidos'];
			$equipe = $row['equipe'];
			$titulo = $row['titulo'];
			return $equipe." ficou na posição ".$colocacao." com ".$pontos_perdidos." na etapa ".$titulo;
				
		}else{
			return $notification->body;
		}
	}
	protected function getTitle($type, $notification, $row, $db) {
		if (strcmp ($type, "resultados" ) == 0) {
			$colocacao = $row['colocacao'];
			$pontos_perdidos = $row['pontos_perdidos'];
			$equipe = $row['equipe'];
			$titulo = $row['titulo'];
			return $colocacao."o colocado com ".$pontos_perdidos." pontos";
			
		}else{
			$notification->title;
		}
	
	}
	public function executeCommand() {
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
		if ($data->type != null) {
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
			} else if (strcmp ( $data->type, "competidor" ) == 0) {
				$result = $this->query ( $db, "select * from MsgDevice msg where msg.idUser=?", array (
						$data->id_Trekker 
				) );
			} else if (strcmp ( $data->type, "equipe" ) == 0) {
				$result = $this->query ( $db, "select * from MsgDevice msg, Competidor t where msg.idUser=t.id_Trekker and t.id_Equipe=?", array (
						$data->id_Equipe 
				) );
			} else if (strcmp ( $data->type, "resultados" ) == 0) {
				$result = $this->query ( $db, "select msg.*,e.titulo, t.equipe, r.colocacao,r.pontos_perdidos from MsgDevice msg, Competidor t, Resultado r, Etapa e where msg.idUser=t.id_Trekker and t.id_Equipe=r.id_Equipe and e.id=r.id_Etapa and e.id=?", array (
						$data->id_Etapa 
				) );
			}else {
				$this->exitWith ( "Sem destinatarios conhecidos", 404, 2 );
			}
		} else {
			$this->exitWith ( "Sem destinatarios", 404, 2 );
		}
		$resp = array ();
		if ($result) {
			
			$gcmTargets = array ();
			$apnTargets = array ();
			while ( $row = $this->fetch_assoc ( $result ) ) {
				
				$body =  $this->getBody( $data->type,$data->notification,$row,$db);
				$title =  $this->getTitle( $data->type,$data->notification,$row,$db);
				syslog ( LOG_INFO, "Enviando ".$data->type." para user id " . $row ["idUser"] . " em " . $row ["platform"] );
				syslog ( LOG_INFO, $title);
				syslog ( LOG_INFO, $body);
				if (strcmp ( "android", $row ["platform"] ) == 0) {
					$task = new PushTask ( '/task/GCMPush', [ 
							'body' =>$body,
							'token' => $row ["token"],
							'title' => $title,
							'image' => $data->notification->image,
							'action' => $data->notification->action 
					], [ 
							'method' => 'POST' 
					] );
					
					$gcmTargets [] = $task;
				} else {
					$task = new PushTask ( '/task/APNPush', [ 
							'body' => $body,
							'token' => $row ["token"],
							'title' => $title,
							'action' => $data->notification->action 
					], [ 
							'method' => 'POST' 
					] );
					
					$apnTargets [] = $task;
				}
				syslog ( LOG_INFO, "Ok para user id " . $row ["idUser"] );
				$resp [] = $row ["idUser"];
			}
			$this->close ( $result );
			$queueGCM = new PushQueue ( "pushGCM" );
			$queueGCM->addTasks ( $gcmTargets );
			$queueAPN = new PushQueue ( "pushAPN" );
			$queueAPN->addTasks ( $apnTargets );
		} else {
			syslog ( LOG_INFO, "nenhum usuario encontrado '" . $data->to . "'" );
			$this->exitWith ( "Nenhum usuario", 500, 2 );
		}
		
		$this->startOutput ( null );
		echo json_encode ( $resp );
		$this->endOutput ( null );
	}
}

$api = new NotificatorAction ( $configArray );
$api->APN_Password = $APN_Password;
$api->GCM_AUTH_KEY = $GCM_AUTH_KEY;

$api->configArray = $configArray;

$api->executeCommand ();

?>