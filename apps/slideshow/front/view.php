<?php
/**
 * Slideshow Application - Front View
 */

defined('WITYCMS_VERSION') or die('Access denied');

/**
 * SlideshowView is the Front View of the Slideshow Application
 *
 * @package Apps\Slideshow\Front
 * @author Johan Dufau <johan.dufau@creatiwity.net>
 * @author Julien Blatecky <julien.blatecky@creatiwity.net>
 * @version 0.5.0-dev-02-01-2015
 */
class SlideshowView extends WView {
	public function block($model) {
		$this->assign('css', '/apps/slideshow/front/css/slideshow.css');
		$this->assign('css', '/libraries/wityslider-1.2.9/wityslider.css');
		$this->assign('require', 'apps!slideshow/slideshow');

		$this->assign("slides", $model["slides"]);
		$this->assign("config", $model["config"]);
	}
}

?>
