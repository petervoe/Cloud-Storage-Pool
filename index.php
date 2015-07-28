<html>
<head>
	<meta charset="UTF-8">
	<title>Cloud Storage Pool (CSP)</title>
	<script type="text/javascript">
	    function confirmDelete(datei) {
               var message = "Wollen Sie die Datei wirklich löschen?";
               if(datei!=null) {
                   message = "Wollen Sie die Datei "+datei+" wirklich löchen?";
               }
               
               var del = confirm(message);
               return del;
	    }
	</script>
</head>
<body>
	<h1>Cloud Storage Pool (CSP)</h1>
<?php
	require_once "config.inc.php";
	require_once "functions.inc.php";
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
	if(isset($_GET['ds_delete']))
		$ds_delete = urldecode($_GET['ds_delete']);

	if(isset($ds_id)){ //Datastorage anzeigen
		//Parameter des Datastorages laden
		$ds = getDatastorageById(mysql_real_escape_string($ds_id),$config_appinfos);
		if($ds){
			//Dateiupload durchführen
			if(isset($_FILES["userfile"])){
				$message = "";
				switch( $_FILES['userfile']['error'] ) {
		            case UPLOAD_ERR_OK:
		                $message = false;
		                break;
		            case UPLOAD_ERR_INI_SIZE:
						$message .= ' - INI_SIZE: file too large (limit of '.ini_get('upload_max_filesize').').';
		                break;
		            case UPLOAD_ERR_FORM_SIZE:
		                $message .= ' - FORM_SIZE: file too large (limit of '.ini_get('upload_max_filesize').').';
		                break;
		            case UPLOAD_ERR_PARTIAL:
		                $message .= ' - file upload was not completed.';
		                break;
		            case UPLOAD_ERR_NO_FILE:
		                $message .= ' - zero-length file uploaded.';
		                break;
		            default:
		                $message .= ' - internal error #'.$_FILES['newfile']['error'];
		                break;
		        }
				echo $message;
		        
				$tmpfile = $_FILES["userfile"]["tmp_name"];
				
				if ($_FILES["userfile"]["error"] == UPLOAD_ERR_OK) {
					$result = $ds->uploadFile($tmpfile,$ds_path."/".$_FILES["userfile"]["name"]);
					echo "Upload von ".htmlentities(utf8_decode($_FILES["userfile"]["name"]))." abgeschlossen.";
			    }
			}

			//Ordner erstellen
			if(isset($_POST["newfolder"])){
				$result = $ds->createFolder($ds_path."/".$_POST["newfolder"]);
				echo "Ordner ".htmlentities(utf8_decode($_POST["newfolder"]))." angelegt.";
			}
		
			//Dateilöschung durchführen
			if(isset($ds_delete)){
				$result = $ds->deleteFile($ds_path."/".$ds_delete);
				echo htmlentities(utf8_decode($ds_delete))." gelöscht.";
			}
		
		
			//Ordnerinhalt ausgeben
			
			//Titelzeile mit Ordnerinfo augeben
			echo "<h2>/".$ds->getName().(strlen($ds_path)>0?"/".$ds_path:"")."</h2>";
			
			//Inhaltsliste abrufen
			$fileList = $ds->getFilelist($ds_path);
			
			//Sortieren der Liste für die Ausgabe (Sortierfunktion)
			function cmp($a, $b) {
				if($a['is_dir'] == $b['is_dir']) {
				  if($a['name'] != $b['name']){
				  	return strcmp($a['name'], $b['name']);
				  }
				  return 0; 
				} 
				return ($a['is_dir'] < $b['is_dir']) ? 1 : -1; 
			}
			
			//Sortieren der Liste für die Ausgabe
			usort($fileList["contents"], "cmp");
			
			//Sprungpunkt für übergeordneten Ordner ausgeben
			if(strlen($ds_path)>0){
				$h_path = substr($ds_path, 0, strlen($ds_path)-strlen(basename($ds_path)));
				if(strlen($h_path)>0)
					$h_path = substr($h_path,0,-1);
				echo ".. (<a href='?ds_id=".$ds_id."&ds_path=".urlencode($h_path)."'>Übergeordneten Ordner öffnen</a>)<br/><br/>\n";
			} else {
				//Wenn bereits im Ordner Root, wieder zurück zur virtuellen Ordnerauswahl
				echo ".. (<a href='?'>Übergeordneten Ordner öffnen</a>)<br/><br/>\n";
			}
			
			//Liste Ausgeben
			foreach ($fileList["contents"] as  $key => $fileListEntry) {
				if($fileListEntry["is_dir"]){ //Wenn Ordner
					echo $fileListEntry["name"].
						" (<a href='?ds_id=".$ds_id."&ds_path=".urlencode((strlen($ds_path)>0?$ds_path."/":"").$fileListEntry["name"])."'>Ordner öffnen</a>".
						", <a href='?ds_id=".$ds_id."&ds_path=".urlencode($ds_path)."&ds_delete=".urlencode($fileListEntry["name"])."' onclick=\"return confirmDelete('".htmlentities(utf8_decode($fileListEntry["name"]))."')\">löschen</a>)<br/>\n";
				} else { //Wenn Datei
					echo $fileListEntry["name"].
						" (<a href='getfile.php?ds_id=".$ds_id."&ds_path=".urlencode((strlen($ds_path)>0?$ds_path."/":"").$fileListEntry["name"])."'>download</a>".
						", <a href='?ds_id=".$ds_id."&ds_path=".urlencode($ds_path)."&ds_delete=".urlencode($fileListEntry["name"])."' onclick=\"return confirmDelete('".htmlentities(utf8_decode($fileListEntry["name"]))."')\">löschen</a>)<br/>\n";
				}	
			}
			
			//Formular für Dateiupload ausgeben
			echo "<br/>";
			echo "<form enctype='multipart/form-data' action='?ds_id=".$ds_id."&ds_path=".urlencode($ds_path)."' method='POST'>";
			echo "    Datei hochladen: <input name='userfile' type='file' />";
			echo "    <input type='submit' value='Datei hochladen' />";
			echo "</form>";
			
			//Formular für neue Unterordner ausgeben
			echo "<br/>";
			echo "<form enctype='multipart/form-data' action='?ds_id=".$ds_id."&ds_path=".urlencode($ds_path)."' method='POST'>";
			echo "    Ordner erstellen: <input name='newfolder' type='text' />";
			echo "    <input type='submit' value='Ordner erstellen' />";
			echo "</form>";
			
		} else { //Datastorage existiert nicht
			echo "Der Datenspeicher ist nicht vorhanden.";
		}
	
		
	} else { //kein Datastorage ausgewählt. Virtuelle Ordnerliste mit allen Datastorages anzeigen.
		echo "<h2>Virtuelle Ordnerliste</h2>\n";
		echo "<h3>Primäre Datenspeicher</h3>\n";
		//Alle primären Datastorages laden
		$res = mysql_query("select id,name from csp_datastorages
					where primary_storage_id is null
					order by name
					");
		while($arr=mysql_fetch_array($res)){
			echo "<a href='?ds_id=".$arr["id"]."'>".$arr["name"]."</a><br/>\n";
		}
		
		echo "<h3>Sekundäre Datenspeicher</h3>\n";
		//Alle primären Datastorages laden
		$res = mysql_query("select id,name from csp_datastorages
					where primary_storage_id is not null
					order by name
					");
		while($arr=mysql_fetch_array($res)){
			echo "<a href='?ds_id=".$arr["id"]."'>".$arr["name"]."</a><br/>\n";
		}
	}

?>
</body>
</html>