<?php
use \google\appengine\api\mail\Message;
include 'DBCrud.php';
include 'connInfo.php';
class UserApi extends MySQL_CRUD_API {
	protected function checkSenhaTemporaria($db, $email, $password) {
		$sqlByEmail = "select * FROM SenhaTemporaria where email=?";
		$resp = $this->getEntity ( $db, $sqlByEmail, array (
				$email 
		) );
		
		$senhaTemporaria = json_decode ( $resp )->codigo;
		syslog ( LOG_INFO, "comparando $senhaTemporaria com $password = " . (strcmp ( $password, $senhaTemporaria )) );
		if (strcmp ( $password, $senhaTemporaria ) != 0) {
			$this->exitWith ( "Temporaria errada", 403, INVALID_PWD );
		}
		
		$sql = "delete from SenhaTemporaria where email=?";
		$this->query ( $db, $sql, array (
				$email 
		) );
		return $senhaTemporaria;
	}
	public function executeCommand() {
		if (isset ( $_SERVER ['REQUEST_METHOD'] )) {
			header ( 'Access-Control-Allow-Origin: *' );
			$this->headersCommand ( NULL );
		}
		if (strcmp ( $_SERVER ['REQUEST_METHOD'], "OPTIONS" ) == 0) {
			header ( 'Access-Control-Allow-Origin: *' );
			return;
		}
		if (strcmp ( $_SERVER ['REQUEST_METHOD'], "GET" ) == 0) {
			$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
			
			$email = strtolower ( $_GET ["email"] );
			
			if (strlen ( $email ) == 0) {
				$this->exitWith ( "email invalido", 500, 101 );
			}
			$sqlByEmail = "select id,state,nome,fbId,password FROM Trekker where email=?";
			$resp = $this->getEntity ( $db, $sqlByEmail, array (
					$_GET ["email"] 
			) );
			
			$newUser = strlen ( $resp ) == 0;
			$needsAPwd = false;
			if ($newUser == false) {
				if (strcmp ( "ACTIVE", json_decode ( $resp )->state ) == 0) {
					// checar se já tem senha e se é do facebook
					if (strlen ( json_decode ( $resp )->password ) == 0) {
						$needsAPwd = true;
					}
				} else {
					$needsAPwd = true;
				}
			}
			$hasFacebookId = strlen ( json_decode ( $resp )->fbId ) > 0;
			$milliseconds = (round ( microtime ( true ) * 1000 )) + (60 * 60 * 1000);
			if ($newUser || $needsAPwd) {
				$senhaTemp = $this->getEntity ( $db, "select * from SenhaTemporaria where email=?", array (
						$email 
				) );
				if ($senhaTemp == NULL || strlen ( $senhaTemp ) == 0) {
					$sql = "delete from SenhaTemporaria where email=?";
					$this->query ( $db, $sql, array (
							$email 
					) );
					
					$sql = "insert into SenhaTemporaria(email,codigo,validade) values (?,?,?)";
					
					$codigo = $this->generateRandomEasyPwd ();
					$params = array ();
					$params [] = $email;
					$params [] = $codigo;
					$params [] = $milliseconds;
					$result = $this->query ( $db, $sql, $params );
				} else {
					$codigo = json_decode ( $senhaTemp )->codigo;
				}
				
				try {
					$msg = "Um pedido para acessar a NorthBrasil foi feito com seu e-mail.\n\nA senha temporária é '$codigo'\n\nCaso não tenha feito essa solicitação por favor ignore esta mensagem.";
					
					// $message = new Message ();
					// $message->setReplyTo ( "northapp@northbrasil.com.br" );
					// $message->setSender ( "northapp@northbrasil.com.br" );
					// $message->addTo ( $email );
					
					// $message->setSubject ( "Senha Temporária gerada para NorthBrasil" );
					// $message->setTextBody ( $msg );
					
					// $message->setHtmlBody ( "<html><body>Um pedido para acessar a NorthBrasil foi feito com seu e-mail.<br><br>A senha temporária é '$codigo'<br><br>Caso não tenha feito essa solicitação por favor ignore esta mensagem.</body></html>" );
					
					// $message->send ();
					syslog ( LOG_INFO, " " . $msg );
				} catch ( InvalidArgumentException $e ) {
					syslog ( LOG_INFO, "ERRO " . $e );
				}
				
				if ($newUser == true) {
					$this->exitWith ( "No user found ", 404, 911 );
				} else {
					if ($hasFacebookId) {
						$this->exitWith ( "Facebook user missing password user", 404, 914 );
					} else {
						$this->exitWith ( "Missing password user", 404, 912 );
					}
				}
			}
			echo $resp;
			
			return;
		}
		$request_body = file_get_contents ( 'php://input' );
		$data = json_decode ( $request_body );
		
		if (! $data || ! $data->email) {
			$this->exitWith ( "Missing parameters", 400 );
		}
		$data->email = strtolower ( $data->email );
		$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
		$existingUser = NULL;
		$sqlByEmail = "select * FROM Trekker where email=?";
		
		$existingUser = $this->getEntityJson ( $db, $sqlByEmail, array (
				$data->email 
		), true );
		
		$email = $data->email;
		
		if (! $existingUser) {
			syslog ( LOG_INFO, "Usuário novo " . $data->email );
			// criar novo.
			if ($data->fbId == null && ! $data->password) {
				$this->exitWith ( "Missing password", 400, MISSING_PWD );
			}
			
			$senhaTemporaria = $this->checkSenhaTemporaria ( $db, $email, $data->password );
			
			if ($data->password) {
				$pwd = md5 ( $data->password );
			} else {
				$pwd = NULL;
			}
			
			$params = array (
					$data->email,
					$pwd,
					$data->nome,
					$data->fbId,
					$data->telefone,
					"ACTIVE" 
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
			// ja existe, pode estar vindo pelo facebook ou com senha temporaria
			syslog ( LOG_INFO, "Usuário logando com FB ou senha temporaria " . $data->email );
			
			if (! $data->fbId || $data->fbId == NULL) {
				if (strcmp ( $existingUser->state, "ACTIVE" ) == 0 && strlen ( $existingUser->password ) > 0) {
					$this->exitWith ( "Usuario já existe", 403, DUPE_USER );
				} else if (! $data->password) {
					$this->exitWith ( "Senha é obrigatória", 403, CONFIRMING_USER_NO_PWD );
				}
			} else if ($existingUser->fbId != null && strcmp ( $data->fbId, $existingUser->fbId ) < 0) {
				$this->exitWith ( "Usuário FB divergente", 403, DIVERGENT_FB );
			}
			
			$senhaTemporaria = $this->checkSenhaTemporaria ( $db, $email, $data->password );
			
			$params = array ();
			$sql = 'UPDATE "!" SET ';
			$params [] = "Trekker";
			
			if ($existingUser->fbId != null) {
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
			
			if ($data->telefone != null && strlen ( $data->telefone )) {
				$sql .= '"!"=?,';
				$params [] = 'telefone';
				$params [] = $data->telefone;
			}
			if ($data->nome != null && strlen ( $data->nome ) > 0) {
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
			// update usuario com dados novos
			$this->query ( $db, $sql, $params );
			$perror = $this->getError ( $db );
			
			if ($perror) {
				syslog ( LOG_INFO, "error? '" . $perror . "'" );
				$this->exitWith ( 'Erro atualizando dados do competidor' . $perror, 500, 199 );
			}
		}
		
		$result = $this->getEntityJson ( $db, $sqlByEmail, array (
				$data->email 
		), true );
		syslog ( LOG_INFO, "$result " );
		if ($result) {
			echo json_decode ( $result );
		} else {
			$perror = $this->getError ( $db );
			
			if ($perror) {
				syslog ( LOG_INFO, "error? '" . $perror . "'" );
				$this->exitWith ( 'Falhou ao carregar o usuario com o email: ' . $data->email . ': ' . $perror, 500, GENERIC_DB_ERROR );
			} else {
				$this->exitWith ( 'Falhou ao carregar o usuario com o email: ' . $data->email . ': ERRO desconhecido', 500, GENERIC_DB_ERROR );
			}
		}
	}
}

$api = new UserApi ( $configArray );

$api->configArray = $configArray;

$api->executeCommand ();
?>