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
?>
<div class="aios-maps" style="<?php echo $css_module_width ?>">
	<?php if( $display_markers == TRUE && $display_markers_pos == 0 ){?>
		<div id="marker_list">
			<?php foreach ( $markers as $id => $marker ){
				echo $marker['title'];
			}
			?>
		</div>
	<?php }?>
	<div id="<?php echo $maps_id ?>" style="<?php echo $css_module_width . $css_module_height ?>"></div>
	<?php foreach ($messages as $message) : ?>
	<div class="alert"><strong><?php echo $message; ?></strong></div>
	<?php endforeach; ?>
</div>
