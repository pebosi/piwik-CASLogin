<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html Gpl v3 or later
 * @version $Id:$
 * 
 * @package Piwik_CASLogin
 */

/**
 * Class that implements an authentication mechanism via CAS (Central Authentication Services)
 *
 * @package Piwik_CASLogin
 */
class Piwik_CASLogin_Auth implements Piwik_Auth
{
	protected $login = null;
	protected $token_auth = null;

	public function getName()
	{
		return 'CASLogin';
	}

	public function authenticate()
	{
		$user = '';
		$rootLogin = Zend_Registry::get('config')->superuser->login;

		$additionalSuperUsers = array();
		$oAdditionalSuperUsers = Zend_Registry::get('config')->caslogin->additionalsuperusers;
		if(is_object($oAdditionalSuperUsers)) {
			$additionalSuperUsers = $oAdditionalSuperUsers->toArray();
		}

		require_once PIWIK_INCLUDE_PATH . '/plugins/CASLogin/CAS/CAS.php';

		// initialize phpCAS

		// What happens here: in some piwik functionality, some additional API-style calls are
		// made from a controller action, where the authenticate() method will be called *again*.
		// This happens for instance when an admin changes some permissions in Settings->Users.
		// The first authenticate() is from the page, and the second is due to an API call.
		// This checks if there was already a phpcas instance already initialized, otherwize
		// phpCAS::client() would fail.
		global $PHPCAS_CLIENT;
		if(!is_object($PHPCAS_CLIENT)) {
			phpCAS::client(
				constant( Zend_Registry::get('config')->caslogin->protocol ),
				Zend_Registry::get('config')->caslogin->host,
				(integer) Zend_Registry::get('config')->caslogin->port,
                '',
                false
			);
		}

		// no SSL validation for the CAS server
		phpCAS::setNoCasServerValidation();

		// Handle single signout requests from CAS server
		phpCAS::handleLogoutRequests();

		// force CAS authentication only if it has been requested by action argument
		$action = Piwik::getAction();
		
		$auth = phpCAS::checkAuthentication();
		if(!$auth) {
			if($action == 'redirectToCAS') {
				phpCAS::forceAuthentication();
			}

			if($action != 'login' && Piwik::getModule() != 'CoreUpdater') {
				Piwik::redirectToModule('CASLogin', 'login');
				return;
			} elseif($action == 'redirectToCAS') {
				phpCAS::forceAuthentication();
			} else {
				return new Piwik_Auth_Result( Piwik_Auth_Result::FAILURE, $user, NULL );
			}
		}

		// Additional Attributes
		// For future retrieval of attributes; they _might_ be of some use, but are highly
		// dependable on a specific installation. CAS|piwik hackers can do some magic
		// here with SAML attributes etc.
		/*
		foreach (phpCAS::getAttributes() as $key => $value) {
			// syslog(LOG_DEBUG, "attribute: $key - ". print_r($value, true));
		}
		 */

		if (isset($_SESSION['phpCAS']) && isset($_SESSION['phpCAS']['user'])) {
			$user = $_SESSION['phpCAS']['user'];
		}

		if($user) {
			if($user == $rootLogin || in_array($user, $additionalSuperUsers)) {
				// Root / Admin login
				return new Piwik_Auth_Result(Piwik_Auth_Result::SUCCESS_SUPERUSER_AUTH_CODE, $user, NULL );
			}

			$login = Zend_Registry::get('db')->fetchOne(
					'SELECT login FROM '.Piwik_Common::prefixTable('user').' WHERE login = ?',
					array($user)
			);
			if($login === false) {
				// ***User Autocreate***
				// We can either add the authenticated but not-yet-authorized user to the piwik users
				// database, or ignore that.
				// TODO: make this a config option
				// $this->_populateDb($user);
				$login = $user;
			}

			if($login == $user)
			{
				return new Piwik_Auth_Result(Piwik_Auth_Result::SUCCESS, $login, NULL );
			}
		}

		return new Piwik_Auth_Result( Piwik_Auth_Result::FAILURE, $user, NULL );
	}

	public function setLogin($login)
	{
		$this->login = $login;
	}
	
    public function setTokenAuth($token_auth)
	{
		$this->token_auth = $token_auth;
	}

	/**
	 * This method is used to inject user into Piwik's tables.
	 * @todo Alias could be the 'cn' returned from CAS attributes.
	 */
	private function _populateDb($user)
	{
		$result = null;
		$dummy = md5('abcd1234');
		if ($this->_helper_userExists($user)) {
			$this->_helper_updateUser($user, $dummy, '', 'alias');
		} else {
			$this->_helper_addUser($user, $dummy, '', 'alias');
		}
	}


	///// The following methods are taken from Piwik's UserManager, but in order to inject data into piwik's user and access tables, we need
	///// to make sure we don't wreck things. The UserManager API uses authenticate() to check if we're eligable to look this up,
	///// soi we can't use it - we need superuser permissions anyway.
	//
	///// Warning - these methods are of course under Piwik's license.
	private function _helper_userExists($name)
	{
		$count = Zend_Registry::get('db')->fetchOne("SELECT count(*)
									FROM ".Piwik_Common::prefixTable("user"). "
									WHERE login = ?", $name);
		return $count > 0;
	}

	private function _helper_updateUser( $userLogin, $password = false, $email = false, $alias = false ) 
	{
		$token_auth = Piwik_UsersManager_API::getTokenAuth($userLogin, $password);

		$db = Zend_Registry::get('db');

		$db->update( Piwik_Common::prefixTable("user"),
					array(
						'password' => $password,
						'alias' => $alias,
						'email' => $email,
						'token_auth' => $token_auth,
						),
					"login = '$userLogin'"
			);
	}

	private function _helper_addUser( $userLogin, $password, $email, $alias = false )
	{		
		$token_auth = Piwik_UsersManager_API::getTokenAuth($userLogin, $password);

		$db = Zend_Registry::get('db');

		$db->insert( Piwik_Common::prefixTable("user"), array(
									'login' => $userLogin,
									'password' => $password,
									'alias' => $alias,
									'email' => $email,
									'token_auth' => $token_auth,
									)
		);
	}
    
}

