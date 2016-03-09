<?php
include 'DBCrud.php';
include 'connInfo.php';

$adfasdf = $configArray;
class PubAppApi extends MySQL_CRUD_API {
	public function executeCommand() {
		if (isset($_SERVER['REQUEST_METHOD'])) {
			header('Access-Control-Allow-Origin: *');
			header('pub: true');
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

$api = new PubAppApi ( $configArray );

$api->configArray = $configArray;

$api->executeCommand ();
?>