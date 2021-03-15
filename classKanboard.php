<?php

class Kanboard {
	private $kanboardAPI = [
		'createTask',
		'getAllTasks',
	];
	function __construct () {
		$this->projectID = $this->getInitialParams('getProjectByIdentifier', ['identifier', KANBOARD_PROJECT_IDENTIFIER]);
		$this->userID = $this->getInitialParams('getUserByName', ['username', KANBOARD_USER_CREATE_TICKETS]);
	}
	function getInitialParams($method, $paramObj)
	{
		$this->kanboardRequest['method'] = $method;
		list($paramName, $paramValue) = $paramObj;
		$this->kanboardRequest['params'] = [$paramName => $paramValue];
		$result = json_decode(callKanboardAPI(json_encode($this->kanboardRequest)), TRUE);
		$resultID = $result['result']['id'] ?? 0;
		if ($resultID == 0) {
			throw new Exception('Error getting '.$paramName. ' with id '.$paramValue);
		}
		return $resultID;
	}
	function __get($name) {
		return $this->$name;
	}
	function checkMethod($methodName) {
		return in_array($methodName, $this->kanboardAPI);
	}
	function callKanboardAPI($kanboardAPIName, $kanboardAPIParams) {
		$this->kanboardRequest['method'] = $kanboardAPIName;
		$this->kanboardRequest['params'] = $kanboardAPIParams;
		$resultCall = json_decode(callKanboardAPI(json_encode($this->kanboardRequest)), TRUE);
		return $resultCall;
	}
	private $kanboardRequest = [
		"jsonrpc"   => "2.0",
  		"method"    => "",
  		"id"        => "autoRequest",
	];
	// private $kanboardRequsetParams = [];
	private $projectID = 0;
	private $userID = 0;
}

function callKanboardAPI($kanbanJSONParams)
{
  $curl = curl_init();

curl_setopt_array($curl, [
  CURLOPT_URL => KANBOARD_CITE,
  CURLOPT_RETURNTRANSFER => TRUE,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 5,
  CURLOPT_FOLLOWLOCATION => TRUE,
//   CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => $kanbanJSONParams,
  CURLOPT_HTTPHEADER => [
    'Authorization: Basic '. KANBOARD_TOKEN
  ],
]);

$response = curl_exec($curl);
curl_close($curl);
return $response;
}

$taskFilesMapper = function($fileItem) 
{
	return [
		'file_id'	=> $fileItem['id'],
		'file_name'	=> $fileItem['name'],
		'file_size'	=> $fileItem['size'],
	];
}
?>