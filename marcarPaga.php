<?php
use google\appengine\api\taskqueue\PushTask;
use google\appengine\api\taskqueue\PushQueue;
include 'gridCommons.php';
class AdicionarAoGrid extends GridCommons {
	public function executeCommand() {
		if (isset ( $_SERVER ['REQUEST_METHOD'] )) {
			header ( 'Access-Control-Allow-Origin: *' );
			$this->headersCommand ( NULL );
		}
		if (strcmp ( $_SERVER ['REQUEST_METHOD'], "OPTIONS" ) == 0) {
			return;
		}
		
		if (strcmp ( $_SERVER ['REQUEST_METHOD'], "GET" ) == 0) {
			$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
			$resp = array ();
			$idEtapa = $_GET ["idEtapa"];
			$idCategoria = $_GET ["idCategoria"];
			$dataEtapa = $this->getDataEtapa ( $db, $idEtapa );
			$gridConfig = $this->getGridConfig ( $db, $idCategoria );
			$inicio_minuto = $gridConfig ["inicio_minuto"];
			$inicio_hora = $gridConfig ["inicio_hora"];
			echo "Inicio as $inicio_hora $inicio_minuto <br><pre>";
			
			$gridData = $this->getEquipesNoGrid ( $db, $idEtapa, $gridConfig, true );
			$deslocamentoEmMinutos = $gridData ["minutes_shift"];
			echo " Numero " . $gridData ["number"] . "\n";
			echo " totaldeslocamento $deslocamentoEmMinutos\n";
			$deslocamentoEmMinutos += $inicio_minuto;
			$addHour = ( int ) ($deslocamentoEmMinutos / 60);
			
			$hora = $inicio_hora;
			$hora += $addHour;
			
			$minutoToGo = ($deslocamentoEmMinutos % 60);
			echo "Largada as $hora: $minutoToGo";
			return;
		}
		
		$request_body = file_get_contents ( 'php://input' );
		$data = json_decode ( $request_body );
		
		if (! $data->id_Etapa || ! $data->id_Trekker || ! $data->id_Equipe) {
			$this->exitWith ( "Missing parameters", 400 );
			return;
		}
		// permite o update
		
		$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
		$resp = array ();
		if (strcmp ( $_SERVER ['REQUEST_METHOD'], "GET" ) == 0) {
			$dataEtapa = $this->getDataEtapa ( $db, $data->id_Etapa );
			return;
		}
		
		$resp ["gridUpdate"] = false;
		$defaultQueue = new PushQueue ();
		$tasksDefault = array ();
		
		if ($data->paga == 1) {
			
			$equipe = $this->getEquipe ( $db, $data->id_Equipe );
			$resp ["id_Equipe"] = $equipe ["id_Equipe"];
			$resp ["id_Trekker"] = $data->id_Trekker;
			$gridInfo = $this->getGridInfo ( $db, $data->id_Etapa, $data->id_Equipe );
			$etapa = $this->getEntityJson ( $db, "select * from Etapa where id=?", array (
					$data->id_Etapa 
			), true );
			if ($gridInfo == null) {
				$this->query ( $db, "START TRANSACTION" );
				$this->query ( $db, "BEGIN" );
				syslog ( LOG_INFO, "Equipe deve ser incluida no grid" );
				
				// parte hc
				$gridConfig = $this->getGridConfig ( $db, $equipe ["id_Categoria"] );
				
				// agora procurar o cara mais atras na lista do grid
				
				$inicio_minuto = $gridConfig ["inicio_minuto"];
				$inicio_hora = $gridConfig ["inicio_hora"];
				syslog ( LOG_INFO, " $inicio_hora $inicio_minuto" );
				
				$deslocamentoEmMinutos = $this->getEquipesNoGrid ( $db, $data->id_Etapa, $gridConfig )["minutes_shift"];
				
				syslog ( LOG_INFO, " totaldeslocamento $deslocamentoEmMinutos" );
				$deslocamentoEmMinutos += $inicio_minuto;
				$addHour = ( int ) ($deslocamentoEmMinutos / 60);
				
				$hora = $inicio_hora;
				$hora += $addHour;
				
				$minutoToGo = ($deslocamentoEmMinutos % 60);
				
				$this->insertEquipeGrid ( $db, $equipe ["id_Equipe"], $data->id_Etapa, $hora, $minutoToGo, $gridConfig ["id"] );
				$this->updateInscricao ( $db, $data );
				$resp ["gridUpdate"] = true;
				if ($minutoToGo < 10) {
					$resp ["horario"] = $hora . ":0" . $minutoToGo;
				} else {
					$resp ["horario"] = $hora . ":" . $minutoToGo;
				}
				
				$this->query ( $db, "COMMIT" );
				
				// notifica equipe
				$tasksDefault [] = new PushTask ( '/notification', [ 
						'notification_title' => 'Equipe confirmada',
						'type' => 'equipe',
						'id_Equipe' => $equipe ["id_Equipe"],
						'notification_body' => 'Sua equipe está no grid, às ' . $resp ["horario"],
						'notification_image' => $etapa ["imgSmall"],
						'notification_action' => 'grid' 
				], [ 
						'method' => 'POST' 
				] );
			} else {
				syslog ( LOG_INFO, "Equipe já estava no grid" );
				
				$this->updateInscricao ( $db, $data );
				$resp ["gridUpdate"] = false;
				$gridInfoJson = json_decode ( $gridInfo );
				if ($gridInfoJson->minuto < 10) {
					$resp ["horario"] = $gridInfoJson->hora . ":0" . $gridInfoJson->minuto;
				} else {
					$resp ["horario"] = $gridInfoJson->hora . ":" . $gridInfoJson->minuto;
				}
			}
			$milliseconds = (round ( microtime ( true ) * 1000 ));
			$this->associaTrekkerEquipe ( $db, $data->id_Trekker, $data->id_Equipe, $milliseconds );
			syslog ( LOG_INFO, "Enviando email para " . $data->id_Etapa . ":" . $data->id_Trekker );
			$task = new PushTask ( '/task/mailer', [ 
					'id_Etapa' => $data->id_Etapa,
					'id_Trekker' => $data->id_Trekker,
					'action' => 'CONFIRM_INSCRIPTION' 
			], [ 
					'method' => 'POST' 
			] );
			$queueMailer = new PushQueue ( "mailerQueue" );
			$queueMailer->addTasks ( [ 
					$task 
			] );
			
			$tasksDefault [] = new PushTask ( '/notification', [ 
					'type' => 'competidor',
					'id_Trekker' => $data->id_Trekker,
					'notification_title' => 'Inscrição confirmada',
					'notification_body' => 'Seu pagamento foi confirmado para a etapa ' . $etapa ["titulo"],
					'notification_image' => $etapa ["imgSmall"],
					'notification_action' => 'grid' 
			], [ 
					'method' => 'POST' 
			] );
			$defaultQueue->addTasks ( $tasksDefault );
		} else {
			syslog ( LOG_INFO, "Setou false no pagametno" );
			$this->updateInscricao ( $db, $data );
		}
		
		$this->startOutput ( $callback );
		echo json_encode ( $resp );
		$this->endOutput ( null );
	}
}

$api = new AdicionarAoGrid ( $configArray );

$api->configArray = $configArray;

$api->executeCommand ();
?>