<?php
	require_once "config.inc.php";
	require_once "api/Dropbox/autoload.php";
	require_once "classes/Datastorage.php";
	require_once "classes/Datastorage_Dropbox.php";
	require_once "classes/Datastorage_Googledrive.php";
	
	//Übergabeparameter zuweisen
	if(isset($_GET['ds_id']))
		$ds_id = htmlentities($_GET['ds_id']);
	if(isset($_GET['ds_path']) and $_GET['ds_path']!="/" and $_GET['ds_path']!=null){
		$ds_path = urldecode($_GET['ds_path']);
	} else {
		$ds_path = "";
	}
	
	
	if(isset($ds_id) && isset($ds_path)){
		//Parameter des Datastorages laden
		$res = mysql_query("select id,name,type,ext_root,token,crypto_key from csp_datastorages
					where id=".mysql_real_escape_string($ds_id)."
					");
		if($res && $arr=mysql_fetch_array($res)){
			switch ($arr["type"]) {
				case "DRPBO":
					$ds = new Datastorage_Dropbox($arr["id"],$arr["name"],$arr["ext_root"],$arr["token"],$arr["crypto_key"],$config_dropbox_appinfo);
					break;
				case "GOODR":
					$ds = new Datastorage_Googledrive($arr["id"],$arr["name"],$arr["ext_root"],$arr["token"],$arr["crypto_key"],$config_googledrive_appinfo);
					break;
				default:
					unset($ds);
					break;
			}
			
			//Datenspeicher ausgewählt
			if(isset($ds)){
				$filedata = $ds->getFile($ds_path);
				
				//Wenn Datei abrufbar
				if($filedata != null && $filedata["is_dir"] == 0){
					header('Content-Description: File Transfer');
				    header('Content-Type: '.$filedata["mime_type"]);
				    header('Content-Disposition: attachment; filename='.$filedata["name"]);
				    header('Expires: 0');
				    header('Cache-Control: must-revalidate');
				    header('Pragma: public');
				    header('Content-Length: ' . $filedata["bytes"]);
					
					//Dateiinhalt ausgeben
					readfile($filedata["tmpfile"]);
					unlink($filedata["tmpfile"]);
				    exit;
			    } elseif($filedata["is_dir"] == 1){
			    	//Wenn Ordner > Ordnerdaten ausgeben
			    	print_r($filedata);
			    }
			
			} else {
				echo "Der Datenspeicher Typ ist unbekannt.";
			}
		} else {
			echo "Der Datenspeicher ist nicht vorhanden.";
		}
	} else {
		echo "Zu wenig Übergabeparameter.";
	}
?>