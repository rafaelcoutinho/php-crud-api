<?php 


include 'DBCrud.php';
include 'connInfo.php';
$api = new MySQL_CRUD_API($configArray);
$api->configArray = $configArray;
$api->executeCommand();



?>