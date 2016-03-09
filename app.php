<?php
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
	protected function getColInfo($result) {
		$colInfo = array ();
		while ( $column_info = $result->fetch_field () ) {
			$colInfo [$column_info->name] = $column_info->type;
			// syslog ( LOG_INFO, $column_info->name . " == " . $column_info->type );
		}
		return $colInfo;
	}
	protected function getObject($row, $colInfo) {
		$keys = array_keys ( $row );
		$response = "{";
		
		foreach ( $row as $key => $value ) {
			$response .= "\"" . $key . "\":";
			if ($colInfo [$key] == 3 || $colInfo [$key] == 8) {
				$response .= $row [$key];
			} else {
				$response .= "\"" . $row [$key] . "\"";
			}
			$response .= ",";
		}
		$response = rtrim ( $response, "," );
		$response .= "}";
		return $response;
	}
	protected function listTable($db, $sql, $params) {
		$response = "";
		syslog ( LOG_INFO, "todas " . $sql );
		if ($result = $this->query ( $db, $sql, $params )) {
			$colInfo = $this->getColInfo ( $result );
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
	protected function getEntity($db, $sql, $params) {
		$response = "";
		
		if ($result = $this->query ( $db, $sql, $params )) {
			$colInfo = $this->getColInfo ( $result );
			if ($row = $this->fetch_assoc ( $result )) {
				
				$response .= $this->getObject ( $row, $colInfo );
			}
			$this->close ( $result );
		} else {
			syslog ( LOG_INFO, "nao achou " );
		}
		
		return $response;
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
				
				$resp = $this->listTable ( $db, "select * from Etapa", array () );
				
				// listar todos
			} else {
				$idEtapa = $paths [1];
				if ($l == 2) {
					$resp = $this->getEntity ( $db, "select * from Etapa where id=?", array (
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
					}
				}
			}
		}else{
			$this->exitWith ( "Sem match ".serialize($paths) , 404, 1 );
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