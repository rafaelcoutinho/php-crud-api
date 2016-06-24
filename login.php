<?php
include 'DBCrud.php';
include 'connInfo.php';
class LoginApi extends MySQL_CRUD_API {
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
		
		if (! $data || ! $data->password || ! $data->email) {
			$this->exitWith ( "Missing parameters", 400 );
		}
		$data->email = strtolower ( $data->email );
		$pwd = md5 ( $data->password );
		$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
		
		$sql = "select * FROM Trekker where email=?";
		
		$result = $this->query ( $db, $sql, array (
				$data->email 
		) );
		
		if ($result) {
			// output data of each row
			if ($row = $this->fetch_assoc ( $result )) {
				
				if (strlen ( $row ["password"] ) == 0) {
					if (strlen ( $row ["fbId"] )> 0) {
						$this->exitWith ( "Facebook user", 401, 101 );
					}else{
						$this->exitWith ( "Existing inactive user", 403, 103 );
					}
				} else if (strcmp ( $row ["password"], $pwd ) == 0) {
					
					$row ["password"] = null;
					$this->startOutput ( null );
					echo json_encode ( $row );
					$this->endOutput ( null );
				} else {
					$this->exitWith ( "User/pwd nao conferem", 403, 101 );
				}
			} else {
				$this->exitWith ( "User	nao encontrado", 404, 102 );
			}
		} else {
			$this->exitWith ( "User	 nao encontrado", 404, 101 );
		}
	}
}

$api = new LoginApi ( $configArray );

$api->configArray = $configArray;

$api->executeCommand ();
?>