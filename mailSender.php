<?php
use \google\appengine\api\mail\Message;

include 'gridCommons.php';
class TesteEmail extends GridCommons {
	public function executeCommand() {
		date_default_timezone_set ( 'America/Sao_Paulo' );
		if (isset ( $_SERVER ['REQUEST_METHOD'] )) {
			header ( 'Access-Control-Allow-Origin: *' );
			$this->headersCommand ( NULL );
		}
		if (strcmp ( $_SERVER ['REQUEST_METHOD'], "OPTIONS" ) == 0) {
			return;
		}
		
		$request = parse_url ( $_SERVER ['REQUEST_URI'], PHP_URL_PATH );
		
		$request_body = file_get_contents ( 'php://input' );
		syslog ( LOG_INFO, " post params" . $request_body );
		syslog ( LOG_INFO, " post params" . $_POST ["id_Trekker"] );
		$id_Trekker = $_POST ["id_Trekker"];
		$id_Etapa = $_POST ["id_Etapa"];
		
		if (! $id_Trekker || ! $id_Etapa) {
			syslog ( LOG_INFO, "Falta parametros id_Trekker=" . $id_Trekker );
			syslog ( LOG_INFO, "Falta parametros id_Etapa=" . $id_Etapa );
			$this->exitWith ( "Missing parameters", 400 );
			return;
		}
		
		$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
		$competidor = $this->getEntityJson ( $db, "select * from InscricaoFull where id_Trekker=? and id_Etapa=?", array (
				$id_Trekker,
				$id_Etapa 
		), true );
		$gridInfo = $this->getEntityJson ( $db, "select * from GridFull where id_Equipe=? and id_Etapa=?", array (
				$competidor ["id_Equipe"],
				$id_Etapa 
		), true );
		$gridConfig = $this->getGridConfig ( $db, $gridInfo ["id_Config"] );
		$gridData = $this->getEntityJson ( $db, "select count(*) as numero from Grid where id_Etapa=? and id_Config=? and (hora<? or hora=? and minuto<?)", array (
				$id_Etapa,
				$gridConfig ["id"],
				$gridInfo ["hora"],
				$gridInfo ["hora"],
				$gridInfo ["minuto"] 
		), true );
		$etapa = $this->getEntityJson ( $db, "select e.*,l.nome,l.endereco from Etapa e, Local l where e.id=? and e.id_Local=l.id", array (
				$id_Etapa 
		), true );
		$horario = $gridInfo ["hora"] . ":";
		if ($gridInfo ["minuto"] < 10) {
			$horario .= "0";
		}
		$horario .= $gridInfo ["minuto"];
		$dateEtapa = new DateTime ();
		$dataNowStr = $dateEtapa->format ( 'Y-m-d\TH:i:sP' );
		$dateEtapa->setTimestamp ( $etapa ["data"] / 1000 );
		$dataStr = $dateEtapa->format ( 'Y-m-d\TH:i:sP' );
		
		$googleNowReservation = '<div itemscope itemtype="http://schema.org/EventReservation">
  <meta itemprop="reservationNumber" content="' . $etapa ["id"] . 'E' . $competidor ["id_Equipe"] . 'C' . $competidor ["id_Trekker"] . '"/>
  <meta itemprop="modifiedTime" content="' . $dataNowStr . '"/>
    <meta itemprop="modifyReservationUrl" content="http://app.northbrasil.com.br/open/index.html#/' . $etapa ["id"] . '"/>
    		
  <link itemprop="reservationStatus" href="http://schema.org/Confirmed"/>
  <div itemprop="underName" itemscope itemtype="http://schema.org/Person">
    <meta itemprop="name" content="' . $competidor ["nome"] . '"/>
    		<meta itemprop="email" content="' . $competidor ["email"] . '"/>
  </div>
  <div itemprop="reservationFor" itemscope itemtype="http://schema.org/Event">
    <meta itemprop="name" content="' . $etapa ['titulo'] . '"/>
    <meta itemprop="startDate" content="' . $dataStr . '"/>
    		<div itemprop="performer" itemscope itemtype="http://schema.org/Person">
    			<meta itemprop="name" content="NorthBrasil"/>
    			<meta itemprop="image" content="http://www.northbrasil.com.br/northbrasil/images/logo%202014.png"/>
    		</div>
   	
    		
    		
    
    <div itemprop="location" itemscope itemtype="http://schema.org/Place">
      <meta itemprop="name" content="' . $etapa ["nome"] . '"/>
      <div itemprop="address" itemscope itemtype="http://schema.org/PostalAddress">
        <meta itemprop="streetAddress" content="' . $etapa ["endereco"] . '"/>
        
        <meta itemprop="addressLocality" content="Itu"/>
        <meta itemprop="postalCode" content="13000000"/>
        <meta itemprop="addressRegion" content="SP"/>
        
        <meta itemprop="addressCountry" content="BR"/>
      </div>
    </div>
  </div>
</div>';
		$resp = array ();
		$resp ["numeracao"] = $gridConfig ["numeracao"];
		$resp ["total"] = $gridData ["numero"];
		
		$numeroEquipe = $gridConfig ["numeracao"] + $gridData ["numero"];
		
		$mailMsg = "<html><body>Olá " . $competidor ['nome'] . "! <br>Recebemos a informação de pagamento e agora a sua equipe, " . $competidor ['nome_Equipe'] . ", já está confirmada para a " . $etapa ['titulo'] . ".<br>Guarde as informações:<br><ul><li>Número da sua equipe é " . $numeroEquipe . "</li><li>Horário de largada da equipe é às " . $horario . "</li></ul><br>Lembrando que a Prova Social (arrecadação de doações) desta etapa é " . $etapa ['provaSocial'] . "<br>  Toda doação será revertida para entidades sociais. <br><br>Confira também outras informações do evento:<br>" . $etapa ['extraInfoEmail'] . " " . $googleNowReservation . "</body></html>";
		
		try {
			$message = new Message ();
			$message->setReplyTo ( "northapp@northbrasil.com.br" );
			$message->setSender ( "northapp@northbrasil.com.br" );
			
			// $message->addTo ( "schema.whitelisting+sample@gmail.com" );
			$message->addTo ( "atendimento@northbrasil.com.br" );
			
			$message->addTo ( $competidor ["email"] );
			$message->setSubject ( "Confirmação de pagamento" );
			syslog ( LOG_INFO, $competidor ["email"] );
			$message->setHtmlBody ( $mailMsg );
			
			$message->send ();
			$resp ["ok"] = true;
		} catch ( InvalidArgumentException $e ) {
			syslog ( LOG_INFO, "ERRO " . $e );
			$resp ["ok"] = false;
		}
		
		$this->startOutput ( null );
		echo json_encode ( $resp );
		$this->endOutput ( null );
	}
}

$api = new TesteEmail ( $configArray );

$api->configArray = $configArray;

$api->executeCommand ();
?>
