<?php
include 'DBCrud.php';
include 'connInfo.php';
syslog ( LOG_INFO, "AQUI " . $configArray );
$adfasdf = $configArray;
class LoginApi extends MySQL_CRUD_API {
	public function executeCommand() {
		if (isset ( $_SERVER ['REQUEST_METHOD'] )) {
			header ( 'Access-Control-Allow-Origin: *' );
			$this->headersCommand(NULL);
		}
		if(strcmp($_SERVER ['REQUEST_METHOD'],"OPTIONS")==0){
			return;
		}
		
		
		$request_body = file_get_contents ( 'php://input' );
		$data = json_decode ( $request_body );
		
		if (! $data || ! $data->password || ! $data->email) {
			$this->exitWith ( "Missing parameters", 400 );
		}
		$pwd = md5 ( $data->password );
		$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
		
		$sql = "select * FROM Trekker where email='" . mysqli_real_escape_string ( $db, $data->email ) . "' and password='" . mysqli_real_escape_string ( $db, $pwd ) . "'";
		
		$result = mysqli_query ( $db, $sql );
		
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

$api = new LoginApi ( $configArray );

$api->configArray = $configArray;

$api->executeCommand ();
?>