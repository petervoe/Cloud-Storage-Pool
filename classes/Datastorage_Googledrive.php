<?php

require_once "classes/Datastorage.php";
set_include_path("api/google-api-php-client/src" . PATH_SEPARATOR . get_include_path());
require_once 'Google/Client.php';
require_once 'Google/Service/Drive.php';
require_once 'Google/Http/MediaFileUpload.php';
require_once 'Google/Http/Request.php';

/**
 * Datastorage_Googledrive
 * 
 * Klasse für die Instanzierung von Google Drive Speichern als virtuelle Datenspeicher des Cloud Storage Pools (CSP)
 *
 * @author      Peter Völkl <peter@vape.net>
 * @package     CSP
 * @version		2014-04-26
 */
class Datastorage_Googledrive extends Datastorage{
	private $gdrClient;
	private $gdrClient_client;

	/**
	* __construct
	* 
	* Initialisierung der allgemenen Klassenvariablen
	*
	* @param string		$id   		Eindeutige ID des virtuellen Datenspeichers
	* @param string		$name   	Eindeutiger Bezeichnung des virtuellen Datenspeichers
	* @param string		$ext_root   Root Ordner der Instanz innerhalb des externen Datenspeichers
	* @param string		$token   	Authorisierungs Token zur Verbindung mit dem externen Datnespeicher
	* @param string		$crypto_key Passphrase zur Verschlüsselung der Daten innderhalb des externen Datenspeichers
	* @param Array		$appinfo	Dropbox Appinfo Array("key","secret")
	*/
	function __construct($id,$name,$ext_root,$token,$crypto_key,$appinfo){
		parent::__construct($id,$name,$ext_root,$token,$crypto_key);
		$this->type = "GOODR";
		
		//Google Drive Zugriff initialisieren
		$client = new Google_Client();
		$client->setClientId($appinfo["client_id"]);
		$client->setClientSecret($appinfo["client_secret"]);
		$client->addScope("https://www.googleapis.com/auth/drive");
		$client->setAccessToken($token);

		$this->gdrClient_client = $client;
		$this->gdrClient = new Google_Service_Drive($client);
	}
	
	/**
	* getMetadataByPath
	* 
	* Liefert die Metadaten einer Google Drive Datei anhand ihres Dateipfades zurück
	*
	* @param string		$path	Dateipfad
	*
	* @return Google_DriveFile 	Metadaten der Datei
	* 					https://developers.google.com/drive/v2/reference/files#resource
	*/	
	private function getMetadataByPath($path){
		if(substr($path,0,1)=="/")
			$path = substr($path,1);
		$path_explode = explode("/", $path);
		$last_id["id"] = "root";

		//Suche der FileID in Google Drive Struktur
		foreach ($path_explode as $key => $value) {
			//Prüfen ob vorletztes Element des Pfades erreicht ist
			$parameters = array();
			$parameters["q"] = "'".$last_id["id"]."' in parents and title = '".$value."'";

			try{
				$files = $this->gdrClient->files->listFiles($parameters);
			} catch(Exception $e) {
				$last_id=null;
				break;
			}
			
			if(get_class($files)=="Google_Service_Drive_FileList" && count($files["modelData"]["items"]) > 0){
				$last_id = $files["modelData"]["items"]["0"];
			} else {
				$last_id=null;
				break;
			}
		}
		
		return $last_id;
	}

	/**
	* getPathById
	* 
	* Liefert den Dateipfad einer Google Drive Datei zurück
	*
	* @param string		$id		FileID der Google Drive Datei
	*
	* @return string	Dateipfad
	*/	
	function getPathById($id){
		$last_id = $id;
		$path = "";

		//Suche der FileID in Google Drive Struktur
		while($last_id) {
			//Prüfen ob vorletztes Element des Pfades erreicht ist
			$parameters = array();
			$parameters["q"] = "id = '".$last_id."'";

			try{
				$file = $this->gdrClient->files->get($last_id);
			} catch(Exception $e) {
				$last_id=null;
				break;
			}
			
			if(count($file["modelData"]) > 0 
				&& count($file["modelData"]["parents"]) > 0){
				$last_id = $file["modelData"]["parents"]["0"]["id"];
				if(strlen($path)>0)
					$path = "/".$file["title"].$path;
				else 
					$path = "/".$file["title"];
			} else {
				$last_id=null;
				break;
			}
		}
		
		return $path;
	}
	
	/**
	* getFilelist
	* 
	* Abfrage aller Dateien und ihrer Eigenschaften eines Verzeichnisses innerhalb des virtuellen Datenspeichers
	*
	* @param string		$directory		Verzeichnispfad
	*
	* @return Array		Daten des Ordners oder der Datei $directory inklusive aller enthaltenen Dateien und Ordner
	* 					Struktur:
	* 					$ret["name"]= Name von $directory
	*					$ret["path"] = Pfad innerhalb des virtuellen Datenspeichers zu $directory
	*					$ret["datastorage_name"] = Eindeutiger Bezeichnung des virtuellen Datenspeichers 
	*					$ret["creation_date"] = Erstellungsdatum von $directory;
	*					$ret["last_modified"] = Datum der letzten Änderung von $directory;
	*					$ret["is_dir"] = [1...wenn es sich um ein Verzeichnis handelt]
	* 					$ret["mime_type"] = MIME Typ von $directory
	* 					$ret["bytes"] = Größe von $directory in Byte
	*					$ret["contents"][] = Dateien und Ordner innrehalb von $directory
	* 					$ret["contents"][]["name"]= Name des Elements
	*					$ret["contents"][]["path"] = Pfad innerhalb des virtuellen Datenspeichers zum Element
	*					$ret["contents"][]["datastorage_name"] = Eindeutiger Bezeichnung des virtuellen Datenspeichers 
	*					$ret["contents"][]["creation_date"] = Erstellungsdatum des Elements;
	*					$ret["contents"][]["last_modified"] = Datum der letzten Änderung des Elements;
	*					$ret["contents"][]["is_dir"] = [1...wenn es sich um ein Verzeichnis handelt]
	* 					$ret["contents"][]["mime_type"] = MIME Typ des Elements
	* 					$ret["contents"][]["bytes"] = Größe des Elements in Byte
	*/
	function getFilelist($directory){
		$ret = null;
		
		if(strlen($directory)>0)
			$path=$this->ext_root."/".$directory;
		else 
			$path=$this->ext_root;
		
		$folderMetadata = $this->getMetadataByPath($path);
		
		if($folderMetadata){
			$ret = array();
			$ret["contents"] = Array();
			$ret["name"]= basename($directory);
			$ret["path"] = substr($directory, 0, strlen($directory)-strlen(basename($directory)));
			if(substr($ret["path"],-1)=="/")
				$ret["path"] = substr($ret["path"],0,-1);
			$ret["datastorage_name"]=$this->name;
			$ret["creation_date"] = strtotime($folderMetadata["createdDate"]);
			$ret["last_modified"] = strtotime($folderMetadata["modifiedDate"]);
			$ret["is_dir"] = ($folderMetadata["mimeType"]=="application/vnd.google-apps.folder"?"1":"0");
			
			//Ordnerinhalt abrufen
			$parameters = array();
			$parameters['q'] = "'".$folderMetadata["id"]."' in parents";
			try{
				$files = $this->gdrClient->files->listFiles($parameters);
			} catch(Exception $e) {
				break;
			}
			
			if(isset($files) && count($files["modelData"]["items"])>0){
				foreach ($files["modelData"]["items"] as $key => $value) {
					if(!isset($value["explicitlyTrashed"]) || $value["explicitlyTrashed"]!=1){
						$ret["contents"][$key]["name"] = $value["title"];
						$ret["contents"][$key]["path"] = $directory;
						$ret["contents"][$key]["datastorage_name"]=$this->name;
						$ret["contents"][$key]["is_dir"] = ($value["mimeType"]=="application/vnd.google-apps.folder"?"1":"0");
						$ret["contents"][$key]["creation_date"] = strtotime($value["createdDate"]);
						$ret["contents"][$key]["last_modified"] = strtotime($value["modifiedDate"]);
						if(isset($value["mimeType"]))
							$ret["contents"][$key]["mime_type"] = $value["mimeType"];
						if(isset($value["fileSize"]))
							$ret["contents"][$key]["bytes"] = $value["fileSize"];
					}
				}
			}
		}
		
		return $ret;
	}
	
	/**
	* getFile
	* 
	* Liefert eine Datei und ihre Eigenschaften zurück
	*
	* @param string		$file		Pfad innerhalb des virtuellen Datenspeichers zur Datei
	*
	* @return Array		Eigenschaften der Datei und Pfad zu temporärer Datei für ihren Abruf
	* 					Struktur:
	* 					$ret["name"]= Name von $file
	*					$ret["datastorage_name"] = Eindeutiger Bezeichnung des virtuellen Datenspeichers 
	*					$ret["creation_date"] = Erstellungsdatum von $file;
	*					$ret["last_modified"] = Datum der letzten Änderung von $file;
	*					$ret["is_dir"] = [1...wenn es sich um ein Verzeichnis handelt]
	* 					$ret["mime_type"] = MIME Typ von $directory
	* 					$ret["bytes"] = Größe von $directory in Byte
	* 					$ret["tmpfile"] = Pfad zur angelegten temporärern Datei für weitere Verarbeitung
	*/
	function getFile($file){

		$local_temp = "tmp/";
		
		
	
		if($file=="")
			$path=$this->ext_root;
		elseif(substr($file,0,1)=="/")
			$path=$this->ext_root.$file;
		else
			$path=$this->ext_root."/".$file;
		
		$filename=basename($path);
		
		$fileMetadata = $this->getMetadataByPath($path);
		$fileList=null;
		if(isset($fileMetadata) && $fileMetadata != null
			&& $fileMetadata["mimeType"]!="application/vnd.google-apps.folder"){
			//Ist Datei
			
			$fp = fopen($local_temp.$filename, "w+b");
			
			//Entschlüsselung
			if($this->crypto_key != null){
				$this->csp_decryptStream($fp,$this->crypto_key);
			}
			
			try{
				$file = $this->gdrClient->files->get($fileMetadata["id"]);
				$downloadUrl = $file->getDownloadUrl();
				if ($downloadUrl) {
				  $request = new Google_Http_Request($downloadUrl, 'GET', null, null);
				  $httpRequest = $this->gdrClient_client->getAuth()->authenticatedRequest($request);
				  
				  if ($httpRequest->getResponseHttpCode() == 200) {
				    fwrite($fp,$httpRequest->getResponseBody());
				  }
				}
				$ret["name"]=$filename;
				$ret["datastorage_name"]=$this->name;
				$ret["tmpfile"]=$local_temp.$filename;
				$ret["mime_type"]=$fileMetadata["mimeType"];
				$ret["bytes"]=$fileMetadata["fileSize"];
				$ret["creation_date"] = strtotime($fileMetadata["createdDate"]);
				$ret["last_modified"] = strtotime($fileMetadata["modifiedDate"]);
				$ret["is_dir"] = 0;
			}catch(Exception $e){
				//Fehler beim Download
				$ret=null;
			}
			
			fclose($fp);

		} elseif(($fileList = $this->getFilelist($file)) && $fileList["is_dir"]==1){
			//Ist Verzeichnis
			$ret["name"]=$filename;
			$ret["datastorage_name"]=$this->ext_root;
			$ret["mime_type"]=null;
			$ret["bytes"]=0;
			$ret["creation_date"] = strtotime($fileMetadata["createdDate"]);
			$ret["last_modified"] = strtotime($fileMetadata["modifiedDate"]);
			if(isset($fileList["contents"]))
				$ret["file_list"] = $fileList["contents"];
			$ret["is_dir"] = 1;
		} else {
			$ret = null;
		}

		return $ret;
	}
	
	/**
	* uploadFile
	* 
	* Ladet eine Datei in den virtuellen Datenspeicher hoch
	*
	* @param string		$tmpfile		Pfad zur temporären Datei die hochgeladen werden soll
	* @param string		$newfile		Pfad und Name der neuen Datei innerhalb des virtuellen Datenspeichers
	*/
	function uploadFile($tmpfile,$newfile){

		if($newfile=="")
			return false;
		elseif(substr($newfile,0,1)=="/")
			$path=$this->ext_root.$newfile;
		else
			$path=$this->ext_root."/".$newfile;
	
		//Wenn Datei existiert wird ihre id zwischengespeichert 
		//und nach dem neuen Upload gelöscht
		$oldFileMetadata = $this->getMetadataByPath($path);
		$folderMetadata = $this->getMetadataByPath(dirname($path));
		
		if($folderMetadata){
			$parentId = $folderMetadata["id"];
			
			$file = new Google_Service_Drive_DriveFile();
			$file->setTitle(basename($path));
			$parent = new Google_Service_Drive_ParentReference();
		    $parent->setId($parentId);
		    $file->setParents(array($parent));
			
			$fp = fopen($tmpfile, "rb");
			
			//Verschlüsselung
			if($this->crypto_key != null){
				$this->csp_encryptStream($fp,$this->crypto_key);
			}
			
			$data = stream_get_contents($fp);
			
			$result = $this->gdrClient->files->insert($file,array(
		      'data' => $data,
		      'uploadType' => 'media'
		    ));
					
			fclose($fp);
			
			//Wenn eine alte version existiert, wird sie gelöscht
			if($oldFileMetadata){
				try{
					$this->gdrClient->files->delete($oldFileMetadata["id"]);
				} catch(Exception $e){
					//Fehler beim löschen
					//beide Dateien bleiben bestehen
				}
			}
			
			return $result;
		}
		return null;
	}

	
	/**
	* uploadFileStream
	* 
	* Ladet eine Datei in den virtuellen Datenspeicher hoch
	*
	* @param string		$fp				Filestream der hochzuladenden Datei
	* @param string		$newfile		Pfad und Name der neuen Datei innerhalb des virtuellen Datenspeichers
	*/
	function uploadFileStream($fp,$newfile){
		if($newfile=="")
			return false;
		elseif(substr($newfile,0,1)=="/")
			$path=$this->ext_root.$newfile;
		else
			$path=$this->ext_root."/".$newfile;
		
		//Wenn Datei existiert wird ihre id zwischengespeichert 
		//und nach dem neuen Upload gelöscht
		$oldFileMetadata = $this->getMetadataByPath($path);

		$folderMetadata = $this->getMetadataByPath(dirname($path));

		if($folderMetadata){
			$parentId = $folderMetadata["id"];
			
			$file = new Google_Service_Drive_DriveFile();
			$file->setTitle(basename($path));
			$parent = new Google_Service_Drive_ParentReference();
		    $parent->setId($parentId);
		    $file->setParents(array($parent));
			
			//Verschlüsselung
			if($this->crypto_key != null){
				$this->csp_encryptStream($fp,$this->crypto_key);
			}
			
			$data = stream_get_contents($fp);
			
			$result = $this->gdrClient->files->insert($file,array(
		      'data' => $data,
		      'uploadType' => 'media'
		    ));
					
			fclose($fp);
			
			//Wenn eine alte version existiert, wird sie gelöscht
			if($oldFileMetadata){
				try{
					$this->gdrClient->files->delete($oldFileMetadata["id"]);
				} catch(Exception $e){
					//Fehler beim Löschen
					//beide Dateien bleiben bestehen
				}
			}
			
			return $result;
		}
		return null;
	}
	
	/**
	* deleteFile
	* 
	* Löscht eine Datei des virtuellen Datenspeichers
	*
	* @param string		$file		Pfad innerhalb des virtuellen Datenspeichers zur Datei
	*/
	function deleteFile($file){
		if(substr($file,0,1)=="/")
			$file = substr($file,1);
		
		$path=$this->ext_root."/".$file;
		
		$fileMetadata = $this->getMetadataByPath($path);

		try{
			$result = $this->gdrClient->files->delete($fileMetadata["id"]);
		}catch(Exception $e){
			//Fehler beim Löschen
			return null;
		}
		
		return $result;
	}
	
	/**
	* copyFile
	* 
	* Kopiert eine Datei innerhalb des Datenspeichers
	*
	* @param String		$source_file	Pfad der Quelldatei innerhalb des virtuellen Datenspeichers
	* @param String		$dest_file	Pfad der Quelldatei innerhalb des virtuellen Datenspeichers
	*/
	function copyFile($source_file, $dest_file){
		if(substr($source_file,0,1)=="/")
			$source_file = substr($source_file,1);
		if(substr($dest_file,0,1)=="/")
			$dest_file = substr($dest_file,1);
		
		$source_file_path=$this->ext_root."/".$source_file;
		$dest_file_path=$this->ext_root."/".$dest_file;
		
		$fileMetadata_source = $this->getMetadataByPath($source_file_path);
		$fileMetadata_destdir = $this->getMetadataByPath(dirname($dest_file_path));
		if($fileMetadata_destdir && $fileMetadata_source){
			$parentId = $fileMetadata_destdir["id"];
			$originFileId = $fileMetadata_source["id"];

			$copiedFile = new Google_Service_Drive_DriveFile();
			$copiedFile->setTitle(basename($dest_file_path));
			$parent = new Google_Service_Drive_ParentReference();
		    $parent->setId($parentId);
		    $copiedFile->setParents(array($parent));
			try {
			  return $this->gdrClient->files->copy($originFileId, $copiedFile);
			} catch (Exception $e) {
			  return null;
			}
		}
		
		return null;
	}
	
	/**
	* createFolder
	* 
	* Erstellt einen neuen Ordner innerhalb des virtuellen Datenspeichers
	*
	* @param string		$folder		Pfad des neuen Ordners inkusive Ordnername innerhalb des virtuellen Datenspeichers
	*/
	function createFolder($folder){
		if($folder=="")
			return false;
		elseif(substr($folder,0,1)=="/")
			$path=$this->ext_root.$folder;
		else
			$path=$this->ext_root."/".$folder;
		
		$newFolderMetadata = $this->getMetadataByPath($path);
		$folderMetadata = $this->getMetadataByPath(dirname($path));

		//Ordner nur Anlegen wenn übergordneter Ordner exisiert und er selbst noch nicht exisiert
		if($folderMetadata && !$newFolderMetadata){
			$parentId = $folderMetadata["id"];
			
			$file = new Google_Service_Drive_DriveFile();
			$file->setTitle(basename($path));
			$parent = new Google_Service_Drive_ParentReference();
		    $parent->setId($parentId);
		    $file->setParents(array($parent));
			$file->setMimetype("application/vnd.google-apps.folder");
			
			return $this->gdrClient->files->insert($file);
			
		}
		return null;
	}
	
	/**
	* getDelta
	* 
	* Liefert alle Änderungen des virtuellen Datenspeichers seit dem übergebenen Cursor
	* Wird null als Cusor übergeben werden alle Änderungen seit bestehen des virtuellen Datenspeichers zurückgeleifert.
	*
	* @param string		$delta_value		Cursor ab dem das Delta berechnet werden soll
	*
	* @return Array		Alle Änderungen des Datenspeichers ab dem übergebenen Cursor
	* 					Struktur:
	* 					$ret["cursor"]= Name von $file
	*					$ret["entries"][] = Eindeutiger Bezeichnung des virtuellen Datenspeichers 
	*					$ret["entries"][]["path"] = Pfad zur/zum betroffenen Datei/Ordner
	*					$ret["entries"][]["action"] = Durchgeführte Aktion [delete|newdir|upload]
	*/
	function getDelta($delta_value){
		
		$pageToken = null;
		if($delta_value)
			$startChangeId = $delta_value;
		else
			$startChangeId = null;
		$result = Array();
	
		do {
		  try {
		    $parameters = array();
		    if ($startChangeId) {
		      $parameters['startChangeId'] = $startChangeId;
		    }
		    if ($pageToken) {
		      $parameters['pageToken'] = $pageToken;
		    }
			$parameters["includeSubscribed"] = false;
		    $changes = $this->gdrClient->changes->listChanges($parameters);
		    $result = array_merge($result, $changes->getItems());
		    $pageToken = $changes->getNextPageToken();
		  } catch (Exception $e) {
		    print "An error occurred: " . $e->getMessage();
		    $pageToken = NULL;
		  }
		} while ($pageToken);
		
		$ret["cursor"] = $startChangeId;
		$ret["entries"] = Array();
		
		//Array mit Updates aufbauen
		foreach ($result as $key => $value) {
			
			//Erstes Elemant überspringen, da der Cursor auf einen bereits geprüften Eintrag zeigt
			if($key==0) 
				continue;
		
			$path =  $this->getPathById($value["fileId"]);
			$path_explode = explode("/", $path);
			
			$ret["cursor"] = $value["id"];
			if(count($path_explode)>0 
				&& substr($path,0,strlen($this->ext_root)+1)==($this->ext_root."/")
				&& $path != $this->ext_root){
				$new_entry = Array();
				$new_entry["path"] = substr($path,strlen($this->ext_root)+1);
				
				if( (sizeof($value["modelData"])==0 && $value["deleted"]==1) 
					|| ($value["modelData"]["file"]["labels"]["trashed"]==1) ){
					$new_entry["action"]="delete";
				}elseif(isset($value["modelData"]["file"])){
						if($value["modelData"]["file"]["mimeType"]=="application/vnd.google-apps.folder")
							$new_entry["action"] = "newdir";
						else 
							$new_entry["action"] = "upload";
				}
				if(isset($new_entry["action"]))
					$ret["entries"][] = $new_entry;
			}
	
		}
		return $ret;
	}
	
}

?>