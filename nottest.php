<?php
if (1 == 1) {
	syslog ( LOG_INFO, "********* " );
	foreach ( getallheaders () as $name => $value ) {
		
		syslog ( LOG_INFO, "$name: $value '\n" );
	}
	
	$request_body = file_get_contents ( 'php://input' );
	syslog ( LOG_INFO,$request_body );
	syslog ( LOG_INFO, "********* " );
	return;
}
$data = array ();
$data ["message"] = "Ola";
$registrationIds = "6bc3385d2cd29c327be64dc63bb8976584605dabfff2aed30b73dea2397f2fb5";

$ctx = stream_context_create ();
stream_context_set_option ( $ctx, 'ssl', 'local_cert', 'pushcert.pem' );
stream_context_set_option ( $streamContext, 'ssl', 'passphrase', "north001" );

$fp = stream_socket_client ( 'ssl://gateway.push.apple.com:2195', $err, $errstr, 60, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $ctx );

if (! $fp) {
	// Handle Error
}

$body ['aps'] = array (
		'alert' => $data ["message"],
		'sound' => 'default' 
);

$payload = json_encode ( $body );

$deviceToken = $registrationIds;
$msg = chr ( 0 ) . pack ( 'n', 32 ) . pack ( 'H*', $deviceToken ) . pack ( 'n', strlen ( $payload ) ) . $payload;
$result = fwrite ( $fp, $msg, strlen ( $msg ) );
fclose ( $fp );
?>