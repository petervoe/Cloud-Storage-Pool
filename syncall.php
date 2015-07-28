<?php
	require_once "config.inc.php";
	require_once "api/Dropbox/autoload.php";
	require_once "classes/Datastorage.php";
	require_once "classes/Datastorage_Dropbox.php";
	require_once "classes/Datastorage_Googledrive.php";

	//Gesamte Ausgabe als Textdatei
    header('Content-Description: CSP Sync Log');
    header('Content-Type: text/plain; charset=UTF-8');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
	
	//set_time_limit ( 30 ); //Ausführungszeit bei Bedarf erhöhen
   echo "*** Synchronisation gestartet. ***\n";
   
   //Primäre Storages der ersten Ebene abrufen (cd1.primary_storage_id is null)
   $res_pri_top = mysql_query("select distinct cd1.id,cd1.name,cd1.ext_root,cd1.token,cd1.crypto_key,cd1.type,cd1.delta_value from csp_datastorages cd1
					join csp_datastorages cd2 on(cd1.id = cd2.primary_storage_id)
					where cd1.primary_storage_id is null");
   while($arr_pri_top = mysql_fetch_array($res_pri_top)	){
	   	//Variable zurücksetzen, da neues primäres Storage oberster Ebene geladen wird
   		unset($delta_done); 
   		unset($ds_cursor);
   		echo "\n### Hauptstorage: (".$arr_pri_top["id"].") ".$arr_pri_top["name"]." ###\n";
		while($res_pri = mysql_query("select distinct cd1.id,cd1.name,cd1.ext_root,cd1.token,cd1.crypto_key,cd1.type,cd1.delta_value from csp_datastorages cd1
						join csp_datastorages cd2 on(cd1.id = cd2.primary_storage_id)
						where cd1.primary_storage_id ".
						(!isset($ds_cursor)?"is null and cd1.id=".$arr_pri_top["id"]:"in (".$ds_cursor.")"))
		 ){
		 	$ds_cursor = "";
			while($res_pri && $arr_pri=mysql_fetch_array($res_pri)){
				echo "> (".$arr_pri["id"].") ".$arr_pri["name"];
				
				if(strlen($ds_cursor)==0)
					$ds_cursor = $arr_pri["id"];
				else
					$ds_cursor .= ",".$arr_pri["id"];
				
				//Primäres Datastorage instanzieren
				switch ($arr_pri["type"]) {
					case "DRPBO":
						$ds_pri = new Datastorage_Dropbox($arr_pri["id"],$arr_pri["name"],$arr_pri["ext_root"],$arr_pri["token"],$arr_pri["crypto_key"],$config_dropbox_appinfo);
						break;
					case "GOODR":
						$ds_pri = new Datastorage_Googledrive($arr_pri["id"],$arr_pri["name"],$arr_pri["ext_root"],$arr_pri["token"],$arr_pri["crypto_key"],$config_googledrive_appinfo);
						break;
					default:
						unset($ds_pri);
						break;
				}
				if(isset($ds_pri)){
				
					//Deltaliste des primären Datastorages abrufen
					$delta_pri = $ds_pri->getDelta($arr_pri["delta_value"]);
					
					//print_r($delta_pri); //Anzeige der Deltawerte bei Bedarf aktivieren
					
					//Zugehöriges sekundäres Datastorage laden
					$res_sec = mysql_query("select distinct id,name,ext_root,token,crypto_key,type,delta_value from csp_datastorages 
							where primary_storage_id = ".$arr_pri["id"].";
							");
					if($res_sec && $arr_sec=mysql_fetch_array($res_sec)){
						echo " <<>> (".$arr_sec["id"].") ".$arr_sec["name"]."\n";
						
						//Sekundäres Datastorage instanzieren
						switch ($arr_sec["type"]) {
							case "DRPBO":
								$ds_sec = new Datastorage_Dropbox($arr_sec["id"],$arr_sec["name"],$arr_sec["ext_root"],$arr_sec["token"],$arr_sec["crypto_key"],$config_dropbox_appinfo);
								break;
							case "GOODR":
								$ds_sec = new Datastorage_Googledrive($arr_sec["id"],$arr_sec["name"],$arr_sec["ext_root"],$arr_sec["token"],$arr_sec["crypto_key"],$config_googledrive_appinfo);
								break;
							default:
								unset($ds_sec);
								break;
						}
						if(isset($ds_sec)){	
							//Deltaliste des sekundären Datastorages abrufen
							$delta_sec = $ds_sec->getDelta($arr_sec["delta_value"]);
							
							//print_r($delta_sec); //Anzeige der Deltawerte bei Bedarf aktivieren
						} else {
							echo "Der Typ des sekundären Datenspeichers (".$arr_pri["id"].") ".$arr_pri["name"]." ist nicht vorhanden.\n";
						}
					} 
				} else {
					echo "Der Typ des primären Datenspeichers (".$arr_pri["id"].") ".$arr_pri["name"]." ist nicht vorhanden.\n";
				}
		
				//Wenn bereits eine Synchronisation von einem höherwertigen Datastorage erfolgt ist,
				// muss diese nach unten weitergegeben werden
				if(isset($delta_done)){
					foreach ($delta_done as $key => $value) {
						$delta_pri["entries"][] = $value;
					}
				}
		
				//Alle abgearbeiteten Deltas um Doppelbearbeitungen zu verhindern
				$delta_done = Array();
				
				while( ( isset($delta_pri["entries"]) && count($delta_pri["entries"])>0 )
					|| ( isset($delta_sec["entries"]) && count($delta_sec["entries"])>0 )){		
					//Dateikonflikte zwischen primärer und sekundärer Deltaliste suchen
					if(isset($delta_pri["entries"]) && isset($delta_sec["entries"])){
						foreach ($delta_pri["entries"] as $key_pri => $value_pri) {
							foreach ($delta_sec["entries"] as $key_sec => $value_sec) {
								//Wenn beide Dateien geändert wurden
								if(	$value_pri["path"]==$value_sec["path"] &&
									$value_sec["action"]=="upload"){
									//Konflikt erkannt (Datei wurde auf primärem Datastorage geändert oder gelöscht)
									//Datei von sekundärem Datastorage als Konflikdatei in primären Datastorage kopieren
									try {
										$filedata = $ds_sec->getFile("/".$value_sec["path"]);
										$konflict_path = "/KONFLIKT".date('YmdHis')."_".$value_pri["path"];
										$ds_pri->uploadFile($filedata["tmpfile"],$konflict_path);
										$ds_sec->uploadFile($filedata["tmpfile"],$konflict_path);
										unlink($filedata["tmpfile"]);
										
										$delta_done_entry["action"] = "upload";
										$delta_done_entry["path"] = $konflict_path;
										$delta_done[] = $delta_done_entry;
										
										echo "Konfliktdatei '".$value_sec["path"]."' von '".$arr_pri["id"].", ".$arr_pri["name"]."' hochgeladen.\n";;
									} catch (Exception $e) {
									    echo "Konfliktdatei '".$value_sec["path"]."' von '".$arr_pri["id"].", ".$arr_pri["name"]."' kann nicht hochgeladen werden.\n";
									}
									//Änderungseintrag aus sekundärer Deltaliste entfernen
									unset($delta_sec["entries"][$key_sec]);
								} elseif($value_pri["path"]==$value_sec["path"] &&
									$value_pri["action"]=="upload" &&
									$value_sec["action"]=="delete"){
									//Datei wurde auf sekundärem Datastorade gelöscht
									//Kann ignoriert werden, da sie auf primärem Datastorage geändert wurde
									//Änderungseintrag aus sekundärer Deltaliste entfernen
									unset($delta_sec["entries"][$key_sec]);
								}
							}
						}
					}
							
					//Deltas der primären Liste in sekundärem Storage ausführen
					if(isset($delta_pri["entries"])){
						foreach ($delta_pri["entries"] as $key => $value) {
							switch ($value["action"]) {
								case "delete":
									try {
										$ds_sec->deleteFile("/".$value["path"]);
										echo "Datei/Ordner '".$value["path"]."' von '".$arr_sec["id"].", ".$arr_sec["name"]."' geloescht.\n";
									} catch (Exception $e) {
									    echo "Datei/Ordner '".$value["path"]."' von '".$arr_sec["id"].", ".$arr_sec["name"]."' kann nicht geloescht werden.\n";
									}
									break;
								case "newdir":
									try {
										$ds_sec->createFolder("/".$value["path"]);
										echo "Ordner '".$value["path"]."' von '".$arr_sec["id"].", ".$arr_sec["name"]."' angelegt.\n";
									} catch (Exception $e) {
									    echo "Ordner '".$value["path"]."' von '".$arr_sec["id"].", ".$arr_sec["name"]."' kann nicht angelegt werden.\n";
									}
									break;
								case "upload":
									try {
										$filedata = $ds_pri->getFile("/".$value["path"]);
										
										if(!$filedata && !isset($filedata["tmpfile"])){
											throw new Exception("Datei nicht vorhanden", 1);
										}
										$ds_sec->uploadFile($filedata["tmpfile"],"/".$value["path"]);
										unlink($filedata["tmpfile"]);
	
										echo "Datei '".$value["path"]."' auf '".$arr_sec["id"].", ".$arr_sec["name"]."' hochgeladen.\n";;
									} catch (Exception $e) {
									    echo "Datei '".$value["path"]."' auf '".$arr_sec["id"].", ".$arr_sec["name"]."' kann nicht hochgeladen werden.\n";
									}
									break;
								default:
									
									break;
							}
							$delta_done[] = $value;
						}
					}
		
					//Deltas der sekundären Liste in primären Storage ausführen
					if(isset($delta_sec["entries"])){
						foreach ($delta_sec["entries"] as $key => $value) {
							switch ($value["action"]) {
								case "delete":
									try {
										$ds_pri->deleteFile("/".$value["path"]);
										echo "Datei/Ordner '".$value["path"]."' von '".$arr_pri["id"].", ".$arr_pri["name"]."' geloescht.\n";
									} catch (Exception $e) {
									    echo "Datei/Ordner '".$value["path"]."' von '".$arr_pri["id"].", ".$arr_pri["name"]."' kann nicht geloescht werden.\n";
									}
									break;
								case "newdir":
									try {
										$ds_pri->createFolder("/".$value["path"]);
										echo "Ordner '".$value["path"]."' von '".$arr_pri["id"].", ".$arr_pri["name"]."' angelegt.\n";
									} catch (Exception $e) {
									    echo "Ordner '".$value["path"]."' von '".$arr_pri["id"].", ".$arr_pri["name"]."' kann nicht angelegt werden.\n";
									}
									break;
								case "upload":
									try {
										$filedata = $ds_sec->getFile("/".$value["path"]);
										
										if(!$filedata && !isset($filedata["tmpfile"])){
											throw new Exception("Datei nicht vorhanden", 1);
										}
										$ds_pri->uploadFile($filedata["tmpfile"],"/".$value["path"]);
										unlink($filedata["tmpfile"]);
										
										echo "Datei '".$value["path"]."' auf '".$arr_pri["id"].", ".$arr_pri["name"]."' hochgeladen.\n";;
									} catch (Exception $e) {
									    echo "Datei '".$value["path"]."' auf '".$arr_pri["id"].", ".$arr_pri["name"]."' kann nicht hochgeladen werden.\n";
									}
									break;
								default:
									
									break;
							}
							$delta_done[] = $value;
						}
					}
		
					//Deltaliste des sekundären Storages neu laden und speichern
					$delta_sec = $ds_sec->getDelta($delta_sec["cursor"]);
					if(mysql_query("update csp_datastorages 
										set delta_value='".$delta_sec["cursor"]."'
										where id = ".$arr_sec["id"].";
									")){
						echo "Neuer Delta Cursor für (".$arr_sec["id"].") ".$arr_sec["name"]." gespeichert.\n";
					}else{
						echo "Neuer Delta Cursor für (".$arr_sec["id"].") ".$arr_sec["name"]." kann nicht gespeichert werden.\n";
					}
							
						
					//Deltaliste des primären Storages neu laden und speichern
					$delta_pri = $ds_pri->getDelta($delta_pri["cursor"]);
					
					if(mysql_query("update csp_datastorages 
										set delta_value='".$delta_pri["cursor"]."'
										where id = ".$arr_pri["id"].";
									")){
						echo "Neuer Delta Cursor für (".$arr_pri["id"].") ".$arr_pri["name"]." gespeichert.\n";
					}else{
						echo "Neuer Delta Cursor für (".$arr_pri["id"].") ".$arr_pri["name"]." kann nicht gespeichert werden.\n";
					}
					
		
					//Deltalisten um die bereits abgearbeiteten Deltas bereinigen
					if(isset($delta_pri["entries"])){
						foreach($delta_pri["entries"] as $key => $value) {
							foreach($delta_done as $key_done => $value_done)
							if($value["path"]==$value_done["path"] && $value["action"]==$value_done["action"]){
								unset($delta_pri["entries"][$key]);
							}
						}
						if(count($delta_pri["entries"])==0) 
							unset($delta_pri["entries"]);
					}
					if(isset($delta_sec["entries"])){
						foreach($delta_sec["entries"] as $key => $value) {
							foreach($delta_done as $key_done => $value_done)
							if($value["path"]==$value_done["path"] && $value["action"]==$value_done["action"]){
								unset($delta_sec["entries"][$key]);
							}
						}
						if(count($delta_sec["entries"])==0) 
							unset($delta_sec["entries"]);
					}
		
				} 
		
			}
		}
	}
	echo "\n*** Synchronisation beendet. ***";
?>