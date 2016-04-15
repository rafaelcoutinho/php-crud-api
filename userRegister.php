<?php
use \google\appengine\api\mail\Message;
include 'DBCrud.php';
include 'connInfo.php';
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
		$data->email=strtolower($data->email);
		$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
		$existingUser = NULL;
		$sqlByEmail = "select * FROM Trekker where email='" . mysqli_real_escape_string ( $db, $data->email ) . "'";
		
		$result = mysqli_query ( $db, $sqlByEmail );
		
		if ($result->num_rows == 0) {
			syslog ( LOG_INFO, "Usuário novo " . $data->email );
			// criar novo.
			if ($data->fbId == null && ! $data->password) {
				$this->exitWith ( "Missing password", 400, MISSING_PWD );
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
					mysqli_real_escape_string ( $db, $data->telefone ),
					mysqli_real_escape_string ( $db, "ACTIVE" ) 
			);
			$sql = "insert into Trekker (email,password,nome,fbId,telefone,state) VALUES (?,?,?,?,?,?)";
			$result = $this->query ( $db, $sql, $params );
			if (! $result) {
				$error = $this->getError ( $db );
				$this->exitWith ( 'Failed to insert object: ' . $error, 500, GENERIC_DB_ERROR );
			} else {
				$idInserted = $this->insert_id ( $db, $result );
			}
		} else {
			// ja existe, pode estar vindo pelo facebook
			if ($row = $result->fetch_assoc ()) {
				$existingUser = $row;
			}
			
			$this->close ( $result );
			
			if (! $data->fbId || $data->fbId == NULL) {
				if (strcmp ( $existingUser ["state"], "ACTIVE" ) == 0) {
					$this->exitWith ( "Usuario já existe", 403, DUPE_USER );
				} else if (! $data->password) {
					$this->exitWith ( "Senha é obrigatória", 403, CONFIRMING_USER_NO_PWD );
				}
			} else if ($existingUser ["fbId"] != null && strcmp ( $data->fbId, $existingUser ["fbId"] )) {
				$this->exitWith ( "Usuário FB divergente", 403, DIVERGENT_FB );
			}
			$params = array ();
			$sql = 'UPDATE "!" SET ';
			$params [] = "Trekker";
			
			if ($existingUser ["fbId"] == null) { // ) {
				$sql .= '"!"=?,';
				$params [] = 'fbId';
				$params [] = $data->fbId;
			}
			if (! $data->fbId && $data->password) {
				$sql .= '"!"=?,';
				$pwd = md5 ( $data->password );
				$params [] = 'password';
				$params [] = $pwd;
			}
			
			if ($data->telefone != null) {
				$sql .= '"!"=?,';
				$params [] = 'telefone';
				$params [] = $data->telefone;
			}
			if ($data->nome != null) {
				$sql .= '"!"=?,';
				$params [] = 'nome';
				$params [] = $data->nome;
			}
			
			$sql .= '"!"=?,';
			$params [] = 'state';
			$params [] = 'ACTIVE';
			
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
			$this->exitWith ( 'Failed to insert object: ' . $error, 500, GENERIC_DB_ERROR );
		}
	}
}

$api = new UserApi ( $configArray );

$api->configArray = $configArray;

$api->executeCommand ();
?>