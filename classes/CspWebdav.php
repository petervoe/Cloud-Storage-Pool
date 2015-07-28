<?php

    require_once "config.inc.php";
	require_once "functions.inc.php";
	require_once "classes/Datastorage.php";
	require_once "classes/Datastorage_Dropbox.php";
	require_once "classes/Datastorage_Googledrive.php";  
	require_once "api/HTTP_WebDAV_Server/Server.php";

	/**
	 * CspWebdav
	 * 
	 * WebDAV Schnittstelle für den Zugriff auf die virtuellen Datenspeicher des Cloud Storage Pools (CSP)	
	 * Ableitung der abstrakten Klasse HTTP_WebDAV_Server und basierend auf dem Codebeispiel HTTP_WebDAV_Server_Filesystem von Hartmut Holzgraefe
	 * 
	 * @author      Peter Völkl <peter@vape.net>
	 * @package     CSP
	 * @version		2014-04-26
	 */
    class CspWebdav extends HTTP_WebDAV_Server{
    	
		private $config_appinfos;
		private $tmp_files = Array();
		
		/**
		* setConfigAppinfos
		* 
		* Stellt das Array mit den Appinfos der externen Datenspeicher zur Verfügung
		*
		* @param Array	$config_appinfos		Appinfo Array
		*/
		function setConfigAppinfos($config_appinfos){
			$this->config_appinfos = $config_appinfos;
		}

		
		/**
		* ServeRequest
		* 
		* Verarbeitet die WebDAV Anfrage
		*
		*/
		function ServeRequest() 
		{
			//Startet den WebDAV Server und verarbeitet die Anfrage
		    parent::ServeRequest();
			
			//Temp Dateien löschen (die bei GET entstanden sind)
			foreach ($this->tmp_files as $key => $value) {
				unlink($value);
			}
		}
		
		/**
		* check_auth
		* 
		* Führt die Prüfung der Authentizität durch.
		* Für den CSP nicht implementiert, da über Basic-Auth mittels .htaccess gearbeitet wird
		*
		* @param String		$type		Authentifikationtyp
		* @param String		$user		Username
		* @param String		$pass		Passwort
		*/
		function check_auth($type, $user, $pass) 
		{
		    return true;
		}
		
		/**
		* PROPFIND
		* 
		* Implementierung der Methode PROPFIND des WebDAV Servers
		*
		* @param Array		&$options	
		* @param Array		&$files		
		*/
		function PROPFIND(&$options, &$files) 
		{
		    $options["path"] = urldecode($options["path"]);
		    
		    //Prüfen ob Anfrage in einem Datastorage oder im Root ausgeführt wurde
		    if($options["path"]=="" || $options["path"]=="/"){
		    	//Angefragter Pfad bezieht sich auf Root-Verzeichnis
		    	//Liste der Datastorages zurückgeben
		    	$res = mysql_query("select id,name from csp_datastorages
					where primary_storage_id is null
					order by name
					");
				$files["files"] = array();
				
				//Rootverzeichnis
				$info = array();
	            $info["path"]  = "/"; 
	            $info["props"] = array();
				$info["props"][] = $this->mkprop("displayname",     "");
	            $info["props"][] = $this->mkprop("creationdate",    time());
	            $info["props"][] = $this->mkprop("getlastmodified", time());
	            $info["props"][] = $this->mkprop("resourcetype", "collection");
            	$info["props"][] = $this->mkprop("getcontenttype", "httpd/unix-directory");  
	            //Microsoft-spezifisch:
	      		$info["props"][] = $this->mkprop("lastaccessed",    time());
	      		$info["props"][] = $this->mkprop("ishidden",        false);
		      	
		      	$files["files"][] = $info;
				
				while($arr=mysql_fetch_array($res)){
					
					$info = array();
		            $info["path"]  = "/".$arr["name"]; 
		            $info["props"] = array();
					$info["props"][] = $this->mkprop("displayname",     $arr["name"]);
		            $info["props"][] = $this->mkprop("creationdate",    time());
		            $info["props"][] = $this->mkprop("getlastmodified", time());
					$info["props"][] = $this->mkprop("resourcetype", "collection");
            		$info["props"][] = $this->mkprop("getcontenttype", "httpd/unix-directory");  
		            //Microsoft-spezifisch:
		      		$info["props"][] = $this->mkprop("lastaccessed",    time());
		      		$info["props"][] = $this->mkprop("ishidden",        false);
					
					$files["files"][] = $info;
				}
		    	return true;
				
		    }else{
				//Angefragter Pfad befindet sich in einem Datastorage
				
				$path_explode = explode("/",$options["path"]);
				$ds_path = substr($options["path"],strlen($path_explode[1])+2);
				if(substr($ds_path,-1)=="/")
					$ds_path=substr($ds_path,0,-1);
				
			    //Parameter des Datastorages laden
				$ds = getDatastorageByName($path_explode[1],$this->config_appinfos);
				if($ds){
					$files["files"] = array();
					$filedata = $ds->getFilelist($ds_path);

					if(!isset($filedata["name"]))
						return false;
					
					//Die Information über das Verzeichnis selbst hinzufügen
					$files["files"][] = $this->fileinfo($filedata);
					
					//Die Information der Dateien im Verzeichnis hinzufügen
					if(isset($filedata["contents"])){
						foreach ($filedata["contents"] as $key => $value) {
							$files["files"][] = $this->fileinfo($value);
						}
					}
					return true;
				} else {
					return false;
				}
			}
			return false;
		    
		} 
		
		/**
		* fileinfo
		* 
		* Wandelt ein Array mit Dateieigenschaften eines Datastorages in ein Array für den WebDAV Server um.
		*
		* @param Array		Array mit den Dateieigenschaften eines Datastorages
		* 	
		* @param Array		Array mit den Dateieigenschaften für den WebDAV Server		
		*/
		private function fileinfo($file) {
            $info = array();
			if(isset($file["datastorage_name"]) && $file["datastorage_name"]!="")
				$info["path"]  = "/".$file["datastorage_name"]."/".($file["path"]!=""?$file["path"]."/":"").$file["name"];
			else 
				$info["path"]  = "/".$file["name"]; 
            $info["props"] = array();
            
            $info["props"][] = $this->mkprop("displayname",     $file["name"]);
            $info["props"][] = $this->mkprop("creationdate",    $file["creation_date"]);
            $info["props"][] = $this->mkprop("getlastmodified", $file["last_modified"]);
			if($file["is_dir"]==1){
				$info["props"][] = $this->mkprop("resourcetype", "collection");
            	$info["props"][] = $this->mkprop("getcontenttype", "httpd/unix-directory");  
			}else{
				$info["props"][] = $this->mkprop("resourcetype",    "");
            	$info["props"][] = $this->mkprop("getcontenttype",  isset($file["mime_type"])?$file["mime_type"]:"");
            	$info["props"][] = $this->mkprop("getcontentlength",isset($file["bytes"])?$file["bytes"]:"");
			}
            //Microsoft-spezifisch:
      		$info["props"][] = $this->mkprop("lastaccessed",    $file["last_modified"]);
      		$info["props"][] = $this->mkprop("ishidden",        false);
            
            //$this->optfileinfo($info["props"]);
            return $info;        
        }
		 
		/**
		* GET
		* 
		* Implementierung der Methode GET des WebDAV Servers
		*
		* @param Array		&$options		
		*/
		function GET(&$options) 
		{
		    $options["path"] = urldecode($options["path"]);
		    
		    //Parameter des Datastorages laden
			$path_explode = explode("/",$options["path"]);
			$ds_path = substr($options["path"],strlen($path_explode[1])+2);
			if(substr($ds_path,-1)=="/")
				$ds_path=substr($ds_path,0,-1);
			
		    //Parameter des Datastorages laden
			$ds = getDatastorageByName($path_explode[1],$this->config_appinfos);
			if($ds){
				$filedata = $ds->getFile($ds_path);
				if($filedata == null){
					
				} elseif($filedata["is_dir"]){
					return $this->GetDir($filedata, $options);
				}else{
					$options['stream'] = fopen($filedata["tmpfile"], "r");
					$this->tmp_files[] = $filedata["tmpfile"];
					return true;
				}		
			} else {
				return false;
			}
			return false;
		}
		
		/**
		* GetDir
		* 
		* Gibt eine HTML formatierte Liste des Inhalts eines Verzeichnisses aus
		*
		* @param Array		&$filedata	Ordnerdaten und Dateiliste (Datastorage->getFilelist())
		* @param Array		&$options	Serveroptionen
		*/
		function GetDir($filedata, &$options) 
		{			
			$format = "%15s  %-19s  %-s\n";
			
			echo "<html><head><title>Index of ".htmlspecialchars($options['path'])."</title></head>\n";
			    
			echo "<h1>Index of ".htmlspecialchars($options['path'])."</h1>\n";
			    
			echo "<pre>";
			printf($format, "Size", "Last modified", "Filename");
			echo "<hr>";
			$path_explode = explode("/",$options['path']);
			$path = $path_explode[count($path_explode)-1];
			foreach ($filedata["file_list"] as $key => $value) {
				$fullpath = $path."/".$value["name"];
		        $name     = htmlspecialchars($value["name"]);
		        printf($format, 
		               number_format(filesize($fullpath)),
		               strftime("%Y-%m-%d %H:%M:%S", filemtime($fullpath)), 
		               '<a href="' . $fullpath . '">' . $name . '</a>');
			}
		    echo "</pre>\n";
			
		    echo "</html>\n";
		
		    exit;
		}
		
		/**
		* PUT
		* 
		* Implementierung der Methode PUT des WebDAV Servers
		*
		* @param Array		&$options	
		* @param Array		&$files		
		*/
		function PUT(&$options) 
		{
			$options["path"] = urldecode($options["path"]);
		    
		    //Parameter des Datastorages laden
			$path_explode = explode("/",$options["path"]);
			$ds_path = substr($options["path"],strlen($path_explode[1])+2);
			if(substr($ds_path,-1)=="/")
				$ds_path=substr($ds_path,0,-1);
			
		    //Parameter des Datastorages laden
			$ds = getDatastorageByName($path_explode[1],$this->config_appinfos);
			if($ds){
					//Dateiupload durchführen
					$result = $ds->uploadFileStream($options["stream"],$ds_path);

					return "";
			} else {
				return "403 Forbidden";
			}
			return "403 Forbidden";
		}
		
		/**
		* MKCOL
		* 
		* Implementierung der Methode MKCOL des WebDAV Servers
		*
		* @param Array		&$options	
		* @param Array		&$files		
		*/
		function MKCOL($options) 
		{           
			$options["path"] = urldecode($options["path"]);
		    
		    //Parameter des Datastorages laden
			$path_explode = explode("/",$options["path"]);
			$ds_path = substr($options["path"],strlen($path_explode[1])+2);
			if(substr($ds_path,-1)=="/")
				$ds_path=substr($ds_path,0,-1);
			
		    //Parameter des Datastorages laden
			$ds = getDatastorageByName($path_explode[1],$this->config_appinfos);
			if($ds){
				//Ordner anlegen
				$result = $ds->createFolder($ds_path);

				return ("201 Created");
			} else {
				return "403 Forbidden";
			}
			return "403 Forbidden";
		}
		    
		/**
		* DELETE
		* 
		* Implementierung der Methode DELETE des WebDAV Servers
		*
		* @param Array		&$options	
		* @param Array		&$files		
		*/   
		function DELETE($options) 
		{
			$options["path"] = urldecode($options["path"]);
		    
		    //Parameter des Datastorages laden
			$path_explode = explode("/",$options["path"]);
			$ds_path = substr($options["path"],strlen($path_explode[1])+2);
			if(substr($ds_path,-1)=="/")
				$ds_path=substr($ds_path,0,-1);

		    //Parameter des Datastorages laden
			$ds = getDatastorageByName($path_explode[1],$this->config_appinfos);
			if($ds){
				//Datei/Ordner löschen
				$result = $ds->deleteFile($ds_path);

				return "204 No Content";
			} else {
				return "404 Not found";
			}
		    return "404 Not found";
		}
		
		/**
		* MOVE
		* 
		* Implementierung der Methode MOVE des WebDAV Servers
		*
		* @param Array		&$options	
		* @param Array		&$files		
		*/
		function MOVE($options) 
		{
		    return $this->COPY($options, true);
		}
		
		/**
		* COPY
		* 
		* Implementierung der Methode COPY des WebDAV Servers
		*
		* @param Array		&$options	
		* @param Array		&$files		
		*/
		function COPY($options, $del=false) 
		{

			if (!empty($this->_SERVER["CONTENT_LENGTH"])) { // no body parsing yet
	            return "415 Unsupported media type";
	        }
			// no copying to different WebDAV Servers yet
	        if (isset($options["dest_url"])) {
	            return "502 bad gateway";
	        }
				
			
		    $source = urldecode($options["path"]);
			$dest = urldecode($options["dest"]);

		    
		    //Parameter des Datastorages laden
			$source_explode = explode("/",$source);
			$dest_explode = explode("/",$dest);
			
			//Prüfen ob das Kopieren innerhalb eines Datastorages geschieht
			if($source_explode[1] == $dest_explode[1]){
				//Datastoreage von Quelle und Ziel sind ident

				$ds_source_path = substr($source,strlen($source_explode[1])+2);
				if(substr($ds_source_path,-1)=="/")
					$ds_source_path=substr($ds_source_path,0,-1);
				
				$ds_dest_path = substr($dest,strlen($dest_explode[1])+2);
				if(substr($ds_dest_path,-1)=="/")
					$ds_dest_path=substr($ds_dest_path,0,-1);
	
			    //Parameter des Datastorages laden
				$ds = getDatastorageByName($source_explode[1],$this->config_appinfos);
				if($ds){
					//Datei/Ordner kopieren
					$ds->copyFile($ds_source_path,$ds_dest_path);
					
					if($del)
						$ds->deleteFile($ds_source_path);

					return "201 Created"; 
				} else {
					return "404 Not found";
				}
			    return "404 Not found";
				
		    }else{
		    	//Datastoreage von Quelle und Ziel sind unterschiedlich
		    	
		    	//Nicht zulässig
		    	return "204 No Content";
		    }
			
		    return "404 Not found";      
		}
		
		/**
		* PROPPATCH
		* 
		* Implementierung der Methode PROPPATCH des WebDAV Servers
		* Eine Änderungen von Dateieigenschaften ist derzeit nicht vorgesehen
		*
		* @param Array		&$options	
		* @param Array		&$files		
		*/
		function PROPPATCH(&$options) 
		{

		    return "";
		}
		
	}
?>