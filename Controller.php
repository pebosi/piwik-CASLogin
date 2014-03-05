<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html Gpl v3 or later
 * @version $Id: Controller.php 943 2009-03-01 23:36:36Z matt $
 *
 * @package Piwik_CASLogin
 */

require PIWIK_INCLUDE_PATH . '/plugins/UsersManager/API.php';
require PIWIK_INCLUDE_PATH . '/core/View.php';

/**
 * @package Piwik_CASLogin
 */
class Piwik_CASLogin_Controller extends Piwik_Controller
{
	public function index()
	{
		Piwik::redirectToModule('CoreHome');
	}
	
	/**
	 * Configure common view properties
	 *
	 * @param Piwik_View $view
	 */
	private function configureView($view)
	{
		
                $this->setBasicVariablesView($view);
                $view->linkTitle = Piwik::getRandomTitle();

		$enableFramedLogins = Zend_Registry::get('config')->General->enable_framed_logins;
		$view->enableFramedLogins = $enableFramedLogins;
		if(!$enableFramedLogins)
		{
			$view->setXFrameOptions('sameorigin');
		}
		$view->forceSslLogin = Zend_Registry::get('config')->General->force_ssl_login;
		// crsf token: don't trust the submitted value; generate/fetch it from session data
		$view->nonce = Piwik_Nonce::getNonce('Piwik_Login.login');
	}
    
	/**
	 * Login form
	 *
	 * @param string $messageNoAccess Access error message
	 * @param string $currentUrl Current URL
	 * @return void
	 */
	function login($messageNoAccess = null)
	{
		$view = Piwik_View::factory('login');
		$view->AccessErrorString = $messageNoAccess;
		$view->linkTitle = Piwik::getRandomTitle();
		$view->subTemplate = 'genericForm.tpl';
		$this->configureView($view);
		echo $view->render();
	}
    
    public function redirectToCAS() {
		// This is simply if we are coming back from CAS.
        // the actual redirect happens in the authentication class.
        if(Piwik::getCurrentUserLogin() != 'anonymous') {
            Piwik::redirectToModule('CoreHome');
        }
    }

	private function clearSession()
	{	
		/* Note: some browsers don't respect server revokation */
		$auth = Zend_Registry::get('auth');
		$auth->setLogin(null);
		$auth->setTokenAuth(null);

		$access = Zend_Registry::get('access');
		$access->reloadAccess($auth);

        $authCookieName = Zend_Registry::get('config')->General->login_cookie_name;
        $cookie = new Piwik_Cookie($authCookieName);
        $cookie->delete();

		@Zend_Session::destroy(true);
	}
	
	public function logout()
	{
        phpCAS::logoutWithUrl(Piwik_Url::getCurrentUrlWithoutQueryString() );
	}
}
