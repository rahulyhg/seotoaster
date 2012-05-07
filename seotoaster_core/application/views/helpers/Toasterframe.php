<?php
/**
 * Created by JetBrains PhpStorm.
 * User: iamne
 * Date: 5/7/12
 * Time: 7:12 PM
 * To change this template use File | Settings | File Templates.
 */
class Zend_View_Helper_Toasterframe extends Zend_View_Helper_Abstract {

    const REMOTE_URL = 'http://new.seotoaster.com/';

    public function toasterFrame($pageUrl = 'index.html', $params = array()) {
        //defaults
        $params = $this->_initDefaults($params);
        return '<iframe
                    src="' . self::REMOTE_URL . $pageUrl . '"
                    width="' . $params['width'] . '"
                    height="' . $params['height'] . '"
                    frameborder="' . intval($params['frameborder']) . '"
                    seamless>
                </iframe>';
    }

    private function _initDefaults($params) {
        return array(
            'width'       => isset($params['width']) ? $params['width'] : '100%',
            'height'      => isset($params['height']) ? $params['height'] : '100%',
            'frameborder' => isset($params['frameborder']) ? $params['frameborder'] : 'no',
        );
    }
}