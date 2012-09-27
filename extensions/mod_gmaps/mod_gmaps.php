<?php



/**
* AIOS Joomla! Module
*
* @author    Yoeri Nijsen
* @copyright Copyright (C) 2012 AllinOneSoftware. All rights reserved.
* @license	 GNU/GPL
*/

// no direct access
defined('_JEXEC') or die('Restricted access');
global $mainframe;
$doc = JFactory::getDocument();
$mod_base = dirname(__FILE__);

// count instances
if (!isset($GLOBALS['aios_maps'])) {
	$GLOBALS['aios_maps'] = 1;
} else {
	$GLOBALS['aios_maps']++;
}

// include helper
require_once (dirname(__FILE__).DS.'helper.php');

//Get the list with all articles
$list  = modAIOSmapsHelper::getList($params, $access);

// Count the total articles
$items = count($list);

// init vars
$google_api_key     	= $params->get('google_api_key', 'abcdefg');
$location           	= $params->get('location', 'Rotterdam, Nederland');

//$marker_popup       	= $params->get('marker_popup', 0);
$marker_popup_onload  	= $params->get('marker_popup', 0);
//$marker_text        	= $params->get('marker_text', '');
$marker_onload_text   	= $params->get('marker_text', '');
$main_icon          	= $params->get('main_icon', 'red-dot');

$custom_icon			= $params->get('custom_icon', false);
$custom_icon_loc		= $params->get('custom_icon_loc', false);

$max_markers			= $params->get('max_markers', 10);

$zoom_level         	= $params->get('zoom_level', 13);
$map_controls       	= $params->get('map_controls', 2);
$scroll_wheel_zoom  	= $params->get('scroll_wheel_zoom', 1);
$map_type           	= $params->get('map_type', 0);
$type_controls      	= $params->get('map_type_controls', '');

$overview_controls  	= $params->get('overview_controls', 1);

$html_preppend			= modAIOSmapsHelper::stripText( $params->get('html_preppend', '') );
$html_append			= modAIOSmapsHelper::stripText( $params->get('html_append', '') );

$geocode_cache      	= $params->get('geocode_cache', 1);

$directions         	= $params->get('directions', 1);
$directions_dest_up 	= $params->get('directions_dest_update', 0);
$locale             	= $params->get('locale', 'en');

$module_width       	= $params->get('module_width', 500);
$module_height      	= $params->get('module_height', 400);
$menuitem				= $params->get('menuitem', 101);

$display_markers		= $params->get('marker_list', FALSE);
$display_markers_pos	= $params->get('marker_list_pos', 1);

$module_base        	= JURI::base() . 'modules/mod_aios_maps/';

// css parameters
$maps_id           = 'aios-maps-' . $GLOBALS['aios_maps'];
$css_module_width  = 'width: ' . $module_width . 'px;';
$css_module_height = 'height: ' . $module_height . 'px;';

// init cache
$cache = $geocode_cache ? new modAIOSmapsCache(dirname(__FILE__).DS.'geocode_cache.txt') : null;
if ($cache && !$cache->check()) { 
	echo "<div class=\"alert\">";
	echo "	<strong>Cache not writeable please update the file permissions! (geocode_cache.txt)</strong>";
	echo "</div><br/>";
	return;
}

// Check if the center coordinates are specified
$center = modAIOSmapsHelper::locate($google_api_key, $location, $cache);
if (!$center) { 
	echo "<div class=\"alert\">";
	echo 	"<strong>Unable to get map center coordinates, please verify your location! (" . $location . ")</strong>";
	echo "</div>";
	return;
}

$markers = array();

// js parameters
$messages    = array();
$maps_var    = $maps_id;
$javascript = "$$$$ = jQuery.noConflict();";
$javascript .= "$$$$(function() { ";
$javascript .= "$$$$('#{$maps_var}').gMap({
				zoom:{$zoom_level},
				latitude: {$center},
				controls: {$type_controls},
				scrollwheel: {$scroll_wheel_zoom},
				maptype: {$map_type},
				html_prepend: '{$html_preppend}',
				html_append: '{$html_append}',
				markers: [";

for ($i=0; $i < $items; $i++) {
	$articleid = $list[$i]->id;
	$categoryid = $list[$i]->catid;
	
	$introtext= "<div id='introtext_map'>". $list[$i]->introtext ."</div>";
	$url = JRoute::_("index.php?option=com_content&amp;view=article&id={$articleid}&catid={$categoryid}&Itemid={$menuitem}");
	$title = $list[$i]->title;
	
	$more_button 	 = 	"<div class='map_btn'>";
	$more_button 	.= 		"<a href='{$url}'>";
	$more_button	.= 			JText::_('COM_CONTENT_READ_MORE');
	$more_button 	.= 		"</a>";
	$more_button	.= 	"</div>";
	
	// Prepare HTML
	$html = modAIOSmapsHelper::stripText($introtext).modAIOSmapsHelper::stripText($more_button);
	
	if (array_key_exists($i, $list)) {
		if ($coordinates = modAIOSmapsHelper::locate($google_api_key, $list[$i]->metakey, $cache)) {

			// Add necessary JS lines
			$javascript .= 	 "{id: ".$i.",";
			$javascript .= 	 "latitude: ".$coordinates['lat'].",";
            $javascript .=   "longitude: ".$coordinates['lng'].",";
			$javascript .= 	 "html: '".$html."'},";
			
			// Check if a list of markers should be shown
			if( $display_markers == TRUE ){
				$markers[$i]['id'] = $title."_".$i;
				$markers[$i]['title'] = $title;
				$markers[$i]['html'] = $html;
				$markers[$i]['link'] = $url;
			}
			
		} else {
			$messages[]  = $list[$i]->title . JText::_('NOT_FOUND');
		}
	}
}
$javascript .= "]});";
$javascript .= "});";

if ($cache) {
	$cache->save();
}

require JModuleHelper::getLayoutPath('mod_aios_maps', $params->get('layout', 'default'));

$document =& JFactory::getDocument();
$document->addScript('https://maps.google.com/maps?file=api&amp;v=2&amp;key=' . $google_api_key);
//$document->addScript('http://maps.google.com/maps/api/js?sensor=false');
$document->addScript('https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js');
$document->addScript($module_base . 'js/jquery.gmap-1.1.0.js');
$document->addScriptDeclaration($javascript);
