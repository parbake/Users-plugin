<?php

/**
 * Provides controll logic for managing users
 *
 * JSC (http://jasonsnider.com/jsc)
 * Copyright 2012, Jason D Snider. (http://jasonsnider.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2012, Jason D Snider. (http://jasonsnider.com)
 * @link http://jasonsnider.com
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author Jason D Snider <jason@jasonsnider.com>
 * @package       Users
 */
App::uses('UsersAppController', 'Users.Controller');

/**
 * Provides controll logic for managing users
 * @author Jason D Snider <jason.snider@42viral.org>
 * @package app/Users
 */
class UsersController extends UsersAppController {

    /**
     * Holds the name of the controller
     *
     * @var string
     */
    public $name = 'Users';

    /**
     * Call the components to be used by this controller
     *
     * @var array
     */
    //public $components = array();

    /**
     * Called before action
     * @return void
     */
    public function beforeFilter() {
        parent::beforeFilter();
        $this->Auth->allow(
            'create', 
            'login', 
            'logout', 
            'reset_password_request', 
            'reset_password'
        );

        $this->Authorize->allow(
            'edit', 
            'index'
        );
    }

    /**
     * The models used by the controller
     *
     * @var array
     */
    public $uses = array(
        'Users.UserGroup',
        'Users.User',
        'Users.PasswordReset'
    );

    /**
     * Displays an index of all users
     * @return void
     */
    public function index() {

        $this->paginate = array(
            'conditions' => array(),
            'limit' => 30
        );

        $data = $this->paginate('User');
        $this->set(compact('data'));
    }

    /**
     * A method for creating a new user in the system
     * @return void
     */
    public function create() {

        if(Configure::read('JSC.Users.allow_new_users')===false){
            $this->Session->setFlash(
                __("I'm sorry, we are not accepting new users at this time."),
                'info'
            );
            $this->redirect('/');
        }
        
        if (!empty($this->request->data)) {
            if ($this->User->createUser($this->request->data)) {
                $this->Session->setFlash(__('The record has been created!'), 'success');
                $this->redirect('/users/users/login/');
            } else {
                $this->Session->setFlash(__('Please correct the erros below!'), 'error');
            }
        }
    }

    /**
     * A method for creating a new user in the system
     * @param string $id The id of the user to be viewed
     * @return void
     */
    public function view($id) {

        $this->Authorize->me($id, "This profile has been marked private.");

        $user = $this->User->find(
                'first', array(
            'conditions' => array(
                'User.id' => $id
            ),
            'contain' => array(
                'UserSetting'
            )
                )
        );

        $this->set(compact('user', 'id'));
    }

    /**
     * Allows a user to update their own data
     * @return void
     */
    public function edit() {

        $id = $this->Session->read('Auth.User.id');

        if (!empty($this->request->data)) {
            if ($this->User->save($this->request->data)) {
                $this->Session->setFlash(__('The record has been updated!'), 'success');
            } else {
                $this->Session->setFlash(__('The record could not be updated!'), 'error');
            }
        }

        $this->request->data = $this->User->find(
            'first', array(
                'conditions' => array(
                    'User.id' => $id
                ),
                'contain' => array(
                    'EmailAddress',
                    'UserPrivilege',
                    'UserSetting'
                )
            )
        );

        $visibilities = $this->User->getList($this->User->visibilities);

        $this->set(compact('id', 'visibilities'));
    }

    /**
     * Displays an index of all users
     * @return void
     */
    public function admin_index() {

        $this->paginate = array(
            'conditions' => array(),
            'limit' => 30
        );

        $data = $this->paginate('User');
        $this->set(compact('data'));
    }

    /**
     * A method for creating a new user in the system
     * @param string $id
     * @return void
     */
    public function admin_view($id) {

        $user = $this->User->find(
            'first', array(
                'conditions' => array(
                    'User.id' => $id
                ),
                'contain' => array()
            )
        );

        $this->set(compact('user', 'id'));
    }

    /**
     * Allows an admin to update a users record
     * @param string $id
     * @return void
     */
    public function admin_edit($id) {

        if (!empty($this->request->data)) {
            //debug($this->request->data);
            //USER PRIVILEGES
            foreach ($this->request->data['UserPrivilege'] as $key => $values) {
                if ($values['allowed'] == 2) {
                    if (isset($values['id'])) {
                        //If a previously set priv is set to undefined, delete it's record fron the system.
                        $this->User->UserPrivilege->delete($values['id']);
                    }
                    unset($this->request->data['UserPrivilege'][$key]);
                }
            }

            //GROUP MEMBERSHIP 
            if (!empty($this->request->data['UserGroupUser'])) {
                foreach ($this->request->data['UserGroupUser'] as $key => $values) {
                    if (isset($values['id'])) {

                        if ($values['member'] == 0) {
                            //If a previously set user user_group is set to undefined, delete it's record fron the system.
                            $this->User->UserGroupUser->delete($values['id']);
                        }
                        //Nothing (more) to do, drop it from the array
                        unset($this->request->data['UserGroupUser'][$key]);
                    } else {

                        if (isset($values['user_group_id']) && isset($values['user_id'])) {
                            //If no membership is set, remove it from the array
                            if ($values['member'] == 0) {
                                unset($this->request->data['UserGroupUser'][$key]);
                            }
                            //Othewise it will get saved as relational data
                        } else {
                            unset($this->request->data['UserGroupUser'][$key]);
                        }
                    }
                }
            }

            //Save the data
            if ($this->User->saveAll($this->request->data)) {
                $this->Session->setFlash(__('The record has been updated!'), 'success');
            } else {
                $this->Session->setFlash(__('The record could not be updated!'), 'error');
            }
        }

        $this->request->data = $this->User->find(
            'first', array(
                'conditions' => array(
                    'User.id' => $id
                ),
                'contain' => array(
                    'UserGroupUser' => array(
                        'UserGroup'
                    ),
                    'UserPrivilege',
                    'EmailAddress'
                )
            )
        );

        $user_groups = $this->UserGroup->fetchUserGroupsWithUser($this->request->data['UserGroupUser']);
        $controllers = $this->Authorize->privileges($this->request->data['UserPrivilege']);
        $this->set(compact('id', 'controllers', 'user_groups'));
    }

    /**
     * A method for deleting a user account
     * @param string $id
     * @return void
     */
    public function admin_delete($id) {

        if (!empty($id)) {
            if ($this->User->purgeUser($id)) {
                $this->Session->setFlash(__("User {$id} has been deleted!"), 'success');
            } else {
                $this->Session->setFlash(__("User {$id} could not be deleted!"), 'error');
            }
        }

        $this->redirect('/admin/users');
    }

    /**
     * Provides a login action
     * @return void
     */
    public function login() {

        //Holds a common flash message for all errors occuring within this action
        $errorFlashMessage = __('You could not be authenticated!');
        
        //// 1. Has a user submitted credentials for authentication?
        if (!empty($this->request->data)) {
            //// 2. Make sure the neither of the fileds have empty data
            if (empty($this->request->data['User']['username'])) {
                $this->User->validationErrors['username'] = 'Please enter your username.';
                $this->Session->setFlash($errorFlashMessage, 'error');
            }

            if (empty($this->request->data['User']['password'])) {
                $this->User->validationErrors['password'] = 'Please enter your password.';
                return $this->Session->setFlash($errorFlashMessage, 'error');
            }            
            
            $user = $this->User->processLoginAttempt($this->request->data);

            if(!empty($user['User'])){
                //Create a login/auth session from the verified data
                if ($this->Auth->login($user['User'])) {
                    
                    $this->Session->setFlash(__('Welcome to the party!'), 'success');
                    return $this->redirect($this->Auth->redirect());
                }
            }
          
            return $this->Session->setFlash($errorFlashMessage, 'error');
        }
    }

    /**
     * Provides a logout action
     * @return void
     */
    public function logout() {
        //$this->Session->destroy();
        $this->Session->setFlash(__('You have been logged out.'), 'success');
        $this->redirect($this->Auth->logout());
    }

    /**
     * Allows a user to request that their password be reset
     * @return void
     */
    public function reset_password_request() {

        if (!empty($this->request->data)) {
            
            $username = $this->request->data['User']['username'];
            
            $user = $this->User->find(
                'first',
                array(
                    'conditions'=>array(
                        'User.username'=>$username, 
                    ),
                    'fields'=>array(
                        'User.id'
                    ),
                    'contain'=>array(
                        'EmailAddress'=>array()
                    )
                )
            );
            
            $passwordResetConfirmation = $this->PasswordReset->createPasswordReset($user['User']['id']);

            if($passwordResetConfirmation){
                
                $serverName = env('SERVER_NAME');
                $entityName = 'JSC';

                $email = new CakeEmail('passwordReset');
                $email->to($user['EmailAddress'][0]['email'])
                    ->viewVars(
                        array(
                            'entityName' => $entityName,
                            'serverName' => $serverName,
                            'username' => $username,
                            'confirmation' => $passwordResetConfirmation
                        )
                    )
                    ->send();
                
                $this->Session->setFlash(
                    __("We've sent password reset instructions to your email address."),
                    'success'
                );

                $this->redirect("/users/users/reset_password/{$username}");
            } else {
                $this->Session->setFlash(__('We could not complete your request'), 'success');
            }
        }
    }

    /**
     * Provided the UI for a user to enter and reset their password
     * @param string $username The account of username for the password to be reset
     * @param string $password_confirmation A confrmation token that verifies the rquestor has control of a targt email 
     * address
     * @return void
     */
    public function reset_password($username=null, $password_confirmation=null) {
        
        if (!empty($this->request->data)) {

            if($this->PasswordReset->reset($this->request->data['User'])){
                $this->Session->setFlash(__('Your password has been reset, you may now login.'), 'successs');
                $this->redirect('/users/users/login/');
                exit;
            }else{
                $this->Session->setFlash(__('Your password could not be reset.'), 'error');
            }

        } else {
            $this->request->data['User'] = compact('username', 'password_confirmation');
        }
    }

}