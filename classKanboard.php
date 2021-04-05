<?php

class Kanboard {
	private $kanboardAPI = [
		'createTask',
		'getAllTasks',
	];
	function __construct () {
		$this->projectID = $this->getInitialParams('getProjectByIdentifier', ['identifier' => KANBOARD_PROJECT_IDENTIFIER]);
		$this->userID = $this->getInitialParams('getUserByName', ['username' => KANBOARD_USER_CREATE_TICKETS]);
		$this->shownedColumnID = $this->getInitialParams('getColumns', [$this->projectID], KANBOARD_SHOW_COLUMN_NAME);
	}
	function getInitialParams($method, $paramObj, $additionalParam = NULL)
	{
		$resultID = 0;
		$this->kanboardRequest['method'] = $method;
		$this->kanboardRequest['params'] = $paramObj;
		$raw_result = callKanboardAPI(json_encode($this->kanboardRequest));
		$result = json_decode($raw_result, TRUE);
		if ($method === 'getColumns' && isset($additionalParam)) {
			$arr_columns = $result['result'] ?? [];
			if (count($arr_columns) != 0) {
				$arr_columns_id = array_map('strtolower', array_column($arr_columns, 'title', 'id'));
				$resultID = array_search(strtolower($additionalParam), $arr_columns_id);
				if ($resultID === FALSE) {
					$resultID = 0;
				}
			}
			else {
				$resultID = 0;
			}
		} else {
			$resultID = $result['result']['id'] ?? 0;
			if ($resultID == 0) {
				throw new Exception('Error getting '.json_encode($paramObj)."\nNetwork result:".$raw_result."\nRequest:".json_encode($this->kanboardRequest));
			}
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
	private $projectID = 0;
	private $userID = 0;
	private $shownedColumnID = 0;
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
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => $kanbanJSONParams,
  CURLOPT_HTTPHEADER => [
    'Authorization: Basic '. KANBOARD_TOKEN
  ],
]);

$response = curl_exec($curl);
curl_close($curl);
// return $kanbanJSONParams;
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