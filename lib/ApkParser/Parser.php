<?php

namespace ApkParser;

/**
* @author Tufan Baris YILDIRIM
* @version v0.1
* @since 27.03.2012
* @link https://github.com/tufanbarisyildirim/php-apk-parser
* 
* Main Class.
* - Set the apk path on construction,
* - Get the Manifest object.
* - Print the Manifest XML.
* 
* @todo  Add getPackageName();
* @todo  Add getVersion();
* @todo  Add getUsesSdk();
* @todo  Add getMinSdk();
*/
class Parser	{
	/**
	* @var \ApkParser\Archive
	*/
	private $apk;

	/**
	* AndrodiManifest.xml
	* 
	* @var \ApkParser\Manifest
	*/
	private $manifest;

	public function __construct($apkFile) {
		$this->apk      = new \ApkParser\Archive($apkFile);
		$this->manifest = new \ApkParser\Manifest(new \ApkParser\XmlParser($this->apk->getManifestStream()));
	}

	/**
	* Get Manifest Object
	* @return \ApkParser\Manifest 
	*/
	public function getManifest() {
		return $this->manifest;
	}

	/**
	* Get the apk. Zip handler. 
	* - Extract all(or sp. entries) files,
	* - add file,
	* - recompress
	* - and other ZipArchive features.
	* 
	* @return \ApkParser\Archive
	*/
	public function getApkArchive() {
		return $this->apk;
	}  

	/**
	* Extract apk content directly
	* 
	* @param mixed $destination
	* @param array $entries
	* @return bool
	*/
	public function extractTo($destination,$entries = NULL) {
		 return $this->apk->extractTo($destination,$entries);
	}
}