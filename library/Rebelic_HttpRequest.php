<?php
/**
 * /includes/classes/class.HttpRequest.php
 * 
 * Detailed description will follow later on.
 * 
 * @copyright  Copyright (c) 2009 Doofus Interactive (http://www.doofus.nl)
 */

	class Rebelic_HttpRequest
	{
		private $httpCode = 200;
		private $httpTimeoutTotal = 12;
		private $httpTimeoutConnect = 10;
		private $httpResponseTime = 2;
		private $lastError = 0;
		private $lastErrorString = '';
		private $lastUrl = '';
		private $lastResponseTime = '';
		private $lastResponse = '';
		private $response = '';
		private $success = false;
		
		private $httpResponseHeader = array();
		
		private $cookieFile = '';
		
		function __construct($totalTimeout = 30, $connectTimeout = 15, $maxResponseTime = 15)
		{
			$this->httpTimeoutTotal = $totalTimeout;
			$this->httpTimeoutConnect = $connectTimeout;
			$this->httpResponseTime = $maxResponseTime;
		}
		
		function __get($name)
		{
			if ($name == 'lastResponse')
				return $this->lastResponse;
			elseif ($name == 'lastError')
				return $this->lastError;
			elseif ($name == 'lastErrorString')
				return $this->lastErrorString;
			elseif ($name == 'lastUrl')
				return $this->lastUrl;
			elseif ($name == 'lastResponseTime')
				return $this->lastResponseTime;				
			elseif ($name == 'httpCode')
				return $this->httpCode;	
			elseif ($name == 'success')
				return $this->success;	
		}
		
		private function readHeader($ch, $header)
		{
			$this->httpResponseHeader[$ch][] = trim($header);
			return strlen($header);
		}
		
		function fetch($url, $method = 'GET', $postVars = array(), $headerVars = array(), $username = null, $password = null, $proxyServer = '', $proxyAuth = '')
		{
			$this->success = false;
			
			$this->lastError = 0;
			$this->lastErrorString = '';
			$this->lastUrl = $url;
			
			$ch = curl_init();
			
			curl_setopt($ch, CURLOPT_URL, $url);
			
			curl_setopt($ch, CURLOPT_VERBOSE, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			
			if ($username !== null)
				curl_setopt($ch, CURLOPT_USERPWD, $username.':'.$password);
				
			curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
			
			if (count($postVars) > 0)
			{
				$escapedPostVars = array();
				
				if(is_array($postVars))
				{
					foreach ($postVars as $postVarName => $postVarValue)
					{
						if (substr($postVarValue, 0, 1) == '@')
						{
							if (file_exists(substr($postVarValue, 1)) === true)
								$escapedPostVars[$postVarName] = $postVarValue;
							else 
								$escapedPostVars[$postVarName] = str_replace("@", "\@", $postVarValue);
						}
						else 
						{
							$escapedPostVars[$postVarName] = $postVarValue;
						}
					}
				} else {
					if (substr($postVars, 0, 1) == '@')
					{
						if (file_exists(substr($postVars, 1)) === true)
							$escapedPostVars = $postVars;
						else 
							$escapedPostVars = str_replace("@", "\@", $postVars);
					}
					else 
					{
						$escapedPostVars = $postVars;
					}
					
				}
				
				curl_setopt($ch, CURLOPT_POSTFIELDS, $escapedPostVars);
			    
			}
							
			if (count($headerVars) > 0)
			{
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headerVars);
			}
			
			if (substr($url, 0, 8) == 'https://')
			{
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			}

		    curl_setopt($ch, CURLOPT_TIMEOUT, $this->httpTimeoutTotal); 
		    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->httpTimeoutConnect); 
		    
		    if (strlen($proxyServer) > 0)
		    {
				curl_setopt($ch, CURLOPT_PROXY, $proxyServer);
				if (strlen($proxyAuth) > 0)
				{
					curl_setopt ($ch, CURLOPT_PROXYUSERPWD, $proxyAuth); 
				}
		    }

			$responseData = curl_exec($ch);
			$responseInfo = curl_getinfo($ch);
			$responseError = curl_errno($ch);
			$responseErrorString = curl_error($ch);
			
			$this->lastResponse = $responseData;
			$this->lastResponseTime = $responseInfo['total_time'];
			
			curl_close($ch);
			
			$this->httpCode = $responseInfo['http_code'];

			if ($responseError > 0)
			{
				/**
				 * A Curl error occured, probably a timeout or something likewise
				 */
				$this->lastError = $responseError;
				if ($responseError == CURLE_OPERATION_TIMEOUTED)
				{
					$this->lastErrorString = _('Timeout occured while requesting page.');
				}
				else 
				{
					$this->lastErrorString = $responseErrorString;
				}

				return false;
			}
			elseif ($responseInfo['http_code'] == 200)
			{
				
				if ($responseInfo['total_time'] > $this->httpResponseTime)
				{
					$this->lastError = 10001;
					$this->lastErrorString = 'HTTP response slow ('.round($responseInfo['total_time'], 2).'s). URL: '.$url;
					return false;
				}
				
				$this->success = true;
				return $responseData;
			}
			else 
			{
				/**
				 * Oh-oh, no "200 OK" received, all webservers down?!
				 */
				if ($responseInfo['total_time'] > $this->httpResponseTime)
				{
					$this->lastError = 10003;
					$this->lastErrorString = 'HTTP response slow and invalid (code '.$responseInfo['http_code'].', took '.round($responseInfo['total_time'], 2).'s). Please check: '.$url;
					return false;
				}
				
				$this->lastError = 10002;
				$this->lastErrorString = 'HTTP '.$responseInfo['http_code'].' received. Please check: '.$url;
				
				return false;
			}
			
		}
		
		public static function post($url, $postVars = array(), $headerVars = array(), $authUsername = null, $authPassword = null, $proxyServer = null, $proxyAuth = null, $httpTimeoutTotal = 30, $httpTimeoutConnect = 20, $followLocation = true)
		{
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_VERBOSE, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			
			if ($followLocation === true)
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

			if ($authUsername !== null)
				curl_setopt($ch, CURLOPT_USERPWD, $authUsername.':'.$authPassword);
				
			curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
			curl_setopt($ch, CURLOPT_POST, 1);
			
			if (count($postVars) > 0)
			{
				$escapedPostVars = array();
				foreach ($postVars as $postVarName => $postVarValue)
				{
					if (substr($postVarValue, 0, 1) == '@')
					{
						if (file_exists(substr($postVarValue, 1)) === true)
							$escapedPostVars[$postVarName] = $postVarValue;
						else 
							$escapedPostVars[$postVarName] = str_replace("@", "\@", $postVarValue);
					}
					else 
					{
						$escapedPostVars[$postVarName] = $postVarValue;
					}
				}
				
				curl_setopt($ch, CURLOPT_POSTFIELDS, $escapedPostVars);
			}
							
			if (count($headerVars) > 0)
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headerVars);
			
			if (substr($url, 0, 8) == 'https://')
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		    curl_setopt($ch, CURLOPT_TIMEOUT, $httpTimeoutTotal); 
		    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $httpTimeoutConnect); 

		    if ($proxyServer !== null)
		    {
				curl_setopt($ch, CURLOPT_PROXY, $proxyServer);

				if ($proxyAuth !== null)
				{
					curl_setopt ($ch, CURLOPT_PROXYUSERPWD, $proxyAuth); 
				}
		    }
		    
		    curl_setopt($ch, CURLOPT_HEADER, 0); 

			$responseData = curl_exec($ch);
			$responseInfo = curl_getinfo($ch);
			$responseError = curl_errno($ch);
			$responseErrorString = curl_error($ch);

			curl_close($ch);
			
			return new HttpResponse($responseInfo, $responseData, $responseError, $responseErrorString);
		}
		
		public static function get($url, $getVars = array(), $headerVars = array(), $authUsername = null, $authPassword = null, $proxyServer = null, $proxyAuth = null, $cookieFile = null, $httpTimeoutTotal = 30, $httpTimeoutConnect = 20)
		{
			$urlParsed = parse_url($url);

			if (strlen(@$urlParsed['query']) > 0)
				$queryParameters = explode('&', @$urlParsed['query']);
			else 
				$queryParameters = array();
			
			$urlParameters = array();
			foreach ($queryParameters as $queryParameter)
			{
				$queryParameterArray = explode('=', $queryParameter);
				$urlParameters[$queryParameterArray[0]] = $queryParameterArray[0].'='.$queryParameterArray[1];
			}
			
			foreach ($getVars as $getVariableName => $getVariableValue)
			{
				$urlParameters[$getVariableName] = $getVariableName.'='.urlencode($getVariableValue);
			}
			
			$urlQueryString = implode('&', $urlParameters);
			if (strlen($urlQueryString) > 0)
				$urlString = $urlParsed['scheme'].'://'.$urlParsed['host'].@$urlParsed['path'].'?'.$urlQueryString;
			else 
				$urlString = $urlParsed['scheme'].'://'.$urlParsed['host'].@$urlParsed['path'];

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $urlString);
			curl_setopt($ch, CURLOPT_VERBOSE, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			
			if ($cookieFile !== null)
				curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

			if ($authUsername !== null)
				curl_setopt($ch, CURLOPT_USERPWD, $authUsername.':'.$authPassword);
				
			curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

			if (count($headerVars) > 0)
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headerVars);
			
			if (substr($url, 0, 8) == 'https://')
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		    curl_setopt($ch, CURLOPT_TIMEOUT, $httpTimeoutTotal); 
		    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $httpTimeoutConnect); 
		    
		    if ($proxyServer !== null)
		    {
				curl_setopt($ch, CURLOPT_PROXY, $proxyServer);
				if ($proxyAuth !== null)
				{
					curl_setopt ($ch, CURLOPT_PROXYUSERPWD, $proxyAuth); 
				}
		    }

			$responseData = curl_exec($ch);
			$responseInfo = curl_getinfo($ch);
			$responseError = curl_errno($ch);
			$responseErrorString = curl_error($ch);

			curl_close($ch);
			
			return new HttpResponse($responseInfo, $responseData, $responseError, $responseErrorString);
		}
		
		public static function put($url, $getVars = array(), $headerVars = array(), $putData, $authUsername = null, $authPassword = null, $proxyServer = null, $proxyAuth = null, $httpTimeoutTotal = 30, $httpTimeoutConnect = 20)
		{
			
		}
		
	}
	
	class HttpResponse
	{
		private $responseInfo;
		private $responseData;
		
		private $responseErrorNumber;
		private $responseErrorString;
		
		private $requestSuccess = true;
		
		public function __construct($responseInfo, $responseData, $responseErrorNumber = null, $responseErrorString = null)
		{
			$this->responseInfo = $responseInfo;
			$this->responseData = $responseData;
			
			/**
			 * Check for a cUrl error
			 */
			if ($responseErrorNumber > 0)
			{
				$this->requestSuccess = false;
				
				if ($responseErrorNumber == CURLE_OPERATION_TIMEOUTED)
					$this->responseErrorString = _('A timeout occured while waiting for a response.');
				else 
					$this->responseErrorString = $responseErrorString;
					
				return false;
			}
		}
		
		public function getErrorString()
		{
			return $this->responseErrorString;
		}
		
		public function getSuccess()
		{
			return $this->requestSuccess;
		}
		
		public function getResponseTime()
		{
			return $this->responseInfo['total_time'];
		}
		
		public function getHttpCode()
		{
			return $this->responseInfo['http_code'];
		}
		
		public function getResponse()
		{
			return $this->responseData;
		}
		
		public function getResponseJson()
		{
			$json = json_decode($this->responseData);

			return $json;
		}
		
		public function getResponseXml($simpleXml = true)
		{
			if ($simpleXml === true)
			{
				libxml_use_internal_errors(true);
				try 
				{
					$xml = new SimpleXMLElement($this->responseData);
					return $xml;
				}
				catch (Exception $e)
				{
					return false;
				}
			}
			else 
			{
				$doc = new DOMDocument();
				$result = @$doc->loadXML($this->responseData);
				
				if ($result === true)
					return $doc;
			}
			
			return false;
		}
	}
	
?>