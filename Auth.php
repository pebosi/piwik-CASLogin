<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html Gpl v3 or later
 * @version $Id:$
 * 
 * @category Piwik_Plugins
 * @package CASLogin
 */

namespace Piwik\Plugins\CASLogin;

use Piwik\AuthResult;
use Piwik\Common;
use Piwik\Config;
use Piwik\Db;
use Piwik\Piwik;
use Piwik\Plugins\UsersManager\API;

/**
 * Class that implements an authentication mechanism via CAS (Central Authentication Services)
 *
 * @package Piwik_CASLogin
 */
class Auth implements \Piwik\Auth
{
	protected $login = null;
	protected $token_auth = null;
	private static $phpcas_client_called = false;

	public function getName()
	{
		return 'CASLogin';
	}

	public function initSession($login, $md5Password, $rememberMe) {}

	public function authenticate()
	{
		$user = '';

		require_once PIWIK_INCLUDE_PATH . '/plugins/CASLogin/CAS/CAS.php';

		// initialize phpCAS

		// What happens here: in some piwik functionality, some additional API-style calls are
		// made from a controller action, where the authenticate() method will be called *again*.
		// This happens for instance when an admin changes some permissions in Settings->Users.
		// The first authenticate() is from the page, and the second is due to an API call.
		// This checks if there was already a phpcas instance already initialized, otherwize
		// phpCAS::client() would fail.
		if (!self::$phpcas_client_called) {
			\phpCAS::client(
				constant( Config::getInstance()->caslogin['protocol'] ),
				Config::getInstance()->caslogin['host'],
				(integer) Config::getInstance()->caslogin['port'],
                '',
                false
			);
			self::$phpcas_client_called = true;
		}

		// no SSL validation for the CAS server
		\phpCAS::setNoCasServerValidation();

		// Handle single signout requests from CAS server
		\phpCAS::handleLogoutRequests();

		// force CAS authentication only if it has been requested by action argument
		$action = Piwik::getAction();
		
		$auth = \phpCAS::checkAuthentication();
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
				return new AuthResult( AuthResult::FAILURE, $user, NULL );
			}
		}

		// Additional Attributes
		// For future retrieval of attributes; they _might_ be of some use, but are highly
		// dependable on a specific installation. CAS|piwik hackers can do some magic
		// here with SAML attributes etc.
		/*
		foreach (\phpCAS::getAttributes() as $key => $value) {
			// syslog(LOG_DEBUG, "attribute: $key - ". print_r($value, true));
		}
		 */

		if (isset($_SESSION['phpCAS']) && isset($_SESSION['phpCAS']['user'])) {
			$user = $_SESSION['phpCAS']['user'];
		}

		if($user) {
			$db_user = Db::fetchRow('SELECT login, superuser_access FROM '.Common::prefixTable('user').' WHERE login = ?',
					array($user)
			);
			if($db_user === null) {
				// ***User Autocreate***
				// We can either add the authenticated but not-yet-authorized user to the piwik users
				// database, or ignore that.
				// TODO: make this a config option
				$this->_populateDb($user);
				$login = $user;
				$superuser = false;
			}
			else {
				$login = $db_user['login'];
				$superuser = $db_user['superuser_access'];
			}
			if($login == $user)
			{
				if ($superuser)
					$code = AuthResult::SUCCESS_SUPERUSER_AUTH_CODE;
				else $code = AuthResult::SUCCESS; 
				return new AuthResult($code, $login, NULL );
			}
		}

		return new AuthResult( AuthResult::FAILURE, $user, NULL );
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
			$this->_helper_updateUser($user, $dummy, '', $user);
		} else {
			$this->_helper_addUser($user, $dummy, '', $user);
		}
	}


	///// The following methods are taken from Piwik's UserManager, but in order to inject data into piwik's user and access tables, we need
	///// to make sure we don't wreck things. The UserManager API uses authenticate() to check if we're eligable to look this up,
	///// soi we can't use it - we need superuser permissions anyway.
	//
	///// Warning - these methods are of course under Piwik's license.
	private function _helper_userExists($name)
	{
		$count = Db::fetchOne("SELECT count(*)
									FROM ".Common::prefixTable("user"). "
									WHERE login = ?", $name);
		return $count > 0;
	}

	private function _helper_updateUser( $userLogin, $password = false, $email = false, $alias = false ) 
	{
		$token_auth = API::getTokenAuth($userLogin, $password);

		Db::get()->update( Common::prefixTable("user"),
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
		$token_auth = API::getTokenAuth($userLogin, $password);

		Db::get()->insert( Common::prefixTable("user"), array(
									'login' => $userLogin,
									'password' => $password,
									'alias' => $alias,
									'email' => $email,
									'token_auth' => $token_auth,
									)
		);
	}
    
}

