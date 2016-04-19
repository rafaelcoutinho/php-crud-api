<?php
use \google\appengine\api\mail\Message;

include 'DBCrud.php';
include 'connInfo.php';
class AppApi extends MySQL_CRUD_API {
	protected function removePrefix($request) {
		if (! $request)
			return false;
		
		$pos = strpos ( $request, 'app/enhanced/' );
		if (! $pos) {
			$pos = strpos ( $request, 'php/' );
			$pos += 4;
		} else {
			$pos += 13;
		}
		
		return $pos ? substr ( $request, $pos ) : '';
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
		$pathInfo = $this->removePrefix ( $request );
		
		$paths = explode ( "/", $pathInfo );
		syslog ( LOG_INFO, "paths " . serialize ( $paths ) );
		
		$request_body = file_get_contents ( 'php://input' );
		$data = json_decode ( $request_body );
		
		$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
		
		$resp = null;
		if (strcmp ( $paths [0], "Etapa" ) == 0) {
			$l = count ( $paths );
			if ($l == 1) {
				
				$resp = $this->listTable ( $db, "select e.*,l.nome from Etapa e left join Local l on  e.id_Local=l.id", array () );
				
				// listar todos
			} else {
				$idEtapa = $paths [1];
				if ($l == 2) {
					$resp = $this->getEntity ( $db, "select * from Etapa  e left join Local l on  e.id_Local=l.id where e.id=?", array (
							$idEtapa 
					) );
				} else {
					syslog ( LOG_INFO, "Pegar info " . $paths [0] . "/" . $paths [1] . "/" . $paths [2] );
					if (strcmp ( $paths [2], "GridInfo" ) == 0) {
						$grid = $this->listTable ( $db, "select * from GridFull where id_Etapa=?", array (
								$idEtapa 
						) );
						$preGrid = $this->listTable ( $db, "select * from PreGrid where id_Etapa=?", array (
								$idEtapa 
						) );
						$etapa = $this->getEntity ( $db, "select * from Etapa  e left join Local l on  e.id_Local=l.id where e.id=?", array (
								$idEtapa 
						) );
						$resp = "{\"grid\":" . $grid . ",\"preGrid\":" . $preGrid . ",\"etapa\":" . $etapa . "	}";
					} else if (strcmp ( $paths [2], "OutOfGrid" ) == 0) {
						$resp = $this->listTable ( $db, "SELECT * FROM northdb.InscricaoFull ins where id_Equipe not in (select id_Equipe from Grid g where g.id_Etapa=?) and id_Etapa=? and paga=1;", array (
								$idEtapa,
								$idEtapa 
						) );
					} else if (strcmp ( $paths [2], "Resultado" ) == 0) {
						$resp = $this->listTable ( $db, "SELECT * FROM northdb.Resultado r, Equipe e where r.id_Etapa=? and r.id_Equipe=e.id", array (
								$idEtapa 
						) );
					} else if (strcmp ( $paths [2], "Performance" ) == 0) {
						$idEquipe = $paths [3];
						$resp = $this->listTable ( $db, "SELECT * FROM northdb.PC pcs where pcs.id_Etapa=? and pcs.id_Equipe=?", array (
								$idEtapa,
								$idEquipe 
						) );
					}
				}
			}
		} else if (strcmp ( $paths [0], "Competidor" ) == 0) {
			$l = count ( $paths );
			if ($l == 1) {
				
				$resp = $this->listTable ( $db, "select * from Competidor ", array () );
				// listar todos
			} else {
				$id = $paths [1];
				if ($l == 2) {
					$resp = $this->getEntity ( $db, "select * from Competidor where e.id=?", array (
							$id 
					) );
				} else {
					if (strcmp ( $paths [2], "Equipe" ) == 0) {
						$equipe = $this->getEntity ( $db, "select eq.*,cat.nome as categoria from Equipe eq, Categoria cat where cat.id=eq.id_Categoria and eq.id=(select id_Equipe from Trekker_Equipe where id_Trekker=? and end=0)", array (
								$id 
						) );
						$integrantes = $this->listTable ( $db, "select * from Competidor where id_Equipe=(select id_Equipe from Trekker_Equipe where id_Trekker=? and end=0)", array (
								$id 
						) );
						$resp = "{\"equipe\":" . $equipe . ",\"integrantes\":" . $integrantes . "}";
					}
				}
			}
		} else if (strcmp ( $paths [0], "InscricaoCompetidores" ) == 0) {
			$id = $paths [1];
			$idEquipe = $paths [2];
			$sql = "SELECT
					c.id AS id,
					c.id AS id_Trekker,		
					c.nome AS nome,
					c.fbId AS fbId,
					c.id_Equipe AS id_Equipe,
					c.equipe AS equipe,
					c.categoria AS categoria,
					ins.nome_Equipe AS ins_EquipeNome,
					ins.id_Equipe AS ins_EquipeId,
					ins.categoria AS ins_CategoriaNome,
					ins.id_Categoria AS ins_CategoriaId,
					ins.id_Etapa AS id_Etapa,
					ins.paga AS paga,
					ins.data AS ins_Data
					FROM
					(
				northdb.Competidor c LEFT JOIN
				(select id_Trekker, nome_Equipe, id_Equipe, categoria,id_Categoria,id_Etapa,paga, data from northdb.InscricaoFull where id_Etapa=? ) ins
				ON (ins.id_Trekker = c.id) where id_Equipe=?)";
			$resp = $this->listTable ( $db, $sql, array (
					$id,
					$idEquipe 
			) );
		} else if (strcmp ( $paths [0], "InscricaoCompetidor" ) == 0) {
			$idEtapa = $paths [1];
			$email = strtolower ( $paths [2] );
			$sql = "SELECT 
c.id AS id,c.id AS id_Trekker, c.nome AS nome,c.fbId AS fbId,
c.id_Equipe AS id_Equipe,
c.equipe AS equipe,
c.categoria AS categoria,ins.nome_Equipe AS ins_EquipeNome,ins.id_Equipe AS ins_EquipeId, ins.categoria AS ins_CategoriaNome, ins.id_Categoria AS ins_CategoriaId, 
ins.id_Etapa AS id_Etapa, ins.paga AS paga, ins.data AS ins_Data 
FROM 
( northdb.Competidor c LEFT JOIN
 (select id_Trekker, nome_Equipe, id_Equipe, categoria,id_Categoria,id_Etapa,paga, data from northdb.InscricaoFull where id_Etapa=? ) ins ON ins.id_Trekker = c.id_Trekker) where c.email=?";
			$resp = $this->getEntity ( $db, $sql, array (
					$idEtapa,
					$email 
			), true );
		} else if (strcmp ( $paths [0], "Ranking" ) == 0) {
			
			$sql = "SELECT r.id_Equipe, e.nome,e.descricao,e.id_Categoria,sum(pontos_ranking) as pontos FROM northdb.Resultado r, Equipe e where e.id=r.id_Equipe group by id_Equipe";
			$resp = $this->listTable ( $db, $sql, array () );
		} else if (strcmp ( $paths [0], "EtapaAtual" ) == 0) {
			$sql = "select * from Etapa where data>(UNIX_TIMESTAMP()*1000) order by data limit 1";
			$resp = $this->getEntity ( $db, $sql, array () );
		} else if (strcmp ( $paths [0], "Msg" ) == 0) {
			$userId = $paths [1];
			$request_body = file_get_contents ( 'php://input' );
			$data = json_decode ( $request_body );
			if ($data->d == NULL) {
				$this->exitWith ( "Sem token ", 500, 1 );
			}
			$resp = array ();
			if (strcmp ( $data->action, "registergcm" ) == 0) {
				$now = (round ( microtime ( true ) * 1000 ));
				$sql = "delete MsgDevice where token=?";
				$this->query ( $db, $sql, array (
						$data->d 
				) );
				$sql = "insert into MsgDevice(idUser,token,platform,data) values (?,?,?,?)";
				$result = $this->query ( $db, $sql, array (
						$userId,
						$data->d,
						$data->p,
						$now 
				) );
				$id = - 1;
				if (! $result) {
					$error = $this->getError ( $db );
					
					$this->exitWith500 ( 'Failed to insert object: ' . $error );
				} else {
					$id = $this->insert_id ( $db, $result );
					$resp ["id"] = $id;
				}
				
				$resp ["ok"] = true;
				$resp = json_encode ( $resp );
			} else {
				$this->exitWith500 ( 'not implemented: ' . $data->action );
			}
		} else {
			$this->exitWith ( "Sem match " . serialize ( $paths ), 404, 1 );
		}
		
		$this->startOutput ( null );
		echo $resp;
		$this->endOutput ( null );
	}
}

$api = new AppApi ( $configArray );

$api->configArray = $configArray;

$api->executeCommand ();
?>