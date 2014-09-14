<?php
App::uses('AppController', 'Controller');
/**
 * Users Controller
 *
 * @property User $User
 */
class UsersController extends AppController {

/**
 * beforeFilter action
 *
 */
	public function beforeFilter() {
		$this->Auth->allow('add', 'login', 'activate', 'reset', 'password');
	}


/**
 * index action
 *
 * @return void
 */
	public function index() {
		$users = $this->User->find('all');
		$this->set(array(
			'users' => $users,
			'_serialize' => array('users')
		));
	}


/**
 * login action
 *
 * @return void
 */
	public function login() {
		$this->layout = 'external';	

		if($this->Session->read('Auth.User')) {
			$this->redirect(array(
				'controller' => 'pages',
				'action' => 'display',
				'home'
			));
		}

		if($this->request->is('post')) {
			if($this->Auth->login()) {
				if($this->Auth->user('status')) {
					$this->set('user', $this->Session->read('Auth.User'));
					$this->redirect(array(
						'controller' => 'pages',
						'action' => 'display',
						'home'
					));
				} else {
					if($this->Auth->logout()) {
						$this->Session->setFlash(
							'Sua conta não está ativada, cheque seu e-mail.', 
							'alerts/alert_warning'
						);
						$this->redirect($this->referer());
					}
				}
			} else {
				$this->Session->setFlash(
					'Ops! Usuário ou senha inválidos, tente novamente.', 
					'alerts/alert_error'
				);
				$this->redirect($this->referer());
			}
		}
	}

/**
 * logout action
 *
 * @return void
 */
	public function logout() {
		if($this->Auth->logout()) {
			$this->Session->setFlash(
				'Você deslogou com sucesso!', 
				'alerts/alert_success'
			);
			$this->redirect(array(
				'controller' => 'users',
				'action' => 'login'
			));
		}
	}



/**
 * view action
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function view() {
		$id = $this->Auth->user('id');
		$user = $this->User->findById($id);
		
		unset($user['User']['password']);

		$this->set(array(
			'user' => $user,
			'_serialize' => array('user')
		));
	}

/**
 * add action
 *
 * @return void
 */
	public function add() {
		$this->layout = 'external';

		if ($this->request->is('post')) {
			$token = $this->User->generateToken();

			$this->User->create();
			$this->User->data['User']['token'] = $token;
			$this->User->data['User']['status'] = 0;
			if ($this->User->save($this->request->data)) {
				$this->User->sendActivationLink($token);
				$this->Session->setFlash(
					'Sua conta foi cadastrada com sucesso, cheque seu e-mail para ativá-la!', 
					'alerts/alert_success'
				);
				$this->redirect($this->referer());
			} else {
				$this->Session->setFlash(
					'O Usuário não pode ser cadastrado, tente novamente mais tarde.', 
					'alerts/alert_error'
				);
				$this->redirect($this->referer());
			}
		}
	}

/**
 * edit action
 *
 * @return void
 */
	public function edit() {
		$this->User->id = $this->Auth->user('id');
		if($this->User->save($this->request->data)) {
			$message = array(
				'text' => __('The user has been edited'),
				'type' => 'info'
			);
		} else {
			$message = array(
				'text' => __('The user could not been saved'),
				'type' => 'error'
			);
		}
		$this->set(array(
			'message' => $message,
			'_serialize' => array('message')
		));
	}

/**
 * delete action
 *
 * @return void
 */
	public function delete() {
		$id = $this->Auth->user('id');

		if($this->User->delete($id)) {
			$this->logout();
		} else {
			$this->set(array(
				'message' => array(
					'text' => __('The user could not been deleted'),
					'type' => 'error'
				),
				'_serialize' => array('message')
			));
		}
	}

/**
 * activate action
 * activate the user account using the token
 * @return void
 */
	public function activate($token) {
		$user = $this->User->findByToken($token);
		$this->User->id = $user['User']['id'];

		if($this->User->field('status') == 0) {
			if($this->User->saveField('status', 1)) {
				$this->Session->setFlash(
					'Sua conta foi ativada.',
					'alerts/alert_success'
				);
			}
		} else {
			$this->Session->setFlash(
				'Sua conta já está ativada',
				'alerts/alert_error'
			);
		}

		$this->redirect(array(
			'controller' => 'users',
			'action' => 'login'
		));
	}


/**
 * reset action
 * send a link to the user to change the password
 * @return void
 */
	public function reset() {
		$this->layout = 'external';

		if($this->request->is('post')) {
			$user = $this->User->findByEmail($this->request->data['User']['email']);
			if($user) {
				$this->User->id = $user['User']['id'];

				$this->User->data['User']['token'] = $this->User->generateToken();
				$this->User->data['User']['status'] = 2;

				if($this->User->save()) {
					$this->User->sendResetLink();
					$this->Session->setFlash(
						'Um link foi enviado ao seu e-mail.',
						'alerts/alert_success'
					);	
				} else {
					$this->Session->setFlash(
						'Não foi possível resetar sua senha, tente novamente.',
						'alerts/alert_error'
					);
				}
			} else {
				$this->Session->setFlash(
					'Este e-mail não está cadastrado no sistema.',
					'alerts/alert_error'
				);
			}
			$this->redirect($this->referer());
		}
	}

/**
 * password action
 * @param  [string] $token [token sent by e-mail]
 * @return void
 */
	public function password($token = null) {
		$this->layout = 'external';

		if($this->request->is('post')) {
			$user = $this->User->findByToken($this->request->data['User']['token']);

			if($user['User']['status'] == 2) {
				$this->User->id = $user['User']['id'];
				if($this->User->saveField('password', $this->request->data['User']['password'])) {
					$this->User->saveField('status', 1);
					$this->Session->setFlash(
						'Sua senha foi atualizada com sucesso',
						'alerts/alert_success'
					);
					$this->redirect(array(
						'controller' => 'users',
						'action' => 'login'
					));
				}

				$this->setFlash(
					'Ocorreu um erro, tente novamente',
					'alerts/alert_error'
				);
				$this->redirect($this->referer());
			}

			$this->setFlash(
				'O pedido de troca de senha já foi utilizado.',
				'alerts/alert_warning'
			);
			$this->redirect(array(
				'controller' => 'users',
				'action' => 'login'
			));
		}

		$user = $this->User->findByToken($token);

		if($user['User']['status'] != 2) {
			$this->Session->setFlash(
				'O link recebido não é válido ou já foi usado.',
				'alerts/alert_error'
			);
			$this->redirect(array(
				'controller' => 'users',
				'action' => 'login'
			));
		}

		$this->set(compact('token'));
	}
}
