<html>
<head>
	<meta charset="UTF-8">
	<title>Cloud Storage Pool (CSP) - Google Drive Autorisierung</title>
</head>
<body>
	<h1>Cloud Storage Pool (CSP) - Google Drive Autorisierung</h1>
<?php
require_once 'config.inc.php';
set_include_path("api/google-api-php-client/src" . PATH_SEPARATOR . get_include_path());
require_once 'Google/Client.php';

$client_id = $config_googledrive_appinfo["client_id"];
$client_secret = $config_googledrive_appinfo["client_secret"];
$redirect_uri = 'https://CSPSERVERPFAD/csp/googledrive_auth.php';

$client = new Google_Client();
$client->setClientId($client_id);
$client->setClientSecret($client_secret);
$client->setRedirectUri($redirect_uri);
$client->setAccessType("offline");
$client->addScope("https://www.googleapis.com/auth/drive");

$authUrl = $client->createAuthUrl();

if (isset($_GET['code'])) {
	$client->authenticate($_GET['code']);
	echo "Autorisierung der App ist erfolgt. <br>";
	echo "Bitte tragen Sie den unten stehenden Access Token in die CSP Konfigruration ein. <br>";
	echo "Access Token: " . $client->getAccessToken() . "\n";
} else {
	if(isset($authUrl)){
		echo "1. Klick auf <a href='".$authUrl."' target='_blank'>Link</a>, Autorisierung der App und Kopieren des Codes<br />";
		echo "2. Den anschließend angezeigten Access Token in die CSP Konfigruration einfügen.<br /><br />";
	} else {
		echo "Keine gültige AuthURL erhalten. Bitte ID und Secret prüfen.";
	}
}

?>
</body>
</html>