<?
	//Dropbox Parameter
	$config_dropbox_appinfo = array(
			"key" 		=> "DROPBOX APP KEY",
			"secret" 	=> "DROPBOX APP SECRET"
		);
		
	//Google Drive Parameter
	$config_googledrive_appinfo = array(
			"client_id" => "GOOGLE APP ID",
			"client_secret"	=> "GOOGLE APP SECRET"
	);
	
	//Appinfos in Array speichern
	$config_appinfos = array();
	$config_appinfos["dropbox"] = $config_dropbox_appinfo;
	$config_appinfos["googledrive"] = $config_googledrive_appinfo;
	
	//Datenbank Parameter
	$db = mysql_connect("localhost", "DB USER NAME", "DB USER PASSWD")
    	or die("Keine DB Verbindung möglich.");
	mysql_select_db("usrdb_vapexnb3_csp", $db);
	/* -------------------------------------------------
	--
	-- Tabellenstruktur für Tabelle `csp_datastorages`
	--
	CREATE TABLE IF NOT EXISTS `csp_datastorages` (
	  `id` int(11) NOT NULL,
	  `name` varchar(255) NOT NULL,
	  `type` varchar(5) NOT NULL,
	  `ext_root` varchar(255) NOT NULL,
	  `token` varchar(255) NOT NULL,
	  `primary_storage_id` int(11) DEFAULT NULL,
	  `delta_value` varchar(255) DEFAULT NULL,
	  `crypto_key` varchar(255) DEFAULT NULL,
	  PRIMARY KEY (`id`),
	  UNIQUE KEY `name` (`name`)
	) ENGINE=MyISAM DEFAULT CHARSET=latin1;
	
	--
	-- Musterdatensatz für Tabelle `csp_datastorages`
	--
	INSERT INTO `csp_datastorages` 
	 (`id`, `name`, `type`, `ext_root`, `token`, `primary_storage_id`, `delta_value`, `crypto_key`) VALUES
	 (1, 'Dropbox Ordner 1', 'DRPBO', '/Ordner 1', 'ZUGRIFFSTOKEN HIER EINFÜGEN ', NULL, NULL, NULL)
	 
	--
	-- Tabellenstruktur für Tabelle `csp_datastorages_types`
	--
	
	CREATE TABLE IF NOT EXISTS `csp_datastorages_types` (
	  `id` varchar(5) NOT NULL,
	  `name` varchar(255) NOT NULL,
	  PRIMARY KEY (`name`)
	) ENGINE=MyISAM DEFAULT CHARSET=latin1;
	
	--
	-- Daten für Tabelle `csp_datastorages_types`
	--
	
	INSERT INTO `csp_datastorages_types` (`id`, `name`) VALUES
	('DRPBO', 'Dropbox'),
	('GOODR', 'Google Drive'); 
	  
	*/
	
?>