<?php
	require_once "config.inc.php";
	require_once "classes/Datastorage.php";
	require_once "classes/Datastorage_Dropbox.php";
	require_once "classes/Datastorage_Googledrive.php";


	/**
	* getDatastorageByName
	* 
	* Liefert eine Instanz der Klasse Datastorage anhand des Datastorage Namens zurück.
	*
	* @param String		$name				Name des Datastorages
	* @param Arra		$config_appinfos	Appinfos der externen Datenspeicher (definiert in config.inc.php)
	*/
	function getDatastorageByName($name,$config_appinfos){
		$res = mysql_query("select id,name,type,ext_root,token,crypto_key from csp_datastorages
							where primary_storage_id is null
							and name='".$name."'");
		if($res && $arr=mysql_fetch_array($res)){
			switch ($arr["type"]) {
				case "DRPBO":
					$ds = new Datastorage_Dropbox($arr["id"],$arr["name"],$arr["ext_root"],$arr["token"],$arr["crypto_key"],$config_appinfos["dropbox"]);
					break;
				case "GOODR":
					$ds = new Datastorage_Googledrive($arr["id"],$arr["name"],$arr["ext_root"],$arr["token"],$arr["crypto_key"],$config_appinfos["googledrive"]);
					break;
				default:
					unset($ds);
					break;
			}
		}
		if(isset($ds))
			return $ds;
		return null;
	}
	
	/**
	* getDatastorageById
	* 
	* Liefert eine Instanz der Klasse Datastorage anhand der Datastorage Id zurück.
	*
	* @param String		$Id					ID des Datastorages
	* @param Arra		$config_appinfos	Appinfos der externen Datenspeicher (definiert in config.inc.php)
	*/
	function getDatastorageById($id,$config_appinfos){
		$res = mysql_query("select id,name,type,ext_root,token,crypto_key from csp_datastorages
					where id=".$id."
					");
		if($res && $arr=mysql_fetch_array($res)){
			switch ($arr["type"]) {
				case "DRPBO":
					$ds = new Datastorage_Dropbox($arr["id"],$arr["name"],$arr["ext_root"],$arr["token"],$arr["crypto_key"],$config_appinfos["dropbox"]);
					break;
				case "GOODR":
					$ds = new Datastorage_Googledrive($arr["id"],$arr["name"],$arr["ext_root"],$arr["token"],$arr["crypto_key"],$config_appinfos["googledrive"]);
					break;
				default:
					unset($ds);
					break;
			}
		}
		if(isset($ds))
			return $ds;
		return null;
	}


?>