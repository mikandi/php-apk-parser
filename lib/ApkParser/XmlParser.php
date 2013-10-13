<?php

namespace ApkParser;

class XmlParser  {    
	const END_DOC_TAG    = 0x00100101;
	const START_TAG      = 0x00100102;
	const END_TAG        = 0x00100103;
	const TEXT_TAG       = 0x00100104;

	private $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n";
	private $bytes = array();
	private $ready = false;

	public static $indent_spaces  = "                                             ";

	/**
	* Store the SimpleXmlElement object
	* @var \SimpleXmlElement
	*/
	private $xmlObject = NULL;


	public function __construct(\ApkParser\Stream $apkStream) {
		$this->bytes = $apkStream->getByteArray();
	}

	public static function decompressFile($file,$destination = NULL) {
		if(!is_file($file))
			throw new \Exception("{$file} is not a regular file");

		$parser = new self(new \ApkParser\Stream(fopen($file,'rd')));
		//TODO : write a method in this class, ->saveToFile();
		file_put_contents($destination === NULL ?  $file : $destination,$parser->getXmlString());
	}

	public function decompress()  {
		$numbStrings    = $this->littleEndianWord($this->bytes, 4*4);
		$sitOff         = 0x24; 
		$stOff          = $sitOff + $numbStrings * 4;
		$this->bytesTagOff      = $this->littleEndianWord($this->bytes, 3*4);

		for ($ii = $this->bytesTagOff; $ii < count($this->bytes) - 4; $ii += 4):
			if ($this->littleEndianWord($this->bytes, $ii) == self::START_TAG) :
				$this->bytesTagOff = $ii;  
				break;
			endif;
		endfor;

		$off            = $this->bytesTagOff;
		$indentCount   = 0;
		$startTagLineNo = -2;

		while ($off < count($this->bytes)) {
			$currentTag     = $this->littleEndianWord($this->bytes, $off);
			$lineNo         = $this->littleEndianWord($this->bytes, $off + 2*4);
			$nameNsSi       = $this->littleEndianWord($this->bytes, $off + 4*4);
			$nameSi         = $this->littleEndianWord($this->bytes, $off + 5*4); 

			switch($currentTag) {
				case self::START_TAG:
				{
					$tagSix         = $this->littleEndianWord($this->bytes, $off + 6*4);
					$numbAttrs      = $this->littleEndianWord($this->bytes, $off + 7*4); 
					$off           += 9*4;
					$tagName       = $this->compXmlString($this->bytes, $sitOff, $stOff, $nameSi);
					$startTagLineNo = $lineNo;
					$attr_string    = "";
					//\D::L("START_TAG", $tagName);

					$foundAttrNames = array();
					for ($ii=0; $ii < $numbAttrs; $ii++) {
						$attrNameNsSi   = $this->littleEndianWord($this->bytes, $off);  
						$attrNameSi     = $this->littleEndianWord($this->bytes, $off + 1*4);
						$attrValueSi    = $this->littleEndianWord($this->bytes, $off + 2*4);
						$attrFlags      = $this->littleEndianWord($this->bytes, $off + 3*4);  
						$attrResId      = $this->littleEndianWord($this->bytes, $off + 4*4);
						$off += 5*4;

						$attrName = $this->compXmlString($this->bytes, $sitOff, $stOff, $attrNameSi);
						if($attrValueSi != 0xffffffff)
							$attrValue =  $this->compXmlString($this->bytes, $sitOff, $stOff, $attrValueSi);
						else
							$attrValue  = "0x" . dechex($attrResId);

						if (in_array($attrName, $foundAttrNames)) {
							$attrName .= "-$ii";
						}
						$attr_string .= " " . $attrName . "=\"" . $attrValue . "\"";
						array_push($foundAttrNames, $attrName);
					}
					$this->appendXmlIndent($indentCount, "<". $tagName . $attr_string . ">");
					$indentCount++;
				}
				break;

				case self::END_TAG:
				{
					$indentCount--;
					$off += 6*4;
					$tagName = $this->compXmlString($this->bytes, $sitOff, $stOff, $nameSi);
					//\D::L("END_TAG", $tagName);
					$this->appendXmlIndent($indentCount, "</" . $tagName . ">");
				}
				break;

				case self::END_DOC_TAG:
				{
					$this->ready = true; 
					break 2;
				}
				break;

				case self::TEXT_TAG:
				{
					// The text tag appears to be used when Android references an id value that is not
					// a string literal
					// To skip it, read forward until finding the sentinal 0x00000000 after finding
					// the sentinal 0xffffffff
					//\D::I("TEXT_TAG", "Found at ".$off);
					//\D::L("Current XML", self::cleanXml($this->xml));
					$sentinal = "0xffffffff";
					while ($off < count($this->bytes)) {
						$curr = "0x".str_pad(dechex($this->littleEndianWord($this->bytes, $off)), 8, "0", STR_PAD_LEFT);;
						//\D::L("TEXT_TAG->SkipText", "$off: $curr");
						$off += 4;
						if ($off > count($this->bytes)) {
							throw new \Exception("Sentinal not found before end of file");
						}
						if ($curr == $sentinal && $sentinal == "0xffffffff") {
							//\D::I("TEXT_TAG->SkipText", "Found $sentinal");
							$sentinal = "0x00000000";
						} else if ($curr == $sentinal) {
							//\D::I("TEXT_TAG->SkipText", "Found $sentinal");
							break;
						}
					}
					//$noff = $off;
					//while($noff < count($this->bytes)) {
					//	\D::L("Remaining", "0x".str_pad(dechex($this->littleEndianWord($this->bytes, $noff)), 8, "0", STR_PAD_LEFT));
					//	$noff += 4;
					//}
				}
				break;


				default:
					throw new \Exception("Unrecognized tag code '"  . dechex($currentTag) . "' at offset " . $off);
					break;
			}
		}
		if (!$this->ready) {
			throw new \Exception("END_TAG not found in AXML");
		}
	}

	public function compXmlString($xml, $sitOff, $stOff, $str_index) {
		if ($str_index < 0) 
			return null;

		$strOff = $stOff + $this->littleEndianWord($xml, $sitOff + $str_index * 4);
		return $this->compXmlStringAt($xml, $strOff);
	}

	public function appendXmlIndent($indent, $str) {
		$this->appendXml(substr(self::$indent_spaces,0, min($indent * 2, strlen(self::$indent_spaces)))  .  $str);
	}

	public function appendXml($str) {
		$this->xml .= $str ."\r\n";
	}

	public function compXmlStringAt($arr, $string_offset) {
		$strlen = $arr[$string_offset + 1] << 8 & 0xff00 | $arr[$string_offset] & 0xff;
		$string = "";

		for ($i=0; $i<$strlen; $i++) 
			$string .= chr($arr[$string_offset + 2 + $i * 2]);

		return $string;
	} 

	public function littleEndianWord($arr, $off) {
		return $arr[$off+3] << 24&0xff000000 | $arr[$off+2] << 16&0xff0000 | $arr[$off+1]<<8&0xff00 | $arr[$off]&0xFF;
	}

	public function output() {
		echo $this->getXmlString();
	}

	public function getXmlString() {
		if(!$this->ready)
			$this->decompress();
		return $this->xml;
	}

	public function getXmlObject($className = '\SimpleXmlElement') {
		if($this->xmlObject === NULL || !$this->xmlObject instanceof $className) {
			\D::L(__FUNCTION__, self::cleanXml($this->getXMLString()));
			$this->xmlObject = simplexml_load_string(self::cleanXml($this->getXmlString()),$className);
		}

		return $this->xmlObject;       
	}

	private static function cleanXml($string) {
		$ret = utf8_encode($string);
		$ret = str_replace("&", "&amp;", $ret);
		return $ret;
	}
}