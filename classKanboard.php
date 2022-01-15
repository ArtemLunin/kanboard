<?php

class Kanboard {
	private $kanboardAPI = [
		'createTask',
		'getAllTasks',
	];
	private $metadataFields = [
		"capop", "oracle", "ticket", "otl", "creator"
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
	function getMetadataFields($metadata) {
		$metadata_arr = [];
		foreach($this->metadataFields as $value) {
			$metadata_arr[$value] = '';
		}
		foreach($metadata as $key => $value) {
			$key = strtolower($key);
			if(in_array($key, $this->metadataFields)) {
				$metadata_arr[$key] = $value;
			}
		}
		return $metadata_arr;
	}
	function setTaskProjectName($task_id, $projectName) {
		$this->callKanboardAPI('setTaskTags', [
			$this->projectID,
			$task_id,
			[$projectName]
		]);
	}
	function getTaskProjectName($task_id) {
		$projectName = '';
		$taskTags = $this->callKanboardAPI('getTaskTags', [$task_id]);
		if (isset($taskTags['result']) && count($taskTags['result'])) {
			$projectName = $this->getProjectNameFromTag($taskTags['result']);
		}
		return $projectName;
	}
	function getTask($task_id) {
		$task = $this->callKanboardAPI('getTask', ['task_id' => $task_id]);
		return $task;
	}
	function fieldsTask($task_id) {
		$task = $this->callKanboardAPI('getTask', ['task_id' => $task_id]);
		if (isset($task['result'])) {
			$task = [
				'id'			=> (int)$task['result']['id'],
				'creator_id'	=> (int)$task['result']['creator_id'],
				'date_creation'	=> (int)$task['result']['date_creation'],
				'date_completed'=> (int)$task['result']['date_completed'],
				'description'	=> nl2br($task['result']['description'], FALSE),
				'title'			=> $task['result']['title'],
			];
		} else {
			$task = null;
		}
		return $task;
	}
	function setTaskMetadata($task_id, $metadataFields) {
		$this->callKanboardAPI('saveTaskMetadata', [$task_id, $metadataFields]);
	}
	function getUserNameFromTag($tags_arr) {
		return array_values($tags_arr)[0];
	}
	function getProjectNameFromTag($tags_arr) {
		return array_values($tags_arr)[0];
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