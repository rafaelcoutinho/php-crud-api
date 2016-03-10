<?php
include 'DBCrud.php';
include 'connInfo.php';

$adfasdf = $configArray;
class AdicionarAoGrid extends MySQL_CRUD_API {
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
		
		if (! $data->id_Etapa || ! $data->id_Trekker) {
			$this->exitWith ( "Missing parameters", 400 );
			return;
		}
		// permite o update
		
		$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
		
		$sql = 'UPDATE "Inscricao" SET "paga"=? WHERE "id_Trekker"=? and "id_Etapa"=?';
		$params = array ();	
		$params [] = $data->paga;
		$params [] = $data->id_Trekker;
		$params [] = $data->id_Etapa;
		$result = $this->query ( $db, $sql, $params );
		$hasEntry = $this->affected_rows ( $db, $result );
		if ($hasEntry == 0) {
			$this->exitWith ( "Cannot find row", 401 );
		}
		
		if ($data->paga == 1) {
			syslog ( LOG_INFO, "Foi paga, checar se deve colocar no grid" );
			$sql = "select id_Equipe,id_Categoria from Competidor_Equipe where id_Trekker=?";
			$params = array ();
			$params [] = $data->id_Trekker;
			$idEquipe = null;
			$idCategoria = null;
			$result = $this->query ( $db, $sql, $params );
			if ($result) {
				if ($row = $this->fetch_assoc ( $result )) {
					syslog ( LOG_INFO, "-- " . ($row ["id_Equipe"]) );
					$idEquipe = $row ["id_Equipe"];
					$idCategoria = $row ["id_Categoria"];
					$this->close ( $result );
				} else {
					$this->close ( $result );
					$this->exitWith ( "Missing parameters", 500 );
				}
			} else {
				syslog ( LOG_INFO, "Equipe não existe!!" );
				$this->close ( $result );
				$this->exitWith ( "Missing parameters", 500 );
			}
			$resp = array();
			
			$sql = 'SELECT id from Grid where id_Etapa=? and id_Equipe=?';
			$params = array ();
			$params [] = $data->id_Etapa;
			$params [] = $idEquipe;
			$result = $this->query ( $db, $sql, $params );
			
			if ($result) {
				if ($row = $this->fetch_row ( $result )) {
					syslog ( LOG_INFO, "Equipe já está no grid " );
					$resp["gridupdate"]=false;
				} else {
					syslog ( LOG_INFO, "Equipe deve ser incluida no grid" );
				}
			} else {
				syslog ( LOG_INFO, "Equipe deve ser incluida no grid, nao achou fora" );
				$sql = 'SELECT data from Etapa where id=?';
				$params = array ();
				$params [] = $data->id_Etapa;				
				$result = $this->query ( $db, $sql, $params );
				
				if ($result) {
					if ($row = $this->fetch_assoc ( $result )) {							
						$dataEtapa=$row["data"];
					} else {
						
					}
				}
				syslog ( LOG_INFO, "dataetapa $dataEtapa" );
				
				// parte hc
				$sql = 'SELECT * from GridConfig where id=?';
				
				$gridConfig = array ();
				$params = array ();
				if($idCategoria==1 || $idCategoria==2){
					$params [] = "1";
				}else{
					$params [] = $idCategoria;
				}
				
				$result = $this->query ( $db, $sql, $params );
				if ($result) {
					if ($row = $this->fetch_assoc ( $result )) {
						$gridConfig = array (
								'id' => $row ["id"],
								'intervalo1' => $row ["intervalo1"],
								'inicio_minuto' => $row ["inicio_minuto"],
								'inicio_hora' => $row ["inicio_hora"],
								'intervalo2' => $row ["intervalo2"],
								'quota_intervalo1' => $row ["quota_intervalo1"] 
						);											
					}
					$this->close ( $result );
					
					// agora procurar o cara mais atras na lista do grid
					$sql = 'SELECT count(*) as total from Grid where id_Etapa=? and id_Config=?';
					$params = array ();
					$params [] = $data->id_Etapa;
					$params [] = $gridConfig["id"];
					$result = $this->query ( $db, $sql, $params );
					if ($result) {
						$total = 0;
						if ($row = $this->fetch_assoc ( $result )) {
							$total = $row ["total"];
						}
						
						
						$inicio_minutos = $gridConfig["inicio_minuto"];
						$inicio_hora = $gridConfig["inicio_hora"];
						$dataEtapa += $inicio_hora*60*60*1000;
						$dataEtapa += $inicio_minutos*1000;
						syslog ( LOG_INFO, "total é " . $total." / ".$gridConfig["quota_intervalo1"] );
						
						
						
						
						if($gridConfig["quota_intervalo1"] && $total>$gridConfig["quota_intervalo1"]){
							syslog ( LOG_INFO, "date é " . $date );
							$deslocamentoEmMinutos = $gridConfig["quota_intervalo1"] * $gridConfig["intervalo1"];
							$deslocamentoEmMinutos += ($total-$gridConfig["quota_intervalo1"])*$gridConfig["intervalo2"];							
						}else{
							$deslocamentoEmMinutos = $total * $gridConfig["intervalo1"];
						}	
						
						$horario = $dataEtapa + ($deslocamentoEmMinutos * 60 * 1000);
						
						$sql = 'insert into Grid (id_Equipe,id_Etapa,largada,id_Config) values (?,?,?,?)';
						$params = array ();
						$params [] = $idEquipe;
						$params [] = $data->id_Etapa;
						$params [] = $horario;
						$params [] = $gridConfig["id"];
						$result = $this->query( $db, $sql, $params );
						$hasEntry = $this->affected_rows ( $db, $result );
						if ($hasEntry == 0) {
							$this->exitWith ( "Cannot find row", 401 );
						}
						$resp["gridupdate"]=true;
					}
				}
			}
			$this->close ( $result );
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