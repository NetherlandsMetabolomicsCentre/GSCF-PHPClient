<?php
/**
 * SDK for interfacing with GSCF / DBNP using PHP
 *
 * API specification: http://studies.dbnp.org/api
 *
 *
 * Copyright 2012 Jeroen Wesbeek <work@osx.eu>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
class GSCF {
	private $version	= "0.1";
	private $endPoint	= "api";
	private $apiKey		= "";
	private $sequence	= 0;
	private $token		= "";	
	private $username	= "";
	private $password	= "";
	private $deviceID	= "";
	private $url		= "";
	private $cache		= array();
	private $cachePath	= "";
	private $macAddress	= "";

	/**
	 * class constructor
	 * @void
	 */
	public function __construct() {
		// check if curl is available
		if (!function_exists("curl_init")) {
			if (file_exists('/etc/debian_version')) {
				throw new Exception("curl required, please install using apt-get install php-curl");
			} else {
				throw new Exception("this class requires curl to be available in php");
			}
		}

		// set default cache path
		$this->cachePath = (preg_match("/^win/i",php_uname('s'))) ? "C:\TEMP" : "/tmp";
	}

	/**
	 * class desctructor
	 * @void
	 */
	public function __destruct() {
		$this->cacheToDisk();
	}

	/**
	 * cache token and sequence to disk
	 * @void
	 */
	private function cacheToDisk() {
		// flush sequence and token to tempfile
		$tempfile = $this->getTempfile();
		$data = array(
			'token'		=> $this->token,
			'sequence'	=> $this->sequence
		);

		// store serialized data
		if (is_writable(dirname($tempfile)) || is_writable($tempfile)) {
			file_put_contents($tempfile,serialize($data));
		}
	}

	/**
	 * restore token and sequence from disk
	 * @return Boolean
	 */
	private function restoreFromDisk() {
		$success = false;

		// read sequence and token from disk
		$tempfile = $this->getTempfile();
		if (is_readable($tempfile)) {
			// read tempfile
			$data = unserialize(file_get_contents($tempfile));

			if ($data['token'] && $data['sequence']) {
				// store data internally
				$this->token	= $data['token'];
				$this->sequence	= $data['sequence'];
				$success 	= true;
			}
		}

		return $success;
	}

	/**
	 * generate a unique device id based on MAC address and script location
	 * @void
	 */
	private function generateDeviceID() {
		$macAddressCache = sprintf("%s/macaddress.data",$this->cachePath);

		// got a mac address?
		if (!$this->macAddress) {
			if (file_exists($macAddressCache) && is_readable($macAddressCache)) {
				// read cache file and de-serialize
				$data = unserialize(file_get_contents($macAddressCache));
				$this->macAddress = $data['macAddress'];
				$mac = $data['macAddress'];
			} else {
				// no, get it
				// determine the device ID based on MAC Address
				$os		= php_uname('s');
				$hostName	= php_uname('n');
				$mac		= "";

				// get MAC Address based on environment
				if (preg_match("/^win/i",$os)) {
					// assume Windoze
					exec('ipconfig /all',$result);
					$find = "Physical Address";
				} elseif (preg_match("/^darwin/i",$os)) {
					// assume Mac
					exec('/sbin/ifconfig en0',$result);
					$find = "ether";
				} else {
					// assume *nix
					exec('/sbin/ifconfig eth0',$result);
					$find = "HWaddr";
				}
	
				// iterate through results to fetch mac address
				foreach ($result as $line) {
					if (preg_match(sprintf("/%s([ |\.|:]+)([0-9abcdef:\-]{17})/i",$find),$line,$matches)) {
						$mac = strtolower(preg_replace("/-/",":",$matches[2]));
						break;
					}
				}

				// if somehow we do not have a mac address,
				// use the hostname instead
				if (!$mac) $mac = $hostName;

				// write the mac address to the cache file
				if (is_writable(dirname($macAddressCache)) || is_writable($macAddressCache)) {
					$data = array('macAddress' => $mac);

					// store serialized
					file_put_contents($macAddressCache,serialize($data));
				}
			}
		} else {
			$mac = $this->macAddress;
		}

		// determine script path
		$myPath = $_SERVER['SCRIPT_NAME'];

		// create a device ID based on md5sum and script path and append the username
		// to make it user specific. This can be important if multiple users will use
		// this class, as the deviceID is used to lookup the user in subsequent request
		// to validate the calls through the predicted md5 sums
		$this->deviceID = md5(sprintf("%s::%s::%s",$mac,$myPath,$this->username));
	}

	/**
	 * username setter
	 * @param String username - the username to authenticate with (with ROLE_CLIENT set!)
	 * @void
	 */
	public function username($username) {
		$this->username = $username;
	}

	/**
	 * password setter
	 * @param String username - the password to authenticate with
	 * @void
	 */
	public function password($password) {
		$this->password = $password;
	}

	/**
	 * apiKey setter
	 * @param String apiKey - the api key for this user
	 * @void
	 */
	public function apiKey($apiKey) {
		$this->apiKey = $apiKey;
	}

	/**
	 * cache path setter
	 * @param String cache path
	 * @void
	 */
	public function cachePath($cachePath) {
		$this->cachePath = $cachePath;
	}

	/**
	 * the device id setter to override the generated device ID
	 * @param String deviceID - the unique identifier of the device making the call
	 * @void
	 */
	public function deviceID($deviceID) {
		$this->deviceID = $deviceID;
	}

	/**
	 * private getter for the device ID
	 */
	private function getDeviceID() {
		if (!$this->deviceID) $this->generateDeviceID();

		return $this->deviceID;
	}

	/**
	 * the gscf url setter
	 * @param String url - the base url where the gscf instance to interface with is located
	 * @void
	 */
	public function url($url) {
		$this->url = $url;
	}

	/**
	 * authenticate against the gscf api
	 *
	 * The authenticate API call authenticates against the GSCF api
	 * using HTTP Basic Authentication. As this particular authentication
	 * scheme just base64 encodes the username + password, it is relatively
	 * unsecure. Therefore the authenticate method will only be called when
	 * 	- an initial device session is started
	 * 	- the client / server becomes out of sync
	 * While a secondary validation method is used (the calculated validation
	 * hash) it is strongly advised to use HTTPS to perform API calls, to reduce
	 * the risk of man in the middle attacks.
	 *
	 * @void
	 */
	private function authenticate() {
		// define http basic authentication hash
		$url		= sprintf("%s/%s/authenticate",$this->url,$this->endPoint);
		$headers	= array(sprintf("Authorization: Basic %s==",base64_encode(sprintf("%s:%s",$this->username,$this->password))));
		$postFields	= array('deviceID' => $this->getDeviceID());

		// perform authenticate call
		$curl	= curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $postFields);
		curl_setopt($curl, CURLOPT_USERAGENT, sprintf("GSCF-PHPClient version %s",$this->version));

		// fetch result
		$json	= curl_exec($curl);

		// check status code
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		// close curl
		curl_close($curl);

		// check result
		if ($status == 401) {
			throw new Exception(sprintf("password for user '%s' is invalid (%s) or user is not authorized to use the api at %s (has ROLE_CLIENT been assigned to the user?",$this->username,str_repeat("*",strlen($this->password)),$this->url));
		} elseif ($status == 404) {
			throw new Exception(sprintf("the server appears to be down at %s",$this->url));
		} elseif ($status == 409) {
			// decode json
			$obj	= json_decode($json);
			throw new Exception(sprintf("%s",$obj->{'error'}));
		} elseif ($status <> 200) {
			throw new Exception(sprintf("server replied with an unexpected status code %d",$status));
		} else {
			// decode json
			$obj	= json_decode($json);

			// store token and sequence locally
			$this->token	= $obj->{'token'};
			$this->sequence	= $obj->{'sequence'};

			return true;
		}
	}

	/**
	 * return the path of the tempfile
	 * @return string
	 */
	private function getTempfile() {
		$tempfile = sprintf("%s/gscf-%s.data",$this->cachePath,$this->getDeviceID());
		return $tempfile;
	}

	/**
	 * return the incremented sequence
	 * @return string
	 */
	private function getSequence() {
		// authenticate if we do not yet have a sequence
		if (!$this->sequence) {
			// try to read sequence (and token) from cache file
			if (!$this->restoreFromDisk()) {
				// authenticate to fetch token and sequence
				$this->authenticate();
			}
		}

		// increment sequence
		$this->sequence++;

		return $this->sequence;
	}

	/**
	 * return the token
	 * @return int
	 */
	private function getToken() {
		// got a token?
		if (!$this->token) {
			// try to read token (and sequence) from cache file
			if (!$this->restoreFromDisk()) {
				// authenticate to fetch token and sequence
				$this->authenticate();
			}
		}

		return $this->token;
	}

	/**
	 * perform an api call
	 * @param string service to call (see api spec for more information)
	 * @param array args 
	 * @param boolean retry - method allows a retry in case client/server become out of sync
	 * @return object
	 */
	private function apiCall($service,$args,$retry=false) {
		$token		= $this->getToken();
		$sequence	= $this->getSequence();
		
		// define the api url and the initial post variables
		$url		= sprintf("%s/%s/%s",$this->url,$this->endPoint,$service);
		$postFields	= array(
			'deviceID'	=> $this->getDeviceID(),
			'validation'	=> md5(sprintf("%s%d%s",$token,$sequence,$this->apiKey))
		);

		// add arguments to postfields
		foreach ($args as $key=>$val) {
			$postFields[$key] = $val;
		}

		// perform api call
		$curl	= curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $postFields);
		curl_setopt($curl, CURLOPT_USERAGENT, sprintf("GSCF-PHPClient version %s",$this->version));

		// execute request
		$json	= curl_exec($curl);

		// check status code
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		// close curl
		curl_close($curl);

		// check if call was okay
		if ($status == 401) {
			// check if this is a retried call
			if ($retry) {
				// yes, seems like we are really denied access, throw
				// an exception
				throw new Exception('Unauthorized api call');
			} else {
				// unauthorized call, this may happen if the client and server
				// become out of sync (e.g. the sequence differs). Try to authenticate
				// again to re-synchronize the sequence
				$this->authenticate();
	
				// and retry the api call
				$this->apiCall($service,$args,true);
			}
		} elseif ($status == 404) {
			throw new Exception(sprintf("the server appears to be down at %s",$this->url));
		} elseif ($status <> 200) {
			throw new Exception(sprintf("server replied with an unexpected status code %d",$status));
		} else {
			// decode json
			$obj	= json_decode($json);

			// return decoded object
			return $obj;
		}
	}

	/**
	 * API Call : getStudes
	 * @return object
	 */
	private function APIGetStudies() {
		if (array_key_exists('studies',$this->cache)) {
			$studies = $this->cache['studies'];
		} else {
			$studies = $this->apiCall('getStudies',array());
			$this->cache['studies'] = $studies;
		}

		return $studies;
	}

	/**
	 * public call to fetch studies
	 * @return object
	 */
	public function getStudies() {
		$rawStudies = $this->APIGetStudies();
		$studies = array();

		// iterate through studies
		try {
			foreach ($rawStudies->{'studies'} as $key=>$rawStudy) {
				// instantiate a study
				$study = new GSCFStudy();
				$study->api = $this;

				// and set the variables
				foreach ($rawStudy as $key=>$val) {
					$study->$key = $val;
				}

				array_push($studies, $study);
			}
		} catch (Exception $e) {
			// just ignore the exception, we have
			// no studies
		}

		return $studies;
	}

	/**
	 * API call to fetch all subjects for a study
	 * @param string studyToken
	 * @return object
	 */
	private function APIGetSubjectsForStudy($studyToken) {
		if (array_key_exists('subjects', $this->cache) &&
		    array_key_exists($studyToken, $this->cache['subjects']))
		{
			$subjects = $this->cache['subjects'][ $studytoken ];
		} else {
			$subjects = $this->apiCall('getSubjectsForStudy',array('studyToken'=>$studyToken));
			$this->cache['subjects'][ $studyToken ] = $subjects;
		}

		return $subjects;
	}

	/**
	 * public call to fetch subjects for a particular study
	 * @param String studyToken
	 * @return array
	 */
	public function getSubjectsForStudy($studyToken) {
		$rawSubjects = $this->APIGetSubjectsForStudy($studyToken);
		$subjects = array();

		foreach ($rawSubjects->{'subjects'} as $rawSubject) {
			// instantiate subject
			$subject = new GSCFSubject();
			$subject->api = $this;
			
			// and set variables
			foreach ($rawSubject as $key=>$val) {
				$subject->$key = $val;
			}

			array_push($subjects, $subject);
		}

		return $subjects;
	}

	/**
	 * API call to fetch all assays for a study
	 * @param string studyToken
	 * @return object
	 */
	private function APIGetAssaysForStudy($studyToken) {
		if (array_key_exists('assays', $this->cache) &&
		    array_key_exists($studyToken, $this->cache['assays']))
		{
			$assays = $this->cache['assays'][ $studytoken ];
		} else {
			$assays = $this->apiCall('getAssaysForStudy',array('studyToken'=>$studyToken));
			$this->cache['assays'][ $studyToken ] = $assays;
		}

		return $assays;
	}

	/**
	 * public call to fetch assays for a particular study
	 * @param String studyToken
	 * @return array
	 */
	public function getAssaysForStudy($studyToken) {
		$rawAssays = $this->APIGetAssaysForStudy($studyToken);
		$assays = array();

		foreach ($rawAssays->{'assays'} as $rawAssay) {
			// instantiate assay
			$assay = new GSCFAssay();
			$assay->api = $this;
			
			// and set variables
			foreach ($rawAssay as $key=>$val) {
				$assay->$key = $val;
			}

			array_push($assays, $assay);
		}

		return $assays;
	}

	/**
	 * API call to get all samples for an assay
	 * @param string assayToken
	 * @return object
	 */
	private function APIGetSamplesForAssay($assayToken) {
		if (array_key_exists('samples', $this->cache) &&
		    array_key_exists($assayToken, $this->cache['samples']))
		{
			$samples = $this->cache['samples'][ $assaytoken ];
		} else {
			$samples = $this->apiCall('getSamplesForAssay',array('assayToken'=>$assayToken));
			$this->cache['samples'][ $assayToken ] = $samples;
		}

		return $samples;
	}

	/**
	 * public call to fetch samples for a particular assay
	 * @param String assayToken
	 * @return array
	 */
	public function getSamplesForAssay($assayToken) {
		$rawSamples = $this->APIGetSamplesForAssay($assayToken);
		$samples = array();

		foreach ($rawSamples->{'samples'} as $rawSample) {
			// instantiate sample
			$sample = new GSCFSample();
			$sample->api = $this;
			
			// and set variables
			foreach ($rawSample as $key=>$val) {
				$sample->$key = $val;
			}

			array_push($samples, $sample);
		}

		return $samples;
	}

	/**
	 * API call to fetch all measurement data for an assay
	 * @param string assayToken
	 * @return object
	 */
	private function APIGetMeasurementDataForAssay($assayToken) {
		if (array_key_exists('measurementData', $this->cache) &&
		    array_key_exists($assayToken, $this->cache['measurementData']))
		{
			$data = $this->cache['measurementData'][ $assaytoken ];
		} else {
			$data = $this->apiCall('getMeasurementDataForAssay',array('assayToken'=>$assayToken));
			$this->cache['measurementData'][ $assayToken ] = $data;
		}

		return $data;
	}

	/**
	 * public call to fetch all measurement data for a particular assay
	 * @param String assayToken
	 * @return array
	 */
	public function getMeasurementDataForAssay($assayToken) {
		$rawData = $this->APIGetMeasurementDataForAssay($assayToken);
		$data = array();

		foreach ($rawData->{'measurements'} as $rawMeasurement) {
			// instantiate measurement
			$measurement = new GSCFMeasurement();
			$measurement->api = $this;
			
			// and set variables
			foreach ($rawMeasurement as $key=>$val) {
				$measurement->$key = $val;
			}

			array_push($data, $measurement);
		}

		return $data;
	}
}

/**
 * base wrapper class to encapsulate api results in
 * and allow for an object oriented aproach to fetch
 * more data
 */
class GSCFEntity {
	private $token;
	private $api;
	
	/**
	 * token setter of this entity
	 * @param string
	 * @void
	 */
	public function setToken($token) {
		$this->token = $token;
	}

	/**
	 * api setter
	 * @param object
	 * @void
	 */
	public function setApi($api) {
		$this->api = $api;
	}
}

/**
 * wrapper class for study
 */
class GSCFStudy extends GSCFEntity {
	/**
	 * fetch subjects for this study
	 * @return object
	 */
	public function getSubjects() {
		return $this->api->getSubjectsForStudy($this->token);
	}

	/**
	 * get assays for this subject
	 * @return object
	 */
	public function getAssays() {
		return $this->api->getAssaysForStudy($this->token);
	}
}

/**
 * wrapper class for subject
 */
class GSCFSubject extends GSCFEntity {
}

/**
 * wrapper class for assay
 */
class GSCFAssay extends GSCFEntity {
	/**
	 * get samples in this assay
	 */
	public function getSamples() {
		return $this->api->getSamplesForAssay($this->token);
	}

	/**
	 * get the measurement data for this assay
	 */
	public function getMeasurementData() {
		return $this->api->getMeasurementDataForAssay($this->token);
	}
}

/**
 * wrapper class for sample
 */
class GSCFSample extends GSCFEntity {
}

/**
 * wrapper class for measurementData
 */
class GSCFMeasurement extends GSCFEntity {
}
?>
