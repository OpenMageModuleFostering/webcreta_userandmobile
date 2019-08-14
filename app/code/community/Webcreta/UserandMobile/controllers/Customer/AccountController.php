<?php

	require_once "Mage/Customer/controllers/AccountController.php";  
	class Webcreta_UserandMobile_Customer_AccountController extends Mage_Customer_AccountController{
		
		public function createPostAction()
		{
			$errUrl = $this->_getUrl('*/*/create', array('_secure' => true));
			
			if (!$this->_validateFormKey()) {
				$this->_redirectError($errUrl);
				return;
			}
			
			/** @var $session Mage_Customer_Model_Session */
			$session = $this->_getSession();
			if ($session->isLoggedIn()) {
				$this->_redirect('*/*/');
				return;
			}
			
			if (!$this->getRequest()->isPost()) {
				$this->_redirectError($errUrl);
				return;
			}
			
			$customer = $this->_getCustomer();
			
			try {
				$errors = $this->_getCustomerErrors($customer);
				
				if (empty($errors)) {
					$customer->cleanPasswordsValidationData();
					$formData = $this->getRequest()->getPost();
					$postMobile = $formData['mobile'];
					if(!empty($postMobile)){
						$customerCollection = Mage::getModel('customer/customer')->getCollection();
						$customerCollection->addAttributeToSelect(array('mobile', 'firstname', 'lastname', 'email'));
						$allMobileArr = array();
						
						foreach ($customerCollection as $singleCustomer) {
							$allMobileArr[] = $singleCustomer->getMobile();
						}
						
						if(in_array($postMobile, $allMobileArr)){
							Mage::getSingleton('core/session')->addError('Mobile number is already in use.');
							$url = Mage::getUrl('customer/account/create');
							$response = Mage::app()->getFrontController()->getResponse();
							$response->setRedirect($url);
							$response->sendResponse();
							exit;
						}
					}
					$customer->save();
					
					$this->_dispatchRegisterSuccess($customer);
					$this->_successProcessRegistration($customer);
					return;
					} else {
					$this->_addSessionError($errors);
				}
				} catch (Mage_Core_Exception $e) {
				$session->setCustomerFormData($this->getRequest()->getPost());
				if ($e->getCode() === Mage_Customer_Model_Customer::EXCEPTION_EMAIL_EXISTS) {
					$url = $this->_getUrl('customer/account/forgotpassword');
					$message = $this->__('There is already an account with this email address. If you are sure that it is your email address, <a href="%s">click here</a> to get your password and access your account.', $url);
					} else {
					$message = $this->_escapeHtml($e->getMessage());
				}
				$session->addError($message);
				} catch (Exception $e) {
				$session->setCustomerFormData($this->getRequest()->getPost());
				$session->addException($e, $this->__('Cannot save the customer.'));
			}
			
			$this->_redirectError($errUrl);
		}
		public function loginPostAction()
		{
			$formData = $this->getRequest()->getPost('login');
			$mobile = $formData['username'];
			
			$CustomAddress = Mage::getResourceModel('customer/customer_collection')
            ->addAttributeToSelect('*')
			->addAttributeToFilter('mobile',$mobile);
			foreach($CustomAddress as $singleCustomer){
				$custEmail = $singleCustomer['email'];
			}
			
			if (!$this->_validateFormKey()) {
				$this->_redirect('*/*/');
				return;
			}
			
			if ($this->_getSession()->isLoggedIn()) {
				$this->_redirect('*/*/');
				return;
			}
			$session = $this->_getSession();
			
			if ($this->getRequest()->isPost()) {
				$login = $this->getRequest()->getPost('login');
				if(!empty($CustomAddress->getData())){
					$login['username'] = $custEmail;
				}
				
				if (!empty($login['username']) && !empty($login['password'])) {
					try {
						$session->login($login['username'], $login['password']);
						if ($session->getCustomer()->getIsJustConfirmed()) {
							$this->_welcomeCustomer($session->getCustomer(), true);
						}
						} catch (Mage_Core_Exception $e) {
						switch ($e->getCode()) {
							case Mage_Customer_Model_Customer::EXCEPTION_EMAIL_NOT_CONFIRMED:
                            $value = $this->_getHelper('customer')->getEmailConfirmationUrl($login['username']);
                            $message = $this->_getHelper('customer')->__('This account is not confirmed. <a href="%s">Click here</a> to resend confirmation email.', $value);
                            break;
							case Mage_Customer_Model_Customer::EXCEPTION_INVALID_EMAIL_OR_PASSWORD:
                            $message = $e->getMessage();
                            break;
							default:
                            $message = $e->getMessage();
						}
						$session->addError($message);
						$session->setUsername($login['username']);
						} catch (Exception $e) {
						// Mage::logException($e); // PA DSS violation: this exception log can disclose customer password
					}
					} else {
					$session->addError($this->__('Login and password are required.'));
				}
			}
			
			$this->_loginPostRedirect();
		}
		
		public function editPostAction()
		{
			if (!$this->_validateFormKey()) {
				return $this->_redirect('*/*/edit');
			}
			
			if ($this->getRequest()->isPost()) {
				/** @var $customer Mage_Customer_Model_Customer */
				$customer = $this->_getSession()->getCustomer();
				$customer->setOldEmail($customer->getEmail());
				/** @var $customerForm Mage_Customer_Model_Form */
				$customerForm = $this->_getModel('customer/form');
				$customerForm->setFormCode('customer_account_edit')
                ->setEntity($customer);
				
				$customerData = $customerForm->extractData($this->getRequest());
				
				$errors = array();
				$customerErrors = $customerForm->validateData($customerData);
				if ($customerErrors !== true) {
					$errors = array_merge($customerErrors, $errors);
					} else {
					$customerForm->compactData($customerData);
					$errors = array();
					
					if (!$customer->validatePassword($this->getRequest()->getPost('current_password'))) {
						$errors[] = $this->__('Invalid current password');
					}
					
					// If email change was requested then set flag
					$isChangeEmail = ($customer->getOldEmail() != $customer->getEmail()) ? true : false;
					$customer->setIsChangeEmail($isChangeEmail);
					
					// If password change was requested then add it to common validation scheme
					$customer->setIsChangePassword($this->getRequest()->getParam('change_password'));
					
					if ($customer->getIsChangePassword()) {
						$newPass    = $this->getRequest()->getPost('password');
						$confPass   = $this->getRequest()->getPost('confirmation');
						
						if (strlen($newPass)) {
							/**
								* Set entered password and its confirmation - they
								* will be validated later to match each other and be of right length
							*/
							$customer->setPassword($newPass);
							$customer->setPasswordConfirmation($confPass);
							} else {
							$errors[] = $this->__('New password field cannot be empty.');
						}
					}
					
					// Validate account and compose list of errors if any
					$customerErrors = $customer->validate();
					if (is_array($customerErrors)) {
						$errors = array_merge($errors, $customerErrors);
					}
				}
				
				if (!empty($errors)) {
					$this->_getSession()->setCustomerFormData($this->getRequest()->getPost());
					foreach ($errors as $message) {
						$this->_getSession()->addError($message);
					}
					$this->_redirect('*/*/edit');
					return $this;
				}
				
				try {
					$customer->cleanPasswordsValidationData();
					
					// Reset all password reset tokens if all data was sufficient and correct on email change
					if ($customer->getIsChangeEmail()) {
						$customer->setRpToken(null);
						$customer->setRpTokenCreatedAt(null);
					}
					
					$postMobile = $this->getRequest()->getPost('mobile');
					if(!empty($postMobile)){
						$currentCustomerId = Mage::getSingleton('customer/session')->getCustomer()->getId();
						$customerModel = Mage::getModel('customer/customer')->load($currentCustomerId);
						$currentCustomerMobile = $customerModel->getMobile();
						$customerCollection = Mage::getModel('customer/customer')->getCollection();
						$customerCollection->addAttributeToSelect(array('mobile', 'firstname', 'lastname', 'email'));
						$allMobileArr = array();
						
						
						
						foreach ($customerCollection as $singleCustomer) {
							$allMobileArr[] = $singleCustomer->getMobile();
						}
						
						if($currentCustomerMobile == $postMobile){
							$customer->setMobile($currentCustomerMobile);
						}elseif(in_array($postMobile, $allMobileArr)){
							Mage::getSingleton('core/session')->addError('Mobile number is already in use.');
							$url = Mage::getUrl('customer/account/edit');
							$response = Mage::app()->getFrontController()->getResponse();
							$response->setRedirect($url);
							$response->sendResponse();
							exit;
						}
						else{
							$customer->setMobile($postMobile);
						}
					}
					$customer->save();
					$this->_getSession()->setCustomer($customer)
                    ->addSuccess($this->__('The account information has been saved.'));
					
					if ($customer->getIsChangeEmail() || $customer->getIsChangePassword()) {
						$customer->sendChangedPasswordOrEmail();
					}
					
					$this->_redirect('customer/account');
					return;
					} catch (Mage_Core_Exception $e) {
					$this->_getSession()->setCustomerFormData($this->getRequest()->getPost())
                    ->addError($e->getMessage());
					} catch (Exception $e) {
					$this->_getSession()->setCustomerFormData($this->getRequest()->getPost())
                    ->addException($e, $this->__('Cannot save the customer.'));
				}
			}
			
			$this->_redirect('*/*/edit');
		}
	}
