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

require PIWIK_INCLUDE_PATH . '/plugins/CASLogin/Auth.php';

class Piwik_CASLogin extends Piwik_Plugin
{
	public function getInformation()
	{
		return array(
			'name' => 'CAS Login',
			'description' => 'CAS Login plugin. It uses JA-SIG Central Authentication Services to authenticate users and grant them access to piwik.',
			'author' => 'OW',
                        'homepage' => 'http://dev.piwik.org/trac/ticket/598/',
                        'version' => '0.6',
		);
	}

	function getListHooksRegistered()
	{
		$hooks = array(
			'FrontController.initAuthenticationObject'	=> 'initAuthenticationObject',
			);
		return $hooks;
	}

	function initAuthenticationObject($notification)
	{
        set_include_path(get_include_path() . PATH_SEPARATOR . PIWIK_INCLUDE_PATH . '/plugins/CASLogin/CAS');
        require_once('CAS/CAS.php');
		$auth = new Piwik_CASLogin_Auth();
		Zend_Registry::set('auth', $auth);
	}
}
