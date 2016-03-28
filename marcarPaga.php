<?php
include 'DBCrud.php';
include 'connInfo.php';

$adfasdf = $configArray;
class AdicionarAoGrid extends MySQL_CRUD_API {
	protected function associaTrekkerEquipe($db, $id_Trekker, $id_Equipe, $milliseconds) {
		$result = $this->query ( $db, 'update Trekker_Equipe set end=(UNIX_TIMESTAMP()*1000) where id_Trekker=' . $id_Trekker . ' and id_Equipe<>' . $id_Equipe . ' and end=0' );
		$hasEntry = $this->affected_rows ( $db, $result );
		
		syslog ( LOG_INFO, "Remocao de equipe retornou " . $hasEntry );
		$params = array (
				mysqli_real_escape_string ( $db, $id_Trekker ),
				mysqli_real_escape_string ( $db, $id_Equipe ),
				mysqli_real_escape_string ( $db, $milliseconds ) 
		);
		$resultId = $this->query ( $db, 'INSERT INTO Trekker_Equipe (id_Trekker,id_Equipe,start) VALUES (?,?,?)', $params );
		if ($resultId == 1) {
			syslog ( LOG_INFO, "Novo membro inserido " . $resultId );
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
		
		$request_body = file_get_contents ( 'php://input' );
		$data = json_decode ( $request_body );
		
		if (! $data->id_Etapa || ! $data->id_Trekker || ! $data->id_Equipe) {
			$this->exitWith ( "Missing parameters", 400 );
			return;
		}
		// permite o update
		
		$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
		$resp = array ();
		$resp ["gridUpdate"] = false;
		if ($data->paga == 1) {
			
			$equipe = $this->getEquipe ( $db, $data->id_Equipe );
			
			$gridInfo = $this->getGridInfo ( $db, $data->id_Etapa, $data->id_Equipe );
			if ($gridInfo == null) {
				
				syslog ( LOG_INFO, "Equipe deve ser incluida no grid" );
				$dataEtapa = $this->getDataEtapa ( $db, $data->id_Etapa );
				
				syslog ( LOG_INFO, "data etapa $dataEtapa" );
				
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
			}
		} else {
			$this->updateInscricao ( $db, $data );
		}
		$milliseconds = (round ( microtime ( true ) * 1000 ));
		$this->associaTrekkerEquipe ( $db, $data->id_Trekker, $data->id_Equipe, $milliseconds );
		
		$this->startOutput ( $callback );
		echo json_encode ( $resp );
		$this->endOutput ( null );
	}
	private function getDiffMinutes($refHora, $refMinutos, $hora, $minuto) {
		// syslog ( LOG_INFO, "$horas = $hora - $refHora" );
		$nHoras = $hora - $refHora;
		// syslog ( LOG_INFO, "$minuto += ($nHoras * 60)" );
		$nMinuto = $minuto + ($nHoras * 60);
		// syslog ( LOG_INFO, "$nMinuto - $refMinutos" );
		return $nMinuto - $refMinutos;
	}
	/**
	 *
	 * @param
	 *        	row
	 * @param
	 *        	dataEtapa
	 */
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
	
	/**
	 */
	private function updateInscricao($db, $data) {
		$sql = 'UPDATE "Inscricao" SET "paga"=? WHERE "id_Trekker"=? and "id_Etapa"=?';
		$params = array ();
		$params [] = $data->paga;
		$params [] = $data->id_Trekker;
		$params [] = $data->id_Etapa;
		$result = $this->query ( $db, $sql, $params );
		$hasEntry = $this->affected_rows ( $db, $result );
		if ($hasEntry == 0) {
			$this->exitWith ( "Cannot find row", 401, 990 );
		}
	}
	private function getGridConfig($db, $idCategoria) {
		$sql = 'SELECT * from GridConfig where id=?';
		
		$params = array ();
		if ($idCategoria == 1 || $idCategoria == 2) {
			$params [] = "1";
		} else {
			$params [] = $idCategoria;
		}
		
		$result = $this->query ( $db, $sql, $params );
		if ($result) {
			if ($row = $this->fetch_assoc ( $result )) {
				return $row;
			}
			
			$this->close ( $result );
		} else {
			syslog ( LOG_INFO, "getGridConfig não existe!!" );
		}
		return null;
	}
	private function getEquipe($db, $idEquipe) {
		$sql = "select id,id_Categoria from Equipe e where id=?";
		$params = array ();
		$params [] = $idEquipe;
		$equipe = array ();
		
		$result = $this->query ( $db, $sql, $params );
		if ($result) {
			if ($row = $this->fetch_assoc ( $result )) {
				
				$equipe ["id_Equipe"] = $row ["id"];
				$equipe ["id_Categoria"] = $row ["id_Categoria"];
				$this->close ( $result );
				return $equipe;
			} else {
				$this->close ( $result );
				$this->exitWith ( "nao foi possivel iterar na tabela de equipes", 500 );
			}
		} else {
			syslog ( LOG_INFO, "Equipe não existe!!" );
			$this->close ( $result );
			$this->exitWith ( "Equipe $idEquipe não existe", 500 );
		}
	}
	private function getGridInfo($db, $idEtapa, $idEquipe) {
		$sql = 'SELECT id_Equipe from Grid where id_Etapa=? and id_Equipe=?';
		$params = array ();
		$params [] = $idEtapa;
		$params [] = $idEquipe;
		$result = $this->query ( $db, $sql, $params );
		
		if ($result) {
			if ($row = $this->fetch_assoc ( $result )) {
				syslog ( LOG_INFO, "Equipe já está no grid " );
				
				return row;
			} else {
				syslog ( LOG_INFO, "Equipe deve ser incluida no grid" );
				return null;
			}
		} else {
			return null;
		}
	}
	private function getEquipesNoGrid($db, $idEtapa, $gridConfig) {
		$sql = 'SELECT * from Grid where id_Etapa=? and id_Config=? order by hora,minuto';
		$params = array ();
		$params [] = $idEtapa;
		$params [] = $gridConfig ["id"];
		$result = $this->query ( $db, $sql, $params );
		$deslocamentoEmMinutos = 0;
		$inicio_hora = $gridConfig ["inicio_hora"];
		$inicio_minuto = $gridConfig ["inicio_minuto"];
		syslog ( LOG_INFO, "Grid Info inicio:  $inicio_hora:$inicio_minuto Quota: " . $gridConfig ["quota_intervalo1"] . " Intervalo A:" . $gridConfig ["intervalo1"] . " Intervalo A: " . $gridConfig ["intervalo2"] );
		$total = 0;
		if ($result) {
			while ( $row = $this->fetch_assoc ( $result ) ) {
				$total ++;
				$cHora = $row ["hora"];
				$cMinuto = $row ["minuto"];
				
				$diffMinutes = $this->getDiffMinutes ( $inicio_hora, $inicio_minuto, $cHora, $cMinuto );
				
				syslog ( LOG_INFO, "$total: diff = $diffMinutes - $deslocamentoEmMinutos" );
				if ($diffMinutes < $deslocamentoEmMinutos) {
					syslog ( LOG_INFO, "Não é o horario certo... " );
					$total --;
				} else if ($diffMinutes != $deslocamentoEmMinutos) {
					syslog ( LOG_INFO, "Há um espaco aqui!" );
					break;
				} else {
					if ($total < $gridConfig ["quota_intervalo1"]) {
						syslog ( LOG_INFO, "dentro quota_intervalo1" );
						$deslocamentoEmMinutos += $gridConfig ["intervalo1"];
					} else {
						$deslocamentoEmMinutos += $gridConfig ["intervalo2"];
					}
				}
			}
		} else {
			syslog ( LOG_INFO, "Nenhuma equipe no grid" );
		}
		return $deslocamentoEmMinutos;
	}
	private function insertEquipeGrid($db, $idEquipe, $idEtapa, $hora, $minuto, $configId) {
		$sql = 'insert into Grid (id_Equipe,id_Etapa,hora,minuto,id_Config,type) values (?,?,?,?,?,?)';
		$params = array ();
		$params [] = $idEquipe;
		$params [] = $idEtapa;
		$params [] = $hora;
		$params [] = $minuto;
		$params [] = $configId;
		$params [] = 0;
		$result = $this->query ( $db, $sql, $params );
		$hasEntry = $this->affected_rows ( $db, $result );
		if ($hasEntry == 0) {
			$this->exitWith ( "Cannot insertEquipeGrid row", 401, 990 );
		}
	}
}

$api = new AdicionarAoGrid ( $configArray );

$api->configArray = $configArray;

$api->executeCommand ();
?>