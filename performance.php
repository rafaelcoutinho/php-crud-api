<?php
include 'DBCrud.php';
include 'connInfo.php';
class Resultados extends MySQL_CRUD_API {
	private function getGridInfo($numero, $grids) {
		if ($grids [2] ["numeracao"] <= $numero) {
			return $grids [2];
		} else if ($grids [1] ["numeracao"] > $numero) {
			return $grids [0];
		} else {
			return $grids [1];
		}
	}
	private function getEquipe($numero, $grids, $db, $idEtapa) {
		$grid = $this->getGridInfo ( $numero, $grids );
		$diff = ( int ) $numero - $grid ["numeracao"];
		$minutos = 0;
		// syslog ( LOG_INFO, $grid ["quota_intervalo1"]." ".$diff." ".$numero." ". $grid ["numeracao"]." ". $grid ["nome"]. " ".$grid ["inicio_hora"].":".$grid ["inicio_minuto"]);
		if ($grid ["quota_intervalo1"] != null && $diff > $grid ["quota_intervalo1"]) {
			$remaining = $diff - $grid ["quota_intervalo1"];
			$minutos = $remaining * $grid ["intervalo2"];
			$diff = $diff - $remaining;
		}
		$minutos += $diff * $grid ["intervalo1"];
		$minutos += $grid ["inicio_minuto"];
		$horas = ( int ) ($minutos / 60);
		$horas += ( int ) $grid ["inicio_hora"];
		$minutos = ( int ) $minutos % 60;
		
		$params = array ();
		$params [] = $idEtapa;
		$params [] = $horas;
		$params [] = $minutos;
		$sql = "SELECT * FROM Grid g, Equipe e where id_Etapa=? and hora=? and minuto=? and g.id_Equipe=e.id";
		
		$equipe = $this->getEntity ( $db, $sql, $params, true );
		
		$gridInf = array ();
		$gridInf ["hora"] = $horas;
		$gridInf ["minuto"] = $minutos;
		// syslog ( LOG_INFO," ".$horas.":".$minutos);
		$gridInf ["grid"] = $grid ["nome"];
		if ($equipe) {
			$gridInf ["equipe"] = json_decode ( $equipe );
		}
		
		// $resp["infer"] = $gridInf;
		
		return $gridInf;
		
		// return $horas . ":" . $minutos . "/quota'" . $grid ["quota_intervalo1"]."',h:".$grid ["inicio_hora"].":".$grid ["inicio_minuto"].",i:".$grid ["intervalo1"].",diff:".$diff." ".$grid ["nome"];
	}
	public function executeCommand() {
		if (strcmp ( $_SERVER ['REQUEST_METHOD'], "OPTIONS" ) == 0) {
			return;
		}
		header ( 'Content-Type: application/json;charset=utf-8', true );
		$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
		
		if (strcmp ( $_SERVER ['REQUEST_METHOD'], "POST" ) == 0) {
			$gresults = $this->query ( $db, "select * from GridConfig order by numeracao", array () );
			$grids = array ();
			if ($gresults) {
				while ( $row = $this->fetch_assoc ( $gresults ) ) {
					$grids [] = $row;
				}
			}
			$this->close ( $gresults );
			$request_body = file_get_contents ( 'php://input' );
			$idEtapa = $_GET ["etapa"];
			// echo "'".$request_body."'\n";
			$lines = explode ( "\n", $request_body );
			
			$responseJson = array ();
			
			for($j = 0; $j < count ( $lines ); $j ++) {
				$line = $lines [$j];
				$csvs = str_getcsv ( $line, ";" );
				if (count ( $csvs ) > 1 && ! is_numeric ( $csvs [0] )) {
					// syslog ( LOG_INFO, "is header " . $line );
					$COL_PC = - 1;
					for($k = 3; $k < count ( $csvs ); $k ++) {
						$pcsTitleCandidate = $csvs [$k];
						// syslog ( LOG_INFO, "is header " . $pcsTitleCandidate );
						if (strlen ( $pcsTitleCandidate ) > 2 && strcmp ( substr ( $pcsTitleCandidate, 0, 2 ), "PC" ) == 0) {
							if ($COL_PC == - 1) {
								$COL_PC = $k;
							}
							
							$PCSTitles = $csvs;
							for($i = 0; $i < count ( $PCSTitles ); $i ++) {
								if (strlen ( $PCSTitles [$i] ) > 0) {
									$PCSTitles [$i] = str_replace ( "PC", "", $PCSTitles [$i] );
								}
							}
							$j ++;
							// syslog ( LOG_INFO, "is pcs ".$COL_PC );
							// syslog ( LOG_INFO, "titles ".$lines [$j] );
							$titles = str_getcsv ( $lines [$j], ";" );
							for($m = 0; $m < count ( $titles ); $m ++) {
								if (strcmp ( substr ( $titles [$m], 0, 3 ), "Tmp" ) == 0) {
									$titles [$m] = "Tmp";
								} elseif (strcmp ( substr ( $titles [$m], 0, 3 ), "Até" ) == 0) {
									$titles [$m] = "ate";
								} elseif (strcmp ( substr ( $titles [$m], 0, 3 ), "Vir" ) == 0) {
									$titles [$m] = "Virt";
								} elseif (strcmp ( substr ( $titles [$m], 0, 4 ), "Canc" ) == 0) {
									$titles [$m] = "Canc";
								} else {
									$titles [$m] = str_replace ( " ", "_", $titles [$m] );
								}
							}
							break;
						}
					}
					continue;
				}
				if ($COL_PC == - 1) {
					$this->exitWith ( "Falhou pra encontrar titulos", 500, 500 );
					return;
				}
				$info = array ();
				
				for($i = 0; $i < $COL_PC; $i ++) {
					$item = $csvs [$i];
					$key = $titles [$i];
					$item = str_replace ( "º", "", $item );
					
					if (is_numeric ( $item )) {
						$info [$key] = (( int ) $item);
					} else {
						$info [$key] = trim ( $item );
					}
				}
				$info ["grid"] = $this->getEquipe ( $csvs [0], $grids, $db, $idEtapa );
				
				$pcs = array ();
				for($i = $COL_PC; $i < count ( $csvs ); $i ++) {
					if (($i - $COL_PC) % 2 == 0) {
						
						if ($i > $COL_PC) {
							$pcs [] = $pc;
						}
						$pc = array ();
						if ((( int ) $PCSTitles [$i]) == 0) {
							
							continue;
						}
						$pc ["pc"] = (( int ) $PCSTitles [$i]);
					}
					$item = $csvs [$i];
					if (strcmp ( $titles [$i], "AtéPC" ) == 0) {
						$titles [$i] = "ate";
					}
					$item = str_replace ( "º", "", $item );
					$key = $titles [$i];
					if (is_numeric ( $item )) {
						$pc [$key] = (( int ) $item);
					} else {
						$pc [$key] = trim ( $item );
					}
				}
				
				$info ["pcs"] = $pcs;
				$responseJson [] = $info;
			}
			
			echo json_encode ( $responseJson );
			return;
		} else if (strcmp ( $_SERVER ['REQUEST_METHOD'], "PUT" ) == 0) {
			
			$request_body = file_get_contents ( 'php://input' );
			
			$data = json_decode ( $request_body, true );
			$sqlDeleteResultados = "delete from Resultado where id_Etapa=?";
			$sqlDeletePCS = "delete from PC where id_Etapa=?";
			$params = array ();
			$params [] = $_GET ["etapa"];
			$totalSemEquipe = 0;
			$this->query ( $db, $sqlDeletePCS, $params );
			$this->query ( $db, $sqlDeleteResultados, $params );
			$sqlInsertResultado = "insert into Resultado (id_Etapa,id_Equipe,colocacao,pcs_pegos,pcs_zerados,pontos_perdidos,numero,penalidade,pontos_ranking) values (?,?,?,?,?,?,?,?,?)";
			
			$sqlInsertPC = "insert into PC (id_Etapa,id_Equipe,numero,variacao,tipo,ate,colocacao) values (?,?,?,?,?,?,?)";
			
			foreach ( $data as &$value ) {
				$idEquipe = $value ["grid"] ["equipe"] ["id_Equipe"];
				if ($idEquipe == NULL) {
					$totalSemEquipe ++;
					continue;
				}
				$params = array ();
				
// 				id_Etapa,id_Equipe,colocacao,pcs_pegos,pcs_zerados,pontos_perdidos,numero,penalidade,pontos_ranking
				$params [] = $_GET ["etapa"];
				$params [] = $idEquipe;
				$params [] = $value ["Col"];
				$params [] = $value ["PCPassou"];
				$params [] = $value ["PCZerado"];
				$params [] = $value ["PtsPerdidos"];
				$params [] = $value ["No"];
				$params [] = $value ["Penal"];
				
				$pontosRanking = - 1;
				if ($value ["Col"] == 1) {
					$pontosRanking = 60;
				} elseif ($value ["Col"] == 2) {
					$pontosRanking = 57;
				} else {
					$pontosRanking = 60 - 2 - $value ["Col"];
				}
				$params [] =$pontosRanking;
				mysqli_query ( $db, "BEGIN" );
				$this->query ( $db, $sqlInsertResultado, $params );
				
				$pcs = $value ["pcs"];
				
				$insertParams = array ();
				for($i = 0; $i < count ( $pcs ); $i ++) {
					$pc = $pcs [$i];
					
					$params = array ();
					$params [] = $_GET ["etapa"];
					$params [] = $idEquipe;
					$params [] = $pc ["pc"];
					if (isset ( $pc ["Tmp"] )) {
						if (strcmp ( $pc ["Tmp"], "*900" ) == 0) {
							$params [] = 900;
						} else {
							$params [] = $pc ["Tmp"];
						}
						$params [] = 1;
					} else if (isset ( $pc ["Canc"] )) {
						$params [] = 0;
						$params [] = 3;
					} else if (isset ( $pc ["Virt"] )) {
						if (strcmp ( $pc ["Virt"], "*900" ) == 0) {
							$params [] = 900;
						} else {
							$params [] = $pc ["Virt"];
						}
						$params [] = 2;
					} else {
						mysqli_query ( $db, "ROLLBACK" );
						$errMsg = "Tipo de PCS Invalido #:" . $value ["No"] . " pc:" . $pcs [$i] ["pc"] . " v:'" . $pc ["Virt"] . "' c:'" . $pc ["Canc"] . "' t:'" . $pc ["Tmp"] . "'";
						$this->exitWith ( $errMsg, 500, 500 );
					}
					
					$params [] = $pc ["ate"];
					$params [] = 0;
					
					$insertParams [] = '(' . $params [0] . ',' . $params [1] . ',' . $params [2] . ',' . $params [3] . ',' . $params [4] . ',' . $params [5] . ',' . $params [6] . ')';
				}
				$finalInsert = 'INSERT INTO PC (id_Etapa,id_Equipe,numero,variacao,tipo,ate,colocacao) VALUES ' . implode ( ',', $insertParams );
				// syslog ( LOG_INFO, " $finalInsert" );
				mysqli_query ( $db, $finalInsert );
				mysqli_query ( $db, "COMMIT" );
				
				mysqli_stmt_close ( $inserter );
			}
			
			echo "{\"status\":\"ok\",\"sem_equipe\":$totalSemEquipe}";
			return;
		} else if (strcmp ( $_SERVER ['REQUEST_METHOD'], "GET" ) == 0) {
			return;
		}
	}
}
$api = new Resultados ( $configArray );

$api->configArray = $configArray;

$api->executeCommand ();

?>
