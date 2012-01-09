<?php
/**
 * UploadController - handler for upload form
 *
 * @author Pavel Kovalyov <pavlo.kovalyov@gmail.com>
 * @todo : response helper
 */
class Backend_UploadController extends Zend_Controller_Action {
    private $_caller = null;
	private $_uploadHandler = null;

	private $_websiteConfig;
	private $_themeConfig;

	public function init() {
		parent::init();
		if(!Tools_Security_Acl::isAllowed(Tools_Security_Acl::RESOURCE_MEDIA)) {
			$this->_redirect($this->_helper->website->getUrl(), array('exit' => true));
		}
		$this->_websiteConfig	= Zend_Registry::get('website');
		$this->_themeConfig		= Zend_Registry::get('theme');

		$this->_caller = $this->getRequest()->getParam('caller');
		$this->_uploadHandler = new Zend_File_Transfer_Adapter_Http();
		$this->_uploadHandler->setDestination(realpath($this->_websiteConfig['path'] . $this->_websiteConfig['tmp']));

	}

	public function uploadAction() {
		$this->_helper->getHelper('layout')->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

		$this->_uploadHandler->clearFilters()->clearValidators();

		$methodName = '_upload'.ucfirst(strtolower($this->_caller));
		if (method_exists($this, $methodName)) {
			$response = $this->$methodName();
		} else {
			throw new Exceptions_SeotoasterException('Method not allowed.');
		}
		clearstatcache();
		//$this->_sendResponse($response);
		$this->_helper->json($response);
	}

	/**
	 * Send response to client
	 * deprecated since became using JSON helper
	 * @param type $response
	 * @deprecated
	 * @todo remove before release
	 */
	private function _sendResponse($response){
		if ($this->getRequest()->isXmlHttpRequest()){
			$this->getResponse()->setHeader('Content-type', 'application/json');
		} else {
			$this->getResponse()->setHeader('Content-type', 'text/plain');
		}
		$this->getResponse()
			->setHeader('Cache-Control', 'no-cache, must-revalidate')
			->setBody(json_encode($response))
			->sendResponse();
//		exit();
	}

	private function _uploadThemes(){
		$this->_uploadHandler->addValidator('Extension', false, 'zip')
			->addValidator(new Validators_MimeType(array('application/zip')), false);

		$themeArchive = $this->_uploadHandler->getFileInfo();

		if (!$this->_uploadHandler->isValid()){
			return 'error';
		}
		if (!extension_loaded('zip')){
			throw new Exceptions_SeotoasterException('No zip extension loaded');
		}
		$tmpFolder = $this->_uploadHandler->getDestination();
		$zip       = new ZipArchive();
		$zip->open($themeArchive['file']['tmp_name']);
		$unzipped = $zip->extractTo($tmpFolder);
		if ($unzipped !== true){
			return array('name'=>$themeArchive['file']['name'], 'error' => 'Can\'t extract zip file to tmp directory');
		}
		$themeName = str_replace('.zip', '', $themeArchive['file']['name']);
		$isValid = $this->_validateTheme($themeName);
		if ( true === $isValid ) {
			$destinationDir = $this->_websiteConfig['path'].$this->_themeConfig['path'];
			if (is_dir($destinationDir.$themeName)){
				Tools_Filesystem_Tools::deleteDir($destinationDir.$themeName);
			}
			$zip->extractTo($destinationDir);
			$zip->close();
			Tools_Filesystem_Tools::deleteDir($tmpFolder.'/'.$themeName);
		} else {
			$zip->close();
			return array('name'=>$themeArchive['file']['name'], 'error' => $isValid);
		}
		return array(
			'error' => false,
			'name'=>$themeArchive['file']['name'],
			'type'=>$themeArchive['file']['type'],
			'size'=>$themeArchive['file']['size'],
			'themename' => $themeName
			);
	}

	/**
     * This function checks name of theme and returns array of errors.
     * @param type $themeFolder
     * @return mixed true if valid array with error description if not valid
     */
    private function _validateTheme($themename)
    {
		$tmpPath = $this->_uploadHandler->getDestination();
		$themeFolder = realpath($tmpPath.'/'.$themename);
		if ($themeFolder === false) {
			return 'Theme directory don\'t match the archive name.';
		}
        if (!is_dir($themeFolder)) {
            return 'Can not create folder for unpack zip file. 0peration not permitted.';
        }

        $listFiles = Tools_Filesystem_Tools::scanDirectory($themeFolder);
        if (empty($listFiles)) {
            return 'Your theme directory is empty.';
        }

        if (!preg_match("/^[a-zA-Z-0-9]{1,255}$/", $themename)) {
            return 'Theme name is invalid. Only letters, digits and dashes allowed.';
        }

        if (!in_array('style.css', $listFiles)) {
            return 'File "style.css" doesn\'t exists.';
        }

        if (!file_exists($themeFolder . '/index.html')) {
            return 'File "index.html" doesn\'t exists.';
        }

        if (!file_exists($themeFolder . '/category.html')) {
            return 'File "category.html" doesn\'t exists.';
        }

        if (!file_exists($themeFolder . '/default.html')) {
            return 'File "default.html" doesn\'t exists.';
        }

        if (!file_exists($themeFolder . '/news.html')) {
            return 'File "news.html" doesn\'t exists.';
        }

        if (!is_dir($themeFolder . '/images/')) {
            return 'Directory "images" doesn\'t exists.';
        }

        return true;
    }

	/**
	 * Handler for pictures/video upload interface
	 * @return array
	 */
	private function _uploadImages($savePath = null, $resize = true) {
		$miscConfig = Zend_Registry::get('misc');

		if (!$savePath) {
			//useful if file submited directly to this method
			$savePath = $this->_getSavePath();
		}

		$this->_uploadHandler->clearValidators()
			->addValidator('Extension', false,  array('jpg', 'png', 'gif'))
			->addValidator(new Validators_MimeType(array('image/gif','image/jpeg','image/jpg','image/png')), false)
			->addValidator('ImageSize', false, array('maxwidth' => $miscConfig['imgMaxWidth'], 'maxheight' => $miscConfig['imgMaxWidth']));

		$explodedFile = explode(DIRECTORY_SEPARATOR, $this->_uploadHandler->getFileName());
		$fileInfo     = explode('.', end($explodedFile));
		$goodFileName = preg_replace('/[^A-z0-9.-_]+/ui', '', $fileInfo[0]) . '.' . $fileInfo[1];

		$this->_uploadHandler->addFilter('Rename', array('target' => $goodFileName, 'overwrite' => true));

		$receivePath = ($resize ? $savePath . DIRECTORY_SEPARATOR . 'original' : $savePath);

		if ($this->_uploadHandler->isUploaded() && $this->_uploadHandler->isValid()){
			if (!is_dir($receivePath)){
				try{
					Tools_Filesystem_Tools::mkDir($receivePath);
				} catch (Exceptions_SeotoasterException $e){
					error_log($e->getMessage());
				}
			}
			$this->_uploadHandler->setDestination($receivePath);
			$this->_uploadHandler->receive();
			$file = $this->_uploadHandler->getFileName();

			if ($resize){
			$status = Tools_Image_Tools::batchResize($file, $savePath);
			} else {
				$status = true;
			}

			return array('error' => ($status !== true), 'result' => $status);
		}

		return array('error' => true, 'result' => $this->_uploadHandler->getMessages());
	}

	/**
 	 * Handler for files uploader
	 * @return array
	 */
	private function _uploadFiles($savePath = null){
		$this->_uploadHandler->clearValidators();
		$this->_uploadHandler->clearFilters();

		if (!$savePath) {
			$savePath = $this->_getSavePath();
		}

		$file = reset($this->_uploadHandler->getFileInfo());
		$fileName = $this->_uploadHandler->getFileName();
		preg_match('~[^\x00-\x1F"<>\|:\*\?/]+\.[\w\d]{2,8}$~iU', $fileName, $match);
		if (!$match) {
			return array('result' => 'Corrupted filename' , 'error' => true);
		}
		$fileName = $match[0];

		$this->_uploadHandler->addFilter('Rename', array(
            'target' => $savePath.DIRECTORY_SEPARATOR.$fileName,
            'overwrite' => true
			));

		if ($this->_uploadHandler->isUploaded() && $this->_uploadHandler->isValid()){
			try {
				$this->_uploadHandler->receive();
			} catch (Exceptions_SeotoasterException $e){
				$response = array('result' => $e->getMessage(), 'error' => true);
			}
		}

		$response = array('result' => $this->_uploadHandler->getMessages() , 'error' => !$this->_uploadHandler->isReceived());

		return $response;
	}

	/**
	 * Handler for "upload media" section
	 */
	private function _uploadMedia(){
		$this->_uploadHandler->clearValidators();
		$this->_uploadHandler->clearFilters();
		$miscConfig = Zend_Registry::get('misc');

		$savePath = $this->_getSavePath();

		$fileInfo = $this->_uploadHandler->getFileInfo();
		$file     = reset($fileInfo);

		switch ($file['type']) {
			case 'image/png':
			case 'image/jpg':
			case 'image/jpeg':
			case 'image/gif':
				$result = $this->_uploadImages($savePath);
				break;
			default:
				$result = $this->_uploadFiles($savePath);
				break;
}

		return $result;
	}

	/**
	 * Method get a 'folder' name from request array and checks if this folder exists.
	 * If not it creates this folder
	 * @return string directory path or false if error
	 */
	private function _getSavePath() {
		$folder = trim(preg_replace('~[^\w]+|[\s\-]+~ui', '-', filter_var($this->getRequest()->getParam('folder'), FILTER_SANITIZE_STRING)), '-');
		if (!$folder || empty($folder)) {
			return array('error' => true, 'result' => 'No files uploaded. Please select folder.');
		}
		$folder = trim($folder, ' \/');
		$folderValidator  = new Zend_Validate_Regex('~^[^\x00-\x1F"<>\|:\*\?/]+$~');
		if (!$folderValidator->isValid($folder)){
			return array('error' => true, 'result' => 'Bad folder name');
		}
		$savePath = $this->_websiteConfig['path'] . $this->_websiteConfig['media'] . $folder . DIRECTORY_SEPARATOR;
		if (!is_dir($savePath)){
			try{
				Tools_Filesystem_Tools::mkDir($savePath);
			} catch (Exceptions_SeotoasterException $e){
				 error_log($e->getMessage());
				 return false;
			}
		}
		return realpath($savePath);
	}

	private function _uploadTemplatepreview(){
		$miscConfig = Zend_Registry::get('misc');

		$currentTheme = $this->_helper->config->getConfig('currentTheme');

		$savePath = $this->_websiteConfig['path'].$this->_themeConfig['path'].$currentTheme.DIRECTORY_SEPARATOR.$this->_themeConfig['templatePreview'];

		$name = trim($this->getRequest()->getParam('templateName'));

		$fileMime = $this->_uploadHandler->getMimeType();

		switch ($fileMime){
			case 'image/png':
				$newName = $name.'.png';
				break;
			case 'image/jpg':
			case 'image/jpeg':
				$newName = $name.'.jpg';
				break;
			case 'image/gif':
				$newName = $name.'.gif';
				break;
			default:
				return false;
				break;
		}

		if (!$name || empty ($name)){
			return false;
		}
		$newImageFile = $savePath.$newName;

		//checking for existing images with same name ...
		if (!is_dir($savePath)){
			Tools_Filesystem_Tools::mkDir($savePath);
		}
		$existingImages = Tools_Filesystem_Tools::scanDirectory($savePath, false, false);
		$existingImages = preg_grep('~^'.$name.'\.(png|jpg|gif)$~i', $existingImages);
		// ...and removing them
		foreach ($existingImages as $img){
			Tools_Filesystem_Tools::deleteFile($savePath.$img);
		}

		$this->_uploadHandler->addFilter('Rename',
                   array('target' => $newImageFile,
                         'overwrite' => true));
		$result = $this->_uploadImages($savePath, false);

		if ($result['error'] == false) {
			Tools_Image_Tools::resize($newImageFile, $miscConfig['templatePreviewWidth'], true);
			$result['thumb'] = 'data:'.$fileMime.';base64,'.base64_encode(Tools_Filesystem_Tools::getFile($newImageFile));
		}

		return $result;
	}

	private function _uploadPagepreview() {
		$miscConfig       = Zend_Registry::get('misc');
		$configTeaserSize = $this->_helper->config->getConfig('teaserSize');

		$savePath = $this->_websiteConfig['path'].$this->_websiteConfig['tmp'];

		$fileMime = $this->_uploadHandler->getMimeType();
		switch ($fileMime){
			case 'image/png':
				$newName = '.png';
				break;
			case 'image/jpg':
			case 'image/jpeg':
				$newName = '.jpg';
				break;
			case 'image/gif':
				$newName = '.gif';
				break;
			default:
				return false;
				break;
		}

		$newName = md5(microtime(1)).$newName;
		$newImageFile = $savePath.$newName;

		$this->_uploadHandler->addFilter('Rename',
                   array('target' => $newImageFile,
                         'overwrite' => true));
		$result = $this->_uploadImages($savePath, false);

		if ($result['error'] == false) {
			Tools_Image_Tools::resize($newImageFile, (($configTeaserSize) ? $configTeaserSize : $miscConfig['pageTeaserSize']), true);
			$result['src'] = $this->_helper->website->getUrl() . $this->_websiteConfig['tmp'] . $newName;
		}

		return $result;
	}


	private function _uploadPlugin() {
		$this->_uploadHandler->addValidator('Extension', false, 'zip')
			->addValidator(new Validators_MimeType(array('application/zip')), false);
		$pluginArchive = $this->_uploadHandler->getFileInfo();

		if (!$this->_uploadHandler->isValid()){
			return array('error' => true);
		}

		$destination = $this->_uploadHandler->getDestination();

		$zip         = new ZipArchive();
		$zip->open($pluginArchive['file']['tmp_name']);

		$unzipped = $zip->extractTo($destination);

		if($unzipped !== true){
			return array(
				'name'  => $pluginArchive['file']['name'],
				'error' => 'Can\'t extract zip file to tmp directory'
			);
		}

		$pluginName      = str_replace('.zip', '', $pluginArchive['file']['name']);

		$validateMessage = $this->_validatePlugin($pluginName);
		$miscConfig      = Zend_Registry::get('misc');
		if($validateMessage === true) {
			$destinationDir = $this->_websiteConfig['path'] . $miscConfig['pluginsPath'];
			if(is_dir($destinationDir . $pluginName)) {
				Tools_Filesystem_Tools::deleteDir($destinationDir . $pluginName);
			}
			$res = $zip->extractTo($destinationDir);
			$zip->close();
			Tools_Filesystem_Tools::deleteDir($destination . '/' . $pluginName);
		}
		else {
			$zip->close();
			return array(
				'name'  => $pluginArchive['file']['name'],
				'error' => $validateMessage
			);
		}
		return array(
			'error'      => false,
			'name'       => $pluginArchive['file']['name'],
			'type'       => $pluginArchive['file']['type'],
			'size'       => $pluginArchive['file']['size'],
			'pluginname' => $pluginName
		);
	}

	private function _validatePlugin($pluginName) {
		$pluginFolder = realpath($this->_uploadHandler->getDestination() . '/' . $pluginName);
		if($pluginFolder === false) {
			return 'Plugin directory don\'t match the archive name.';
		}
		if(!is_dir($pluginFolder)) {
			return 'Can not create folder for unpack zip file. 0peration not permitted.';
		}


		$listFiles = Tools_Filesystem_Tools::scanDirectory($pluginFolder);
        if (empty($listFiles)) {
            return 'Your plugin directory is empty.';
        }

        if (!preg_match("/^[a-zA-Z-0-9]{1,255}$/", $pluginName)) {
            return 'Theme name is invalid. Only letters, digits and dashes allowed.';
        }

        if (!in_array(ucfirst($pluginName) . '.php', $listFiles)) {
            return 'Plugin main file doesn\'t exist or has a wrong name';
        }

		if (!in_array('readme.txt', $listFiles)) {
            return 'File "readme.txt" doesn\'t exist.';
        }

        return true;

	}

}