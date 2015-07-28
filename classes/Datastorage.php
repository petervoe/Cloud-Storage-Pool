<?php
/**
 * Datastorage
 * 
 * Abstrakte Klasse für die einheitliche Insanzierung von Datenspeichern unterschiedlicher Quelle
 * als virtueller Datenspeicher des Cloud Storage Pools (CSP)
 *
 * @author      Peter Völkl <peter@vape.net>
 * @package     CSP
 * @version		2014-04-26
 */
abstract class Datastorage{
	protected $id;
	protected $name;
	protected $type;
	protected $ext_root;
	protected $token;
	protected $crypto_key;
	
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
	*/
	function __construct($id,$name,$ext_root,$token,$crypto_key){
		$this->id = $id;
		$this->name = $name;
		$this->ext_root = $ext_root;
		$this->token = $token;
		$this->crypto_key = $crypto_key;
	}
	
	/**
	* getName
	* 
	* Liefert den Namen des Datastorages zurück
	*
	* @return string	Name des Datastorages
	*/				
	function getName(){
		return $this->name;
	}
	
	/**
	* csp_encryptStream
	* 
	* Verschlüsselt einen Filestream
	* Nach dem Beispiel von http://www.php.net/manual/en/filters.encryption.php 
	*
	* @param resource	&$fp		Filepointer des zu verschlüsselnden Filestreams
	* @param string		$crypto_key	Verwendeter Schlussel (Passphrase)
	*/
	function csp_encryptStream(&$fp, $crypto_key){
		$iv = substr(md5('iv#'.$crypto_key, true), 0, 8);
		$key = substr(md5('pass1#'.$crypto_key, true) . 
		             md5('pass2#'.$crypto_key, true), 0, 24);
		$opts = array('iv'=>$iv, 'key'=>$key);
		stream_filter_append($fp, 'mcrypt.tripledes', STREAM_FILTER_READ, $opts);
	}
	
	
	/**
	* csp_decryptStream
	* 
	* Entschlüsselt einen Filestream
	* Nach dem Beispiel von http://www.php.net/manual/en/filters.encryption.php 
	*
	* @param resource	&$fp		Filepointer des zu entschlüsselnden Filestreams
	* @param string		$crypto_key	Verwendeter Schlussel (Passphrase)
	*/
	function csp_decryptStream(&$fp, $crypto_key){
		$iv = substr(md5('iv#'.$crypto_key, true), 0, 8);
		$key = substr(md5('pass1#'.$crypto_key, true) . 
		             md5('pass2#'.$crypto_key, true), 0, 24);
		$opts = array('iv'=>$iv, 'key'=>$key);
		stream_filter_append($fp, 'mdecrypt.tripledes', STREAM_FILTER_WRITE, $opts);
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
	abstract function getFilelist($directory);
	
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
	abstract function getFile($file);
	
	/**
	* uploadFile
	* 
	* Ladet eine Datei in den virtuellen Datenspeicher hoch
	*
	* @param string		$tmpfile		Pfad zur temporären Datei die hochgeladen werden soll
	* @param string		$newfile		Pfad und Name der neuen Datei innerhalb des virtuellen Datenspeichers
	*/
	abstract function uploadFile($tmpfile,$newfile);
	
	/**
	* uploadFileStream
	* 
	* Lädt eine Datei in den virtuellen Datenspeicher hoch
	*
	* @param string		$fp				Filestream der hochzuladenden Datei
	* @param string		$newfile		Pfad und Name der neuen Datei innerhalb des virtuellen Datenspeichers
	*/
	abstract function uploadFileStream($fp,$newfile);
	
	/**
	* deleteFile
	* 
	* Löscht eine Datei des virtuellen Datenspeichers
	*
	* @param string		$file		Pfad innerhalb des virtuellen Datenspeichers zur Datei
	*/
	abstract function deleteFile($file);
	
	/**
	* copyFile
	* 
	* Kopiert eine Datei innerhalb des Datenspeichers
	*
	* @param String		$source_file	Pfad der Quelldatei innerhalb des virtuellen Datenspeichers
	* @param String		$dest_file	Pfad der Quelldatei innerhalb des virtuellen Datenspeichers
	*/
	abstract function copyFile($source_file, $dest_file);
	
	/**
	* createFolder
	* 
	* Erstellt einen neuen Ordner innerhalb des virtuellen Datenspeichers
	*
	* @param string		$folder		Pfad des neuen Ordners inkusive Ordnername innerhalb des virtuellen Datenspeichers
	*/
	abstract function createFolder($folder);
	
	/**
	* getDelta
	* 
	* Liefert alle Änderungen des virtuellen Datenspeichers seit dem übergebenen Cursor.
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
	abstract function getDelta($delta_value);
	
}

?>