<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html Gpl v3 or later
 * @version $Id: ExamplePlugin.php 838 2008-12-17 17:26:15Z matt $
 * 
 * @package Piwik_CASLogin
 */

namespace Piwik\Plugins\CASLogin;

use Piwik\Piwik;

require PIWIK_INCLUDE_PATH . '/plugins/CASLogin/Auth.php';

class CASLogin extends \Piwik\Plugin
{

	function getListHooksRegistered()
	{
		$hooks = array(
			'Request.initAuthenticationObject'	=> 'initAuthenticationObject',
			);
		return $hooks;
	}

	function initAuthenticationObject()
	{
        set_include_path(get_include_path() . PATH_SEPARATOR . PIWIK_INCLUDE_PATH . '/plugins/CASLogin/CAS');
        require_once('CAS/CAS.php');
		$auth = new Auth();
		\Piwik\Registry::set('auth', $auth);
	}
}
