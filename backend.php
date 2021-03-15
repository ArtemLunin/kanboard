<?php
require_once 'config.php';
require_once 'classKanboard.php';

$out_res = [];
$param_error_msg['answer'] = [];

$paramJSON = json_decode(file_get_contents("php://input"), TRUE);
$method = $paramJSON['method'] ?? 0;
$params = $paramJSON['params'] ?? 0;
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
				$param_error_msg['answer'][] = [
					'id'			=> (int)$task['id'],
					'creator_id'	=> (int)$task['creator_id'],
					'date_creation'	=> (int)$task['date_creation'],
					'date_completed'=> (int)$task['date_completed'],
					'description'	=> nl2br($task['description'], FALSE),
					'title'			=> $task['title'],
				];
			}
		}
		$out_res=['success' => $param_error_msg];
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
}



header('Content-type: application/json');
echo json_encode($out_res);
?>