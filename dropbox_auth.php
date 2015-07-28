<html>
<head>
	<meta charset="UTF-8">
	<title>Cloud Storage Pool (CSP) - Dropbox Autorisierung</title>
</head>
<body>
	<h1>Cloud Storage Pool (CSP) - Dropbox Autorisierung</h1>
<?php	
	require_once "config.inc.php";
	require_once "api/Dropbox/autoload.php";
	
	use \Dropbox as dbx;

	$appInfo = dbx\AppInfo::loadFromJson($config_dropbox_appinfo);
	$webAuth = new dbx\WebAuthNoRedirect($appInfo, "PHP-Example/1.0");
	
	if(isset($_POST['auth_code'])){
		list($accessToken, $dropboxUserId) = $webAuth->finish($_POST['auth_code']);
		echo "Autorisierung der App ist erfolgt. <br>";
		echo "Bitte tragen Sie den unten stehenden Access Token in die CSP Konfigruration ein. <br>";
		echo "Access Token: " . $accessToken . "\n";
	} else {
		$authorizeUrl = $webAuth->start();
		if($authorizeUrl){
			echo "1. Klick auf <a href='".$authorizeUrl."' target='_blank'>Link</a>, Autorisierung der App und Kopieren des Codes<br />";
			echo "2. Den Code hier einfügen und absenden:<br /><br />";	
			echo "<form method='post'>";	
			echo "	<input type='text' name='auth_code''>";	
			echo "	<input type='submit' value='Code absenden' class='button' />";	
			echo "</form>";
			echo "3. Den anschließend angezeigten Access Token in die CSP Konfigruration einfügen.<br /><br />";

		} else {
			echo "Keine gültige AuthURL erhelten. Bitte Key und Secret prüfen.";
		}
	}

?>
</body>
</html>