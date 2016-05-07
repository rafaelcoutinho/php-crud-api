<?php
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
			echo "Inicio às $inicio_hora $inicio_minuto \n";
			
			$deslocamentoEmMinutos = $this->getEquipesNoGrid ( $db, $idEtapa, $gridConfig, true );
			
			echo " totaldeslocamento $deslocamentoEmMinutos";
			$deslocamentoEmMinutos += $inicio_minuto;
			$addHour = ( int ) ($deslocamentoEmMinutos / 60);
			
			$hora = $inicio_hora;
			$hora += $addHour;
			
			$minutoToGo = ($deslocamentoEmMinutos % 60);
			echo "Largada às $hora:$minutoToGo";
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
		if ($data->paga == 1) {
			$this->query ( $db, "START TRANSACTION" );
			$this->query ( $db, "BEGIN" );
			$equipe = $this->getEquipe ( $db, $data->id_Equipe );
			$resp ["id_Equipe"] = $equipe ["id_Equipe"];
			$resp ["id_Trekker"] =$data->id_Trekker;
			$gridInfo = $this->getGridInfo ( $db, $data->id_Etapa, $data->id_Equipe );
			if ($gridInfo == null) {
				
				syslog ( LOG_INFO, "Equipe deve ser incluida no grid" );
				$dataEtapa = $this->getDataEtapa ( $db, $data->id_Etapa );
				
				// parte hc
				$gridConfig = $this->getGridConfig ( $db, $equipe ["id_Categoria"] );
				
				// agora procurar o cara mais atras na lista do grid
				
				$inicio_minuto = $gridConfig ["inicio_minuto"];
				$inicio_hora = $gridConfig ["inicio_hora"];
				syslog ( LOG_INFO, " $inicio_hora $inicio_minuto" );
				
				$deslocamentoEmMinutos = $this->getEquipesNoGrid ( $db, $data->id_Etapa, $gridConfig );
				
				syslog ( LOG_INFO, " totaldeslocamento $deslocamentoEmMinutos" );
				$deslocamentoEmMinutos += $inicio_minuto;
				$addHour = ( int ) ($deslocamentoEmMinutos / 60);
				
				$hora = $inicio_hora;
				$hora += $addHour;
				
				$minutoToGo = ($deslocamentoEmMinutos % 60);
				
				$this->insertEquipeGrid ( $db, $equipe ["id_Equipe"], $data->id_Etapa, $hora, $minutoToGo, $gridConfig ["id"] );
				$this->updateInscricao ( $db, $data );
				$resp ["gridUpdate"] = true;
				$resp ["horario"] = $hora . ":" . $minutoToGo;
				
				
			} else {
				syslog ( LOG_INFO, "Equipe já estava no grid" );
				
				$this->updateInscricao ( $db, $data );
				$resp ["gridUpdate"] = false;
			}
			$this->query ( $db, "COMMIT" );
		} else {
			$this->updateInscricao ( $db, $data );
		}
		$milliseconds = (round ( microtime ( true ) * 1000 ));
		$this->associaTrekkerEquipe ( $db, $data->id_Trekker, $data->id_Equipe, $milliseconds );
		
		$this->startOutput ( $callback );
		echo json_encode ( $resp );
		$this->endOutput ( null );
	}
}

$api = new AdicionarAoGrid ( $configArray );

$api->configArray = $configArray;

$api->executeCommand ();
?>