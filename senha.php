<?php
use \google\appengine\api\mail\Message;

include 'DBCrud.php';
include 'connInfo.php';
class SenhaApi extends MySQL_CRUD_API {
	protected function removePrefix($request) {
		if (! $request)
			return false;
		
		$pos = strpos ( $request, 'endpoints/senha/' );
		if (! $pos) {
			$pos = strpos ( $request, 'php/' );
			$pos += 4;
		} else {
			$pos += 16;
		}
		
		return $pos ? substr ( $request, $pos ) : '';
	}
	protected function removeResets($db, $email) {
		$sql = "delete from LembrarSenha where email=?";
		$params = array ();
		$params [] = $email;
		$result = $this->query ( $db, $sql, $params );
	}
	protected function geraPedidoLembrete($db, $email) {
		$codigo = $this->generateRandomString ( 6 );
		$sql = "insert into LembrarSenha(email,codigo,validade) values (?,?,?)";
		$milliseconds = (round ( microtime ( true ) * 1000 )) + (60 * 60 * 1000);
		$params = array ();
		$params [] = $email;
		$params [] = $codigo;
		$params [] = $milliseconds;
		$result = $this->query ( $db, $sql, $params );
		return $codigo;
	}
	public function executeCommand() {
		if (isset ( $_SERVER ['REQUEST_METHOD'] )) {
			header ( 'Access-Control-Allow-Origin: *' );
			$this->headersCommand ( NULL );
		}
		if (strcmp ( $_SERVER ['REQUEST_METHOD'], "OPTIONS" ) == 0) {
			return;
		}
		
		$request = parse_url ( $_SERVER ['REQUEST_URI'], PHP_URL_PATH );
		$pathInfo = $this->removePrefix ( $request );
		
		$paths = explode ( "/", $pathInfo );
		syslog ( LOG_INFO, "paths " . serialize ( $paths ) );
		
		$request_body = file_get_contents ( 'php://input' );
		$data = json_decode ( $request_body );
		$data->email = strtolower ( $data->email );
		$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
		
		$resp = null;
		if (strcmp ( $paths [0], "LembrarSenha" ) == 0) {
			header ( 'Content-Type: application/json;charset=utf-8', true );
			$trekker = $this->getEntity ( $db, "select * from Trekker where email=?", array (
					$data->email 
			) );
			if (! $trekker) {
				$this->exitWith ( "Nao existe usuario", 404, 0 );
			}
			$this->removeResets ( $db, $data->email );
			$codigo = $this->geraPedidoLembrete ( $db, $data->email );
			// Notice that $image_data is the raw file data of the attachment.
			try {
				$message = new Message ();
				
				$message->setSender ( "senha@cumeqetrekking.appspotmail.com" );
				$message->addTo ( $data->email );
				$message->setSubject ( "Lembrar Senha NorthApp" );
				$message->setTextBody ( "Um pedido para lembrar senha foi solicitado para o aplicativo NorthApp.\n Abra o seguinte link para  receber uma nova senha.Caso contrário ignore este e-mail. \nhttp://cumeqetrekking.appspot.com/endpoints/senha/Confirma?c=" . $codigo . "&email=" . $data->email . "\n" );
				$message->setHtmlBody ( "<html><body>Um pedido para lembrar senha foi solicitado para o aplicativo NorthApp.<br><a href=\"http://cumeqetrekking.appspot.com/endpoints/senha/Confirma?c=" . $codigo . "&email=" . $data->email . "\">Clique aqui</a> se você deseja receber uma nova senha.<br>Caso contrário ignore este e-mail.</body></html>" );
				syslog ( LOG_INFO, "Iniciado $data->email" );
				$message->send ();
			} catch ( InvalidArgumentException $e ) {
				syslog ( LOG_INFO, "ERRO " . $e );
			}
		} else if (strcmp ( $paths [0], "Confirma" ) == 0) {
			header ( 'Content-Type: text/html;charset=utf-8', true );
			$codigo = $_GET ["c"];
			$email = strtolower ( $_GET ["email"] );
			
			syslog ( LOG_INFO, "Pedido '$codigo' '$email'" );
			$params = array ();
			$params [] = $email;
			$params [] = $codigo;
			$pedido = $this->getEntity ( $db, "select * from LembrarSenha where email=? and codigo=?", $params );
			if (! $pedido) {
				$this->exitWith ( "Pedido invalido", 404, 0 );
			}
			$senhaProvisoria = $this->generateRandomEasyPwd ();
			
			$params = array ();
			$params [] = md5 ( $senhaProvisoria );
			$params [] = $email;
			$result = $this->query ( $db, "update Trekker set password=? where email=?", $params );
			
			try {
				$message = new Message ();
				syslog ( LOG_INFO, "Iniciado $data->email" );
				$message->setSender ( "senha@cumeqetrekking.appspotmail.com" );
				$message->addTo ( $email );
				$message->setSubject ( "Senha Provisória NorthApp" );
				
				$message->setTextBody ( "Uma nova senha foi gerada para você. Você pode acessar o aplicativo agora com a senha '" . $senhaProvisoria . "'." );
				$message->setHtmlBody ( "<html><body>Uma nova senha foi gerada para você. Você pode acessar o aplicativo agora com a senha:<br><h3>" . $senhaProvisoria . "</h3>" );
				$message->send ();
				$this->removeResets ( $db, $data->email );
				$resp = "Uma nova senha foi enviada para seu e-mail.";
			} catch ( InvalidArgumentException $e ) {
				syslog ( LOG_INFO, "ERRO " . $e );
			}
		} else {
			$this->exitWith ( "Sem match " . serialize ( $paths ), 404, 1 );
		}
		
		$this->startOutput ( null );
		echo $resp;
		$this->endOutput ( null );
	}
}

$api = new SenhaApi ( $configArray );

$api->configArray = $configArray;

$api->executeCommand ();
?>