<?php
include 'DBCrud.php';
include 'connInfo.php';

class PubAppApi extends MySQL_CRUD_API {
	public function executeCommand() {
		if (isset($_SERVER['REQUEST_METHOD'])) {
			header('Access-Control-Allow-Origin: *');
		
		}
			
		
		$parameters = $this->getParameters($this->settings);
		switch($parameters['action']){
			case 'list': $this->listCommand($parameters); break;
			case 'read': $this->readCommand($parameters); break;
			//case 'create': $this->createCommand($parameters); break;
			//case 'update': $this->updateCommand($parameters); break;
			//case 'delete': $this->deleteCommand($parameters); break;
			case 'headers': $this->headersCommand($parameters); break;
		}
	}
}
$configArray['column_authorizer']= function($action,$database,$table,$column) { return !($column=='email'&&$action=='list')&& !($column=='password'&&$action=='read')  && !($column=='password'&&$action=='list') && !($column=='telefone'&&$action=='list') ; };
$api = new PubAppApi ( $configArray );

$api->configArray = $configArray;

$api->executeCommand ();
?>	