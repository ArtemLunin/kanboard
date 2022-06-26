<?php

class Kanboard {
	private $kanboardAPI = [
		'createTask',
		'getAllTasks',
	];
	private $metadataFields = [
		"capop", "oracle", "ticket", "otl", "creator", "version", "origintask", "master_date"
	];
	private $kanboardColumns = [];

	function __construct () {
		$this->projectID = $this->getField([
			'method'	=> 'getProjectByIdentifier', 
			'paramObj'	=> ['identifier' => KANBOARD_PROJECT_IDENTIFIER],
			'additionalParam' => null,
			'fieldName'		=> 'id',
			'defaultVal'	=> 0
			]);
		$this->userID = $this->getField([
			'method'	=> 'getUserByName', 
			'paramObj'	=> ['username' => KANBOARD_USER_CREATE_TICKETS],
			'additionalParam' => null,
			'fieldName'		=> 'id',
			'defaultVal'	=> 0
			]);
		$this->shownedColumnID = $this->getField([
			'method'	=> 'getColumns', 
			'paramObj'	=> [$this->projectID], 
			'additionalParam' => KANBOARD_SHOW_COLUMN_NAME,
			'fieldName'		=> 'id',
			'defaultVal'	=> 0
			]);
	}
	function getField($params)
	{
		error_log(__FUNCTION__.', '.$this->projectID);
		['method' => $method, 'paramObj' => $paramObj, 'additionalParam' => $additionalParam, 'fieldName' => $fieldName, 'defaultVal' => $defaultVal] = $params;
		$this->kanboardRequest['method'] = $method;
		$this->kanboardRequest['params'] = $paramObj;
		$raw_result = callKanboardAPI(json_encode($this->kanboardRequest));
		$result = json_decode($raw_result, true);
		if ($method === 'getColumns' && isset($additionalParam)) {
			$arr_columns = $result['result'] ?? [];
			if (count($arr_columns) != 0) {
				$arr_columns_id = array_column($arr_columns, 'title', 'id');
				$this->kanboardColumns = $arr_columns_id;
				$arr_columns_id = array_map('strtolower', $arr_columns_id);
				$fieldVal = array_search(strtolower($additionalParam), $arr_columns_id);
				if ($fieldVal === false) {
					$fieldVal = $defaultVal;
				}
			}
			else {
				$fieldVal = $defaultVal;
			}
		} else {
			$fieldVal = $result['result'][$fieldName] ?? $defaultVal;
			// if ($fieldVal == 0) {
			// 	throw new Exception('Error getting '.json_encode($paramObj)."\nNetwork result:".$raw_result."\nRequest:".json_encode($this->kanboardRequest));
			// }
		}
		return $fieldVal;
	}
	function setProjectID($projectID) {
		$this->projectID = $projectID;
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
		error_log(__FUNCTION__.', '.$this->projectID);
		$task = $this->callKanboardAPI('getTask', ['task_id' => $task_id]);
		if (isset($task['result'])) {
			$statusColumn = '';
			$column_names = $this->getColumnsNames();
			$statusColumn = $column_names[(int)$task['result']['column_id']] ?? 'undefined';
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
				'status'		=> $statusColumn,
				'assignee_name'	=> $this->getField([
					'method'	=> 'getUser', 
					'paramObj'	=> ['user_id' => (int)$task['result']['owner_id']],
					'additionalParam' => null,
					'fieldName'		=> 'username',
					'defaultVal'	=> ''
				]),
			];
		} else {
			$task = null;
		}
		return $task;
	}
	function getColumnsNames() {
		if (count($this->kanboardColumns) > 0) {
			error_log(__FUNCTION__.', '.$this->projectID);
			return $this->kanboardColumns;
		}
		
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
	function getUserIDByName($user_name) {
		$user_id = 0;
		if (isset($user_name) && $user_name != '') {
			$user_id = (int)$this->getField([
				'method'	=> 'getUserByName', 
				'paramObj'	=> ['username' => $user_name],
				'additionalParam' => null,
				'fieldName'		=> 'id',
				'defaultVal'	=> 0
			]);
		}
		return $user_id;
	}
	function getColumnID($column_name) {
		$column_id = false;
		if (isset($column_name) && $column_name != '') {
			$column_id = array_search($column_name, $this->getColumnsNames());
		}
		return $column_id;
	}
	function setTaskMetadata($task_id, $metadataFields) {
		return $this->callKanboardAPI('saveTaskMetadata', [$task_id, $metadataFields]);
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

function setDateStarted($date_started_ts) {
	$correct_date = (is_numeric($date_started_ts)) ? (int)$date_started_ts : 0;
	if (($correct_date < time() - 3600 * 24 * 30) || ($correct_date > time() + 3600 * 24 * 365))
	{
		$correct_date = 0;
	}
	return $correct_date;
}

function removeVersion($taskTitle) {
	$pattern = '/_v\d+$/i';
	$title = preg_replace($pattern, '', trim($taskTitle));
	return $title;
}
?>