<?php

	require_once "config.inc.php";
	require_once "classes/CspWebdav.php";
	
	$server = new CspWebdav();
	set_time_limit ( 60*2 ); //Ausführungszeit auf 2 Minuten erhöhen
	$server->setConfigAppinfos($config_appinfos);
	$server->ServeRequest();

?>