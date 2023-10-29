<?php
namespace mySQLDatabaseUtils;

class databaseUtils {
	private $unauthorized = true;
	private $root_access = false;
	private $pdo = null;
	private $tableAccessError = false;
	private $initialRights = [[
		'pageName' => 'Main',
		'sectionName' => 'main',
		'sectionAttr'	=> 'main',
		'accessType'	=> 'admin',
	],
	];
	private $superRights = [
	[
		'pageName' => 'Automator',
		'sectionName' => 'automator',
		'sectionAttr'	=> 'automator',
		'accessType'	=> 'admin',
	],
	[
		'pageName' => 'Services',
		'sectionName' => 'services',
		'sectionAttr'	=> 'services',
		'accessType'	=> 'admin',
	],
	[
		'pageName' => 'Documentation',
		'sectionName' => 'documentation',
		'sectionAttr'	=> 'documentation',
		'accessType'	=> 'admin',
	],
	[
		'pageName' => 'Template',
		'sectionName' => 'template',
		'sectionAttr'	=> 'template',
		'accessType'	=> 'admin',
	],
	[
		'pageName' => 'MOP',
		'sectionName' => 'mop',
		'sectionAttr'	=> 'mop',
		'accessType'	=> 'admin',
	],
	[
		'pageName' => 'Template DIP',
		'sectionName' => 'templateDIP',
		'sectionAttr'	=> 'templateDIP',
		'accessType'	=> 'admin',
	],
	[
		'pageName' => 'DIP',
		'sectionName' => 'dip',
		'sectionAttr'	=> 'dip',
		'accessType'	=> 'admin',
	],
	[
		'pageName' => 'Settings',
		'sectionName' => 'settings',
		'sectionAttr'	=> 'settings',
		'accessType'	=> 'admin',
	]];
	function __construct () {
		try
		{
			$this->pdo = new \PDO(
				'mysql:host='.HOST.';dbname='.BASE.';charset=UTF8MB4',
				USER, 
				PASSWORD, 
				[\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
			);
		}
		catch (\PDOException $e){
			error_log("err connect to database:".$e->getMessage());
			return FALSE;
		}
	}
	function __get($name) {
		return $this->$name;
	}
	function modSQL($sql_query, $params_arr, $needCount = true) {
		try {
			$row = $this->pdo->prepare($sql_query);
			$row->execute($params_arr);
			if (!$needCount || ($row->rowCount())) {
				return true;
			}
		} catch (\PDOException $e){
			$this->setSQLError($e, 'SQL error. "'.$sql_query);
		}
		return false;
	}
	function modSQLInsUpd($sqlInsUpd, $params_arr, $needCount = true) {
		if (!$this->tableAccessError) {
			try {
				$row = $this->pdo->prepare($sqlInsUpd['ins']);
				$row->execute($params_arr);
				if (!$needCount || ($row->rowCount())) {
					return true;
				}
			} catch (\PDOException $e){
				if (preg_match('/Duplicate entry/i', $e->getMessage()) == 1) {
					$row = $this->pdo->prepare($sqlInsUpd['upd']);
					$row->execute($params_arr);
					if (!$needCount || ($row->rowCount())) {
						return true;
					}
				}
				else {
					$this->tableAccessError = true;
					$this->setSQLError($e, 'SQL error. "'.$sqlInsUpd['ins']);
				}
			}
		}
		return false;
	}
	function getKanboardUsers() {
		if ($this->root_access === true) {
			$users = [];
			$sql = "SELECT id, user_name, user_rights FROM users ORDER BY ID";
			$row = $this->pdo->prepare($sql);
			$row->execute();
			if ($table_res = $row->fetchall())
			{
				foreach ($table_res as $result)
				{
					if ($result['user_name'] === SUPER_USER) continue;
					$users[] = [
						'user'	=> $result['user_name'],
						'rights'	=> json_decode($result['user_rights'], true),
					];
				}
			}
			return $users;
		}
		return false;
	}
	function addUser($user, $password) {
		if ($this->root_access === true) {
			$sql = "INSERT INTO users (user_name, password, user_rights) VALUES (:user, :password, :rights)";
			if ($this->modSQL($sql, [
				'user'		=> $user,
				'password'	=> password_hash($password, PASSWORD_DEFAULT),
				'rights'	=> json_encode($this->initialRights),
			], true)) {	
				return [
					'user'	=> $user,
					'rights'	=> $this->initialRights,
				];
			}
		}
		return false;
	}
	function modUser($user, $password) {
		if ($this->root_access === true) {
			$sql = "UPDATE users set password=:password WHERE user_name=:user";
			if ($this->modSQL($sql, [
				'user'		=> $user,
				'password'	=> password_hash($password, PASSWORD_DEFAULT),
			], true)) {	
				return [
					'user'	=> $user,
				];
			}
		}
		return false;
	}
	function delUser($user) {
		if ($this->root_access === true) {
			$sql = "DELETE FROM users WHERE user_name=:user";
			if ($this->modSQL($sql, [
				'user'		=> $user,
			], true)) {	
				return true;
			}
		}
		return false;
	}
	function getRights($user, $password, $storedSession = false) {
		$rights = [];
		$token = null;
		$sql = "SELECT id, user_name, password, user_rights FROM users WHERE user_name=:user";
		$row = $this->pdo->prepare($sql);
		$row->execute(['user' => $user]);
		$result = $row->fetch();
		if (isset($result['id']) && 
			(password_verify($password, $result['password']) || $storedSession === true))
		{
			$rights = json_decode($result['user_rights'], true);
			if ($rights) {
				// $rights = array_filter($rights, array($this, 'hideNoAccessRights'));
				array_walk($rights, function (&$one_right) {
					if ($one_right['pageName'] == 'Status') {
						$one_right['pageName'] = 'Request';
					}
				});
			}
			$this->unauthorized = false;
			if ($result['user_name'] === SUPER_USER) {
				$this->root_access = true;
				$rights = array_merge($rights, $this->superRights);
				$uniqRights = [];
				foreach ($rights as $key => $value) {
					if (array_search($value['pageName'], $uniqRights) === false) {
						$uniqRights[] = $value['pageName'];
					} else {
						unset($rights[$key]);
					}
				}
			}
		} else {
			$rights = false;
		}
		return $rights;
	}
	function getAccessType($rights, $section) {
		foreach ($rights as $item) {
			if (isset($item['sectionName']) && $item['sectionName'] === $section) {
				return $item['accessType'] ?? false;
			}
		}
		return false;
	}
	function setRights($user, $rights) {
		if ($this->root_access === true) {
			$token_str = '';
			// if (strlen(trim($tokenObj['token_id'])) > 30 && strlen(trim($tokenObj['token_secret'])) > 30) {
			// 	$token_str = 'Token '.trim($tokenObj['token_id']). ':'. trim($tokenObj['token_secret']);
			// }
			$new_rights = array_merge($this->initialRights, $rights);
			// $sql = "UPDATE users SET user_rights=:rights, token=:token_str WHERE user_name=:user";
			$sql = "UPDATE users SET user_rights=:rights WHERE user_name=:user";
			if ($this->modSQL($sql, [
				'rights'	=> json_encode($new_rights),
				'user'		=> $user,
			], true)) {	
				return $new_rights;
			}
		}
		return false;
	}
	function getKBTaskProps($task_id) {
		$taskProps = [
			'metadata'	=> [],
			'project'	=> null
		];
		if (!$this->tableAccessError) {
			try {
				$sql_query = "SELECT task_id, task_tag, task_meta FROM kanboard_cache WHERE task_id=:task_id";
				$row = $this->pdo->prepare($sql_query);
				$row->execute(['task_id' => $task_id]);
				$result = $row->fetch();
				if (isset($result['task_id'])) {
					$taskProps['project'] = $result['task_tag'];
					$taskProps['metadata'] = json_decode($result['task_meta'], TRUE);
				} 
			} catch (\PDOException $e){
				$this->tableAccessError = true;
				$this->setSQLError($e, 'SQL error. "'.$sql_query);
			}
		}
		return $taskProps;
	}
	function setKBTaskProps($task_id, $taskProps) {
		$sqlIns = "INSERT INTO kanboard_cache (task_id, task_tag, task_meta) VALUES (:task_id, :task_tag, :task_meta)";
		$sqlUpd = "UPDATE kanboard_cache SET task_tag=:task_tag, task_meta=:task_meta WHERE task_id=:task_id";
		$this->modSQLInsUpd(['ins' => $sqlIns, 'upd' => $sqlUpd], [
			'task_id'	=> $task_id,
			'task_tag'	=> $taskProps['project'],
			'task_meta'	=> json_encode($taskProps['metadata'] ?? []),
		], false);
	}
	function delKBTaskProps($task_id) {
		$sql = "DELETE FROM kanboard_cache WHERE task_id=:task_id";
		$this->modSQL($sql, [
			'task_id'	=> $task_id,
		], false);
	}
	function getKBProjectName($task_id) {
		$projectName = null;
		$sql = "SELECT task_tag FROM kanboard_cache WHERE task_id=:task_id";
		$row = $this->pdo->prepare($sql);
		$row->execute(['task_id' => $task_id]);
		$result = $row->fetch();
		if (isset($result['task_tag'])) {
			$projectName = $result['task_tag'];
		}
		return $projectName;
	}
	function setKBProjectName($task_id, $projectName) {
		$sql = "INSERT INTO kanboard_cache (task_id, task_tag) VALUES (:task_id, :task_tag)";
		$this->modSQL($sql, [
				'task_id'	=> $task_id,
				'task_tag'	=> $projectName,
			], false);
	}

	function hideNoAccessRights($user_rights) {
		return $user_rights['accessType'] != '';
	}

	function doGetDevicesAll($in_exp = FALSE)
	{
		$device_list = [];
		$sql = "SELECT id, name, platform, service, owner, contact_info, manager, comments FROM devices";
		if ($in_exp !== false) {
			$sql .= " WHERE id IN ({$in_exp})";
		}
		$row = $this->pdo->prepare($sql);
		$row->execute();
		if($table_res = $row->fetchall())
		{
			foreach ($table_res as $row_res)
			{
				$device_list[] = [
					'id'			=> (int)$row_res['id'],
					'name'			=> $this->removeBadSymbols($row_res['name']),
					'platform'		=> $this->removeBadSymbols($row_res['platform']),
					'service'		=> $this->removeBadSymbols($row_res['service']),
					'owner'			=> $this->removeBadSymbols($row_res['owner']),
					'contact_info'	=> $this->removeBadSymbols($row_res['contact_info']),
					'manager'		=> $this->removeBadSymbols($row_res['manager']),
					'comments'		=> $this->removeBadSymbols($row_res['comments']),
				];
			}
		}
		return $device_list;
	}
	function doAddDevice($deviceParam)
	{
		$sql = "INSERT INTO devices (name, platform, service,  owner, contact_info, manager, comments) VALUES (:name, :platform, :service, :owner, :contact_info, :manager, :comments)";
		if ($this->modSQL($sql, $deviceParam, true)) {	
			return true;
		}
		return false;
	}
	function doApplyDeviceSettings($deviceParam)
	{
		$sql = "UPDATE devices SET name=:name, platform=:platform, service=:service, owner=:owner, contact_info=:contact_info, manager=:manager, comments=:comments WHERE id=:id";
		if ($this->modSQL($sql, $deviceParam, true)) {	
			return true;
		}
		return false;
	}
	
	function doDeleteDevice($id)
	{
		$sql = "DELETE FROM devices WHERE id=:id";
		if ($this->modSQL($sql, [
			'id' => $id
		], true)) {	
			return true;
		}
		return false;
	}

	function installCacheTable() {
		$sqls = [
			"DROP TABLE IF EXISTS `kanboard_cache`",
			"CREATE TABLE IF NOT EXISTS `kanboard_cache` (`id` bigint unsigned NOT NULL AUTO_INCREMENT,`task_id` bigint unsigned NOT NULL DEFAULT '0',`task_tag` varchar(250) NOT NULL DEFAULT '',`task_meta` text CHARACTER SET utf8mb4 NOT NULL,PRIMARY KEY (`id`),UNIQUE KEY `task_id_idx` (`task_id`)) DEFAULT CHARSET=utf8mb4"
			];

		$common_result = null;
		if ($this->root_access === true) {
			foreach ($sqls as $sql) {
				if (!isset($common_result) || $common_result) {
					$common_result = $this->modSQL($sql, [], false);
				} else {
					break;
				}
			}
		}
		return $common_result ?? false;
	}
	
	function removeBadSymbols($str)
	{
		return str_replace(["\"","'","\t"]," ", $str);
	}

	function setSQLError($pdo_exception, $error_text)
	{
		$error_txt_info = $error_text.' Text: '.$pdo_exception->getMessage().', file: '.$pdo_exception->getFile().', line: '.$pdo_exception->getLine();
		$this->errorLog($error_txt_info, 1);
	}

	function errorLog($error_message, $debug_mode = 1)
	{
		if ($debug_mode === 1)
		{
			error_log($error_message);
		}
		return TRUE;
	}
}