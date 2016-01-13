<?php
namespace AmazonMWS;
    /**
     * Copyright 2013 CPI Group, LLC
     *
     * Licensed under the Apache License, Version 2.0 (the "License");
     *
     * you may not use this file except in compliance with the License.
     * You may obtain a copy of the License at
     *
     *     http://www.apache.org/licenses/LICENSE-2.0
     *
     * Unless required by applicable law or agreed to in writing, software
     * distributed under the License is distributed on an "AS IS" BASIS,
     * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
     * See the License for the specific language governing permissions and
     * limitations under the License.
     */

abstract class AmazonCore
{
	const AMAZON_VERSION_PRODUCTS = '2011-10-01';
	
    protected $urlbase;
    protected $urlbranch;
    protected $throttleLimit;
    protected $throttleTime;
    protected $throttleSafe;
    protected $throttleGroup;
    protected $throttleStop = false;
    protected $storeName;
    protected $options;
    protected $config = [];
    protected $mockMode = false;
    protected $mockFiles;
    protected $mockIndex = 0;
    protected $logpath;
    protected $env;
    protected $rawResponses = array();

    protected function __construct($config = [], $mock = false, $m = null)
    {
		$this->env = __DIR__ . '/../environment.php';
		
		if (!isset($config)) {
			return;
		}
		
		$this->config = $config;
        $this->setMock($mock, $m);
		
        if (array_key_exists('merchantId', $this->config)) {
            $this->options['SellerId'] = $this->config['merchantId'];
        } 
		
        if (array_key_exists('marketplaceId', $this->config)) {
            $this->options['MarketplaceId'] = $this->config['marketplaceId'];
        } 
		
        if (array_key_exists('mwsAuthToken', $this->config)) {
            $this->options['MWSAuthToken'] = $this->config['mwsAuthToken'];
        } 
		
        if (array_key_exists('keyId', $this->config)) {
            $this->options['AWSAccessKeyId'] = $this->config['keyId'];
        } else {
            $this->log('Access Key ID is missing!', 'Warning');
        }
		
        if (!array_key_exists('secretKey', $this->config)) {
            $this->log('Secret Key is missing!', 'Warning');
        }
		
        if (!empty($this->config['serviceUrl'])) {
            $this->urlbase = $this->config['serviceUrl'];
        } else {
        	$this->urlbase = 'https://mws.amazonservices.com/';
        }

        $this->options['SignatureVersion'] = 2;
        $this->options['SignatureMethod'] = 'HmacSHA256';
    }
	
	public function setMerchantId($merchantId = null) {
		$this->options['SellerId'] = $merchantId;
	}
	
	public function setMarketplaceId($marketplaceId = null) {
		$this->options['MarketplaceId'] = $marketplaceId;
	}
	
	public function setMWSAuthToken($mwsAuthToken = null) {
		$this->options['MWSAuthToken'] = $mwsAuthToken;
	}

    protected function log($msg, $level = 'Info')
    {
        if ($msg != false) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            if (!isset($this->config['debug'])) {
                return;
            }
			
            if (isset($backtrace) && isset($backtrace[1]) && isset($backtrace[1]['file']) && isset($backtrace[1]['line']) && isset($backtrace[1]['function'])) {
                $fileName = basename($backtrace[1]['file']);
                $file = $backtrace[1]['file'];
                $line = $backtrace[1]['line'];
                $function = $backtrace[1]['function'];
            } else {
                $fileName = basename($backtrace[0]['file']);
                $file = $backtrace[0]['file'];
                $line = $backtrace[0]['line'];
                $function = $backtrace[0]['function'];
            }

			error_log("[$level][" . date("Y/m/d H:i:s") . " $fileName:$line $function] " . $msg);
        } else {
            return false;
        }
    }

    public function setMock($b = true, $files = null)
    {
        if (is_bool($b)) {
            $this->resetMock(true);
            $this->mockMode = $b;
            if ($b) {
                $this->log("Mock Mode set to ON");
            }

            if (is_string($files)) {
                $this->mockFiles = array();
                $this->mockFiles[0] = $files;
                $this->log("Single Mock File set: $files");
            } else if (is_array($files)) {
                $this->mockFiles = $files;
                $this->log("Mock files array set.");
            } else if (is_numeric($files)) {
                $this->mockFiles = array();
                $this->mockFiles[0] = $files;
                $this->log("Single Mock Response set: $files");
            }
        }
    }

    /**
     * Sets mock index back to 0.
     *
     * This method is used for returning to the beginning of the mock file list.
     * @param boolean $mute [optional]<p>Set to <b>TRUE</b> to prevent logging.</p>
     */
    protected function resetMock($mute = false)
    {
        $this->mockIndex = 0;
        if (!$mute) {
            $this->log("Mock List index reset to 0");
        }
    }

    /**
     * Enables or disables the throttle stop.
     *
     * When the throttle stop is enabled, throttled requests will not  be repeated.
     * This setting is off by default.
     * @param boolean $b <p>Defaults to <b>TRUE</b>.</p>
     */
    public function setThrottleStop($b = true)
    {
        $this->throttleStop = !empty($b);
    }

    /**
     * Returns options array.
     *
     * Gets the options for the object, for debugging or recording purposes.
     * Note that this also includes key information such as your Amazon Access Key ID.
     * @return array All of the options for the object.
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Gives the latest response data received from Amazon.
     * Response arrays contain the following keys:
     * <ul>
     * <li><b>head</b> - The raw HTTP head, including the response code and content length</li>
     * <li><b>body</b> - The raw HTTP body, which will almost always be in XML format</li>
     * <li><b>code</b> - The HTTP response code extracted from the head for convenience</li>
     * <li><b>answer</b> - The HTTP response message extracted from the head for convenience</li>
     * <li><b>ok</b> - Contains a <b>1</b> if the response was normal, or <b>0</b> if there was a problem</li>
     * <li><b>headarray</b> - An associative array of the head data, for convenience</li>
     * </ul>
     * @param int $i [optional] <p>If set, retrieves the specific response instead of the last one.
     * If the index for the response is not used, <b>FALSE</b> will be returned.</p>
     * @return array associative array of HTTP response or <b>FALSE</b> if not set yet
     */
    public function getLastResponse($i = NULL)
    {
        if (!isset($i)) {
            $i = count($this->rawResponses) - 1;
        }
        if ($i >= 0 && isset($this->rawResponses[$i])) {
            return $this->rawResponses[$i];
        } else {
            return false;
        }
    }

    /**
     * Gives all response code received from Amazon.
     * @return array list of associative arrays of HTTP response or <b>FALSE</b> if not set yet
     * @see getLastResponse
     */
    public function getRawResponses()
    {
        if (!empty($this->rawResponses)) {
            return $this->rawResponses;
        } else {
            return false;
        }
    }

    /**
     * Fetches the given mock file, or attempts to.
     *
     * This method is only called when Mock Mode is enabled. This is where
     * files from the mock file list are retrieved and passed back to the caller.
     * The success or failure of the operation will be recorded in the log,
     * including the name and path of the file involved. For retrieving response
     * codes, see <i>fetchMockResponse</i>.
     * @param boolean $load [optional] <p>Set this to <b>FALSE</b> to prevent the
     * method from loading the file's contents into a SimpleXMLObject. This is
     * for when the contents of the file are not in XML format, or if you simply
     * want to retrieve the raw string of the file.</p>
     * @return \SimpleXMLObject|string|boolean <p>A SimpleXMLObject holding the
     * contents of the file, or a string of said contents if <i>$load</i> is set to
     * <b>FALSE</b>. The return will be <b>FALSE</b> if the file cannot be
     * fetched for any reason.</p>
     */
    protected function fetchMockFile($load = true)
    {
        if (!is_array($this->mockFiles) || !array_key_exists(0, $this->mockFiles)) {
            $this->log("Attempted to retrieve mock files, but no mock files present", 'Warning');
            return false;
        }

        if (!array_key_exists($this->mockIndex, $this->mockFiles)) {
            $this->log("End of Mock List, resetting to 0");
            $this->resetMock();
        }

        //check for absolute/relative file paths
        if (strpos($this->mockFiles[$this->mockIndex], '/') === 0 || strpos($this->mockFiles[$this->mockIndex], '..') === 0) {
            $url = $this->mockFiles[$this->mockIndex];
        } else {
            $url = 'mock/' . $this->mockFiles[$this->mockIndex];
        }

        $this->mockIndex++;

        if (file_exists($url)) {
            try {
                $this->log("Fetched Mock File: $url");
                if ($load) {
                    $return = simplexml_load_file($url);
                } else {
                    $return = file_get_contents($url);
                }
                return $return;
            } catch (\Exception $e) {
                $this->log("Error when opening Mock File: $url - " . $e->getMessage(), 'Warning');
                return false;
            }
        } else {
            $this->log("Mock File not found: $url", 'Warning');
            return false;
        }
    }

    /**
     * Generates a fake HTTP response using the mock file list.
     *
     * This method uses the response codes in the mock file list to generate an
     * HTTP response. The success or failure of this operation will be recorded
     * in the log, including the response code returned. This is only used by
     * a few operations. The response array will contain the following fields:
     * <ul>
     * <li><b>head</b> - ignored, but set for the sake of completion</li>
     * <li><b>body</b> - empty XML, also ignored</li>
     * <li><b>code</b> - the response code fetched from the list</li>
     * <li><b>answer</b> - answer message</li>
     * <li><b>error</b> - error message, same value as answer, not set if status is 200</li>
     * <li><b>ok</b> - 1 or 0, depending on if the status is 200</li>
     * </ul>
     * @return boolean|array An array containing the HTTP response, or simply
     * the value <b>FALSE</b> if the response could not be found or does not
     * match the list of valid responses.
     */
    protected function fetchMockResponse()
    {
        if (!is_array($this->mockFiles) || !array_key_exists(0, $this->mockFiles)) {
            $this->log("Attempted to retrieve mock responses, but no mock responses present", 'Warning');
            return false;
        }
        if (!array_key_exists($this->mockIndex, $this->mockFiles)) {
            $this->log("End of Mock List, resetting to 0");
            $this->resetMock();
        }
        if (!is_numeric($this->mockFiles[$this->mockIndex])) {
            $this->log("fetchMockResponse only works with response code numbers", 'Warning');
            return false;
        }

        $r = array();
        $r['head'] = 'HTTP/1.1 200 OK';
        $r['body'] = '<?xml version="1.0"?><root></root>';
        $r['code'] = $this->mockFiles[$this->mockIndex];
        $this->mockIndex++;
        if ($r['code'] == 200) {
            $r['answer'] = 'OK';
            $r['ok'] = 1;
        } else if ($r['code'] == 404) {
            $r['answer'] = 'Not Found';
            $r['error'] = 'Not Found';
            $r['ok'] = 0;
        } else if ($r['code'] == 503) {
            $r['answer'] = 'Service Unavailable';
            $r['error'] = 'Service Unavailable';
            $r['ok'] = 0;
        } else if ($r['code'] == 400) {
            $r['answer'] = 'Bad Request';
            $r['error'] = 'Bad Request';
            $r['ok'] = 0;
        }

        if ($r['code'] != 200) {
            $r['body'] = '<?xml version="1.0"?>
<ErrorResponse xmlns="http://mws.amazonaws.com/doc/2009-01-01/">
  <Error>
    <Type>Sender</Type>
    <Code>' . $r['error'] . '</Code>
    <Message>' . $r['answer'] . '</Message>
  </Error>
  <RequestID>123</RequestID>
</ErrorResponse>';
        }


        $r['headarray'] = array();
        $this->log("Returning Mock Response: " . $r['code']);
        return $r;
    }

    /**
     * Checks whether or not the response is OK.
     *
     * Verifies whether or not the HTTP response has the 200 OK code. If the code
     * is not 200, the incident and error message returned are logged.
     * @param array $r <p>The HTTP response array. Expects the array to have
     * the fields <i>code</i>, <i>body</i>, and <i>error</i>.</p>
     * @return boolean <b>TRUE</b> if the status is 200 OK, <b>FALSE</b> otherwise.
     */
    protected function checkResponse($r)
    {
        if (!is_array($r) || !array_key_exists('code', $r)) {
            $this->log("No Response found", 'Warning');
            return false;
        }
        if ($r['code'] == 200) {
            return true;
        } else {
            $xml = simplexml_load_string($r['body'])->Error;
            $this->log("Bad Response! " . $r['code'] . " " . $r['error'] . ": " . $xml->Code . " - " . $xml->Message, 'Urgent');
            return false;
        }
    }

    /**
     * Handles generation of the signed query string.
     *
     * This method uses the secret key from the config file to generate the
     * signed query string.
     * It also handles the creation of the timestamp option prior.
     * @return string query string to send to cURL
     * @throws \Exception if config file or secret key is missing
     */
    protected function genQuery()
    {
        if (array_key_exists('secretKey', $this->config)) {
            $secretKey = $this->config['secretKey'];
        } else {
            throw new \Exception("Secret Key is missing!");
        }

        unset($this->options['Signature']);
        $this->options['Timestamp'] = $this->genTime();
        $this->options['Signature'] = $this->_signParameters($this->options, $secretKey);
        return $this->_getParametersAsString($this->options);
    }

    /**
     * Generates timestamp in ISO8601 format.
     *
     * This method creates a timestamp from the provided string in ISO8601 format.
     * The string given is passed through <i>strtotime</i> before being used. The
     * value returned is actually two minutes early, to prevent it from tripping up
     * Amazon. If no time is given, the current time is used.
     * @param string|bool $time [optional] <p>The time to use. Since this value is
     * passed through <i>strtotime</i> first, values such as "-1 hour" are fine.
     * Defaults to the current time.</p>
     * @return string Unix timestamp of the time, minus 2 minutes.
     */
    protected function genTime($time = false)
    {
        if (!$time) {
            $time = time();
        } else {
            $time = strtotime($time);
        }

        return date('Y-m-d\TH:i:sO', $time - 120);

    }

    /**
     * validates signature and sets up signing of them, copied from Amazon
     * @param array $parameters
     * @param string $key
     * @return string signed string
     * @throws \Exception
     */
    protected function _signParameters(array $parameters, $key)
    {
        $algorithm = $this->options['SignatureMethod'];
        $stringToSign = null;
        if (2 === $this->options['SignatureVersion']) {
            $stringToSign = $this->_calculateStringToSignV2($parameters);
        } else {
            throw new \Exception("Invalid Signature Version specified");
        }
        return $this->_sign($stringToSign, $key, $algorithm);
    }

    /**
     * generates the string to sign, copied from Amazon
     * @param array $parameters
     * @return type
     */
    protected function _calculateStringToSignV2(array $parameters)
    {
        $data = 'POST';
        $data .= "\n";
        $endpoint = parse_url($this->urlbase . $this->urlbranch);
        $data .= $endpoint['host'];
        $data .= "\n";
        $uri = array_key_exists('path', $endpoint) ? $endpoint['path'] : null;
        if (!isset ($uri)) {
            $uri = "/";
        }
        $uriencoded = implode("/", array_map(array($this, "_urlencode"), explode("/", $uri)));
        $data .= $uriencoded;
        $data .= "\n";
        uksort($parameters, 'strcmp');
        $data .= $this->_getParametersAsString($parameters);
        return $data;
    }

    /**
     * Fuses all of the parameters together into a string, copied from Amazon
     * @param array $parameters
     * @return string
     */
    protected function _getParametersAsString(array $parameters)
    {
        $queryParameters = array();
        foreach ($parameters as $key => $value) {
            $queryParameters[] = $key . '=' . $this->_urlencode($value);
        }
        return implode('&', $queryParameters);
    }

    //Functions from Athena:
    /**
     * Reformats the provided string using rawurlencode while also replacing ~, copied from Amazon
     *
     * Almost the same as using rawurlencode
     * @param string $value
     * @return string
     */
    protected function _urlencode($value)
    {
        return rawurlencode($value);
    }
    // End Functions from Athena

    // Functions from Amazon:
    /**
     * Runs the hash, copied from Amazon
     * @param string $data
     * @param string $key
     * @param string $algorithm 'HmacSHA1' or 'HmacSHA256'
     * @return string
     * @throws \Exception
     */
    protected function _sign($data, $key, $algorithm)
    {
        if ($algorithm === 'HmacSHA1') {
            $hash = 'sha1';
        } else if ($algorithm === 'HmacSHA256') {
            $hash = 'sha256';
        } else {
            throw new \Exception ("Non-supported signing method specified");
        }

        return base64_encode(
            hash_hmac($hash, $data, $key, true)
        );
    }

    /**
     * Sends a request to Amazon via cURL
     *
     * This method will keep trying if the request was throttled.
     * @param string $url <p>URL to feed to cURL</p>
     * @param array $param <p>parameter array to feed to cURL</p>
     * @return array cURL response array
     */
    protected function sendRequest($url, $param)
    {
        $this->log("Making request to Amazon: " . $this->options['Action']);
        $response = $this->fetchURL($url, $param);

        while ($response['code'] == '503' && $this->throttleStop == false) {
            $this->sleep();
            $response = $this->fetchURL($url, $param);
        }

        $this->rawResponses[] = $response;
        return $response;
    }

    /**
     * Get url or send POST data
     * @param string $url
     * @param array $param ['Header']
     *               $param['Post']
     * @return array $return['ok'] 1  - success, (0,-1) - fail
     *               $return['body']  - response
     *               $return['error'] - error, if "ok" is not 1
     *               $return['head']  - http header
     */
    function fetchURL($url, $param)
    {
        $return = array();

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        if (!empty($param)) {
            if (!empty($param['Header'])) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $param['Header']);
            }
            if (!empty($param['Post'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $param['Post']);
            }
        }

        $data = curl_exec($ch);
        if (curl_errno($ch)) {
            $return['ok'] = -1;
            $return['error'] = curl_error($ch);
            return $return;
        }

        if (is_numeric(strpos($data, 'HTTP/1.1 100 Continue'))) {
            $data = str_replace('HTTP/1.1 100 Continue', '', $data);
        }
        $data = preg_split("/\r\n\r\n/", $data, 2, PREG_SPLIT_NO_EMPTY);
        if (!empty($data)) {
            $return['head'] = (isset($data[0]) ? $data[0] : null);
            $return['body'] = (isset($data[1]) ? $data[1] : null);
        } else {
            $return['head'] = null;
            $return['body'] = null;
        }

        $matches = array();
        $data = preg_match("/HTTP\/[0-9.]+ ([0-9]+) (.+)\r\n/", $return['head'], $matches);
        if (!empty($matches)) {
            $return['code'] = $matches[1];
            $return['answer'] = $matches[2];
        }

        $data = preg_match("/meta http-equiv=.refresh. +content=.[0-9]*;url=([^'\"]*)/i", $return['body'], $matches);
        if (!empty($matches)) {
            $return['location'] = $matches[1];
            $return['code'] = '301';
        }

        if ($return['code'] == '200' || $return['code'] == '302') {
            $return['ok'] = 1;
        } else {
            $return['error'] = (($return['answer'] and $return['answer'] != 'OK') ? $return['answer'] : 'Something wrong!');
            $return['ok'] = 0;
        }

        foreach (preg_split('/\n/', $return['head'], -1, PREG_SPLIT_NO_EMPTY) as $value) {
            $data = preg_split('/:/', $value, 2, PREG_SPLIT_NO_EMPTY);
            if (is_array($data) and isset($data['1'])) {
                $return['headarray'][$data['0']] = trim($data['1']);
            }
        }

        curl_close($ch);

        return $return;
    }

    /**
     * Sleeps for the throttle time and records to the log.
     */
    protected function sleep()
    {
        flush();
        $s = ($this->throttleTime == 1) ? '' : 's';
        $this->log("Request was throttled, Sleeping for " . $this->throttleTime . " second$s", 'Throttle');
        sleep($this->throttleTime);
    }

    /**
     * Checks for a token and changes the proper options
     * @param SimpleXMLObject $xml <p>response data</p>
     * @return boolean <b>FALSE</b> if no XML data
     */
    protected function checkToken($xml)
    {
        if (!$xml) {
            return false;
        }
        if ($xml->NextToken) {
            $this->tokenFlag = true;
            $this->options['NextToken'] = (string)$xml->NextToken;
        } else {
            unset($this->options['NextToken']);
            $this->tokenFlag = false;
        }
    }
    // -- End Functions from Amazon --
}