<?php
/**
* @category 	Payment
* @package		payment/interkassa
* @author 		Oleksandr Polizhak
* @license		Open Software License (OSL 3.0) http://opensouce.org/osl-3.0.php
*/

namespace API\Payment {
	class InterKassa
	{
		protected $_options = array(
			'signature'			=> false, 	// boolean
			'testMode'			=> false, 	// boolean
			'signAlg'			=> 'md5', 	// md5 default OR sha256
			'secret_key'		=> '',		// string
			'secret_test_key'	=> '',	 	// string
			);
		protected $_sci_url	= 'https://sci.interkassa.com/';
		private $_kass_id;
		private $_private_key;

		private $validate_keys = array(
			'ik_pm_no'	=> '/^([\w\-]{1,32})$/D',
			'ik_cur' 	=> '/^(EUR|USD|UAH|RUB|BYR|XAU)$/ ',
			'ik_am' 	=> '/^(?=(0|\.|,)*[1-9])\d{0,8}(([\.|,])|(?:[\.|,]\d{1,2}))?$/',
			'ik_am_ed'	=> '/(0|1)/ ',
			'ik_am_t' 	=> '/(invoice|payway)/ ',
			'ik_desc' 	=> '/^(.{0,255})$/',
			'ik_exp' 	=> '/^.{0,30}$/',
			'ik_itm' 	=> '/^([\d]{1,10})$/ ',
			'ik_pw_on' 	=> '/^([\w;,\.]{0,512})$/ ',
			'ik_pw_off' => '/^([\w;,\.]{0,512})$/',
			'ik_pw_via' => '/^([\w]{0,62})$/',
			'ik_loc'	=> '/^(.{5})$/',
			'ik_enc' 	=> '/^(.{0,16})$/',
			'ik_cli' 	=> '/^(.{0,64})$/',
			'ik_ia_u' 	=> '/^(https|http):\/\//',
			'ik_ia_m' 	=> '/^(get|post)$/i',
			'ik_suc_u' 	=> '/^(https|http):\/\//',
			'ik_suc_m' 	=> '/^(get|post)$/i',
			'ik_pnd_u' 	=> '/^(https|http):\/\//',
			'ik_pnd_m' 	=> '/^(get|post)$/i',
			'ik_fal_u' 	=> '/^(https|http):\/\//',
			'ik_fal_m' 	=> '/^(get|post)$/i',
			'ik_act' 	=> '/^(process|payways|payways_calc|payway)$/',
			'ik_int' 	=> '/^(web|json)$/'
			);

		private $required_keys = array('ik_pm_no','ik_am','ik_cur','ik_desc');

		const CUR_EUR 	= 'EUR';
		const CUR_USD 	= 'USD';
		const CUR_UAH 	= 'UAH';
		const CUR_RUB 	= 'RUB';
		const CUR_BYR 	= 'BYR';
		const CUR_XAU 	= 'XAU';

		const AMT_INVOICE	= "innvoice";
		const AMT_PAYWAY	= "payway";

		const ACTION_PROGRESS		= 'progress';
		const ACTION_PAYWAYS		= 'payways';
		const ACTION_PAYWAYS_CALC	= 'payways_calc';
		const ACTION_PAYWAY			= 'payway';

		const INTERFACE_WEB		= 'web';
		const INTERFACE_JSON	= 'json';


		/**
		* Constructor
		* @param strign $kass_id
		* @param array $options
		* @throws InvalidArgumentException
		*/
		public function __construct($kass_id, $options = array())
		{
			if(!preg_match('/^([\w\-]{1,36})$/D', $kass_id))
				throw new \InvalidArgumentException("kass_id is not valid");
			
			$this->_kass_id 	= $kass_id;
			$this->_options = array_merge($this->_options, $options);
		}


		/**
		* sci_form
		* @param array $params
		* @return string
		* @throws InvalidArgumentException
		*/
		public function sci_form($params, $submit = '<input type="submit" value="Pay">'){
			$params = $this->validateParams($params);
			if($this->_options['signature'])
				$params['ik_sign'] = $this->signature($params);
			$data	= '';

			foreach ($params as $name => $value)
				$data .= sprintf('<input type="hidden" name="%s" value="%s">', $name, $value);

			return sprintf('<form method="POST" action="%s" enctype="utf-8">%s %s</form>',
				$this->_sci_url,
				$data, 
				$submit);
		}


		/**
		* sci_link
		* @param array $params
		* @return string
		* @throws InvalidArgumentException	
		*/
		public function sci_link($params){
			$params = $this->validateParams($params);
			if($this->_options['signature'])
				$params['ik_sign'] = $this->signature($params);
			$data	= '';

			foreach ($params as $name => $value)
				$data .= sprintf('&%s=%s', $name, $value);

			return sprintf("%s?payment%s", $this->_sci_url, $data);
		}


		/**
		* validateParams
		* @param array $params
		* @return array $params
		*/
		private function validateParams($params)
		{
			foreach ($this->required_keys as $key)
				if(empty($params[$key]))
					throw new \InvalidArgumentException(sprintf("Required parameter '%s' missing or empty. See the documentation.", $key));

				foreach ($params as $param => $value)
					if(isset($this->validate_keys[$param]))
						if(!preg_match($this->validate_keys[$param], $value))
							throw new \InvalidArgumentException(sprintf("Parameter '%s' not valid. See the documentation.", $param));

						$params['ik_co_id'] = $this->_kass_id;

						return $params;
					}


		/**
		* signature
		* @param array $params
		* @return string
		*/
		private function signature($params){
			ksort($params, SORT_STRING);
			array_push($params, $this->_options['secret_key']);
			$signString = implode(":", $params);

			switch ($this->_options['signAlg']) {
				case 'sha256':
				$data = hash('sha256',$signString, true);
				break;

				default:
				$data = md5($signString, true);	
				break;
			}

			return base64_encode($data);
		}


		/**
		* interactive
		* @param array $params
		* @return array $params
		*/
		public function interactive($params){
			$sign = $params['ik_sign'];
			unset($params['ik_sign']);

			if(preg_match('/^85\.10\.255\.([0-9]{1,3})$/', $_SERVER['REMOTE_ADDR']))
				return ($this->signature($params) == $sign)?$params:false;
		}

	}
}
