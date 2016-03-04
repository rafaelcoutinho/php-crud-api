<?php
include 'DBCrud.php';
include 'connInfo.php';

$adfasdf = $configArray;
class UserApi extends MySQL_CRUD_API {
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
		
		if (! $data || ! $data->email) {
			$this->exitWith ( "Missing parameters", 400 );
		}
		$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
		$existingUser = NULL;
		$sqlByEmail = "select * FROM Trekker where email='" . mysqli_real_escape_string ( $db, $data->email ) . "'";
		syslog ( LOG_INFO, $sqlByEmail );
		$result = mysqli_query ( $db, $sqlByEmail );
		
		if ($result->num_rows == 0) {
			
			// criar novo.
			if ($data->fbId == null && ! $data->password) {
				$this->exitWith ( "Missing password", 400, 801 );
			}
			if ($data->password) {
				$pwd = md5 ( $data->password );
			} else {
				$pwd = NULL;
			}
			$params = array (
					mysqli_real_escape_string ( $db, $data->email ),
					mysqli_real_escape_string ( $db, $pwd ),
					mysqli_real_escape_string ( $db, $data->nome ),
					mysqli_real_escape_string ( $db, $data->fbId ),
					mysqli_real_escape_string ( $db, $data->telefone ) 
			);
			$sql = "insert into Trekker (email,password,nome,fbId,telefone) VALUES (?,?,?,?,?)";
			$result = $this->query( $db, $sql, $params );
			if (! $result) {
				$error = $this->getError ( $db );
				$this->exitWith ( 'Failed to insert object: ' . $error, 500, 990 );
			} else {
				$idInserted = $this->insert_id ( $db, $result );
			}
		} else {
			// ja existe, pode estar vindo pelo facebook
			if ($row = $result->fetch_assoc ()) {
				$existingUser = json_encode ( $row );
			}
			$this->close ( $result );
			
			if (! $data->fbId) {
				$this->exitWith ( "Usuario já existe", 403, 800 );
			}
			$params = array ();
			$sql = 'UPDATE "!" SET ';
			$params [] = "Trekker";
			
			if ($existingUser->fbId == null) { // strcmp($data->fbId,$existingUser->fbId)) {
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
		$this->startOutput ( $callback );
		
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
		
		$this->endOutput ( $callback );
	}
}

$api = new UserApi ( $configArray );

$api->configArray = $configArray;

$api->executeCommand ();
?>