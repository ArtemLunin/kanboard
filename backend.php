<?php
require_once 'config.php';
require_once 'classKanboard.php';

$out_res = [];
$param_error_msg['answer'] = [];

$paramJSON = json_decode(file_get_contents("php://input"), TRUE);
$method = $paramJSON['method'] ?? $_REQUEST['method'] ?? 0;
$params = $paramJSON['params'] ?? $_REQUEST ?? 0;
if ($method !== 0)
{
	try
	{
		$kanboard = new Kanboard;
		$projectID = $kanboard->projectID;
		$userID = $kanboard->userID;
	}
	catch (Exception $e) {
		echo 'Exception: ',  $e->getMessage(), "\n";
	}
	if ($method === 'getAllTasks')
	{
		$taskResult = $kanboard->callKanboardAPI($method, [
						'project_id'	=> $projectID,
						'status_id'		=> 1,
						]);
		if (isset($taskResult['result']) && count($taskResult['result'])) {
			foreach ($taskResult['result'] as $key => $task) {
				if (($task['creator_id'] != $userID) ||
					($task['date_moved'] - $task['date_creation'] > 5) ||
					((int)$task['date_completed'] !== 0)) {
					continue;
				}
				$taskFiles = $kanboard->callKanboardAPI('getAllTaskFiles', [
						'task_id'	=> $task['id'],
						]);
				$param_error_msg['answer'][] = [
					'id'			=> (int)$task['id'],
					'creator_id'	=> (int)$task['creator_id'],
					'date_creation'	=> (int)$task['date_creation'],
					'date_completed'=> (int)$task['date_completed'],
					'description'	=> nl2br($task['description'], FALSE),
					'title'			=> $task['title'],
					'files'			=> array_map($taskFilesMapper, $taskFiles['result']),
				];
			}
		}
		$out_res = ['success' => $param_error_msg];
	}
	elseif($method === 'createTask' && $params !== 0)
	{
		$taskResult = $kanboard->callKanboardAPI($method, [
						'project_id'	=> $projectID,
						'title'			=> trim($params['title']) ?? "",
						'description'	=> (trim($params['description']) ?? "")."\nSubmitted by: ".(trim($params['creator']) ?? ""),
						'date_started'	=> date('Y-m-d H:i'),
						'creator_id'	=> $userID,
						]);
	}
	elseif ($method === 'updateTask' && $params !== 0 && $params['id'] != 0)
	{
		$taskResult = $kanboard->callKanboardAPI($method, [
						'title'			=> trim($params['title']) ?? "",
						'description'	=> (trim($params['description']) ?? "")."\nSubmitted by: ".(trim($params['creator']) ?? ""),
						'id'	=> $params['id'],
						]);
	}
	elseif ($method === 'createTaskFile' && $params !== 0 && $params['id'] != 0)
	{
		$tmp = $_FILES['file']['tmp_name'];
		if (($tmp!='') && is_uploaded_file($tmp)) 
		{   
			$taskResult = $kanboard->callKanboardAPI($method, [
						$projectID,
						$params['id'],
						$_FILES['file']['name'],
						base64_encode(file_get_contents($tmp)),
						]);	
			if (isset($taskResult['result']))
			{
				$taskFiles = $kanboard->callKanboardAPI('getAllTaskFiles', [
						'task_id'	=> $params['id'],
						]);
				$param_error_msg['answer'] = [
					'id'			=> (int)$params['id'],
					'files'			=> array_map($taskFilesMapper, $taskFiles['result']),
				];
				// $param_error_msg['answer'] = [
				// 	'file_id'	=> $taskResult['result'],
				// 	'file_name'	=> $_FILES['file']['name'],
				// 	'file_size'	=> $_FILES['file']['size'],
				// ];
			}
		}
		$out_res = ['success' => $param_error_msg];
	}
	elseif ($method === 'removeTaskFile' && $params !== 0 && $params['id'] != 0)
	{
		$taskResult = $kanboard->callKanboardAPI('getTaskFile', [
						$params['id'],
						]);
		$taskID = $taskResult['result']['task_id'] ?? 0;
		$userTaskID = $taskResult['result']['user_id'] ?? 0;
		if ($taskID != 0)
		{
			$taskResult = $kanboard->callKanboardAPI($method, [
						$params['id'],
						]);
			// $resultDelete = $taskResult['result'] ?? 0;
			// if ($resultDelete == TRUE) {
				$taskFiles = $kanboard->callKanboardAPI('getAllTaskFiles', [
						'task_id'	=> $taskID,
						]);
				$param_error_msg['answer'] = [
					'id'			=> (int)$taskID,
					'files'			=> array_map($taskFilesMapper, $taskFiles['result']),
				];
			// }
		}
		$out_res = ['success' => $param_error_msg];
	}
		
	
}

header('Content-type: application/json');
echo json_encode($out_res);
?>