<?php
/**
* YOOmaps Joomla! Module
*
* @author    yootheme.com
* @copyright Copyright (C) 2007 YOOtheme Ltd. & Co. KG. All rights reserved.
* @license	 GNU/GPL
*/

// no direct access
defined('_JEXEC') or die('Restricted access');

require_once (JPATH_SITE . '/components/com_content/helpers/route.php');

class modAIOSmapsHelper
{

	function getList(&$params, &$access)
	{
		global $mainframe;

		$db 	=& JFactory::getDBO();

		$aid	= self::getUserAccessId();

		$catids	   = self::categoryIds($params);
		$items 	   = (int) $params->get('items', 10);
		$order 	   = $params->get('order', 'o_asc');

		$contentConfig	= &JComponentHelper::getParams( 'com_content' );
		$noauth			= !$contentConfig->get('shownoauth');

		$nullDate = $db->getNullDate();
		jimport('joomla.utilities.date');
		$date = new JDate();
		$now = $date->toMySQL();

		// Ordering
		switch ($order) {
			case 'm_dsc':
				$ordering = 'a.modified DESC, a.created DESC';
				break;
			case 'h_dsc':
				$ordering = 'a.hits DESC, a.created DESC';
				break;				
			case 'c_dsc':
				$ordering = 'a.created DESC';
				break;
			case 'o_asc':
			default:
				$ordering = 'a.ordering';
				break;
		}

		// Query to determine article count
		$query = 	'SELECT a.*,' .
					' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(":", a.id, a.alias) ELSE a.id END as slug,'.
					' CASE WHEN CHAR_LENGTH(cc.title) THEN CONCAT_WS(":", cc.id, cc.title) ELSE cc.id END as catslug'.
					' FROM #__content AS a' .
					' INNER JOIN #__categories AS cc ON cc.id = a.catid' .
					' WHERE a.state = 1 ' .
					($noauth ? ' AND a.access <= ' .(int) $aid. ' AND cc.access <= ' .(int) $aid : '').
					' AND (a.publish_up = "'.$nullDate.'" OR a.publish_up <= "'.$now.'" ) ' .
					' AND (a.publish_down = "'.$nullDate.'" OR a.publish_down >= "'.$now.'" )' .
					' AND cc.id IN ('. $catids .') ' .
					' AND cc.published = 1' .
					' ORDER BY ' . $ordering;
					' LIMIT '.$items;
		
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		if ($order == 'rnd') shuffle($rows);
		
		return $rows;
	}
	
	function stripText($text) {
		$text = str_replace(array("\r\n", "\n", "\r", "\t"), "", $text);
		$text = addcslashes($text, "'");
		return $text;
	}

	function locate($gapikey, $location, $cache = null) {
		// check if location are lng / lat values
		$location = trim($location);
		//var_dump($location);exit;
		if (preg_match('/([0-9.-]{1,})[,] ([0-9.-]{1,})|([0-9.-]{1,})[,]([0-9.-]{1,})/', $location, $regs)) {
			if ($location == $regs[0]) {
				return array('lat' => ($regs[1] ? $regs[1] : $regs[3] ), 'lng' => ( $regs[2] ? $regs[2] : $regs[4] ));
			}
		}
		
		// use geocode to translate location
		return modAIOSmapsHelper::geoCode($gapikey, $location, $cache);
	}

	function geoCode($gapikey, $address, $cache = null) {
		// use cache result
		if ($cache !== null && $value = $cache->get($address)) {
			if (preg_match('/([0-9.-]{1,})[,] ([0-9.-]{1,})|([0-9.-]{1,})[,]([0-9.-]{1,})/', $value, $regs)) {
				return array('lat' => ($regs[1] ? $regs[1] : $regs[3] ), 'lng' => ( $regs[2] ? $regs[2] : $regs[4] ));
			}
		}
				
		// query google maps geocoder
		$coordinates = modAIOSmapsHelper::queryGeoCoder($gapikey, $address);
		if ($cache !== null && $coordinates !== null) {
			$cache->set($address, $coordinates['lat'].",".$coordinates['lng']);
		}
		return $coordinates;
	}

	function queryGeoCoder($gapikey, $address) {
		//Prepare address
		$address = ltrim ($address, '[');
		$address = rtrim ($address, ']');
		
		$gapiurl  = 'http://maps.google.com/maps/geo?&output=xml&key=' . $gapikey . '&q=' . urlencode($address);
		$handle   = @fopen($gapiurl, 'r');
	    $contents = '';
		if ($handle !== false) {
			while (!feof($handle)) {
		        $contents .= fread($handle, 8192);
		    }
		    @fclose($handle);		    
		    if (preg_match('/([0-9.-]{1,})[,]([0-9.-]{1,})/', $contents, $regs)) {
			    return array('lng' => $regs[1], 'lat' => $regs[2]);
			}
		}
	    return null;
	}
	
	function getUserAccessId($default = 1){
		$user 	=& JFactory::getUser();
		
		return $user->get('aid', $default) == 0 ? $default : $user->get('aid', $default);
	}
	
	function categoryIds($params, $order = "up"){
		$ids = $params->get('catid', 0);

		$where = "";
		$i=0;
		
		foreach($ids as $id){
			$i++;
			if($i == count($ids)){
				$where .= $id;
			}else{
				$where .= $id.",";
			}
		}
		
		return $where;
	}
}

class modAIOSmapsCache
{
	var $file  = 'config.txt';
	var $items = array();
	var $dirty = false;
	var $hash  = true;

	function modAIOSmapsCache($file) {
		$this->file = $file;
		$this->parse();
	}

	function check() {
		return is_readable($this->file) && is_writable($this->file);
	}

	function get($key) {
		if ($this->hash) $key = md5($key);
		if (!array_key_exists($key, $this->items)) return null;

		return $this->items[$key];
	}

	function set($key, $value) {
		if ($this->hash) $key = md5($key);
		if (array_key_exists($key, $this->items) && $this->items[$key] == $value) return;
		
		$this->items[$key] = $value;
		$this->dirty = true;
	}

	function parse() {
		$handle = fopen($this->file, 'r');
		if ($handle !== false) {
			while ($l = fgets($handle)) {
				if (preg_match('/^#/', $l) == false) {
					if (preg_match('/^(.*?)=(.*?)$/', $l, $regs)) {
						$this->items[$regs[1]] = $regs[2];
					}
				}
			}
			@fclose($handle);
		}
	}

	function save() {
		if (!$this->dirty) return;
		
		$new = '';
		foreach ($this->items as $key => $value) {
			$new .= $key . "=" . $value . "\n";
		}
		$handle = fopen($this->file, 'w');
		if ($handle !== false) {
			fwrite($handle, $new);
			@fclose($handle);
		}
	}
}

