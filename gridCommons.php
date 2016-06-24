<?php
include 'DBCrud.php';
include 'connInfo.php';
class GridCommons extends MySQL_CRUD_API {
	protected function getGridInfo($db, $idEtapa, $idEquipe) {
		$sql = 'SELECT * from Grid where id_Etapa=? and id_Equipe=?';
		$params = array ();
		$params [] = $idEtapa;
		$params [] = $idEquipe;
		$result = $this->query ( $db, $sql, $params );
		
		if ($result) {
			if ($row = $this->fetch_assoc ( $result )) {
				syslog ( LOG_INFO, "Equipe já está no grid " );
				$colInfo = $this->getColInfo ( $result, TRUE );
				return $this->getObject ( $row, $colInfo );
			} else {
				syslog ( LOG_INFO, "Equipe deve ser incluida no grid" );
				return null;
			}
		} else {
			return null;
		}
	}
	protected function updateEquipeGrid($db, $idEquipe, $idEtapa, $hora, $minuto) {
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
	protected function getDiffMinutes($refHora, $refMinutos, $hora, $minuto) {
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
	protected function getDataEtapa($db, $idEtapa) {
		$sql = 'SELECT data from Etapa where id=?';
		$params = array ();
		$params [] = $idEtapa;
		$result = $this->query ( $db, $sql, $params );
		
		if ($result) {
			if ($row = $this->fetch_assoc ( $result )) {
				return $row ["data"];
			} else {
				
				$this->exitWith ( "Não achou etapa " . $idEtapa, 400, 990 );
			}
		} else {
			$this->exitWith ( "Não achou etapa " . $idEtapa, 400, 990 );
		}
	}
	
	/**
	 */
	protected function updateInscricao($db, $data) {
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
	protected function getGridConfig($db, $idCategoria) {
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
	protected function getEquipe($db, $idEquipe) {
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
	protected function getEquipesNoGrid($db, $idEtapa, $gridConfig, $debug) {
		$sql = 'SELECT * from Grid where id_Etapa=? and id_Config=? order by hora,minuto';
		$params = array ();
		$params [] = $idEtapa;
		$params [] = $gridConfig ["id"];
		$result = $this->query ( $db, $sql, $params );
		$deslocamentoEmMinutos = 0;
		$inicio_hora = $gridConfig ["inicio_hora"];
		$inicio_minuto = $gridConfig ["inicio_minuto"];
		$quota1 = $gridConfig ["quota_intervalo1"];
		$intervalo1 = $gridConfig ["intervalo1"];
		$intervalo2 = $gridConfig ["intervalo2"];
		if ($debug == true) {
			echo "Grid Info inicio:  $inicio_hora:$inicio_minuto Quota: " . $quota1 . " Intervalo A:" . $intervalo1 . " Intervalo B: " . $intervalo2 . "\n";
		} else {
			syslog ( LOG_INFO, "Grid Info inicio:  $inicio_hora:$inicio_minuto Quota: " . $quota1 . " Intervalo A:" . $intervalo1 . " Intervalo B: " . $intervalo2 );
		}
		$total = 0;
		if ($result) {
			while ( $row = $this->fetch_assoc ( $result ) ) {
				
				$cHora = $row ["hora"];
				$cMinuto = $row ["minuto"];
				
				$diffMinutes = $this->getDiffMinutes ( $inicio_hora, $inicio_minuto, $cHora, $cMinuto );
				if ($debug == true) {
					$numeroEquipe = $gridConfig ["numeracao"]+$total;
					echo "$numeroEquipe: diff = $diffMinutes - $deslocamentoEmMinutos\n";
				}
				syslog ( LOG_INFO, "$total: diff = $diffMinutes - $deslocamentoEmMinutos" );
				if ($diffMinutes < $deslocamentoEmMinutos) {
					if ($debug == true) {
						echo "\t**Não é o horario certo... \n";
					} else {
						syslog ( LOG_INFO, "Não é o horario certo... " );
					}
					$total --;
				} else if ($diffMinutes != $deslocamentoEmMinutos) {
					if ($debug == true) {
						echo "Há um espaco aqui!\n";
					}
					syslog ( LOG_INFO, "Há um espaco aqui!" );
					break;
				} else {
					if (! $quota1 || $total < $quota1) {
						if ($debug == true) {
							echo "\tdentro quota_intervalo1\n";
						} else {
							syslog ( LOG_INFO, "dentro quota_intervalo1" );
						}
						$deslocamentoEmMinutos += $gridConfig ["intervalo1"];
					} else {
						if ($debug == true) {
							echo "\tdentro quota_intervalo2\n";
						} else {
							syslog ( LOG_INFO, "dentro quota_intervalo2" );
						}
						$deslocamentoEmMinutos += $gridConfig ["intervalo2"];
					}
				}
				$total++;
			}
		} else {
			syslog ( LOG_INFO, "Nenhuma equipe no grid" );
		}
		return $deslocamentoEmMinutos;
	}
	protected function insertEquipeGrid($db, $idEquipe, $idEtapa, $hora, $minuto, $configId) {
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

?>