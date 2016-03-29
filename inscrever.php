<?php
include 'DBCrud.php';
include 'connInfo.php';

$adfasdf = $configArray;
class InscricaoApi extends MySQL_CRUD_API {
	protected function validateInscricao($db, $id_Equipe, $id_Etapa, $id_Lider, $integrantes_Ids, $participantes) {
		$sqlParam = "";
		$sqlTrekkerParams = array ();
		$sqlTrekkerParams [] = $id_Equipe;
		$sqlTrekkerParams [] = $id_Etapa;
		
		syslog ( LOG_INFO, "val " . $participantes );
		for($i = 0; $i < $participantes; $i ++) {
			if ($integrantes_Ids [$i]->id != null) {
				syslog ( LOG_INFO, "participante " . $i . ":" . $integrantes_Ids [$i]->id );
				$sqlParam .= '?,';
				$sqlTrekkerParams [] = $integrantes_Ids [$i]->id;
			}
		}
		
		$sqlParam .= '?';
		$sqlTrekkerParams [] = $id_Lider;
		
		$result = $this->query ( $db, 'select id_Trekker from Inscricao where id_Trekker in (select id_Trekker from Trekker_Equipe where id_Equipe=?) and id_Etapa=? and id_Trekker not in (' . $sqlParam . ')  and paga=(1)', $sqlTrekkerParams );
		syslog ( LOG_INFO, "result->num_rows " . $result->num_rows );
		if ($result) {
			if ($row = $this->fetch_row ( $result )) {
				syslog ( LOG_INFO, "Inscrição não pode ser alterada" );
				$this->exitWith ( "Inscricao tenta remover participante com status pago", 500, 661 );
			}
		}
		$this->close ( $result );
		
		$paramsDelete = array (
				mysqli_real_escape_string ( $db, $id_Equipe ),
				mysqli_real_escape_string ( $db, $id_Etapa ) 
		);
		$result = $this->query ( $db, 'delete from Inscricao where id_Trekker in (select id_Trekker from Trekker_Equipe where id_Equipe=?) and id_Etapa=?', $paramsDelete );
		$affected = $this->affected_rows ( $db, $result );
		syslog ( LOG_INFO, "apagou  " . $affected );
	}
	protected function createInscricao($db, $id_Trekker, $id_Equipe, $id_Etapa, $milliseconds) {
		$params = array (
				mysqli_real_escape_string ( $db, $id_Trekker ),
				mysqli_real_escape_string ( $db, $id_Equipe ),
				mysqli_real_escape_string ( $db, $id_Etapa ),
				mysqli_real_escape_string ( $db, $milliseconds ) 
		);
		$result = $this->query ( $db, 'INSERT INTO Inscricao (id_Trekker,id_Equipe,id_Etapa,data) VALUES (?,?,?,?)', $params );
	}
	protected function saveTrekker($db, $data, $type) {
		// criar novo.
		if ($data->nome == null) {
			$this->exitWith ( "Missing nome trekker", 401, 801 );
		}
		if (filter_var ( $data->email, FILTER_VALIDATE_EMAIL )) {
			
			$type = "PASSIVE_EMAIL";
		}
		
		$params = array (
				mysqli_real_escape_string ( $db, $data->email ),
				mysqli_real_escape_string ( $db, $pwd ),
				mysqli_real_escape_string ( $db, $data->nome ),
				mysqli_real_escape_string ( $db, $data->fbId ),
				mysqli_real_escape_string ( $db, $data->telefone ),
				mysqli_real_escape_string ( $db, $type ) 
		);
		$sql = "insert into Trekker (email,password,nome,fbId,telefone,state) VALUES (?,?,?,?,?,?)";
		$result = $this->query ( $db, $sql, $params );
		if (! $result) {
			$error = $this->getError ( $db );
			$this->exitWith ( 'Failed to insert object: ' . $error, 500, 990 );
		} else {
			$idInserted = $this->insert_id ( $db, $result );
		}
		return $idInserted;
	}
	protected function saveEquipe($db, $data) {
		// criar novo.
		if ($data->nome == null) {
			$this->exitWith ( "Missing nome equipe", 401, 701 );
		}
		if ($data->id_Categoria == null) {
			$this->exitWith ( "Missing categoria equipe", 401, 702 );
		}
		
		$params = array (
				mysqli_real_escape_string ( $db, $data->nome ),
				mysqli_real_escape_string ( $db, $data->descricao ),
				mysqli_real_escape_string ( $db, $data->id_Categoria ),
				mysqli_real_escape_string ( $db, "INSCRIPTION" ) 
		);
		$sql = "insert into Equipe (nome,descricao,id_Categoria,state) VALUES (?,?,?,?)";
		$result = $this->query ( $db, $sql, $params );
		if (! $result) {
			$error = $this->getError ( $db );
			$this->exitWith ( 'Failed to insert object: ' . $error, 500, 990 );
		} else {
			$idInserted = $this->insert_id ( $db, $result );
		}
		return $idInserted;
	}
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
			$idTrekker = $_GET ["id_Trekker"];
			$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
			
			$result = $this->query ( $db, 'DELETE FROM Inscricao WHERE id_Equipe = ? and id_Etapa=? and id_Trekker=?', array (
					$idEquipe,
					$idEtapa,
					$idTrekker 
			) );
			$num = $this->affected_rows ( $db, $result );
			
			$this->startOutput ( $callback );
			
			echo json_encode ( $num );
			$this->endOutput ( null );
			return;
		}
		
		$request_body = file_get_contents ( 'php://input' );
		$data = json_decode ( $request_body );
		if (! $data->lider || ! $data->etapa || ! $data->equipe) {
			$this->exitWith ( "Missing parameters", 400 );
			return;
		}
		$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
		
		$idEquipe = $data->equipe->id;
		if (! $idEquipe || $idEquipe == - 1) {
			syslog ( LOG_INFO, "Equipe nova, criando" );
			$idEquipe = $this->saveEquipe ( $db, $data->equipe );
		}
		if (! $idEquipe || $idEquipe == - 1) {
			$this->exitWith ( "FAIL_INSERT_EQUIPE", 500, 701 );
		}
		
		$idLider = $data->lider->id;
		if ($data->lider->id == - 1) {
			syslog ( LOG_INFO, "Lider é novo usuário, criando" );
			$idLider = $this->saveTrekker ( $db, $data->lider, "INSCRIPTION" );
		}
		
		if ($idLider == - 1) {
			$this->exitWith ( "FAIL_INSERT_LEADER", 500, 801 );
		}
		syslog ( LOG_INFO, "Lider id é " . $idLider );
		
		// validar
		$participantes = count ( $data->integrantes );
		$this->validateInscricao ( $db, $idEquipe, $data->etapa->id, $idLider, $data->integrantes, $participantes );
		
		$milliseconds = (round ( microtime ( true ) * 1000 ));
		// $this->associaTrekkerEquipe($db,$idLider,$idEquipe,$milliseconds);
		$this->createInscricao ( $db, $idLider, $idEquipe, $data->etapa->id, $milliseconds );
		
		syslog ( LOG_INFO, "Há " . $participantes . "  integrantes " );
		$integrantes = array ();
		for($i = 0; $i < $participantes; $i ++) {
			
			$integrante = $data->integrantes [$i];
			
			$idIntegrante = $integrante->id;
			
			if (! $idIntegrante) {
				$idIntegrante = $this->saveTrekker ( $db, $integrante, "PASSIVE" );
			}
			
			syslog ( LOG_INFO, "IntegranteId " . $idIntegrante );
			$this->createInscricao ( $db, $idIntegrante, $idEquipe, $data->etapa->id, $milliseconds );
			$integrantes [] = array (
					'id' => $idIntegrante 
			);
		}
		
		$equipe = array (
				'id' => $idEquipe 
		);
		$lider = array (
				'id' => $idLider 
		);
		$inscricao = array (
				'equipe' => $equipe,
				'lider' => $lider,
				'integrantes' => $integrantes 
		);
		$this->startOutput ( $callback );
		
		echo json_encode ( $inscricao );
		$this->endOutput ( null );
	}
}

$api = new InscricaoApi ( $configArray );

$api->configArray = $configArray;

$api->executeCommand ();
?>