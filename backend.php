<?php
ini_set('session.use_cookies',1);
ini_set('session.use_only_cookies',0);
ini_set('session.use_trans_sid',1);
session_name('kanboardSession');
session_start();
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
\PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder() );
// use Bookstack\Bookstack;

require_once 'config.php';
require_once 'db_conf.php';
require_once 'classDatabase.php';
require_once 'classKanboard.php';


mb_internal_encoding("UTF-8");

$out_res = [];
$param_error_msg['answer'] = [];

function isInt($val) {
    return filter_var($val, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
}

function getRightsAnswer($user, $rights, $root_access) {
	return 
	[
		'user' => $user,
		'rights' => $rights,
		'cache_setup' => $root_access,
		'docsHref' => 'http://bookstack',
		'doscLDAP' => 0
	];
}

$xls_output = false;
$file_output = false;
$accessType = false;
$txt_output = false;
$filename = tempnam(sys_get_temp_dir(), 'xls');

$noCheckedKanboardMethods = [
	'getRights',
	'signIn',
	'logout',
];

$paramJSON = json_decode(file_get_contents("php://input"), TRUE);
$method = $paramJSON['method'] ?? $_REQUEST['method'] ?? 0;
$splitProjects = $paramJSON['splitProjects'] ?? $_REQUEST['splitProjects'] ?? null;
$params = $paramJSON['params'] ?? $_REQUEST ?? 0;
$env = $paramJSON['env'] ?? $_REQUEST['env'] ?? 'kanboard';
$efcrTable = $paramJSON['efcrTable'] ?? $_REQUEST['efcrTable'] ?? null;

$projectName = trim($params['projectName'] ?? '');
$params['id']  = $params['id'] ?? 0;

$book_id = isInt($paramJSON['book_id'] ?? 0);
$id = isInt($paramJSON['id'] ?? 0);
$action = $paramJSON['action'] ?? '';
$item = $paramJSON['item'] ?? '';
$innerMethod = $paramJSON['innerMethod'] ?? '';
$name = mb_substr($paramJSON['name'] ?? '', 0, 254) ;
$html = $paramJSON['html'] ?? '';

$date_ts = setDateStarted($params['date_started'] ?? 0);

$linksToTask = preg_replace('/jsonrpc\.php/', '?controller=TaskViewController&action=show&', KANBOARD_CITE);

$db_object = new mySQLDatabaseUtils\databaseUtils();

$projectID = 0;
$userID = 0;
$shownedColumnID = 0;

if (!isset($kanboardProjectsID)) {
	error_log('Warning: kanboardProjectsID not set, use default value ('.KANBOARD_PROJECT_IDENTIFIER.')');
	$kanboardProjectsID[0] = KANBOARD_PROJECT_IDENTIFIER;
}

try
{
	if (!in_array($method, $noCheckedKanboardMethods) && $env !== 'services') {
		$kanboard = new Kanboard($db_object);
		$projectID = $kanboard->projectID;
		$userID = $kanboard->userID;
		$shownedColumnID = $kanboard->shownedColumnID;
	}

	$rights = $db_object->initialRights;
	$token = null;
	$currentUser = 'defaultUser';
}
catch (Exception $e) {
	$projectID = false;
	unset($param_error_msg['answer']);
	error_log('Exception: ' . $e->getMessage());
	$param_error_msg['error'] = $e->getMessage();
}

if (isset($_SESSION['logged_user']) && $_SESSION['logged_user']) {
	$currentUser = $_SESSION['logged_user'];
	// list($rights, $token) = $db_object->getRights($_SESSION['logged_user'], 'dummypass', true);
	$rights = $db_object->getRights($_SESSION['logged_user'], 'dummypass', true);
}

$section = $params['section'] ?? $env;
$accessType = $db_object->getAccessType($rights, $section);

// if (count($_POST) >0 && isset($_POST['uploaded_to']) && isset($_FILES['file']) 
// 		&& $accessType !== false && $token) {
// 	$tmp = $_FILES['file']['tmp_name'];
//     $uploaded_to = isInt($_POST['uploaded_to'] ?? 0);
//     if ($uploaded_to && $tmp != '' && is_uploaded_file($tmp)) 
//     {   
//         $file_name = $_FILES['file']['name'];

// 		$book = new Bookstack($token);
//         $response = json_decode($book->postFile($file_name, $uploaded_to, $tmp), true);
//         $param_error_msg['answer'] = $response;
//     }

// 	$out_res = ['success' => $param_error_msg];	

// 	header('Content-type: application/json');
// 	echo json_encode($out_res);

// 	exit;
// }

if ($env === 'documentation' && $accessType !== false && isset($_SESSION['logged_user'])) {
	
	$login = $_SESSION['logged_user'] ?? false;
	$password = $_SESSION['password'] ?? false;
	$out_res = ['success' => [
		'login'	=> $login,
		'password' => $password
	]];	

	header('Content-type: application/json');
	echo json_encode($out_res);

	exit;
}

if ($env === 'services') {
	require_once 'db_conf_mosaic.php';

	$call = $paramJSON['call'] ?? $_REQUEST['call'] ?? null;
	$mode = $paramJSON['mode'] ?? $_REQUEST['mode'] ?? null;
	$param_error_msg['answer'] = false;


	$data_fields = [
		'name'	=> '', 
		'platform'	=> '', 
		'service'	=> '', 
		'owner'	=> '', 
		'contact_info'	=> '', 
		'manager'	=> '', 
		'comments'	=> '',
	];

	$devices_data = [
		'id'		=> '0',
		'locked'	=> '1',
		'platform'	=> '',
		'tags'		=> '',
		'group'		=> '',
		'owner'		=> '',
		'contacts'	=> '',
		'comments'	=> '',
		'oldPlatform' => '',
		'oldOwner'	=> '',
		'oldGroup'	=> '',
	];

	$inventory_data = [
		'id'		=> '0',
		'locked'	=> '1',
		'ca_year'	=> null,
		'comment'	=> '',
		'hw_eol'	=> null,
		'hw_eos'	=> null,
		'serial'	=> '',
		'software'	=> '',
		'sw_eol'	=> null,
		'sw_eos'	=> null,
	];
	
	foreach ($data_fields as $key => $value) {
		if(isset($paramJSON[$key])) {
			$data_fields[$key] = $db_object->removeBadSymbols($paramJSON[$key]);
		}
	}

	foreach ($devices_data as $key => $value) {
		if(isset($paramJSON[$key])) {
			$devices_data[$key] = trim($db_object->removeBadSymbols($paramJSON[$key]));
		}
	}

	foreach ($inventory_data as $key => $value) {
		if(isset($paramJSON[$key])) {
			$date_now = $date = new DateTime();

			if ($key == 'hw_eol' || $key == 'hw_eos' ||$key == 'sw_eol' ||$key == 'sw_eos') {
				if (preg_match('/(\d{4}).{0,1}(\d{2})/', trim($db_object->removeBadSymbols($paramJSON[$key])), $matches)) {
					$date = new DateTime();
					$date->setDate($matches[1],$matches[2],'1');
					if ($date_now <= $date) {
						$inventory_data[$key] = $date->format('Y-m-d');
					}
				}
			} elseif ($key == 'ca_year') {
				if (preg_match('/(\d{4})/', trim($db_object->removeBadSymbols($paramJSON[$key])), $matches)) {
					$date = new DateTime();
					$date->setDate($matches[1],'12','31');
					if ($date_now <= $date) {
						$inventory_data[$key] = $date->format('Y-m-d');
					}
				}
			} else {
				$inventory_data[$key] = trim($db_object->removeBadSymbols($paramJSON[$key]));
			}
		}
	}

	if ($call == 'doGetDevicesAll' && $accessType !== false)
	{
		$start = $paramJSON['start'] ?? $_REQUEST['start'] ?? 0;
		$length = $paramJSON['length'] ?? $_REQUEST['length'] ?? 0;
		$search = $paramJSON['search'] ?? $_REQUEST['search'] ?? false;
		$order = $paramJSON['order'] ?? $_REQUEST['order'] ?? false;
		$columns = $paramJSON['columns'] ?? $_REQUEST['columns'] ?? false;
		$no_virt = $paramJSON['no_virt'] ?? $_REQUEST['no_virt'] ?? false;

		$virt_int = ['ae', 'fxp', 'irb', 'lo0', 'vlan'];

		$search_par = '';
		$column_name = '';
		$sort_dir = '';

		if ($search && strlen(trim($search['value'])) > 1) {
			$search_par = trim($search['value']);
		}

		if ($order && $columns) {
			$column_name = $columns[$order[0]['column']]['name'];
			$sort_dir = $order[0]['dir'];
		}

		$countDevices = $db_object->countDevices([
			'search_par'    => $search_par,
			'virt_int'      => ($no_virt) ? $virt_int : [],
		]);

		$output_data = [
			"draw" => (int)$_REQUEST['draw'],
			"recordsTotal" => $countDevices,
			"recordsFiltered" => $countDevices,
			"data" => $db_object->doGetDevicesFiltered([
				'start'     => $start,
				'length'    => $length, 
				'search_par'    => $search_par,
				'column_name'   => $column_name,
				'sort_dir'  => $sort_dir,
				'count'     => $countDevices,
				'virt_int'  => ($no_virt) ? $virt_int : [],
			]),
		];
		header('Content-type: application/json');
		echo json_encode($output_data);
		exit();
	}
	elseif ($call == 'doAddDevice' && $accessType === 'admin') 
	{
		$param_error_msg['answer'] = $db_object->doAddDevice([
			'name'		=> $data_fields['name'],
			'platform'	=> $data_fields['platform'],
			'service'	=> $data_fields['service'],
			'owner'		=> $data_fields['owner'],
			'contact_info'	=> $data_fields['contact_info'],
			'manager'	=> $data_fields['manager'],
			'comments'	=> $data_fields['comments'],
		]);
	}
	elseif ($call == 'doApplyDeviceSettings' && $accessType === 'admin') 
	{
		$param_error_msg['answer'] = $db_object->doApplyDeviceSettings([
			'name'		=> $data_fields['name'],
			'platform'	=> $data_fields['platform'],
			'service'	=> $data_fields['service'],
			'owner'		=> $data_fields['owner'],
			'contact_info'	=> $data_fields['contact_info'],
			'manager'	=> $data_fields['manager'],
			'comments'	=> $data_fields['comments'],
			'id'		=> $paramJSON['id'] ?? 0,
		]);
	} elseif ($call == 'updateDevicesData' && $accessType === 'admin') {
		$param_error_msg['answer'] = $db_object->updateDevicesData([
			'id'		=> $devices_data['id'],
			'locked'	=> $devices_data['locked'],
			'platform'	=> $devices_data['platform'],
			'oldPlatform' => $devices_data['oldPlatform'],
			'tags'		=> $devices_data['tags'],
			'group'		=> $devices_data['group'],
			'owner'		=> $devices_data['owner'],
			// 'contacts'	=> $devices_data['contacts'],
			'contacts'	=> '',
			'comments'	=> $devices_data['comments'],
		]);
	} elseif ($call == 'doChangeOwner' && $accessType === 'admin') {
		// && $devices_data['owner'] != '' && $devices_data['oldOwner'] != ''
		$param_error_msg['answer'] = $db_object->changeOwner([
			'id'		=> $devices_data['id'],
			'locked'	=> $devices_data['locked'],
			'oldOwner'	=> $devices_data['oldOwner'],
			'owner'		=> $devices_data['owner'],
			'group'		=> $devices_data['group'],
		]);
	} elseif ($call == 'doChangeGroup' && $accessType === 'admin') {
		// && $devices_data['group'] != '' && $devices_data['oldGroup'] != ''
		$param_error_msg['answer'] = $db_object->changeGroup([
			'id'		=> $devices_data['id'],
			'locked'	=> $devices_data['locked'],
			'oldGroup'	=> $devices_data['oldGroup'],
			'group'		=> $devices_data['group'],
			'platform'		=> $devices_data['platform'],
		]);
	} elseif ($call == 'doDeleteDevice' && $accessType === 'admin') {
		$param_error_msg['answer'] = $db_object->doDeleteDevice($paramJSON['id'] ?? 0, $mode);
	} elseif ($call == 'loadData' && $accessType === 'admin') {
		$tmp = $_FILES['file']['tmp_name'];
			if (($tmp != '') && is_uploaded_file($tmp)) 
			{  
				$reader = new Xlsx();
				$reader->setReadDataOnly(true);
				$spreadsheet = $reader->load($tmp);
				$worksheet = $spreadsheet->getActiveSheet();
				$rows = $worksheet->toArray();

				$param_error_msg['answer'] = $db_object->loadData($rows);
			}
	} elseif ($call == 'doGetInventory' && $accessType === 'admin') {

		$start = $paramJSON['start'] ?? $_REQUEST['start'] ?? 0;
		$length = $paramJSON['length'] ?? $_REQUEST['length'] ?? 0;
		$search = $paramJSON['search'] ?? $_REQUEST['search'] ?? false;
		$order = $paramJSON['order'] ?? $_REQUEST['order'] ?? false;
		$columns = $paramJSON['columns'] ?? $_REQUEST['columns'] ?? false;
		$vendor_par = $paramJSON['vendor_filter'] ?? $_REQUEST['vendor_filter'] ?? '';
		$date_par = $paramJSON['date_filter'] ?? $_REQUEST['date_filter'] ?? '';

		$search_par = '';
		$column_name = '';
		$sort_dir = '';

		if ($search && strlen(trim($search['value'])) > 1) {
			$search_par = trim($search['value']);
		}

		if ($order && $columns) {
			$column_name = $columns[$order[0]['column']]['name'];
			$sort_dir = $order[0]['dir'];
		}

		$countDevices = $db_object->countInventory([
			'search_par'    => $search_par,
			'vendor_par'	=> $vendor_par,
			'date_par'		=> $date_par,
		]);

		$get_data = $paramJSON['get_data'] ?? $_REQUEST['get_data'] ?? 0;
		$output_data = [
			"draw" => (int)$_REQUEST['draw'],
			"recordsTotal" => $countDevices,
			"recordsFiltered" => $countDevices,
			"data" => $db_object->doGetInventory([
				'start'     => $start,
				'length'    => $length, 
				'search_par'    => $search_par,
				'vendor_par'	=> $vendor_par,
				'date_par'		=> $date_par,
				'column_name'   => $column_name,
				'sort_dir'  => $sort_dir,
				'count'     => $countDevices,
				'get_data'	=> $get_data,
			]),
		];
		header('Content-type: application/json');
		echo json_encode($output_data);
		exit();
	} elseif ($call == 'loadInventory' && $accessType === 'admin') {
		$tmp = $_FILES['file']['tmp_name'];
		if (($tmp != '') && is_uploaded_file($tmp)) 
		{  
			$reader = new Xlsx();
			$reader->setReadDataOnly(true);
			$spreadsheet = $reader->load($tmp);
			$worksheet = $spreadsheet->getActiveSheet();
			$rows = $worksheet->toArray();

			$param_error_msg['answer'] = $db_object->loadInventory($rows);
		}
	} elseif ($call == 'doDeleteInventory' && $accessType === 'admin') {
		$param_error_msg['answer'] = $db_object->doDeleteInventory($paramJSON['id'] ?? 0, $mode);
	} elseif ($call == 'updateInventoryData' && $accessType === 'admin') {
		$param_error_msg['answer'] = $db_object->updateInventoryData([
			'id'		=> $inventory_data['id'],
			'locked'	=> $inventory_data['locked'],
			'ca_year'	=> $inventory_data['ca_year'],
			'hw_eol'	=> $inventory_data['hw_eol'],
			'hw_eos'	=> $inventory_data['hw_eos'],
			'serial'	=> $inventory_data['serial'],
			'software'	=> $inventory_data['software'],
			'sw_eol'	=> $inventory_data['sw_eol'],
			'sw_eos'	=> $inventory_data['sw_eos'],
		]);
	} elseif ($call == 'doGetComments' && $accessType === 'admin') {
		$param_error_msg['answer'] = $db_object->doGetComments($paramJSON['id'] ?? 0);
	}  elseif ($call == 'doSetComments' && $accessType === 'admin') {
		$param_error_msg['answer'] = $db_object->doSetComments($paramJSON['id'] ?? 0, $inventory_data['comment']);
	} elseif ($call == 'loadEFCR' && $accessType === 'admin') {
		$tmp = $_FILES['file']['tmp_name'];
		if (($tmp != '') && is_uploaded_file($tmp)) 
		{  
			$reader = new Xlsx();
			$reader->setReadDataOnly(true);
			$spreadsheet = $reader->load($tmp);
			$worksheet = $spreadsheet->getActiveSheet();
			$rows = $worksheet->toArray();
			array_shift($rows);

			$param_error_msg['answer'] = $db_object->loadEFCR($rows);
		}
	} elseif ($call === 'exportEFCR' && $accessType !== false) {
		$efcr_out = '';
		if ($efcrTable) {
			$efcrTable_arr = json_decode($efcrTable, true);
			if  ($efcrTable_arr !== null) {
				$efcr_res = $db_object->exportEFCR($efcrTable_arr);
			}
		}
		header("Content-Type: text/plain; charset=utf-8");
		header("Content-Disposition: attachment; filename={$efcr_res['file']}.txt");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private", false);
		echo $efcr_res['efcr_out'];
		exit;
	}
	
	// elseif ($call == 'clearDevicesDataTemp' && $accessType === 'admin') {
	// 	$param_error_msg['answer'] = $db_object->clearDevicesDataTemp();
	// }
	$out_res = ['success' => $param_error_msg];	

	header('Content-type: application/json');
	echo json_encode($out_res);

	exit;
}


if ($projectID !== false && $method !== 0)
{
	if ($method === 'signIn') {
		$kanboardUserName = trim($params['userName'] ?? 'defaultUser');
		$kanboardUserPass = trim($params['password'] ?? '');
		if (strlen($kanboardUserName) && strlen($kanboardUserPass)) {
			// list($rights, $token) = $db_object->getRights($kanboardUserName, $kanboardUserPass);
			$rights = $db_object->getRights($kanboardUserName, $kanboardUserPass);
			if ($rights) {
				$_SESSION['logged_user'] = $kanboardUserName;
				$_SESSION['password'] = $kanboardUserPass;
				$currentUser = $kanboardUserName;
			}
		}
		$param_error_msg['answer'] = getRightsAnswer($kanboardUserName, $rights, $db_object->root_access);
	} elseif ($method === 'logout') {
		$_SESSION = array();
		$param_error_msg['answer'] = [
			'logout' => true,
		];
	} elseif ($method === 'getRights') {
		$param_error_msg['answer'] = getRightsAnswer($currentUser, $rights, $db_object->root_access);
		// $param_error_msg['answer'] = [
		// 	'user'	 => $currentUser,
		// 	'rights' => $rights,
		// 	'docsHref' => DOCUMENTATION_HREF,
		// ];
	} elseif ($method === 'addUser' && $params !== 0 && strlen(trim($params['userName'])) > 2 && strlen($params['password']) > 2) {
		$param_error_msg['answer'] = $db_object->addUser(trim($params['userName']), $params['password']);
	} elseif ($method === 'modUser' && $params !== 0 && strlen(trim($params['userName'])) > 2 && strlen($params['password']) > 2) {
		$param_error_msg['answer'] = $db_object->modUser(trim($params['userName']), $params['password']);
	} elseif ($method === 'delUser' && $params !== 0 && strlen(trim($params['userName'])) > 2) {
		$param_error_msg['answer'] = $db_object->delUser(trim($params['userName']));
	} elseif ($method === 'setRights' && $params !== 0 && strlen(trim($params['userName'])) > 2 && $params['rights']) {
		if (isset($params['rights'][0]['pageName'])) {
			$param_error_msg['answer'] = $db_object->setRights(trim($params['userName']), $params['rights']);
		}
	} elseif ($method === 'getKanboardUsers') {
		$param_error_msg['answer'] = $db_object->getKanboardUsers();
	} elseif ($method === 'installCacheTable') {
		$param_error_msg['answer'] = $db_object->installCacheTable();
	}
	elseif ($projectID) 
	{
		if ($method === 'getAllTasks')
		{
			$taskResult = $kanboard->callKanboardAPI($method, [
							'project_id'	=> $projectID,
							'status_id'		=> 1,
							]);
			if (isset($taskResult['result']) && count($taskResult['result'])) {
				foreach ($taskResult['result'] as $key => $task) {
					if (/* ($task['creator_id'] != $userID) || */
						($shownedColumnID != $task['column_id']) || 
						((int)$task['date_completed'] !== 0)) {
						continue;
					}
					$projectName = $kanboard->getTaskProjectName($task['id']);
					$taskMetadata = $kanboard->callKanboardAPI('getTaskMetadata', [$task['id']]);
					$task_version = $taskMetadata['result']['version'] ?? false;
					$task_origin_id = $taskMetadata['result']['origintask'] ?? 0;
					if ($task_origin_id == 0 || ($task_origin_id === $task['id'])){
						$task_version = false;
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
						'title'			=> $task['title']. (($task_version != false) ? '_v'.$task_version : ''),
						'project_name'	=> $projectName,
						'url'			=> $task['url'],
						'files'			=> array_map("taskFilesMapper", $taskFiles['result'] ?? []),
					];
				}
			}
		}
		elseif ($method === 'createTask' && $params !== 0 && (trim($params['title'] ?? "") != ''))
		{
			$taskCreator = trim($params['creator'] ?? "");
			if (isset($params['groupName']) && in_array($params['groupName'], $kanboardProjectsID)) {
				$projectResult = $kanboard->callKanboardAPI('getProjectByIdentifier', 
						['identifier' => $params['groupName']]
					);
				if (isset($projectResult['result']) && count($projectResult['result'])) {
					$projectID = $projectResult['result']['id'];
					$kanboard->setProjectID($projectID);
				}
			}
			// имя создателя приходит от фронта
			// if ($currentUser !== 'defaultUser' || isset($params['section']) && $section === 'status') {
			// 	$taskCreator = $currentUser;
			// }
			if ($accessType !== false)
			{
				$kanboardUserID = $kanboard->getUserIDByName(trim($params['assignee_name'] ?? ""));
				$columnID = $kanboard->getColumnID($params['status'] ?? "");

				if (isset($params['version'])) {
					$task_version = (int)$kanboard->getMetadataField($params['id'], 'version');
					if ($task_version === 0) $task_version = 1;
					$task_version++;
					$task_origin = $kanboard->fieldsTask($params['id'], true, false);
					$task_origin_title = $task_origin['title'] ?? null;
					$kanboard->setTaskMetadata($params['id'], 
						[
							"version"	=> $task_version,
						]);
				} else {
					$task_version = false;
				}
				$task_title = ($task_version !== false && $task_origin_title !== null) ? $task_origin_title : (trim($params['title'] ?? ""));

				$arr_params = [
					'title'			=> $task_title,
					'project_id'	=> $projectID,
					'reference'		=> trim($params['reference'] ?? ""),
					'description'	=> (trim($params['description'] ?? ""))."\nSubmitted by: ".$taskCreator."\nOTL: ".(trim($params['OTL'] ?? "")),
					'creator_id'	=> $userID,
				];
				if ($kanboardUserID != 0) {
					$arr_params['owner_id'] = $kanboardUserID;
				}
				if ($columnID !== false) {
					$arr_params['column_id'] = $columnID;
				}
				if ($date_ts) {
					$arr_params['date_started']	= date('Y-m-d H:i', $date_ts);
				}
				
				$taskResult = $kanboard->callKanboardAPI($method, $arr_params);
				if (isset($taskResult['result']))
				{
					// if (isset($params['projectName']) && trim($params['projectName']) !== '') {
						$kanboard->setTaskProjectName((int)$taskResult['result'], $projectName);
					// }
					$kanboard->setTaskMetadata((int)$taskResult['result'], 
						[
							"otl"		=> trim($params['OTL'] ?? ""),
							// "oracle"	=> trim($params['oracle'] ?? ""),
							"capop"		=> trim($params['capop'] ?? ""),
							"creator"	=> $taskCreator,
							"version"	=> ($task_version !== false) ? $task_version : 1,
							"origintask"	=> ($task_version !== false) ? $params['id'] : (int)$taskResult['result'],
							"master_date" => $date_ts,
						]);
					$task_out = $kanboard->fieldsTask($taskResult['result'], true, $task_version);
					$taskMetadata = $kanboard->callKanboardAPI('getTaskMetadata', [$taskResult['result']]);
					$projectName = $kanboard->getTaskProjectName($taskResult['result']);
					$param_error_msg['answer'] = $task_out + 
						['fields'	=> $kanboard->getMetadataFields($taskMetadata['result'])] + 
						['project_name'	=> $projectName];
				}
			}
		}
		elseif ($method === 'removeTask' && $accessType === 'admin' && $section === 'excel' && $params !== 0 && $params['id'] != 0)
		{
			$taskResult = $kanboard->callKanboardAPI($method, [
				'task_id'	=> $params['id'],
			]);
			if (isset($taskResult['result']))
			{
				$kanboard->dbObject->delKBTaskProps($params['id']);
				$param_error_msg['answer'] = [
					'id'	=> (int)$params['id'],
				];
			}
		}
		elseif ($method === 'updateTask' && $accessType !== false && $params !== 0 && $params['id'] != 0)
		{
			$title = removeVersion($params['title'] ?? "");
			$taskResult = $kanboard->callKanboardAPI($method, [
							'title'			=> $title,
							'description'	=> (trim($params['description'] ?? ""))."\nSubmitted by: ".(trim($params['creator'] ?? ""))."\nOTL: ".(trim($params['OTL'] ?? "")),
							'id'	=> $params['id'],
							]);
			if (isset($taskResult['result']))
			{
				// if (isset($params['projectName']) && trim($params['projectName']) !== '') {
					$kanboard->setTaskProjectName((int)$params['id'], $projectName);
				// }
				$kanboard->setTaskMetadata((int)$params['id'], 
				[
					"otl"		=> (trim($params['OTL'] ?? "")),
					"creator"	=> (trim($params['creator'] ?? "")),
				]);
				
				$task_out = $kanboard->fieldsTask($params['id'], true);
				$taskMetadata = $kanboard->callKanboardAPI('getTaskMetadata', [$taskResult['result']]);
				$projectName = $kanboard->getTaskProjectName($params['id']);
				$param_error_msg['answer'] = $task_out + ['fields'		=> $kanboard->getMetadataFields($taskMetadata['result'])] + ['project_name'	=> $projectName];
			}
		}
		elseif ($method === 'updateTaskFull' && $accessType === 'admin' && $section === 'excel' && $params !== 0 && $params['id'] != 0)
		{
			$taskCreator = trim($params['creator'] ?? "");
			$kanboardUserID = $kanboard->getUserIDByName(trim($params['assignee_name'] ?? ""));
			$columnID = $kanboard->getColumnID($params['status'] ?? "");
			$title = removeVersion($params['title'] ?? "");
			$arr_params = [
				'id'	=> $params['id'],
				'title'	=> $title,
				'reference'		=> trim($params['reference'] ?? ""),
				'description'	=> (trim($params['description'] ?? ""))."\nSubmitted by: ".$taskCreator."\nOTL: ".(trim($params['OTL'] ?? "")),
			];
			if ($date_ts) {
				$arr_params['date_started']	= date('Y-m-d H:i', $date_ts);
			}
			if ($kanboardUserID != 0) {
				$arr_params['owner_id'] = $kanboardUserID;
			}
			$taskResult = $kanboard->callKanboardAPI('updateTask', $arr_params);
			if (isset($taskResult['result']) && $taskResult['result']) {
				// if (isset($params['projectName']) && trim($params['projectName']) !== '') {
					$kanboard->setTaskProjectName((int)$params['id'], $projectName);
				// }
				if ($columnID !== false) {
					$taskResult = $kanboard->callKanboardAPI('moveTaskPosition', [
						'project_id'	=> $projectID,
						'task_id'		=> $params['id'],
						'column_id'		=> $columnID,
						'position'		=> 10000,
						'swimlane_id' 	=> 1
					]);
				}	
				$taskResult = $kanboard->setTaskMetadata($params['id'], 
					[
						"otl"		=> trim($params['OTL'] ?? ""),
						"capop"		=> trim($params['capop'] ?? ""),
						"creator"	=> $taskCreator,
						"master_date" => $date_ts,
					]);
				if (isset($taskResult['result']))
				{
					$task_out = $kanboard->fieldsTask($params['id'], false);
					$taskMetadata = $kanboard->callKanboardAPI('getTaskMetadata', [$params['id']]);
					$projectName = $kanboard->getTaskProjectName($params['id']);
					$param_error_msg['answer'] = $task_out + ['fields'		=> $kanboard->getMetadataFields($taskMetadata['result'])]+ ['project_name'	=> $projectName];
				}
			}
		} elseif ($method === 'updateCreator' && $accessType === 'admin' && $params !== 0 && $params['id'] != 0 && trim($params['creator']) != '') {
			$taskResult = $kanboard->setTaskMetadata((int)$params['id'], 
				[
					"creator"	=> (trim($params['creator'] ?? "")),
				]);
			if (isset($taskResult['result']) && $taskResult['result'] === true)
			{
				$param_error_msg['answer'] = [
					'id' => $params['id'],
					'creator' => $params['creator']
				];
			}

		}
		elseif ($method === 'getAllTaskFiles' && $params !== 0 && $params['id'] != 0)
		{
			$param_error_msg['answer'] = $kanboard->getAllTaskFiles(trim($params['id']));
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
					$param_error_msg['answer'] = $kanboard->getAllTaskFiles(trim($params['id']));
				}
			}
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
				$param_error_msg['answer'] = $kanboard->getAllTaskFiles($taskID);
			}
		}
		elseif ($method === 'downloadTaskFile' && $params['id'] !== 0) {
			$originalFileName = $kanboard->getField([
				'method'	=> 'getTaskFile', 
				'paramObj'	=> [$params['id']],
				'additionalParam' => null,
				'fieldName'		=> 'name',
				'defaultVal'	=> null
			]);
			if ($originalFileName) {
				$taskResult = $kanboard->callKanboardAPI($method, [
				$params['id'],
			]);
				$file64 = $taskResult['result'] ?? 0;
				if ($file64) {
					$filename = tempnam(sys_get_temp_dir(), 'xls');
					$handle = fopen($filename, "w");
					fwrite($handle, base64_decode($file64));
					fclose($handle);
					$file_output = true;
				}
			}
		}
		elseif ($method === 'getBoard')
		{
			if (($section === 'excel' || $section === 'status' || $section === 'statistics') && 
				($accessType === 'user' || $accessType === 'admin')) {
				$arrProjects = [];
				foreach($kanboardProjectsID as $projectIdentifier) {
					if ($section !== 'status' && $projectIdentifier !== KANBOARD_PROJECT_IDENTIFIER) {
						continue;
					}
					$projectResult = $kanboard->callKanboardAPI('getProjectByIdentifier', 
						['identifier' => $projectIdentifier]
					);
					if (isset($projectResult['result']) && count($projectResult['result'])) {
						$arrProjects[$projectResult['result']['identifier']] = $projectResult['result']['name'];
						$taskResult = $kanboard->callKanboardAPI('getBoard', [
							$projectResult['result']['id'],
						]);
						if (isset($taskResult['result']) && count($taskResult['result'])) {
							$assignee_name = '';
							$all_column = false;
							if ($params !== 0 && $params['status'] == 'all') {
								$all_column = true;
							}
							foreach($taskResult['result'][0]['columns'] as $key => $column) {
								if ($shownedColumnID != $column['id'] && !$all_column) continue;
								foreach ($column['tasks'] as $key => $task) {
									if ($task['is_active'] == 1 /* && ($section !== 'status' || $task['creator_id'] == $userID) */) {
										$taskProps = $kanboard->getTaskProps($task['id'], $db_object);

										// $taskMetadata = $kanboard->callKanboardAPI('getTaskMetadata', [$task['id']]);
										$taskMetadata = $taskProps['metadata'];

										if ($accessType === 'user' && ($taskMetadata['result']['creator'] ?? '') !== $currentUser)
										{
											continue;
										}
										$task_version = $taskMetadata['result']['version'] ?? false;
										$task_origin_id = $taskMetadata['result']['origintask'] ?? 0;
										$projectName = $taskProps['project'];
										if ($assignee_name === '') {
											$assignee_name = $task['assignee_username'] ?? 'not assigned';
										}
										if ($task_origin_id == 0 || ($task_origin_id === $task['id'])){
											$task_version = false;
										}
										$param_error_msg['answer'][] = [
											'id'			=> (int)$task['id'],
											'date_due'		=> (int)$task['date_due'],
											'date_creation'	=> (int)$task['date_creation'],
											'date_started'	=> (int)$task['date_started'],
											'title'			=> $task['title']. (($task_version != false) ? '_v'.$task_version : ''),
											'status'		=> $column['title'],
											'reference'		=> $task['reference'],
											'description'	=> $task['description'],
											'project_name'	=> $projectName,
											'url'			=> $linksToTask . 'task_id=' . $task['id'] . '&project_id='.$projectResult['result']['id'],
											'assignee_name'	=> $assignee_name,
											'fields'		=> $kanboard->getMetadataFields($taskMetadata['result'] ?? []),
											'editable'		=> ($accessType === 'user') ? 0 : 1,
											'kanboard_project_name' => $projectResult['result']['name'],
											'kanboard_project_id' => $projectResult['result']['identifier']
										];
										$assignee_name = '';
									}
								}
							}
						}
					}
				}
				$param_error_msg['answer'][] = $arrProjects;
			}
		}
		elseif( ($method === 'getTagsByProject' || $method === 'getAssignableUsers'))
		{
			if ($method === 'getTagsByProject') {
				$field_name = 'name';
			} else {
				$field_name = 'user';
			}
			if ($method === 'getTagsByProject' && $splitProjects && $splitProjects == '1') {
				foreach($kanboardProjectsID as $projectIdentifier) {
					$projectResult = $kanboard->callKanboardAPI('getProjectByIdentifier', 
						['identifier' => $projectIdentifier]
					);
					if (isset($projectResult['result']) && count($projectResult['result'])) {
						$result = $kanboard->callKanboardAPI($method, [$projectResult['result']['id']]);
						if (isset($result['result']) && count($result['result'])) {
							foreach($result['result'] as $result_object) {
								$param_error_msg['answer'][] = [
									$projectResult['result']['identifier'] => $result_object[$field_name]
								];
							}
						}
					}
				}
			} else {
				$result = $kanboard->callKanboardAPI($method, [$projectID]);

				if (isset($result['result']) && count($result['result'])) {
					foreach($result['result'] as $result_object) {
						if ($field_name == 'user') {
							$param_error_msg['answer'][] = $result_object;
						} else {
							$param_error_msg['answer'][] = $result_object[$field_name];
						}
					}
				}
			}
		}
		elseif ($method === 'getColumns')
		{
			foreach(array_values($kanboard->getColumnsNames()) as $result_object) {
				$param_error_msg['answer'][] = $result_object;
			}
		}
		elseif ($method === 'doDataExport' && $params !== 0 && ($section === 'excel' || $section === 'statistics') && $accessType !== false) 
		{
			$all_column = false;
			if (isset($params['status']) && $params['status'] === 'all') {
				$all_column = true;
			}

			$spreadsheet = new Spreadsheet();
			$sheet = $spreadsheet->getActiveSheet();
			$spreadsheet->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
			$spreadsheet->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
			$spreadsheet->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);
			$spreadsheet->getActiveSheet()->getColumnDimension('D')->setAutoSize(true);
			$spreadsheet->getActiveSheet()->getColumnDimension('E')->setAutoSize(true);
			$spreadsheet->getActiveSheet()->getColumnDimension('F')->setAutoSize(true);
			$spreadsheet->getActiveSheet()->getColumnDimension('G')->setAutoSize(true);
			$spreadsheet->getActiveSheet()->getColumnDimension('H')->setAutoSize(true);

			$taskResult = $kanboard->callKanboardAPI('getBoard', [
						$projectID,
					]);

			if ($section === 'statistics') {
				try
				{
					if (isset($taskResult['result']) && count($taskResult['result'])) {
						$sheet->fromArray([
								'Project Name',
								'Date created',
								'OTL',
								'Tilte',
								'Ticket creator',
							], 
							NULL, 
							'A1');
						$rowExcel = 2;
						foreach ($taskResult['result'][0]['columns'] as $key => $column) {
							if ($shownedColumnID !== $column['id'] && !$all_column) continue;
							foreach ($column['tasks'] as $key => $task) {
								if ($task['is_active'] != 1 /*  || ($task['creator_id'] != $userID) */) continue;
								$taskMetadata = $kanboard->callKanboardAPI('getTaskMetadata', [$task['id']]);
								$fieldsMetadata = $kanboard->getMetadataFields($taskMetadata['result']);
								$sheet->fromArray([
									$kanboard->getTaskProjectName($task['id']),
									$task['date_creation'] > 0 ? date("Y-m-d", $task['date_creation']) : '',
									$fieldsMetadata['otl'],
									$task['title'],
									$fieldsMetadata['creator'],
								], 
								NULL, 
								'A'.$rowExcel);
								$rowExcel++;
							}
						}
					}
					$writer = new Xls($spreadsheet);
					$writer->save($filename);
					$xls_output = TRUE;
				}
				catch (Exception $e) 
				{
					error_log($e->getMessage());
				}
			} elseif ($section === 'excel') {
				$days = 1;
				if(isset($params['days'])) {
					$days = $params['days'];
				}
				$dayObj = new DateTime();
				$today = new DateTime();
				$prevday = new DateTime();
				$prevday->setTime(0,0,0);
				$today->setTime(23,59,59);
				if ($days == '7' || $days == '14') {
					$dayOfWeek = $prevday->format('w');
					$prevday->sub(new DateInterval('P'.$dayOfWeek.'D'));
					$dayStart = $prevday->getTimestamp();
					$today->add(new DateInterval('P'.($days - $dayOfWeek - 1).'D'));
					$dayEnd = $today->getTimestamp();
				} elseif ($days == '31' || $days == '62') {
					$prevday->setDate((int)$prevday->format('Y'), (int)$prevday->format('n'), 1);
					$dayStart = $prevday->getTimestamp();
					$today->setDate((int)$prevday->format('Y'), (int)$prevday->format('n') + ($days / 31), 0);
					$dayEnd = $today->getTimestamp();
				} elseif ($days == '365') {
					$prevday->setDate((int)$prevday->format('Y'), 1, 1);
					$dayStart = $prevday->getTimestamp();
					$today->setDate((int)$prevday->format('Y'), 12, 31);
					$dayEnd = $today->getTimestamp();
				} else {
					$dayObj->setTime(23,59,59);
					$dayEnd = $dayObj->getTimestamp();
					if ($days >=0) {
						$dayObj->sub(new DateInterval('P'.$days.'D'));
					} else {
						$dayEnd -= $days * 24 * 3600;
						$dayObj->add(new DateInterval('P'.abs($days).'D'));
					}
					$dayObj->setTime(0,0,0);
					$dayStart = $dayObj->getTimestamp();
				}
				try
				{
					if (isset($taskResult['result']) && count($taskResult['result'])) {
						$sheet->fromArray([
								'Ticket',
								'Date started',
								'Assigned',
								'Title',
								'Reference',
								'Capex/Opex',
								'Oracle',
								'Status',
							], 
							NULL, 
							'A1');
						$rowExcel = 2;
						foreach ($taskResult['result'][0]['columns'] as $key => $column) {
							if ($shownedColumnID != $column['id'] && !$all_column) continue;
							foreach ($column['tasks'] as $key => $task) {
								$task_started = (int)$task['date_started'];
								if($task['is_active'] == 1 /* && ($task['creator_id'] == $userID) */ && ($task_started > $dayStart && $task_started < $dayEnd || $task_started == 0)) {
									$taskMetadata = $kanboard->callKanboardAPI('getTaskMetadata', [$task['id']]);
									// if ($accessType === 'user' && ($taskMetadata['result']['creator'] ?? '') !== $currentUser)
									// {
									// 	continue;
									// }
									$fieldsMetadata = $kanboard->getMetadataFields($taskMetadata['result']);
									$sheet->fromArray([
										$task['id'],
										$task['date_started'] > 0 ? date("Y-m-d", $task['date_started']) : '',
										$task['assignee_username'] ?? 'not assigned',
										$task['title'],
										$task['reference'],
										$fieldsMetadata['capop'],
										$fieldsMetadata['oracle'],
										$column['title'],
									], 
									NULL, 
									'A'.$rowExcel);
									$rowExcel++;
								}
							}
						}
					}
					$writer = new Xls($spreadsheet);
					$writer->save($filename);
					$xls_output = TRUE;
				}
				catch (Exception $e) 
				{}
			}

		}
	}
	
}
if ($projectID !== false) {
	$out_res = ['success' => $param_error_msg];	
} else {
	$out_res = ['error' => $param_error_msg];	
}
if ($xls_output !== false) {
	header("Content-Type: application/vnd.ms-excel; charset=utf-8");
	header("Content-Disposition: attachment; filename=export.xls");
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Cache-Control: private", false);
	$handle = fopen($filename, "r");
	$contents = fread($handle, filesize($filename));
	echo $contents;
} elseif ($file_output !== false) {
	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename=".$originalFileName);
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Cache-Control: private", false);
	$handle = fopen($filename, "r");
	$contents = fread($handle, filesize($filename));
	echo $contents;
} else {
	header('Content-type: application/json');
	echo json_encode($out_res);
}
?>