<?php
class ZabbixApi {
    private $username = "[USER]";
    private $password = "[PASSWORD]";
    public $apitoken = "notset"; //default value
    public $zbxhost = '';  //we add  this  for  the  description  of the trigger
    public $zabbixhost = 'http://[URL]/api_jsonrpc.php'; // out  call for api
    public $triggers = 'notset';
    public $hosts = 'notset';

    function __construct($info = "hosts", $keys = '') {
        $this->login();
    }
    public function curlie($data_string) {
        // we  build the  curl options for contacting the  zabbix host 
        $ch = curl_init($this->zabbixhost);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        //execute the curl and close the connection
        curl_close($ch);
        return $result;
    }
    private function login() {
        //we  detect if  the API token is default value, if it is not, we dont need to log in again.
        if ($this->apitoken == 'notset') {
            	$jsoninfo = '{ "jsonrpc":"2.0","method": "user.login", "params" :{ "user": "' . $this->username . '", "password": "' . $this->password . '"},"id":1}';
            	$test = $this->curlie($jsoninfo);
        	$files = json_decode($test);
		//set api token so we can log in
		$this->apitoken = $files->result;
        }
    }

    private function apiBuilder($method,$params,$addons){
     //we build  the array that is need for the contact with Zabbix
        $array = array();
        $array['jsonrpc'] = '2.0';
        $array['method'] = $method;
        $array['params'] = $params;
        if(is_array($addons)){
            array_merge($array,$addons);
        }
        $array['auth'] = $this->apitoken;
        $array['id'] = 1;
        //we return  the converted array to json
        return json_encode($array);
    }
    public function get_zabbix_req($method,$params,$addons) {
        // we ask to get our arrays converted to json
        $jsoninfo = $this->apiBuilder($method,$params,$addons);
        // we fire off our json request and then return the Json decoded content
        $files = json_decode($this->curlie($jsoninfo));
        return $files;
    }
    function __destruct() {
    }
}

$ZabbixApi = new ZabbixApi();
