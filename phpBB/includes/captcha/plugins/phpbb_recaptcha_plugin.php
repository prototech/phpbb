<?php
/**
*
* @package VC
* @version $Id$
* @copyright (c) 2006 2008 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

// we need the classic captcha code for tracking solutions and attempts
include_once(PHPBB_ROOT_PATH . 'includes/captcha/plugins/captcha_abstract.' . PHP_EXT);

class phpbb_recaptcha extends phpbb_default_captcha implements phpbb_captcha_plugin
{
	const recaptcha_server = 'http://api.recaptcha.net';
	const recaptcha_verify_server = 'api-verify.recaptcha.net';
	protected $challenge;
	protected $response;

	function init($type)
	{
		phpbb::$user->add_lang('recaptcha');
		parent::init($type);

		$this->challenge = request_var('recaptcha_challenge_field', '');
		$this->response = request_var('recaptcha_response_field', '');
	}

	public static function get_instance()
	{
		return new phpbb_recaptcha();
	}

	static function is_available()
	{
		phpbb::$user->add_lang('recaptcha');
		return (isset(phpbb::$config['recaptcha_pubkey']) && !empty(phpbb::$config['recaptcha_pubkey']));
	}

	static function get_name()
	{
		return 'CAPTCHA_RECAPTCHA';
	}

	static function get_class_name()
	{
		return 'phpbb_recaptcha';
	}

	function acp_page($id, &$module)
	{
		$captcha_vars = array(
			'recaptcha_pubkey'				=> 'RECAPTCHA_PUBKEY',
			'recaptcha_privkey'				=> 'RECAPTCHA_PRIVKEY',
		);

		$module->tpl_name = 'captcha_recaptcha_acp';
		$module->page_title = 'ACP_VC_SETTINGS';
		$form_key = 'acp_captcha';
		add_form_key($form_key);

		$submit = request_var('submit', '');

		if ($submit && check_form_key($form_key))
		{
			$captcha_vars = array_keys($captcha_vars);
			foreach ($captcha_vars as $captcha_var)
			{
				$value = request_var($captcha_var, '');
				if ($value)
				{
					set_config($captcha_var, $value);
				}
			}
			trigger_error(phpbb::$user->lang['CONFIG_UPDATED'] . adm_back_link($module->u_action));
		}
		else if ($submit)
		{
			trigger_error(phpbb::$user->lang['FORM_INVALID'] . adm_back_link($module->u_action));
		}
		else
		{
			foreach ($captcha_vars as $captcha_var => $template_var)
			{
				$var = request_var($captcha_var, (isset(phpbb::$config[$captcha_var])) ? (string) phpbb::$config[$captcha_var] : '');
				phpbb::$template->assign_var($template_var, $var);
			}

			phpbb::$template->assign_vars(array(
				'CAPTCHA_PREVIEW'	=> $this->get_demo_template($id),
				'CAPTCHA_NAME'		=> $this->get_class_name(),
			));

		}
	}


	// not needed
	function execute_demo()
	{
	}


	// not needed
	function execute()
	{
	}


	function get_template()
	{
		phpbb::$template->set_filenames(array(
			'captcha' => 'captcha_recaptcha.html',
		));

		phpbb::$template->assign_vars(array(
			'RECAPTCHA_SERVER'			=> self::recaptcha_server,
			'RECAPTCHA_PUBKEY'			=> isset(phpbb::$config['recaptcha_pubkey']) ? phpbb::$config['recaptcha_pubkey'] : '',
			'RECAPTCHA_ERRORGET'		=> '',
			'S_RECAPTCHA_AVAILABLE'		=> self::is_available(),
		));

		return phpbb::$template->assign_display('captcha');
	}

	function get_demo_template($id)
	{
		return $this->get_template();
	}

	function get_hidden_fields()
	{
		$hidden_fields = array();

		// this is required for postig.php - otherwise we would forget about the captcha being already solved
		if ($this->solved)
		{
			$hidden_fields['confirm_code'] = $this->confirm_code;
		}
		$hidden_fields['confirm_id'] = $this->confirm_id;
		return $hidden_fields;
	}

	function uninstall()
	{
		self::garbage_collect(0);
	}

	function install()
	{
		return;
	}

	function validate()
	{
		if (!parent::validate())
		{
			return false;
		}
		else
		{
			return $this->recaptcha_check_answer();
		}
	}


// Code from here on is based on recaptchalib.php
/*
 * This is a PHP library that handles calling reCAPTCHA.
 *	- Documentation and latest version
 *		  http://recaptcha.net/plugins/php/
 *	- Get a reCAPTCHA API Key
 *		  http://recaptcha.net/api/getkey
 *	- Discussion group
 *		  http://groups.google.com/group/recaptcha
 *
 * Copyright (c) 2007 reCAPTCHA -- http://recaptcha.net
 * AUTHORS:
 *   Mike Crawford
 *   Ben Maurer
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

	/**
	* Submits an HTTP POST to a reCAPTCHA server
	* @param string $host
	* @param string $path
	* @param array $data
	* @param int port
	* @return array response
	*/
	protected function _recaptcha_http_post($host, $path, $data, $port = 80)
	{
		$req = $this->_recaptcha_qsencode ($data);

		$http_request  = "POST $path HTTP/1.0\r\n";
		$http_request .= "Host: $host\r\n";
		$http_request .= "Content-Type: application/x-www-form-urlencoded;\r\n";
		$http_request .= "Content-Length: " . strlen($req) . "\r\n";
		$http_request .= "User-Agent: reCAPTCHA/PHP/phpBB\r\n";
		$http_request .= "\r\n";
		$http_request .= $req;

		$response = '';
		if (false == ($fs = @fsockopen($host, $port, $errno, $errstr, 10)))
		{
			trigger_error('Could not open socket', E_USER_ERROR);
		}

		fwrite($fs, $http_request);

		while (!feof($fs))
		{
			// One TCP-IP packet
			$response .= fgets($fs, 1160);
		}
		fclose($fs);

		$response = explode("\r\n\r\n", $response, 2);

		return $response;
	}

	/**
	* Calls an HTTP POST function to verify if the user's guess was correct
	* @param array $extra_params an array of extra variables to post to the server
	* @return ReCaptchaResponse
	*/
	protected function recaptcha_check_answer($extra_params = array())
	{
		// discard spam submissions
		if ($this->challenge == null || strlen($this->challenge) == 0 || $this->response == null || strlen($this->response) == 0)
		{
			return phpbb::$user->lang['RECAPTCHA_INCORRECT'];
		}

		$response = $this->_recaptcha_http_post(self::recaptcha_verify_server, '/verify', array(
			'privatekey'	=> phpbb::$config['recaptcha_privkey'],
			'remoteip'		=> phpbb::$user->ip,
			'challenge'		=> $this->challenge,
			'response'		=> $this->response,
			) + $extra_params
		);

		$answers = explode("\n", $response[1]);

		if (trim($answers[0]) === 'true')
		{
			$this->solved = true;
			return false;
		}
		else
		{
			if ($answers[1] === 'incorrect-captcha-sol')
			{
				return phpbb::$user->lang['RECAPTCHA_INCORRECT'];
			}
		}
	}

	/**
	* Encodes the given data into a query string format
	* @param $data - array of string elements to be encoded
	* @return string - encoded request
	*/
	protected function _recaptcha_qsencode($data)
	{
		$req = '';

		foreach ($data as $key => $value)
		{
			$req .= $key . '=' . urlencode(stripslashes($value)) . '&';
		}
		// Cut the last '&'
		$req = substr($req, 0, strlen($req) - 1);
		return $req;
	}
}

?>