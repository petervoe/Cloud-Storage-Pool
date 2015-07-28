<?php

require_once "classes/Datastorage.php";
require_once "api/Dropbox/autoload.php";

/**
 * Datastorage_Dropbox
 * 
 * Klasse für die Instanzierung von Dropbox-Speichern als virtuelle Datenspeicher des Cloud Storage Pools (CSP)
 *
 * @author      Peter Völkl <peter@vape.net>
 * @package     CSP
 * @version		2014-04-26
 */
class Datastorage_Dropbox extends Datastorage{
	private $appInfo;
	private $webAuth;
	private $dbxClient;

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
		$this->type = "DRPBO";
		
		//Dropboxzugriff initialisieren
		$this->appInfo = \Dropbox\AppInfo::loadFromJson($appinfo);
		$this->webAuth = new \Dropbox\WebAuthNoRedirect($this->appInfo, "PHP-Example/1.0");
		$this->dbxClient = new \Dropbox\Client($this->token, "PHP-Example/1.0");
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
		$ret = array();
		
		
		if(strlen($directory)>0)
			$path=$this->ext_root."/".$directory;
		else 
			$path=$this->ext_root;
		
		$folderMetadata = $this->dbxClient->getMetadataWithChildren($path);
		$ret["contents"] = Array();
		if($folderMetadata){
			$ret["name"]= basename($directory);
			$ret["path"] = substr($directory, 0, strlen($directory)-strlen(basename($directory)));
			if(substr($ret["path"],-1)=="/")
				$ret["path"] = substr($ret["path"],0,-1);
			$ret["datastorage_name"]=$this->name;
			$ret["creation_date"] = strtotime($folderMetadata["modified"]);
			$ret["last_modified"] = strtotime($folderMetadata["modified"]);
			$ret["is_dir"] = $folderMetadata["is_dir"];
			if(isset($folderMetadata["contents"])){
				foreach ($folderMetadata["contents"] as $key => $value) {
					$ret["contents"][$key]["name"] = substr($value["path"],strlen($path)+1);
					$ret["contents"][$key]["path"] = $directory;
					$ret["contents"][$key]["datastorage_name"]=$this->name;
					$ret["contents"][$key]["is_dir"] = $value["is_dir"];
					$ret["contents"][$key]["creation_date"] = strtotime($value["modified"]);
					$ret["contents"][$key]["last_modified"] = strtotime($value["modified"]);
					if(isset($value["mime_type"]))
						$ret["contents"][$key]["mime_type"] = $value["mime_type"];
					if(isset($value["bytes"]))
						$ret["contents"][$key]["bytes"] = $value["bytes"];
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
		else
			$path=$this->ext_root."/".$file;
		$filename=basename($path);
	
		$fp = fopen($local_temp.$filename, "w+b");
		
		//Entschlüsselung
		if($this->crypto_key != null){
			$this->csp_decryptStream($fp, $this->crypto_key);
		}
		
		$fileMetadata = $this->dbxClient->getFile($path, $fp);
		fclose($fp);
		
		$fileList=null;
		if(isset($fileMetadata) && $fileMetadata != null){
			//Ist Datei
			$ret["name"]=$filename;
			$ret["datastorage_name"]=$this->name;
			$ret["tmpfile"]=$local_temp.$filename;
			$ret["mime_type"]=$fileMetadata["mime_type"];
			$ret["bytes"]=$fileMetadata["bytes"];
			$ret["creation_date"] = strtotime($fileMetadata["modified"]);
			$ret["last_modified"] = strtotime($fileMetadata["modified"]);
			$ret["is_dir"] = 0;
		} elseif(($fileList = $this->getFilelist($file)) && $fileList["is_dir"]==1){
			//Ist Verzeichnis
			$ret["name"]=$filename;
			$ret["datastorage_name"]=$this->ext_root;
			$ret["mime_type"]=null;
			$ret["bytes"]=0;
			$ret["creation_date"] = strtotime($fileMetadata["modified"]);
			$ret["last_modified"] = strtotime($fileMetadata["modified"]);
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
		$path=$this->ext_root."/".$newfile;
		
		$fp = fopen($tmpfile, "rb");
		
		//Verschlüsselung
		if($this->crypto_key != null){
			$this->csp_encryptStream($fp, $this->crypto_key);
		}
		
		$result = $this->dbxClient->uploadFile($path, \Dropbox\WriteMode::update(null), $fp);
		fclose($fp);
		
		return $result;
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
		$path=$this->ext_root."/".$newfile;
		
		//Verschlüsselung
		if($this->crypto_key != null){
			$this->csp_encryptStream($fp, $this->crypto_key);
		}
		
		$result = $this->dbxClient->uploadFile($path, \Dropbox\WriteMode::update(null), $fp);
		fclose($fp);
		
		return $result;
	}
	
	/**
	* deleteFile
	* 
	* Löscht eine Datei des virtuellen Datenspeichers
	*
	* @param string		$file		Pfad innerhalb des virtuellen Datenspeichers zur Datei
	*/
	function deleteFile($file){
		$path=$this->ext_root."/".$file;
		
		$result = $this->dbxClient->delete($path);
		
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
		$source_path=$this->ext_root."/".$source_file;
		$dest_path=$this->ext_root."/".$dest_file;
		
		$result = $this->dbxClient->copy($source_path,$dest_path);
		
		return $result;
	}
	
	/**
	* createFolder
	* 
	* Erstellt einen neuen Ordner innerhalb des virtuellen Datenspeichers
	*
	* @param string		$folder		Pfad des neuen Ordners inkusive Ordnername innerhalb des virtuellen Datenspeichers
	*/
	function createFolder($folder){
		$path=$this->ext_root."/".$folder;
		
		$result = $this->dbxClient->createFolder($path);
		
		return $result;
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
		$result = $this->dbxClient->getDelta($delta_value,null);
	
		//einheitliches Return Array aufbauen
		$ret["cursor"] = $result["cursor"];
		foreach ($result["entries"] as $key => $value) {
			if(strtolower(substr($value[0],0,strlen($this->ext_root)+1))==strtolower($this->ext_root."/")){
				$ret["entries"][$key]["path"] = substr($value[0],strlen($this->ext_root)+1);
				if(sizeof($value[1])==0)
					$ret["entries"][$key]["action"]="delete";
				elseif($value[1]["is_dir"]==1)
					$ret["entries"][$key]["action"] = "newdir";
				else
					$ret["entries"][$key]["action"] = "upload";
			}
		}
		//$ret["debug"] = $result;
		return $ret;
	}
	
}

?>