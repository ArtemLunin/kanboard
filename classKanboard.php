<?php

class Kanboard {
	private $kanboardAPI = [
		'createTask',
		'getAllTasks',
	];
	private $metadataFields = [
		"capop", "oracle", "ticket", "otl", "creator", "version", "origintask"
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
	function getMetadataField($task_id, $field_name) {
		$taskMetadata = $this->callKanboardAPI('getTaskMetadata', [$task_id]);
		return $taskMetadata['result'][$field_name] ?? false;
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
	function fieldsTask($task_id, $convert_nl = true, $task_version = false) {
		$task = $this->callKanboardAPI('getTask', ['task_id' => $task_id]);
		if (isset($task['result'])) {
			$column_names = $this->getColumnsNames();
			$task = [
				'id'			=> (int)$task['result']['id'],
				'creator_id'	=> (int)$task['result']['creator_id'],
				'date_creation'	=> (int)$task['result']['date_creation'],
				'date_started'	=> (int)$task['result']['date_started'],
				'date_completed'=> (int)$task['result']['date_completed'],
				'date_due'		=> (int)$task['result']['date_due'],
				'reference'		=> $task['result']['reference'],
				'description'	=> $convert_nl ? nl2br($task['result']['description'], FALSE) : $task['result']['description'],
				'title'			=> $task['result']['title'] . (($task_version !== false) ? '_v'.$task_version : ''),
				'status'		=> $column_names[(int)$task['result']['column_id']] ?? 'undefined',
				'assignee_name'	=> 'not assigned',
			];
		} else {
			$task = null;
		}
		return $task;
	}
	function getColumnsNames() {
		$column_names = [];
		$columns = $this->callKanboardAPI('getColumns', [
			$this->projectID,
		]);
		if (isset($columns['result'])) {
			foreach ($columns['result'] as $column) {
				$column_names[$column['id']] = $column['title'];
			}
		}
		return $column_names;
	}
	function setTaskMetadata($task_id, $metadataFields) {
		return $this->callKanboardAPI('saveTaskMetadata', [$task_id, $metadataFields]);
	}
	function getUserNameFromTag($tags_arr) {
		return array_values($tags_arr)[0];
	}
	function getProjectNameFromTag($tags_arr) {
		return array_values($tags_arr)[0];
	}
	function getAllTaskFiles($task_id) {
		if (is_numeric($task_id)) {
			$taskFiles = $this->callKanboardAPI('getAllTaskFiles', [
				'task_id'	=> $task_id,
			]);
			return [
				'id'	=> (int)$task_id,
				'files'	=> array_map("taskFilesMapper", $taskFiles['result']),
			];
		}
		return [];
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

function taskFilesMapper($fileItem) 
{
	return [
		'file_id'	=> $fileItem['id'],
		'file_name'	=> $fileItem['name'],
		'file_size'	=> $fileItem['size'],
	];
}
?>