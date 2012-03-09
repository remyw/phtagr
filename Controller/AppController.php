<?php
/**
 * PHP versions 5
 *
 * phTagr : Tag, Browse, and Share Your Photos.
 * Copyright 2006-2012, Sebastian Felis (sebastian@phtagr.org)
 *
 * Licensed under The GPL-2.0 License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2006-2012, Sebastian Felis (sebastian@phtagr.org)
 * @link          http://www.phtagr.org phTagr
 * @package       Phtagr
 * @since         phTagr 2.2b3
 * @license       GPL-2.0 (http://www.opensource.org/licenses/GPL-2.0)
 */

App::uses('Logger', 'Lib');
App::uses('Controller', 'Controller');

class AppController extends Controller
{
  var $helpers = array('Html', 'Js', 'Form', 'Session', 'Menu', 'Option');
  var $components = array('Session', 'Cookie', 'Feed', 'RequestHandler', 'Menu');
  var $uses = array('User', 'Option');
  
  var $_nobody = null;
  var $_user = null;

  /** Calls _checkSession() to check the credentials of the user 
    @see _checkSession() */
  function beforeFilter() {
    parent::beforeFilter();
    $this->_checkSession();
    $this->Feed->add('/explorer/rss', array('title' => __('Recent photos')));
    $this->Feed->add('/explorer/media', array('title' =>  __('Media RSS of recent photos'), 'id' => 'gallery'));
    $this->Feed->add('/comment/rss', array('title' => __('Recent comments')));
    
    $this->_configureEmail();
    $this->_setMainMenu();
    $this->_setTopMenu();
  }
  
  function _setMainMenu() {
    $this->Menu->setCurrentMenu('main-menu');
    $this->Menu->addItem(__('Home'), "/");
    $this->Menu->addItem(__('Explorer'), array('controller' => 'explorer', 'action' => 'index'));
    if ($this->hasRole(ROLE_GUEST)) {
      $user = $this->getUser();
      $this->Menu->addItem(__('My Photos'), array('controller' => 'explorer', 'action' => 'user', $user['User']['username']));
    }
    if ($this->hasRole(ROLE_USER)) {
      $this->Menu->addItem(__('Upload'), array('controller' => 'browser', 'action' => 'quickupload'));
    }
  }

  function _setTopMenu() {
    $this->Menu->setCurrentMenu('top-menu');
    $role = $this->getUserRole();
    if ($role == ROLE_NOBODY) {
      $this->Menu->addItem(__('Login'), array('controller' => 'users', 'action' => 'login'));
      if ($this->getOption('user.register.enable', 0)) {
        $this->Menu->addItem(__('Sign Up'), array('controller' => 'users', 'action' => 'register'));
      }
    } else {
      $user = $this->getUser();
      $this->Menu->addItem(__('Howdy, %s!', $user['User']['username']), false);
      $this->Menu->addItem(__('Logout'), array('controller' => 'users', 'action' => 'logout'));
      $this->Menu->addItem(__('Dashboard'), array('controller' => 'options'));
    }
  }

  /** Configure email component on any SMTP configuration values in core.php */
  function _configureEmail() {
    if (isset($this->Email)) {
      if (Configure::read('Mail.from')) {
        $this->Email->from = Configure::read('Mail.from');
      } else {
        $this->Email->from = "phTagr <noreply@{$_SERVER['SERVER_NAME']}>";
      }
      if (Configure::read('Mail.replyTo')) {
        $this->Email->replyTo = Configure::read('Mail.replyTo');
      } else {
        $this->Email->replyTo = "noreply@{$_SERVER['SERVER_NAME']}";
      }
      $names = array('host', 'port', 'username', 'password');
      foreach($names as $name) {
        $value = Configure::read("Smtp.$name");
        if ($value) {
          $this->Email->smtpOptions[$name] = $value;
        }
      }
      if (!empty($this->Email->smtpOptions['host'])) {
        $this->Email->delivery = 'smtp';
      }
    }
 }
  function beforeRender() {
    parent::beforeRender();
    if ($this->getUserId() > 0) {
      // reread user for updated options
      $user = $this->User->findById($this->getUserId());
    } else {
      $user = $this->getUser();
    }
    $this->request->params['options'] = $this->Option->getOptions($user);
    $this->set('currentUser', $user);

    if ($this->RequestHandler->isMobile()) {
      $this->viewClass = "Theme";
      $this->theme = "Mobile";
    }
  }

  function _checkCookie() {
    $this->Cookie->name = 'phTagr';
    return $this->Cookie->read('user');
  }

  function _checkKey() {
    if (!isset($this->request->params['named']['key'])) {
      return false;
    }

    // fetch and delete key from passed parameters
    $key = $this->request->params['named']['key'];
    unset($this->request->params['named']['key']);

    $data = $this->User->findByKey($key, array('User.id'));
    if ($data) {
      $this->Session->write('Authentication.key', $key);
      return $data['User']['id'];
    }
    return false;
  }

  /** Checks a cookie for a valid user id. If a id found, the user is load to
   * the session 
   * @todo Check expired user */
  function _checkSession() {
    //$this->Session->activate();
    if (!$this->Session->check('Session.requestCount')) {
      $this->Session->write('Session.requestCount', 1);
      $this->Session->write('Session.start', time());
    } else {
      $count = $this->Session->read('Session.requestCount');
      $this->Session->write('Session.requestCount', $count + 1);
    }

    if ($this->Session->check('User.id')) {
      return true;
    }

    $authType = 'Cookie';
    $id = $this->_checkCookie();
    if (!$id) {
      $id = $this->_checkKey();
      $authType = 'Key';
    }

    if (!$id) {
      return false;
    }

    // Fetch User
    $user = $this->User->findById($id);
    if (!$user) {
      return false;
    }

    if ($this->User->isExpired($user)) {
      Logger::warn("User account of '{$user['User']['username']}' (id {$user['User']['id']}) is expired!");
      return false;
    }

    $this->User->writeSession($user, &$this->Session);
    Logger::info("User '{$user['User']['username']}' (id {$user['User']['id']}) authenticated via $authType!");

    return true;
  }

  /** Checks the session for valid user. If no user is found, it checks for a
   * valid cookie
   * @return True if the correct session correspond to an user */ 
  function _checkUser() {
    if (!$this->_checkSession()) {
      return false;
    }

    if ($this->_user) {
      return true;
    }

    $userId = $this->Session->read('User.id');
    $user = $this->User->findById($userId);
    if (!$user) {
      return false;
    }

    $this->_user = $user;
    return true;
  }
 
  function getUser() {
    if (!$this->_checkUser() || !$this->_user) {
      if (!$this->_nobody && isset($this->User)) {
        $this->_nobody = $this->User->getNobody();
      } elseif (!$this->_nobody) {
        $this->_nobody = array('User' => array('username' => '', 'password' => '', 'role' => ROLE_NOBODY));
      }
      return $this->_nobody;
    }
    return $this->_user;
  }

  function getUserRole() {
    $user =& $this->getUser();
    return $user['User']['role'];
  }
  
  function getUserId() {
    $user =& $this->getUser();
    return $user['User']['id'];
  }

  function hasRole($requiredRole = ROLE_NOBODY) {
    if ($requiredRole <= $this->getUserRole()) {
      return true;
    }
    return false;
  }

  function requireRole($requiredRole=ROLE_NOBODY, $options = null) {
    $options = am(array(
      'redirect' => '/users/login', 
      'loginRedirect' => false, 
      'flash' => false), 
      $options);
    if (!$this->hasRole($requiredRole)) {
      if ($options['loginRedirect']) {
        $this->Session->write('loginRedirect', $options['loginRedirect']);
      }
      if ($options['flash']) {
        $this->Session->setFlash($options['flash']);
      }
      $this->redirect($options['redirect']);
      exit();
    }
    return true;
  }
  
  function getOption($name, $default=null) {
    $user = $this->getUser();
    return $this->Option->getValue($user, $name, $default);
  }

  /** Load a component
    */
  function loadComponent($componentName, &$parent = null) {
    if (is_array($componentName)) {
      $loaded = true;
      foreach ($componentName as $name) {
        $loaded &= $this->loadComponent($name, &$parent);
      }
      return $loaded;
    }
    
    if (!$parent) {
      $parent = &$this;
    }
    if (isset($parent->{$componentName})) {
      return true;
    }
    if (!in_array($componentName, $parent->components)) {
      $parent->components[] = $componentName;
    }
    $component = $this->Components->load($componentName);
    if (!$component) {
      Logger::warn("Could not load component $componentName");
      return false;
    }
    $parent->{$componentName} = $component;
    $component->initialize(&$this);

    return true;
  }
 
}
?>
