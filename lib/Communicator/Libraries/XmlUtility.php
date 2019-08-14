<?php

/**
 * Utility class for processing XML documents
 */
class XmlUtility {	
	
	public static function parse($xml) {
		$xml = preg_replace("/(<\\/?)[\\w]*?:/m", '$1', $xml);	
		$xml = preg_replace("/xmlns:[\\w]*?=\"[^\"]*?\"/m", '', $xml);
		
		$element = simplexml_load_string($xml);
		return $element;
	}
}
