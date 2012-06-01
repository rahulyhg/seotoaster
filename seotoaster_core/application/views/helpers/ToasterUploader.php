<?php
/**
 * ToasterUploader
 *
 * @author Pavel Kovalyov <pavlo.kovalyov@gmail.com>
 */
class Zend_View_Helper_ToasterUploader extends Zend_View_Helper_Abstract {

	private $_libraryPath = 'system/js/external/plupload/';

	private $_uploadForm = null;

	private $_uploadActionUrl = array(
		'controller' => 'backend_upload',
		'action'     => 'upload'
	);

	private $_fileTypes = array(
		'image' => array('title'=>'Image files', 'extensions' => 'jpg,gif,png,jpeg'),
		'zip'	=> array('title' => 'Zip files', 'extensions' => 'zip'),
		'video' => array('title' => 'Video files', 'extensions' => 'mp4, avi, mov, flv'),
	);

	/**
	 * Generates upload form
	 * @param array $options
	 * @param string $options['id'] Unique id for uploader form
	 * @param boolean $options['caller'] Define context from which upload was called
	 * @param boolean $options['disableResize'] Turn off client-side resizing (if supported in browser)
	 * @param boolean $options['noMultiupload'] Turn off client-side multiple file selection for upload (will be applyed for all instances of upload on page)
	 * @param array $options['filters'] Type of files allowed to be upload (for filtering in file select dialog): possible 'image', 'zip', 'video'
	 * @return html generated form.
	 */
	public function toasterUploader($options = null){
		if (isset($options['caller']) && !empty($options['caller'])) {
			$this->_uploadActionUrl['caller'] = $options['caller'];
			$this->view->caller = $options['caller'];
		}
		if (isset($options['disableResize']) && !empty ($options['disableResize'])){
			$this->view->disableResize = (bool) $options['disableResize'];
		}
		if (isset($options['noMultiupload']) && !empty ($options['noMultiupload'])){
			$this->view->noMultiupload = (bool) $options['noMultiupload'];
		}

		$dbConfigHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('config');
		//assign all necessary JS and CSSs
		$websiteUrl = Zend_Controller_Action_HelperBroker::getExistingHelper('website')->getUrl();
		$this->view->jQuery()->addJavascriptFile($websiteUrl.$this->_libraryPath.'plupload.js');
		$this->view->jQuery()->addJavascriptFile($websiteUrl.$this->_libraryPath.'plupload.html5.js');
		$this->view->jQuery()->addJavascriptFile($websiteUrl.$this->_libraryPath.'plupload.html4.js');
		$this->view->jQuery()->addJavascriptFile($websiteUrl.$this->_libraryPath.'plupload.flash.js');

		//assign all view variables
		$this->view->config     = Zend_Registry::get('misc');
		$this->view->teaserSize = $dbConfigHelper->getConfig('teaserSize');
		$this->view->actionUrl  = preg_replace('~/.*[/]*backend/~iu', $websiteUrl . 'backend/', $this->view->url($this->_uploadActionUrl, 'backend'));
		//$this->view->actionUrl = trim($websiteUrl,'/') . $this->view->url($this->_uploadActionUrl, 'backend');
		$this->view->formId = isset($options['id']) && !empty ($options['id']) ? $options['id'] : 'toaster-uploader';
		$this->view->buttonCaption = isset($options['caption']) && !empty ($options['caption']) ? $options['caption'] : 'Upload files';

		$this->view->fileTypes = $this->_fileTypes;
		$this->view->filters = isset($options['filters']) && !empty ($options['filters']) ? $options['filters'] : null;

		$this->view->caller = isset($this->_uploadActionUrl['caller']) ? $this->_uploadActionUrl['caller'] : false;

		return $this->view->render('admin'.DIRECTORY_SEPARATOR.'uploadForm.phtml');
	}

}