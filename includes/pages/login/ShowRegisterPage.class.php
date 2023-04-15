<?php

/**
 *  2Moons
 *   by Jan-Otto Kröpke 2009-2016
 *
 * For the full copyright and license information, please view the LICENSE
 *
 * @package 2Moons
 * @author Jan-Otto Kröpke <slaver7@gmail.com>
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
 */

class ShowRegisterPage extends AbstractLoginPage
{
	function __construct()
	{
		parent::__construct();
		$this->setWindow('light');
	}

	function show()
	{
		global $LNG, $config;
		$referralData	= array('id' => 0, 'name' => '');
		$accountName	= "";

		$externalAuth	= HTTP::_GP('externalAuth', array());
		$referralID 	= HTTP::_GP('referralID', 0);



		if(!isset($externalAuth['account'], $externalAuth['method']))
		{
			$externalAuth['account']	= 0;
			$externalAuth['method']		= '';
		}
		else
		{
			$externalAuth['method']		= strtolower(str_replace(array('_', '\\', '/', '.', "\0"), '', $externalAuth['method']));
		}

		if(!empty($externalAuth['account']) && file_exists('includes/extauth/'.$externalAuth['method'].'.class.php'))
		{
			$path	= 'includes/extauth/'.$externalAuth['method'].'.class.php';
			require($path);
			$methodClass	= ucwords($externalAuth['method']).'Auth';
			/** @var $authObj externalAuth */
			$authObj		= new $methodClass;

			if(!$authObj->isActiveMode())
			{
				$this->redirectTo('index.php?code=5');
			}

			if(!$authObj->isValid())
			{
				$this->redirectTo('index.php?code=4');
			}

			$accountData	= $authObj->getAccountData();
			$accountName	= $accountData['name'];
		}

		if($config->ref_active == 1 && !empty($referralID))
		{
			$db = Database::get();

			$sql = "SELECT username FROM %%USERS%% WHERE id = :referralID AND universe = :universe;";
			$referralAccountName = $db->selectSingle($sql, array(
				':referralID'	=> $referralID,
				':universe'		=> Universe::current()
			), 'username');

			if(!empty($referralAccountName))
			{
				$referralData	= array('id' => $referralID, 'name' => $referralAccountName);
			}
		}

		$this->assign(array(
			'use_recaptcha_on_register' => $config->use_recaptcha_on_register,
			'referralData'		=> $referralData,
			'accountName'		=> $accountName,
			'externalAuth'		=> $externalAuth,
			'registerPasswordDesc'	=> sprintf($LNG['registerPasswordDesc'], 6),
			'registerRulesDesc'	=> sprintf($LNG['registerRulesDesc'], '<a href="index.php?page=rules">'.$LNG['menu_rules'].'</a>')
		));

		$this->display('page.register.default.tpl');
	}

	function send()
	{
		global $LNG, $config;

		if($config->game_disable == 0 || $config->reg_closed == 1)
		{
			$this->printMessage($LNG['registerErrorUniClosed'], array(array(
				'label'	=> $LNG['registerBack'],
				'url'	=> 'javascript:window.history.back()',
			)));
		}

		$userName 		= HTTP::_GP('username', '', UTF8_SUPPORT);
		$password 		= HTTP::_GP('password', '', true);
		$mailAddress 	= HTTP::_GP('email', '');
		$mailAddress2	= HTTP::_GP('emailReplay', '');
		$rulesChecked	= HTTP::_GP('rules', 0);
		$language 		= HTTP::_GP('lang', '');
		$user_secret_question_id = HTTP::_GP('secretQuestion', 0);
		$user_secret_question_answer = HTTP::_GP('secretQuestionAnswer', '', true);

		$referralID 	= HTTP::_GP('referralID', 0);

		$externalAuth	= HTTP::_GP('externalAuth', array());
		if(!isset($externalAuth['account'], $externalAuth['method']))
		{
			$externalAuthUID	= 0;
			$externalAuthMethod	= '';
		}
		else
		{
			$externalAuthUID	= $externalAuth['account'];
			$externalAuthMethod	= strtolower(str_replace(array('_', '\\', '/', '.', "\0"), '', $externalAuth['method']));
		}

		$errors 	= array();

		
		if (!array_key_exists($user_secret_question_id, $LNG['registerSecretQuestionArray'])) {
			$errors[] = $LNG['registerSecretQuestionError_1'];
		}

		if (empty($user_secret_question_answer)) {
			$errors[] = $LNG['registerSecretQuestionError_2'];
		}

		if (strlen($user_secret_question_answer) > 64) {
			$errors[] = $LNG['registerSecretQuestionError_3'];
		}

		if(empty($userName)) {
			$errors[]	= $LNG['registerErrorUsernameEmpty'];
		}

		if(!PlayerUtil::isNameValid($userName)) {
			$errors[]	= $LNG['registerErrorUsernameChar'];
		}

		if(strlen($password) < 6) {
			$errors[]	= sprintf($LNG['registerErrorPasswordLength'], 6);
		}


		if(!PlayerUtil::isMailValid($mailAddress)) {
			$errors[]	= $LNG['registerErrorMailInvalid'];
		}

		if(empty($mailAddress)) {
			$errors[]	= $LNG['registerErrorMailEmpty'];
		}

		if($rulesChecked != 1) {
			$errors[]	= $LNG['registerErrorRules'];
		}

		$db = Database::get();

		$sql = "SELECT (
				SELECT COUNT(*)
				FROM %%USERS%%
				WHERE universe = :universe
				AND username = :userName
			) + (
				SELECT COUNT(*)
				FROM %%USERS_VALID%%
				WHERE universe = :universe
				AND username = :userName
			) as count;";

		$countUsername = $db->selectSingle($sql, array(
			':universe'	=> Universe::current(),
			':userName'	=> $userName,
		), 'count');

		$sql = "SELECT (
			SELECT COUNT(*)
			FROM %%USERS%%
			WHERE universe = :universe
			AND (
				email = :mailAddress
				OR email_2 = :mailAddress
			)
		) + (
			SELECT COUNT(*)
			FROM %%USERS_VALID%%
			WHERE universe = :universe
			AND email = :mailAddress
		) as count;";

		$countMail = $db->selectSingle($sql, array(
			':universe'		=> Universe::current(),
			':mailAddress'	=> $mailAddress,
		), 'count');

		if($countUsername!= 0) {
			$errors[]	= $LNG['registerErrorUsernameExist'];
		}

		if($countMail != 0) {
			$errors[]	= $LNG['registerErrorMailExist'];
		}

		if ($config->capaktiv === '1' && $config->use_recaptcha_on_register)
		{
			require('includes/libs/reCAPTCHA/src/autoload.php');

            $recaptcha = new \ReCaptcha\ReCaptcha($config->capprivate);
            $resp = $recaptcha->verify(HTTP::_GP('g-recaptcha-response', ''), Session::getClientIp());
            if (!$resp->isSuccess())
            {
                $errors[]	= $LNG['registerErrorCaptcha'];
            }
		}

		if (!empty($errors)) {
			$this->printMessage(implode("<br>\r\n", $errors), array(array(
				'label'	=> $LNG['registerBack'],
				'url'	=> 'javascript:window.history.back()',
			)));
		}

		$path	= 'includes/extauth/'.$externalAuthMethod.'.class.php';

		if(!empty($externalAuth['account']) && file_exists($path))
		{
			require($path);

			$methodClass		= ucwords($externalAuthMethod).'Auth';
			/** @var $authObj externalAuth */
			$authObj			= new $methodClass;
			$externalAuthUID	= 0;
			if($authObj->isActiveMode() && $authObj->isValid()) {
				$externalAuthUID	= $authObj->getAccount();
			}
		}

		if($config->ref_active == 1 && !empty($referralID))
		{
			$sql = "SELECT COUNT(*) as state FROM %%USERS%% WHERE id = :referralID AND universe = :universe;";
			$Count = $db->selectSingle($sql, array(
				':referralID' 	=> $referralID,
				':universe'		=> Universe::current()
			), 'state');

			if($Count == 0)
			{
				$referralID	= 0;
			}
		}
		else
		{
			$referralID	= 0;
		}

		$validationKey	= md5(uniqid('2m'));

		$sql = "INSERT INTO %%USERS_VALID%% SET
				`userName` = :userName,
				`validationKey` = :validationKey,
				`password` = :password,
				`email` = :mailAddress,
				`date` = :timestamp,
				`ip` = :remoteAddr,
				`language` = :language,
				`universe` = :universe,
				`referralID` = :referralID,
				`externalAuthUID` = :externalAuthUID,
				`externalAuthMethod` = :externalAuthMethod,
				`user_secret_question_id` = :user_secret_question_id,
				`user_secret_question_answer` = :user_secret_question_answer;";


		$db->insert($sql, array(
			':userName'				=> $userName,
			':validationKey'		=> $validationKey,
			':password'				=> PlayerUtil::cryptPassword($password),
			':mailAddress'			=> $mailAddress,
			':timestamp'			=> TIMESTAMP,
			':remoteAddr'			=> Session::getClientIp(),
			':language'				=> $language,
			':universe'				=> Universe::current(),
			':referralID'			=> $referralID,
			':externalAuthUID'		=> $externalAuthUID,
			':externalAuthMethod'	=> $externalAuthMethod,
			':user_secret_question_id' => $user_secret_question_id,
			':user_secret_question_answer' => $user_secret_question_answer,
		));

		$validationID	= $db->lastInsertId();
		$verifyURL	= 'index.php?page=vertify&i='.$validationID.'&k='.$validationKey;

		if($config->user_valid == 0 || !empty($externalAuthUID))
		{
			$this->redirectTo($verifyURL);
		}
		else
		{
			require 'includes/classes/Mail.class.php';
			$MailRAW		= $LNG->getTemplate('email_vaild_reg');
			$MailContent	= str_replace(array(
				'{USERNAME}',
				'{PASSWORD}',
				'{GAMENAME}',
				'{VERTIFYURL}',
				'{GAMEMAIL}',
			), array(
				$userName,
				$password,
				$config->game_name.' - '.$config->uni_name,
				HTTP_PATH.$verifyURL,
				$config->smtp_sendmail,
			), $MailRAW);

			$subject	= sprintf($LNG['registerMailVertifyTitle'], $config->game_name);
			Mail::send($mailAddress, $userName, $subject, $MailContent);

			$this->printMessage($LNG['registerSendComplete']);
		}
	}
}
