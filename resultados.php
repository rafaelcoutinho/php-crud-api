<?php
include 'DBCrud.php';
include 'connInfo.php';
class Resultados extends MySQL_CRUD_API {
	public function executeCommand() {
		if (strcmp ( $_SERVER ['REQUEST_METHOD'], "OPTIONS" ) == 0) {
			return;
		}
		header ( 'Content-Type: application/json;charset=utf-8', true );
		$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
		
		if (strcmp ( $_SERVER ['REQUEST_METHOD'], "POST" ) == 0) {
			$request_body = file_get_contents ( 'php://input' );
			
			// echo "'".$request_body."'\n";
			$lines = explode ( "\n", $request_body );
			$titles = str_getcsv ( $lines [0], ";" );
			$json = "[\n";
			
			for($j = 1; $j < count ( $lines ); $j ++) {
				$line = $lines [$j];
				$csvs = str_getcsv ( $line, ";" );
				
				$json .= "{";
				for($i = 0; $i < count ( $csvs ); $i ++) {
					$item = $csvs [$i];
					$json .= "\"" . $titles [$i] . "\":\"" . trim ( $item ) . "\",";
				}
				$json = rtrim ( $json, "," );
				$json .= "},";
			}
			
			$json = rtrim ( $json, "," );
			$json .= "]\n";
			
			$resultado = json_decode ( $json );
			$etapaId = $_GET ["etapa"];
			$resp = array ();
			for($j = 0; $j < count ( $resultado ); $j ++) {
				$entrada = $resultado [$j];
				$largada = $entrada->HoraLargada;
				$horario = explode ( ":", $largada );
				$gridData = $this->getEntity ( $db, "SELECT * FROM Grid g, Equipe e where hora=? and minuto=? and id_Etapa=? and e.id=g.id_Equipe", array (
						$horario [0],
						$horario [1],
						$etapaId 
				) );
				syslog ( LOG_INFO, json_decode ( $gridData )->nome . "/" . $entrada->Piloto );
				
				$entrada->grid = json_decode ( $gridData );
				$resp [] = $entrada;
			}
			
			echo json_encode ( $resp );
			return;
		} else if (strcmp ( $_SERVER ['REQUEST_METHOD'], "PUT" ) == 0) {
			
			$request_body = file_get_contents ( 'php://input' );
			
			$data = json_decode ( $request_body, true );
			$sql = "delete from Resultado where id_Etapa=?";
			$params = array();
			$params[] =  $_GET ["etapa"];
			$totalSemEquipe = 0;
			$this->query($db, $sql, $params);
			$sql = "insert into Resultado (id_Etapa,id_Equipe,colocacao,pcs_pegos,pcs_zerados,pontos_perdidos) values (?,?,?,?,?,?)";
			foreach ( $data as &$value ) {
				$idEquipe=$value ["grid"] ["id"];
				if($idEquipe==NULL){
					$totalSemEquipe++;
					continue;
				}
				$params = array();
				$params[] =  $_GET ["etapa"];
				$params[] =  $idEquipe;
				$params[] =  $value ["Col"];
				$params[] =  $value ["PCPassou"];
				$params[] =  $value ["PCZerado"];
				$params[] =  $value ["PtsPerdidos"];
				$this->query($db, $sql, $params);				
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
