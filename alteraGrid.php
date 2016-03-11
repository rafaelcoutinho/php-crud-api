<?php
include 'DBCrud.php';
include 'connInfo.php';
class AtualizaGrid extends MySQL_CRUD_API {
	public function executeCommand() {
		if (isset ( $_SERVER ['REQUEST_METHOD'] )) {
			header ( 'Access-Control-Allow-Origin: *' );
			$this->headersCommand ( NULL );
		}
		if (strcmp ( $_SERVER ['REQUEST_METHOD'], "OPTIONS" ) == 0) {
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
		
		syslog ( LOG_INFO, "data etapa $dataEtapa" );
		
		$this->updateEquipeGrid ( $db, $data->id_Equipe, $data->id_Etapa, $data->hora, $data->minuto );
		
		$resp = $this->getGridInfo ( $db, $data->id_Equipe, $data->id_Etapa );
		
		$this->startOutput ( $callback );
		echo ($resp);
		$this->endOutput ( null );
	}
	private function getGridInfo($db, $idEquipe, $idEtapa) {
		$sql = 'SELECT * from Grid where id_Equipe=? and id_Etapa=?';
		$params = array ();
		$params [] = $idEquipe;
		$params [] = $idEtapa;
		$result = $this->query ( $db, $sql, $params );
		
		if ($result) {
			$colInfo = $this->getColInfo ( $result, true );
			if ($row = $this->fetch_assoc ( $result )) {
				return $this->getObject ( $row, $colInfo );
			} else {
				
				$this->exitWith ( "Não achou etapa", 400, 990 );
			}
		} else {
			$this->exitWith ( "Não achou etapa", 400, 990 );
		}
	}
	private function getDataEtapa($db, $idEtapa) {
		$sql = 'SELECT data from Etapa where id=?';
		$params = array ();
		$params [] = $idEtapa;
		$result = $this->query ( $db, $sql, $params );
		
		if ($result) {
			if ($row = $this->fetch_assoc ( $result )) {
				return $row ["data"];
			} else {
				
				$this->exitWith ( "Não achou etapa", 400, 990 );
			}
		} else {
			$this->exitWith ( "Não achou etapa", 400, 990 );
		}
	}
	private function updateEquipeGrid($db, $idEquipe, $idEtapa, $hora, $minuto) {
		$sql = 'update Grid set hora=?, minuto=?,type=1 where id_Equipe=? and id_Etapa=?';
		$params = array ();
		$params [] = $hora;
		$params [] = $minuto;
		$params [] = $idEquipe;
		$params [] = $idEtapa;
		
		$result = $this->query ( $db, $sql, $params );
		$hasEntry = $this->affected_rows ( $db, $result );
		if ($hasEntry == 0) {
			$this->exitWith ( "Update has not made any changes $idEquipe, $idEtapa, $hora:$minuto", 202, 990 );
		}
	}
}

$api = new AtualizaGrid ( $configArray );

$api->configArray = $configArray;

$api->executeCommand ();
?>