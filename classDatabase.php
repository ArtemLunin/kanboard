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
		'pageName' => 'Capacity',
		'sectionName' => 'capacity',
		'sectionAttr'	=> 'capacity',
		'accessType'	=> 'admin',
	],
	[
		'pageName' => 'Inventory',
		'sectionName' => 'inventory',
		'sectionAttr'	=> 'inventory',
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
	function getSQL($sql_query, $params_arr) {
        try {
			$row = $this->pdo->prepare($sql_query);
			$row->execute($params_arr);
			return $row->fetchall();
		} catch (\PDOException $e){
			$this->setSQLError($e, 'SQL error. "'.$sql_query);
		}
		return null;
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
					if ($sqlInsUpd['upd'] !== null && $sqlInsUpd['upd'] !== '') {
						$row = $this->pdo->prepare($sqlInsUpd['upd']);
						$row->execute($params_arr);
						if (!$needCount || ($row->rowCount())) {
							return true;
						}
					} else {
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

	function doGetDevicesAll_old($in_exp = FALSE)
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

	function doGetDevicesAll() 
	{
		$device_list = [];
		$sql = "SELECT id, name, port, descr, platform,group_name,manager,contacts,tags, comments FROM devices_new";
		// $sql_tags = "SELECT id, tag, device_id FROM devices_tags WHERE device_id=:device_id";
		// $sql_owner = "SELECT id, group_name, manager, contacts, device_id FROM devices_owners WHERE device_id=:device_id";

		if ($table_res = $this->getSQL($sql, [])) {
			foreach ($table_res as $result)
			{
				// $tags = '';
				// $table_tags = $this->getSQL($sql_tags, [
				// 	'device_id' => $result['id'],
				// ]);
				// foreach ($table_tags as $tag) {
				// 	$tags .= ' ' . $tag['tag'];
				// }
				// $group = ''; $manager = ''; $contacts = '';
				// if ($table_owners = $this->getSQL($sql_owner, [
				// 	'device_id' => $result['id'],
				// ])) {
				// 	$group = $table_owners[0]['group_name'];
				// 	$manager = $table_owners[0]['manager'];
				// 	$contacts = $table_owners[0]['contacts'];
				// }

				$device_list[] = [
					'id' 		=> (int)$result['id'],
					'name'		=> $result['name'],
					'port'		=> $result['port'],
					'description'	=> $result['descr'] ?? '',
					'platform'	=> $result['platform'] ?? '',
					'tags'		=> $result['tags'],
					'group'		=> $result['group_name'],
					'owner'		=> $result['manager'],
					'comments'	=> $this->removeBadSymbols($result['comments']),
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

	function updateDevicesData($deviceParam)
	{
		$group = $deviceParam['group'];
		$manager = $deviceParam['owner'];

		$sql = "SELECT id, descr,platform,group_name,manager FROM devices_new WHERE platform=:filter";
		$filter = '';
		if ($deviceParam['locked'] == '1') {
			$sql = "SELECT id, descr,platform,group_name,manager FROM devices_new WHERE id=:filter";
			$filter = $deviceParam['id'];
		} elseif ($deviceParam['group'] == '') {
			$sql_pre = "SELECT id, descr,platform,group_name,manager FROM devices_new WHERE platform=:filter LIMIT 1";
			if ($table_res = $this->getSQL($sql_pre, [
				'filter' => $deviceParam['platform'],
			])) {
				$group = $table_res[0]['group_name'];
				$manager = $table_res[0]['manager'];
				$filter = $deviceParam['platform'];
			}
		}
		// $sql_upd = "UPDATE devices_new SET platform=:platform WHERE id=:id";
		// $sql_tags = "INSERT INTO devices_tags (tag, device_id) VALUES (:tag,:device_id)";
		// $sql_ins_owner = "INSERT INTO devices_owners (group_name, manager,device_id) VALUES (:group_name,:manager,:device_id)";
		// $sql_upd_owner = "UPDATE devices_owners SET group_name=:group_name,manager=:manager WHERE device_id=:device_id";
		// $tags = strtolower($deviceParam['tags']);
		$tags = explode(" ", trim(strtolower($deviceParam['tags'])));

		$sql_upd = "UPDATE devices_new SET platform=:platform,group_name=:group_name,manager=:manager,contacts=:contacts,tags=:tags WHERE id=:id";
		$sql_udp_note = "UPDATE devices_new SET comments=:comments WHERE id=:id";

		if ($table_res = $this->getSQL($sql, [
			'filter' => $filter,
		])) {
			foreach ($table_res as $result)
			{
				foreach ($tags as $tag) {
					if (str_contains(strtolower($result['descr']), $tag)) {
						$this->modSQL($sql_upd, [
							'platform'		=> $deviceParam['platform'],
							'group_name'	=> $group,
							'manager'		=> $manager ,
							'contacts'		=> $deviceParam['contacts'],
							'tags'			=> $deviceParam['tags'],
							'id'			=> $result['id'],
						], false);
						// $this->modSQL($sql_tags, [
						// 	'tag'	=> $tags,
						// 	'device_id'	=> $result['id'],
						// ], false);
						// $this->modSQLInsUpd(['ins' => $sql_ins_owner, 'upd' => $sql_upd_owner], [
						// 	'group_name'=> $deviceParam['group'],
						// 	'manager'	=> $deviceParam['owner'],
						// 	'device_id' => $result['id'],
						// ], false);
						break;
					}
				}
			}
		}
		$this->modSQL($sql_udp_note, [
			'comments'	=> $deviceParam['comments'],
			'id'		=> $deviceParam['id'],
		], false);
		return $this->doGetDevicesAll();
	}

	function loadData($rows)
	{
		// $sql_fresh = "DELETE FROM devices_new WHERE id<>0";
		// $this->modSQL($sql_fresh, [], false);
		$sql = "INSERT INTO devices_new (name,port,descr,platform,group_name,manager,tags,comments) VALUES (:name,:port,:descr,:platform,:group_name,:manager,:tags,:comments)";
		foreach ($rows as $row) {
			$this->modSQLInsUpd(['ins' => $sql, 'upd' => null], [
				'name'		=> $row[0],
				'port'		=> $row[1],
				'descr' 	=> $row[2],
				'platform'	=> $row[3] ?? '',
				'tags'		=> $row[4] ?? '',
				'group_name' => $row[5] ?? '',
				'manager'	=> $row[6] ?? '',
				'comments'	=> $row[7] ?? '',
			], false);
		}
		return $this->doGetDevicesAll();
	}
	
	function doDeleteDevice($id)
	{
		$sql = "DELETE FROM devices_new WHERE id=:id";
		if ($this->modSQL($sql, [
			'id' => $id
		], true)) {	
			return $this->doGetDevicesAll();
		}
		return false;
	}

	function clearDevicesDataTemp() {
		$sql = "DELETE FROM devices_new WHERE id<>0";
		return $this->modSQL($sql, [], false);
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
		return str_replace(["\"","'","\t"]," ", $str ?? '');
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
			error_log(date("Y-m-d H:i:s") . " ". $error_message);
		}
		return TRUE;
	}
}

class databaseUtilsMOP {
    private $pdo = null;
	private const OGPAEXPORTFILE = 'ogpa_export.json';
	private const PATHEXPORTFILE = 'temp'; 

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
			$this->errorLog("err connect to database:".$e->getMessage());
			return FALSE;
		}
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
			error_log(date("Y-m-d H:i:s") . " ". $error_message);
		}
		return TRUE;
	}
    function getSQL($sql_query, $params_arr) {
        try {
			$row = $this->pdo->prepare($sql_query);
			$row->execute($params_arr);
			return $row->fetchall();
		} catch (\PDOException $e){
			$this->setSQLError($e, 'SQL error. "'.$sql_query);
		}
		return null;
    }
	function modSQL($sql_query, $params_arr, $needCount = true) {
		try {
			$row = $this->pdo->prepare($sql_query);
			$row->execute($params_arr);
			if (!$needCount || ($row->rowCount())) {
				return true;
			}
		} catch (\PDOException $e) {
			$this->setSQLError($e, 'SQL error. "'.$sql_query);
		}
		return false;
	}
    function getOGPA() {
        if ($this->pdo) {
            $ogpa = [];
			$sql = "SELECT el.id AS id, el.element AS element FROM prime_element AS el";
			try {
				if ($table_res = $this->getSQL($sql, [])) {
					foreach ($table_res as $result)
					{
						$ogpa[] = [
							'id' => (int)$result['id'],
							'element' => $result['element'],
						];
					}
				}
				return $ogpa;
			} catch (Throwable $e) {
				$error_txt_info = $e->getMessage().', file: '.$e->getFile().', line: '.$e->getLine();
				$this->errorLog($error_txt_info, 1);
			}
			return null;
        }
    }

	function getOGPAActivity($primeElemID) {
        if ($this->pdo) {
            $ogpa = [];
		$sql = "SELECT act.id AS id, act.activity AS element FROM activities AS act WHERE id_parent_element=:id";
        try {
            if ($table_res = $this->getSQL($sql, [
				'id'	=> $primeElemID,
			]))
            {
                foreach ($table_res as $result)
                {
                    $ogpa[] = [
						'id' => (int)$result['id'],
						'element' => $result['element'],
					];
                }
            }
            return $ogpa;
        } catch (Throwable $e) {
			$error_txt_info = $e->getMessage().', file: '.$e->getFile().', line: '.$e->getLine();
		    $this->errorLog($error_txt_info, 1);
		}
		return null;
        }
    }

	function addPrimeElement($value) {
		$sql = "INSERT into prime_element (element) VALUES (:value)";
		$this->modSQL($sql, [
			'value'		=> $value,
		], true);
		return $this->getOGPA();
	}

	function modPrimeElement($value, $id) {
		$sql = "UPDATE prime_element SET element=:value WHERE id=:id";
		$this->modSQL($sql, [
			'value'	=> $value,
			'id'	=> $id,
		], false);
		return $this->getOGPA();
	}
	
	function delPrimeElement($value) {
		$sql = "DELETE FROM prime_element WHERE element=:value";
		$this->modSQL($sql, [
			'value'		=> $value,
		], false);
		return $this->getOGPA();
	}

	function addActivity($value, $parentId) {
		$sql = "INSERT into activities (activity, id_parent_element) VALUES (:value, :parentId)";
		$this->modSQL($sql, [
			'value'		=> $value,
			'parentId'	=> $parentId,
		], true);
		return $this->getOGPAActivity($parentId);
	}

	function modActivity($value, $id, $parentId) {
		$sql = "UPDATE activities SET activity=:value WHERE id=:id";
		$this->modSQL($sql, [
			'value'	=> $value,
			'id'	=> $id,
		], false);
		return $this->getOGPAActivity($parentId);
	}

	function delActivity($value) {
		$sql = "SELECT activity, id_parent_element FROM activities WHERE id=:value";
		try {
            if ($table_res = $this->getSQL($sql, [
				'value'	=> $value,
			]))
            {
				$parentId = (int)$table_res[0]['id_parent_element'];
				$sql = "DELETE FROM activities WHERE id=:value";
				$this->modSQL($sql, [
					'value'		=> $value,
				], true);
				return $this->getOGPAActivity($parentId);
			}
		} catch (Throwable $e) {
			$error_txt_info = $e->getMessage().', file: '.$e->getFile().', line: '.$e->getLine();
		    $this->errorLog($error_txt_info, 1);
		}
	}

	function getActivityFields($id) {
		$sql = "SELECT field_json_props FROM mop_fields WHERE id_parent_activity=:id";
		try {
            if ($table_res = $this->getSQL($sql, [
				'id'	=> $id,
			]))
            {
				$field_json_props = $table_res[0]['field_json_props'];
				return json_decode($field_json_props, true);
			}
		} catch (Throwable $e) {
			$error_txt_info = $e->getMessage().', file: '.$e->getFile().', line: '.$e->getLine();
		    $this->errorLog($error_txt_info, 1);
		}
	}
	function setActivityFields($value, $id) {
		$sql = "UPDATE mop_fields SET field_json_props=:props WHERE id_parent_activity=:id";
		if (!$this->modSQL($sql, [
			'props'	=> json_encode($value),
			'id'	=> $id
		], true)) {
			$sql = "INSERT into mop_fields (field_json_props, id_parent_activity) VALUES (:props, :id)";
			$this->modSQL($sql, [
				'props'	=> json_encode($value),
				'id'	=> $id
			], true);
		}
		return $this->getActivityFields($id);
	}

	function incActivityCounter($id, $mode = "mopCounter") {
		$sql = "SELECT dip_counter, mop_counter FROM mop_fields WHERE id_parent_activity=:id";
		if ($table_res = $this->getSQL($sql, [
			'id'	=> $id,
		]))
		{
			$dip_counter = $table_res[0]['dip_counter'];
			$mop_counter = $table_res[0]['mop_counter'];
			if ($mode == "mopCounter") {
				$mop_counter++;
			} elseif ($mode == "dipCounter") {
				$dip_counter++;
			} else {
				return false;
			}
			$sql = "UPDATE mop_fields SET dip_counter=:dip_counter, mop_counter=:mop_counter WHERE id_parent_activity=:id";
			return $this->modSQL($sql, [
				'dip_counter'	=> $dip_counter,
				'mop_counter'	=> $mop_counter,
				'id'			=> $id
			]);
		}
		return false;
	}

	function getActivitiesCounter($id = 0) {
		$ogpaCounters = [];
		$sql = "SELECT field_json_props, id_parent_activity FROM mop_fields";
		if ($id !== 0) {
			$sql = "SELECT field_json_props, id_parent_activity FROM mop_fields WHERE id_parent_activity=:id";
		}
		try {
			if ($id !== 0) {
				$table_res = $this->getSQL($sql, [
					'id'	=> $id,
				]);
			} else {
				$table_res = $this->getSQL($sql, []);
			}
            foreach ($table_res as $result)
            {
				$json_props = json_decode($result['field_json_props'], true);
				if ($json_props) {
					$sql = "SELECT prime_element.element AS element, activities.activity AS activity FROM prime_element, activities WHERE activities.id=:id AND activities.id_parent_element=prime_element.id";
					if ($ogpa_res = $this->getSQL($sql, [
						'id'	=> $result['id_parent_activity'],
					]))
					{
						$ogpaCounters[] = [
							'element'	=> $ogpa_res[0]['element'],
							'activity'	=> $ogpa_res[0]['activity'],
							'counters'	=> $json_props['counters'] ?? []
						];
					}
				}
			}
			return $ogpaCounters;
		} catch (Throwable $e) {
			$error_txt_info = $e->getMessage().', file: '.$e->getFile().', line: '.$e->getLine();
		    $this->errorLog($error_txt_info, 1);
		}
	}

	function exportActivity($element, $activity) {
		$sql = 'SELECT pe.element, act.activity, mf.field_json_props FROM prime_element AS pe, activities AS act, mop_fields AS mf WHERE pe.element=:element AND act.activity=:activity AND act.id_parent_element=pe.id AND mf.id_parent_activity=act.id';
		try {
            if ($table_res = $this->getSQL($sql, [
				'element'	=> $element,
				'activity'	=> $activity,
			]))
            {
				$field_json_props = [];
				$field_json_props['fields'] = json_decode($table_res[0]['field_json_props'], true);
				if (!is_dir(self::PATHEXPORTFILE)) {
					if (!mkdir(self::PATHEXPORTFILE)) {
						return false;
					}
				}
				$exportFile = fopen(self::PATHEXPORTFILE.'/'.self::OGPAEXPORTFILE,'w');
				$field_json_props['primeElement'] = $element;
				$field_json_props['activity'] = $activity;

				fwrite($exportFile, json_encode($field_json_props));
				fclose($exportFile);
				return self::PATHEXPORTFILE.'/'.self::OGPAEXPORTFILE;
			}
		} catch (Throwable $e) {
			$error_txt_info = $e->getMessage().', file: '.$e->getFile().', line: '.$e->getLine();
		    $this->errorLog($error_txt_info, 1);
		}
	}

	function importActivity($importOGPAData, $element, $activity) {
		$sql = 'SELECT act.id FROM prime_element AS pe, activities AS act WHERE pe.element=:element AND act.activity=:activity';
		try {
            if ($table_res = $this->getSQL($sql, [
				'element'	=> $element,
				'activity'	=> $activity,
			]))
            {
				return $this->setActivityFields($importOGPAData, $table_res[0]['id']);
			}
		} catch (Throwable $e) {
			$error_txt_info = $e->getMessage().', file: '.$e->getFile().', line: '.$e->getLine();
		    $this->errorLog($error_txt_info, 1);
		}
	}

	function getInventory() {
		if ($this->pdo) {
			$dataset = [];
			$sql = "SELECT id, chassis_name, vendor, model, software, serial, year_service, comment FROM chassis";
			try {
				if ($table_res = $this->getSQL($sql, [])) {
					foreach ($table_res as $result)
					{
						$dataset[] = [
							'id' => (int)$result['id'],
							'chassis_name'	=> $result['chassis_name'],
							'vendor'		=> $result['vendor'],
							'model'			=> $result['model'],
							'software'		=> $result['software'],
							'serial'		=> $result['serial'],
							'year_service'	=> $result['year_service'],
							'comment'		=> $result['comment'],
						];
					}
				}
				return $dataset;
			} catch (Throwable $e) {
				$error_txt_info = $e->getMessage().', file: '.$e->getFile().', line: '.$e->getLine();
				$this->errorLog($error_txt_info, 1);
			}
			return null;
		}
	}
	
	function getChassisTags($chassis_id) {
		$sql = "SELECT id, tag FROM chassis_tags WHERE chassis_id=:chassis_id";
		$dataset = [];
		try {
			if ($table_res = $this->getSQL($sql, [
				'chassis_id' => $chassis_id,
			])) {
				foreach ($table_res as $result)
				{
					$dataset[] = [
						'id'	=> (int)$result['id'],
						'tag'	=> $result['tag']
					];
				}
			}
			return $dataset;
		}
		catch (Throwable $e) {
			$error_txt_info = $e->getMessage().', file: '.$e->getFile().', line: '.$e->getLine();
			$this->errorLog($error_txt_info, 1);
		}
		return null;
	}

	function setChassisTags($chassis_id, $tags) {
		if (is_array($tags)) {
			$sql = "DELETE FROM chassis_tags WHERE chassis_id=:chassis_id";
			$sql_ins = "INSERT INTO chassis_tags (tag, chassis_id) VALUES (:tag, :chassis_id)";
			$this->modSQL($sql, [
					'chassis_id' => $chassis_id,
			]);
			foreach ($tags as $tag) {
				$this->modSQL($sql_ins, [
					'tag'			=> $tag,
					'chassis_id'	=> $chassis_id,
				]);
			}
		}
		return $this->getChassisTags($chassis_id);
	}

	function setChassisData($chassis_id, $chassis_data) {
		if (is_array($chassis_data)) {
			$sql_inj = ''; $sql_inj2 = '';
			foreach ($chassis_data as $key => $value) {
				if ($chassis_id == "0") {
					$sql_inj .= "$key,";
					$sql_inj2 .= ":$key,";
				} else {
					$sql_inj .= "$key=:$key,";
				}
			}
			
			$sql_inj = rtrim($sql_inj, ', ');
			$sql_inj2 = rtrim($sql_inj2, ', ');

			if ($chassis_id == "0") {
				$sql = "INSERT INTO chassis (" . $sql_inj . ") VALUES (" . $sql_inj2 . ")";
			} else {
				$sql = "UPDATE chassis SET " . $sql_inj . " WHERE id=:chassis_id";
				$chassis_data['chassis_id'] = $chassis_id;
			}
			$this->modSQL($sql, $chassis_data);
		}
		return $this->getInventory();
	}
}