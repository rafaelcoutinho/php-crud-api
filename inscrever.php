<?php
use \google\appengine\api\mail\Message;
include 'DBCrud.php';
include 'connInfo.php';

$adfasdf = $configArray;
class InscricaoApi extends MySQL_CRUD_API {
	protected function validateInscricao($db, $id_Equipe, $id_Etapa, $id_Lider, $integrantes_Ids, $integrantes_remover) {
		
		// 1. O líder pode mudar de equipe, se estiver com a inscrição não paga
		// 2. Remove os participantes a serem removidos SE não estiverem com pagamento feito.
		// 3. Checar se os integrantes estão inscritos (pagos ou nao) em outras equipes. Se estiverem falha.
		// 4. Apagar qqer inscricao desses desta prova, e recriar.
		{ // 1
			$params = array (
					$id_Etapa,
					$id_Lider 
			);
			syslog ( LOG_INFO, "Checando se o líder tem uma inscrição" );
			$result = $this->getEntity ( $db, 'select id_Trekker, id_Equipe, paga from Inscricao where id_Etapa=? and id_Trekker=?', $params );
			if ($result != null) {
				if (json_decode ( $result )->paga == 1 && json_decode ( $result )->id_Equipe != $id_Equipe) {
					$this->exitWith ( "Lider já está com pagamento confirmadoe e não pode mudar de equipe.", 500, 662 );
				} else {
					syslog ( LOG_INFO, "Lider não possui inscrição paga em outra equipe." );
					$result = $this->query ( $db, 'delete from Inscricao where id_Trekker=?', array (
							$id_Lider 
					) );
				}
			} else {
				syslog ( LOG_INFO, "Não encontrou nenhuma inscrição do lider" );
			}
		}
		
		{ // 2
			$sqlParam = "";
			$sqlTrekkerParams = array ();
			$sqlTrekkerParams [] = $id_Equipe;
			$sqlTrekkerParams [] = $id_Etapa;
			
			for($i = 0; $i < count ( $integrantes_Ids ); $i ++) {
				if ($integrantes_Ids [$i]->id_Trekker != null) {
					$sqlParam .= '?,';
					$sqlTrekkerParams [] = $integrantes_Ids [$i]->id_Trekker;
				}
			}
			
			$sqlParam .= '?';
			$sqlTrekkerParams [] = $id_Lider;
			syslog ( LOG_INFO, "Procura se está removendo algum integrante que tem inscrição paga." );
			$result = $this->query ( $db, 'select id_Trekker from Inscricao where id_Equipe=? and id_Etapa=? and id_Trekker not in (' . $sqlParam . ')  and paga=(1)', $sqlTrekkerParams );
			syslog ( LOG_INFO, "result->num_rows " . $result->num_rows );
			if ($result) {
				if ($row = $this->fetch_row ( $result )) {
					syslog ( LOG_INFO, "Inscrição não pode ser alterada" );
					$this->exitWith ( "Inscricao tenta remover participante com status pago", 500, 661 );
				}
			}
			$this->close ( $result );
		}
		
		{ // 3
			
			for($i = 0; $i < count ( $integrantes_Ids ); $i ++) {
				if ($integrantes_Ids [$i]->id_Trekker != null) {
					$result = $this->getEntity ( $db, 'select id_Trekker, nome, nome_Equipe from InscricaoFull where id_Equipe<>? and id_Etapa=? and id_Trekker=?', array (
							$id_Equipe,
							$id_Etapa,
							$integrantes_Ids [$i]->id_Trekker 
					) );
					
					if ($result) {
						$row = json_decode ( $result );
						
						syslog ( LOG_INFO, "Inscrição não pode ser alterada participante em outra equipe" );
						$this->exitWith ( "Participante '" . $row->nome . "' já inscrito em outra equipe '" . $row->nome_Equipe . "'", 500, 663 );
					}
				}
			}
		}
		{ // 3.1 remover integrantes a remover
			if (count ( $integrantes_remover ) > 0) {
				$sqlParam = "";
				$sqlTrekkerParams = array ();
				$now = (round ( microtime ( true ) * 1000 ));
				syslog ( LOG_INFO, "Há  " . count ( $integrantes_remover ) . " para remover" );
				for($i = 0; $i < count ( $integrantes_remover ); $i ++) {
					syslog ( LOG_INFO, "A remover: " . json_decode ( $integrantes_remover ) );
					if ($integrantes_remover [$i]->id_Trekker != null) {
						
						$sqlParam .= '?,';
						$sqlTrekkerParams [] = $integrantes_remover [$i]->id;
						$this->query ( $db, 'insert into InscricaoLog (id_Trekker,id_Equipe,id_Etapa,data,id_Lider,acao) VALUES (?,?,?,?,?,?)', array (
								$integrantes_remover [$i]->id_Trekker,
								$id_Equipe,
								$id_Etapa,
								$now,
								$id_Lider,
								"Removeu inscrição" 
						) );
					}
				}
				
				$sqlParam = rtrim ( $sqlParam, "," );
				$sqlTrekkerParams [] = $id_Equipe;
				$sqlTrekkerParams [] = $id_Etapa;
				
				$result = $this->query ( $db, 'delete from Inscricao where id_Trekker in (' . $sqlParam . ') and id_Equipe=? and id_Etapa=? and paga<>(1)', $sqlTrekkerParams );
				$affected = $this->affected_rows ( $db, $result );
				syslog ( LOG_INFO, "Apagou  " . $affected . " inscricoes" );
			}
		}
		{ // 4
			if (count ( $integrantes_Ids ) > 0) {
				syslog ( LOG_INFO, "Há  " . count ( $integrantes_Ids ) . " para inserir, vamos apagar qqer inscricao deles para depois recria-las" );
				$sqlParam = "";
				$sqlTrekkerParams = array ();
				for($i = 0; $i < count ( $integrantes_Ids ); $i ++) {
					if ($integrantes_Ids [$i]->id_Trekker != null) {
						
						$sqlParam .= '?,';
						$sqlTrekkerParams [] = $integrantes_Ids [$i]->id_Trekker;
					}
				}
				
				$sqlParam .= '?';
				$sqlTrekkerParams [] = $id_Lider;
				$sqlTrekkerParams [] = $id_Etapa;
				$result = $this->query ( $db, 'delete from Inscricao where id_Trekker in (' . $sqlParam . ')  and id_Etapa=? and paga<>(1)', $sqlTrekkerParams );
				$affected = $this->affected_rows ( $db, $result );
				syslog ( LOG_INFO, "Apagou  " . $affected . " inscricoes" );
			}
		}
	}
	protected function createInscricao($db, $id_Trekker, $id_Equipe, $id_Etapa, $milliseconds, $id_Lider) {
		$params = array (
				mysqli_real_escape_string ( $db, $id_Trekker ),
				mysqli_real_escape_string ( $db, $id_Equipe ),
				mysqli_real_escape_string ( $db, $id_Etapa ),
				mysqli_real_escape_string ( $db, $milliseconds ),
				mysqli_real_escape_string ( $db, $id_Lider ) 
		);
		$result = $this->query ( $db, 'INSERT INTO Inscricao (id_Trekker,id_Equipe,id_Etapa,data,id_Lider) VALUES (?,?,?,?,?)', $params );
		syslog ( LOG_INFO, "createInscricao  " . $result );
		$perror = $this->getError ( $db );
		syslog ( LOG_INFO, "error? '" . $perror . "'" );
		if ($perror) {
			$this->query ( $db, "ROLLBACK" );
			$this->exitWith ( 'Erro inserindo inscrição ' . $perror, 500, 199 );
		}
	}
	protected function saveTrekker($db, $data, $type) {
		// criar novo.
		if ($data->nome == null) {
			$this->exitWith ( "Missing nome trekker", 401, 801 );
		}
		if (filter_var ( $data->email, FILTER_VALIDATE_EMAIL )) {
			
			$type = "PASSIVE_EMAIL";
		}
		
		$params = array (
				mysqli_real_escape_string ( $db, $data->email ),
				mysqli_real_escape_string ( $db, $pwd ),
				mysqli_real_escape_string ( $db, $data->nome ),
				mysqli_real_escape_string ( $db, $data->fbId ),
				mysqli_real_escape_string ( $db, $data->telefone ),
				mysqli_real_escape_string ( $db, $type ) 
		);
		$sql = "insert into Trekker (email,password,nome,fbId,telefone,state) VALUES (?,?,?,?,?,?)";
		$result = $this->query ( $db, $sql, $params );
		if (! $result) {
			$error = $this->getError ( $db );
			$this->exitWith ( 'Failed to insert object: ' . $error, 500, 990 );
		} else {
			$idInserted = $this->insert_id ( $db, $result );
		}
		return $idInserted;
	}
	protected function saveEquipe($db, $data) {
		// criar novo.
		if ($data->nome == null) {
			$this->exitWith ( "Missing nome equipe", 401, 701 );
		}
		if ($data->id_Categoria == null) {
			$this->exitWith ( "Missing categoria equipe", 401, 702 );
		}
		
		$params = array (
				mysqli_real_escape_string ( $db, $data->nome ),
				mysqli_real_escape_string ( $db, $data->descricao ),
				mysqli_real_escape_string ( $db, $data->id_Categoria ),
				mysqli_real_escape_string ( $db, "INSCRIPTION" ) 
		);
		$sql = "insert into Equipe (nome,descricao,id_Categoria,state) VALUES (?,?,?,?)";
		$result = $this->query ( $db, $sql, $params );
		if (! $result) {
			$error = $this->getError ( $db );
			$this->exitWith ( 'Failed to insert object: ' . $error, 500, 703 );
		} else {
			$idInserted = $this->insert_id ( $db, $result );
		}
		return $idInserted;
	}
	public function executeCommand() {
		if (isset ( $_SERVER ['REQUEST_METHOD'] )) {
			header ( 'Access-Control-Allow-Origin: *' );
			$this->headersCommand ( NULL );
		}
		if (strcmp ( $_SERVER ['REQUEST_METHOD'], "OPTIONS" ) == 0) {
			return;
		}
		if (strcmp ( $_SERVER ['REQUEST_METHOD'], "DELETE" ) == 0) {
			$idEquipe = $_GET ["id_Equipe"];
			$idEtapa = $_GET ["id_Etapa"];
			$idTrekker = $_GET ["id_Trekker"];
			$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
			
			$result = $this->query ( $db, 'DELETE FROM Inscricao WHERE id_Equipe = ? and id_Etapa=? and id_Trekker=?', array (
					$idEquipe,
					$idEtapa,
					$idTrekker 
			) );
			$num = $this->affected_rows ( $db, $result );
			
			$this->startOutput ( $callback );
			
			echo json_encode ( $num );
			$this->endOutput ( null );
			return;
		}
		
		$request_body = file_get_contents ( 'php://input' );
		$data = json_decode ( $request_body );
		if (! $data->lider || ! $data->etapa || ! $data->equipe) {
			$this->exitWith ( "Missing parameters", 400 );
			return;
		}
		$data->email = strtolower ( $data->email );
		$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
		
		$this->query ( $db, "START TRANSACTION" );
		$this->query ( $db, "BEGIN" );
		$idEquipe = $data->equipe->id;
		$isEquipeNew = false;
		if (! $idEquipe || $idEquipe == - 1) {
			syslog ( LOG_INFO, "Equipe nova, criando" );
			$isEquipeNew = true;
			$idEquipe = $this->saveEquipe ( $db, $data->equipe );
		}
		if (! $idEquipe || $idEquipe == - 1) {
			$this->query ( $db, "ROLLBACK" );
			$this->exitWith ( "FAIL_INSERT_EQUIPE", 500, 701 );
		}
		$idEquipeDoLider = null;
		$idLider = $data->lider->id;
		if ($data->lider->id == - 1) {
			syslog ( LOG_INFO, "Lider é novo usuário, criando" );
			$idLider = $this->saveTrekker ( $db, $data->lider, "INSCRIPTION" );
		}
		$milliseconds = (round ( microtime ( true ) * 1000 ));
		
		if ($idLider == - 1) {
			$this->query ( $db, "ROLLBACK" );
			$this->exitWith ( "FAIL_INSERT_LEADER", 500, 801 );
		}
		syslog ( LOG_INFO, "Lider id é " . $idLider );
		if ($isEquipeNew) {
			syslog ( LOG_INFO, "Equipe é nova, associando lider" );
			$this->query ( $db, "insert into Trekker_Equipe(id_Trekker,id_Equipe,start) values(?,?,?)", array (
					$idLider,
					$idEquipe,
					$milliseconds 
			) );
			$idEquipeDoLider = $idEquipe;
		}
		// validar
		
		$this->validateInscricao ( $db, $idEquipe, $data->etapa->id, $idLider, $data->integrantes, $data->removidos );
		
		// $this->associaTrekkerEquipe($db,$idLider,$idEquipe,$milliseconds);
		$this->createInscricao ( $db, $idLider, $idEquipe, $data->etapa->id, $milliseconds, $idLider );
		
		syslog ( LOG_INFO, "Há " . count ( $data->integrantes ) . "  integrantes " );
		$integrantes = array ();
		for($i = 0; $i < count ( $data->integrantes ); $i ++) {
			
			$integrante = $data->integrantes [$i];
			
			$idIntegrante = $integrante->id_Trekker;
			
			if (! $idIntegrante) {
				$idIntegrante = $this->saveTrekker ( $db, $integrante, "PASSIVE" );
			}
			
			$this->createInscricao ( $db, $idIntegrante, $idEquipe, $data->etapa->id, $milliseconds, $idLider );
			$integrantes [] = array (
					'id_Trekker' => $idIntegrante 
			);
		}
		
		$equipe = array (
				'id' => $idEquipe 
		);
		$lider = array (
				'id' => $idLider,
				'id_Equipe' => $idEquipeDoLider 
		);
		$inscricao = array (
				'equipe' => $equipe,
				'lider' => $lider,
				'integrantes' => $integrantes 
		);
		$this->query ( $db, "COMMIT" );
		
		$equipeInfo = json_decode ( $this->getEntity ( $db, "select * from Equipe where id=?", array (
				$idEquipe 
		), true ) );
		$etapaInfo = json_decode ( $this->getEntity ( $db, "select e.*, l.nome as local from Etapa e, Local l where e.id=? and e.id_Local=l.id", array (
				$data->etapa->id 
		), true ) );
		date_default_timezone_set ( 'America/Sao_Paulo' );
		$liderInfo = $this->sendConfEmailLider ( $db, $idLider, $equipeInfo, $etapaInfo );
		for($i = 0; $i < count ( $integrantes ); $i ++) {
			$this->sendConfEmailIntegrantes ( $db, $data->integrantes [$i]->id_Trekker, $equipeInfo, $etapaInfo, $liderInfo );
		}
		$this->startOutput ( $callback );
		
		echo json_encode ( $inscricao );
		$this->endOutput ( null );
	}
	protected function sendConfEmailLider($db, $idLider, $equipeInfo, $etapaInfo) {
		$liderInfo = $this->getEntity ( $db, "select * from Trekker where id=?", array (
				$idLider 
		), true );
		if (! $liderInfo) {
			syslog ( LOG_INFO, "erro carregando lider" );
		}
		$liderInfo = json_decode ( $liderInfo );
		
		$msgText = "ENDURO A PÉ NORTHBRASIL\n" . $etapaInfo->titulo . "\nCOPA NORTH 2016\n\nParabéns " . $liderInfo->nome . "! A inscrição da Equipe " . $equipeInfo->nome . " foi efetuada com sucesso! Seus dados foram cadastrados para " . $etapaInfo->titulo . ", " . $etapaInfo->local . ", " . gmdate ( "d/m", ($etapaInfo->data / 1000) ) . " \n\n"; // TODO colocar data
		$msgText .= $this->getDefText ( $etapaInfo );
		try {
			$message = new Message ();
			
			$message->setSender ( "inscricao@cumeqetrekking.appspotmail.com" );
			// $message->addTo ( $liderInfo->email );
			
			$message->addTo ( "rafael.coutinho+test@gmail.com" );
			$message->addTo ( "logistica@northbrasil.com.br" );
			
			$message->setSubject ( "Inscrição na CopaNorth" );
			$message->setTextBody ( $msgText );
			
			// $message->setHtmlBody ();
			
			$message->send ();
		} catch ( Exception $e ) {
			syslog ( LOG_INFO, "ERRO " . $e );
		}
		
		
		return $liderInfo;
	}
	protected function sendConfEmailIntegrantes($db, $idIntegrante, $equipeInfo, $etapaInfo, $liderInfo) {
		$integranteInfo = $this->getEntity ( $db, "select * from Trekker where id=?", array (
				$idIntegrante 
		), true );
		if (! $integranteInfo) {
			syslog ( LOG_INFO, "erro carregando integrante" );
		}
		$integranteInfo = json_decode ( $integranteInfo );
		if ($integranteInfo->email == null || strlen ( $integranteInfo->email )==0) {
			syslog ( LOG_INFO, "Integrante não possui email" );
			return;
		}
		
		$integranteInfo = json_decode ( $integranteInfo );
		$msgText = "ENDURO A PÉ NORTHBRASIL\n" . $etapaInfo->titulo . "\nCOPA NORTH 2016\n\nParabéns " . $integranteInfo->nome . "! " . $liderInfo->nome . " já fez sua inscrição e os dados da Equipe  " . $equipeInfo->nome . " foi efetuada com sucesso! Seus dados foram cadastrados para " . $etapaInfo->titulo . ", " . $etapaInfo->local . ", " . gmdate ( "d/m", ($etapaInfo->data / 1000) ) . " \n\n"; // TODO colocar data
		$msgText .= $this->getDefText ( $etapaInfo );
		try {
			$message = new Message ();
			
			$message->setSender ( "inscricao@cumeqetrekking.appspotmail.com" );
			// $message->addTo ( $liderInfo->email );
			
			$message->addTo ( "rafael.coutinho+test@gmail.com" );
			$message->addTo ( "logistica@northbrasil.com.br" );
			
			$message->setSubject ( "Inscrição na CopaNorth" );
			$message->setTextBody ( $msgText );
			
			// $message->setHtmlBody ();
			
			$message->send ();
		} catch ( Exception $e ) {
			syslog ( LOG_INFO, "ERRO " . $e );
		}
		
		
	}
	protected function getDefText($etapaInfo) {
		$txt = "Agora sua equipe já consta no PRÉ-GRID da prova. Para confirmar a inscrição e ter a equipe listado no GRID FINAL, com horário de largada oficial, siga os procedimentos abaixo:\n\n";
		
		$txt .= "1) PAGAMENTO\nEscolha uma das contas abaixo para pagamento/transferência bancária:\n";
		$txt .= ".BRADESCO - AG 2297-7  /  CC 109.704-0  /  Fav: Sílvia H. Andreo e ou\n";
		$txt .= ".ITAÚ - AG 4271  /  CC 01565-5  /  Fav: Sílvia H. Andreo e ou\n";
		$txt .= ".BANCO DO BRASIL - AG 1515-6  /  CC 6108-5  /  Fav: Sílvia H. Andreo\n";
		$txt .= "\n";
		$txt .= "Em caso de DOC informar o CPF do favorecido. Solicitar o No. do CPF do favorecido pelo Tel (19) 3289-5281, WhatsApp (19) 98142-6043 ou recibo@northbrasil.com.br.\n";
		$txt .= "\n";
		$txt .= "2) COMPROVANTE DE PAGAMENTO\n";
		$txt .= "Encaminhe o comprovante para recibo@northbrasil.com.br ou por WhatsApp (19) 98142-6043, informando:\n";
		$txt .= ".Nome da equipe;\n";
		$txt .= ".Categoria (Turismo, Trekkers, Graduados, Pro);\n";
		$txt .= ".No. telefone celular para contato\n";
		$txt .= "\n";
		$txt .= "3) VALOR DE INSCRIÇÃO POR PARTICIPANTE:\n";
		$txt .= ".Lote I (pagto até " . gmdate ( "d/m", ($etapaInfo->dataLimiteLote1 / 1000) ) . "): R$" . $etapaInfo->precoLote1 . " + Prova Social*\n"; // TODO pegar da etapa
		$txt .= ".Lote II (pagto até " . gmdate ( "d/m", ($etapaInfo->dataLimiteLote2 / 1000) ) . "): R$" . $etapaInfo->precoLote2 . " + Prova Social*\n";
		$txt .= ".Lote III: R$" . $etapaInfo->precoLote3 . " + Prova Social*\n";
		$txt .= "\n";
		$txt .= "*Prova Social da etapa (item para doação) = " . $etapaInfo->provaSocial . "\n";
		$txt .= "\n";
		$txt .= "Toda doação arrecadada é revertida para a cidade sede do evento. A doação é variável a cada etapa e deve ser entregue no Check-in.\n";
		$txt .= "\n";
		$txt .= "Acompanhe todas as informações do evento em www.northbrasil.com.br e  www.facebook.com/northbrasil: cronograma e briefing eletrônico, dicas para iniciantes, o que levar, como chegar, estrutura do local das provas, horários e tudo sobre o evento.\n";
		$txt .= "\n";
		$txt .= "NORTHBRASIL\n";
		$txt .= "www.northbrasil.com.br\n";
		$txt .= "contato@northbrasil.com.br\n";
		$txt .= "www.facebook.com/northbrasil\n";
		$txt .= "Tel (19) 3289-5281\n";
		$txt .= "Cel, WhatsApp (19) 98142-6043\n";
		return $txt;
	}
}

$api = new InscricaoApi ( $configArray );

$api->configArray = $configArray;

$api->executeCommand ();
?>