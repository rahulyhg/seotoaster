<?php

/**
 * UserController
 *
 * @author Eugene I. Nezhuta [Seotoaster Dev Team] <eugene@seotoaster.com>
 */
class Backend_UserController extends Zend_Controller_Action {

	public function init() {
		parent::init();
		if(!Tools_Security_Acl::isAllowed(Tools_Security_Acl::RESOURCE_USERS)) {
			$this->_redirect($this->_helper->website->getUrl(), array('exit' => true));
		}
		$this->_helper->AjaxContext()->addActionContext('list', 'json')->initContext('json');
		$this->view->websiteUrl = $this->_helper->website->getUrl();
	}

	public function manageAction() {
		$userForm = new Application_Form_User();
		if($this->getRequest()->isPost()) {
			if($userForm->isValid($this->getRequest()->getParams())) {
				$userMapper = new Application_Model_Mappers_UserMapper();
				$data       = $userForm->getValues();
				$user       = new Application_Model_Models_User($data);
				$userMapper->save($user);
				$this->_helper->response->success('Added.');
				exit;
			}
			else {
				$this->_helper->response->fail(Tools_Content_Tools::proccessFormMessagesIntoHtml($userForm->getMessages()));
				exit;
			}
		}
		$this->view->userForm = $userForm;
	}

	public function listAction() {
		$userMapper            = new Application_Model_Mappers_UserMapper();
		$this->view->users     = $userMapper->fetchAll();
		$this->view->usersList = $this->view->render('backend/user/list.phtml');
	}

}
