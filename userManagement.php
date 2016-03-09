<?php
include 'DBCrud.php';
include 'connInfo.php';
class UserManagementApi extends MySQL_CRUD_API {
	public function executeCommand() {
		if (isset ( $_SERVER ['REQUEST_METHOD'] )) {
			header ( 'Access-Control-Allow-Origin: *' );
			$this->headersCommand ( NULL );
		}
		syslog ( LOG_INFO, "REQUEST_METHOD " .$_SERVER ['REQUEST_METHOD'] );
		if (strcmp ( $_SERVER ['REQUEST_METHOD'], "OPTIONS" ) == 0) {
			syslog ( LOG_INFO, "options " );
			return;
		}
		syslog ( LOG_INFO, "Rpassou");
		$request_body = file_get_contents ( 'php://input' );
		$data = json_decode ( $request_body );
		syslog ( LOG_INFO, "data " . serialize ( $data ) );
		if (! $data || ! $data->email) {
			$this->exitWith ( "Missing parameters", 400 );
		}
		$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
		$existingUser = NULL;
		$sqlByEmail = "select * FROM Trekker where email='" . mysqli_real_escape_string ( $db, $data->email ) . "'";
		
		$result = mysqli_query ( $db, $sqlByEmail );
		
		if ($result->num_rows == 0) {
			$this->exitWith ( "Usuario nao existe existe", 404, 899 );	
		} else {
			if ($row = $result->fetch_assoc ()) {
				$existingUser = json_encode ( $row );
			}
			$this->close ( $result );
			
			$params = array ();
			$sql = 'UPDATE "!" SET ';
			$params [] = "Trekker";
			
			if ($existingUser->fbId == null) { // ) {
				$sql .= '"!"=?,';
				$params [] = 'fbId';
				$params [] = $data->fbId;
			}
			if ($data->telefone == null) {
				$sql .= '"!"=?,';
				$params [] = 'telefone';
				$params [] = $data->telefone;
			}
			if ($data->nome == null) {
				$sql .= '"!"=?,';
				$params [] = 'nome';
				$params [] = $data->nome;
			}
			$sql = rtrim ( $sql, "," );
			$sql .= ' WHERE "!"=?';
			$params [] = 'email';
			$params [] = $data->email;
			
			$this->query ( $db, $sql, $params );
		}
		
		$result = mysqli_query ( $db, $sqlByEmail );
		
		if ($result->num_rows == 1) {
			// output data of each row
			if ($row = $result->fetch_assoc ()) {
				$row ["password"] = null;
				$this->startOutput ( null );
				echo json_encode ( $row );
				$this->endOutput ( null );
			}
		} else {
			$this->exitWith404 ( "User not found" );
		}
	}
}

$api = new UserManagementApi ( $configArray );

$api->configArray = $configArray;

$api->executeCommand ();
?>