<?php
include 'gridCommons.php';
class AtualizaGrid extends GridCommons {
	public function executeCommand() {
		if (isset ( $_SERVER ['REQUEST_METHOD'] )) {
			header ( 'Access-Control-Allow-Origin: *' );
			$this->headersCommand ( NULL );
		}
		if (strcmp ( $_SERVER ['REQUEST_METHOD'], "OPTIONS" ) == 0) {
			return;
		}
		if (strcmp ( $_SERVER ['REQUEST_METHOD'], "DELETE" ) == 0) {
			$idEquipe = $_GET ["id_Equipe"];
			$idEtapa = $_GET ["id_Etapa"];
			$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
			
			$result = $this->query ( $db, 'DELETE FROM Grid WHERE id_Equipe = ? and id_Etapa=? ', array (
					$idEquipe,
					$idEtapa 
			) );
			$num = $this->affected_rows ( $db, $result );
			
			$this->startOutput ( $callback );
			
			echo json_encode ( $num );
			$this->endOutput ( null );
			return;
		}
		
		$request_body = file_get_contents ( 'php://input' );
		$data = json_decode ( $request_body );
		
		if (! $data->id_Etapa || ! $data->id_Equipe || $data->hora == null || ! is_numeric ( $data->minuto )) {
			$this->exitWith ( "Missing parameters $data->id_Etapa || $data->id_Equipe ||  $data->hora " . ($data->hora == null) . " || $data->minuto " . (! is_numeric ( $data->minuto )), 400 );
			return;
		}
		// permite o update
		
		$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
		$resp = array ();
		
		$dataEtapa = $this->getDataEtapa ( $db, $data->id_Etapa );
		if (strcmp ( $_SERVER ['REQUEST_METHOD'], "POST" ) == 0) {
			$gridConfig = $this->getGridConfig ( $db, $data->categoria_Equipe );
			$this->insertEquipeGrid ( $db, $data->id_Equipe, $data->id_Etapa, $data->hora, $data->minuto, $gridConfig ["id"] );
		} else {
			$this->updateEquipeGrid ( $db, $data->id_Equipe, $data->id_Etapa, $data->hora, $data->minuto );
		}
		
		$resp = $this->getGridInfo ( $db, $data->id_Etapa, $data->id_Equipe );
		
		
		
		$this->startOutput ( $callback );
		echo ($resp);
		$this->endOutput ( null );
	}
}

$api = new AtualizaGrid ( $configArray );

$api->configArray = $configArray;

$api->executeCommand ();
?>