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
	protected function listTable($db, $sql, $params) {
		$response = "";
		
		if ($result = $this->query ( $db, $sql, $params )) {
			$colInfo = $this->getColInfo ( $result, true );
			while ( $row = $this->fetch_assoc ( $result ) ) {
				$response .= $this->getObject ( $row, $colInfo );
				$response .= ",";
			}
			$this->close ( $result );
		} else {
			syslog ( LOG_INFO, "nao achou " );
		}
		
		$response = rtrim ( $response, "," );
		
		return "[" . $response . "]";
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
		syslog ( LOG_INFO, "app.php " );
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
						$resp = "{\"grid\":" . $grid . ",\"preGrid\":" . $preGrid . "}";
					} else if (strcmp ( $paths [2], "OutOfGrid" ) == 0) {
						$resp = $this->listTable ( $db, "SELECT * FROM northdb.InscricaoFull ins where id_Equipe not in (select id_Equipe from Grid g where g.id_Etapa=?) and id_Etapa=? and paga=1;", array (
								$idEtapa,
								$idEtapa 
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
					syslog ( LOG_INFO, "Pegar Equipe " . $paths [0] . "/" . $paths [1] . "/" . $paths [2] );
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
		} else if (strcmp ( $paths [0], "CompetidorInscricao" ) == 0) {
			$id = $paths [1];
			$sql = "SELECT
		`c`.`id` AS `id`,
		`c`.`id` AS `id_Trekker`,
		`c`.`email` AS `email`,
		`c`.`nome` AS `nome`,
		`c`.`fbId` AS `fbId`,
		`c`.`id_Equipe` AS `id_Equipe`,
		`c`.`equipe` AS `equipe`,
		`c`.`categoria` AS `categoria`,
		`ins`.`nome_Equipe` AS `ins_EquipeNome`,
		`ins`.`id_Equipe` AS `ins_EquipeId`,
		`ins`.`categoria` AS `ins_CategoriaNome`,
		`ins`.`id_Categoria` AS `ins_CategoriaId`,
		`ins`.`id_Etapa` AS `id_Etapa`,
		`ins`.`paga` AS `paga`,
		`ins`.`data` AS `ins_Data`
		FROM
		(
				`northdb`.`Competidor` `c` LEFT JOIN
				(select id_Trekker, nome_Equipe, id_Equipe, categoria,id_Categoria,id_Etapa,paga, data from `northdb`.`InscricaoFull` where id_Etapa=?) `ins`
				ON (`ins`.`id_Trekker` = `c`.`id`) )";
			$resp = $this->listTable ( $db, $sql, array (
					$id 
			) );
		} 

		else {
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