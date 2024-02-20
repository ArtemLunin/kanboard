<?php
namespace mySQLDatabaseUtils;

class databaseUtils {
	private $unauthorized = true;
	private $root_access = false;
	private $pdo = null;
	private $tableAccessError = false;
	private $platforms = [];
	private $sql_ins_hash = '';
	private $sql_upd_hash = '';
	private $row_ins = null;
	private $row_upd = null;
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
		'pageName' => 'cTemplate',
		'sectionName' => 'ctemplate',
		'sectionAttr'	=> 'ctemplate',
		'accessType'	=> 'admin',
	],
	[
		'pageName' => 'cMOP',
		'sectionName' => 'cmop',
		'sectionAttr'	=> 'cmop',
		'accessType'	=> 'csadmin',
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
	function getSQL($sql_query, $params_arr) 
	{
        try {
			$row = $this->pdo->prepare($sql_query);
			$row->execute($params_arr);
			return $row->fetchall();
		} catch (\PDOException $e){
			$this->setSQLError($e, 'SQL error. "'.$sql_query);
		}
		return null;
    }

	function insSQL($sql_query, $params_arr) 
	{
		if ($this->modSQL($sql_query, $params_arr)) {
			return $this->pdo->lastInsertId();
		} else {
			return null;
		}
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
		$sql_ins_hash = md5($sqlInsUpd['ins']);
		$sql_upd_hash = md5($sqlInsUpd['upd'] ?? '');
		if ($sql_ins_hash !== $this->sql_ins_hash) {
			$this->sql_ins_hash = $sql_ins_hash;
			$this->row_ins = $this->pdo->prepare($sqlInsUpd['ins']);
		}
		if ($sql_upd_hash !== $this->sql_upd_hash) {
			$this->sql_upd_hash = $sql_upd_hash;
			if ($sqlInsUpd['upd'] !== null && $sqlInsUpd['upd'] !== '') {
				$this->row_upd = $this->pdo->prepare($sqlInsUpd['upd']);
			} else {
				$this->row_upd = null;
			}
		}
		if (!$this->tableAccessError) {
			try {
				// $row = $this->pdo->prepare($sqlInsUpd['ins']);
				// $row->execute($params_arr);
				$this->row_ins->execute($params_arr);
				if (!$needCount || ($this->row_ins->rowCount())) {
					return true;
				}
			} catch (\PDOException $e){
				if (preg_match('/Duplicate entry/i', $e->getMessage()) == 1) {
					// if ($sqlInsUpd['upd'] !== null && $sqlInsUpd['upd'] !== '') {
					if ($this->row_upd !== null) {
						// $row = $this->pdo->prepare($sqlInsUpd['upd']);
						// $row->execute($params_arr);
						$this->row_upd->execute($params_arr);
						if (!$needCount || ($this->row_upd->rowCount())) {
							return true;
						}
					} else {
						return null;
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

	function doGetDevicesAll($start = 0, $length = 100) 
	{
		$device_list = [];

		$sql = "SELECT dev.id, dev.name, dev.port, dev.descr, p.platform,p.group_name,p.manager,p.contacts,dev.tags, dev.comments FROM devices_new AS dev, devices_platform AS p WHERE p.id=dev.platform_id ORDER BY dev.name, dev.port";

		if ($table_res = $this->getSQL($sql, [])) {
			foreach ($table_res as $result)
			{
				$device_list[] = [
					'id' 		=> (int)$result['id'],
					'name'		=> $result['name'],
					'port'		=> $result['port'] ?? '',
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

	function updateDevicesData_old($deviceParam)
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
				// $filter = $deviceParam['platform'];
			}
		}
		$tags = explode(" ", trim(strtolower($deviceParam['tags'])));

		$sql_upd = "UPDATE devices_new SET platform=:platform,group_name=:group_name,manager=:manager,contacts=:contacts,tags=:tags WHERE id=:id";
		$sql_udp_note = "UPDATE devices_new SET comments=:comments WHERE id=:id";

		if ($table_res = $this->getSQL($sql, [
			'filter' => $filter,
		])) {
			foreach ($table_res as $result)
			{
				foreach ($tags as $tag) {
					error_log($tag);
					if (strpos(strtolower($result['descr'] ?? ''), $tag) !== false) {
						$this->modSQL($sql_upd, [
							'platform'		=> $deviceParam['platform'],
							'group_name'	=> $group,
							'manager'		=> $manager ,
							'contacts'		=> $deviceParam['contacts'],
							'tags'			=> $deviceParam['tags'],
							'id'			=> $result['id'],
						], false);
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

	function updateDevicesData($deviceParam)
	{
		$group_name = $deviceParam['group'];
		$manager = $deviceParam['owner'];
		$platform = $deviceParam['platform'];

		$sql = "SELECT dev.id, dev.descr, p.platform,p.group_name,p.manager FROM devices_new AS dev, devices_platform AS p WHERE p.platform=:filter AND p.id=dev.platform_id";
		
		$filter = '';
		if ($deviceParam['locked'] == '1') {
			$sql = "SELECT dev.id, dev.descr, p.platform,p.group_name,p.manager FROM devices_new AS dev, devices_platform AS p WHERE dev.id=:filter AND p.id=dev.platform_id";
			$filter = $deviceParam['id'];
		} elseif ($deviceParam['group'] == '') {
			$sql_pre = "SELECT dev.id, dev.descr, p.platform,p.group_name,p.manager FROM devices_new AS dev, devices_platform AS p WHERE p.platform=:filter AND p.id=dev.platform_id LIMIT 1";
			if ($table_res = $this->getSQL($sql_pre, [
				'filter' => $deviceParam['platform'],
			])) {
				$group_name = $table_res[0]['group_name'];
				$manager = $table_res[0]['manager'];
				// $filter = $deviceParam['platform'];
			}
		}

		$tags = explode(" ", trim(strtolower($deviceParam['tags'])));

		$sql_upd = "UPDATE devices_new SET tags=:tags, platform_id=:platform_id WHERE id=:id";
		$sql_udp_note = "UPDATE devices_new SET comments=:comments WHERE id=:id";

		$platform_id = $this->getPlatformId([
			'platform'		=> $platform,
			'group_name'	=> $group_name,
			'manager'		=> $manager,
			'contacts'		=> null,
		]);

		if ($table_res = $this->getSQL($sql, [
			'filter' => $filter,
		])) {
			foreach ($table_res as $result)
			{
				foreach ($tags as $tag) {
					// error_log($tag);
					if (strpos(strtolower($result['descr'] ?? ''), $tag) !== false) {
						$this->modSQL($sql_upd, [
							// 'platform'		=> $deviceParam['platform'],
							// 'group_name'	=> $group,
							// 'manager'		=> $manager ,
							// 'contacts'		=> $deviceParam['contacts'],
							'tags'			=> $deviceParam['tags'],
							'platform_id'	=> $platform_id,
							'id'			=> $result['id'],
						], false);
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

	function changeOwner_old($deviceParam) 
	{
		$sql = "UPDATE devices_new SET manager=:manager WHERE manager=:oldManager AND group_name=:group_name";
		if ($deviceParam['locked'] == '1') {
			$sql .= " AND id=:id";
			$this->modSQL($sql, [
				'oldManager'=> $deviceParam['oldOwner'],
				'manager'	=> $deviceParam['owner'],
				'group_name'=> $deviceParam['group'],
				'id'		=> $deviceParam['id'],
			], false);
		} else {
			$this->modSQL($sql, [
				'oldManager'=> $deviceParam['oldOwner'],
				'manager'	=> $deviceParam['owner'],
				'group_name'=> $deviceParam['group'],
			], false);
		}
		return $this->doGetDevicesAll();
	}

	function changeOwner($deviceParam) {
		$sql = "UPDATE devices_platform SET manager=:manager WHERE manager=:oldManager AND group_name=:group_name";
		// if ($deviceParam['locked'] == '1') {
		// 	$sql .= " AND id=:id";
		// 	$this->modSQL($sql, [
		// 		'oldManager'=> $deviceParam['oldOwner'],
		// 		'manager'	=> $deviceParam['owner'],
		// 		'group_name'=> $deviceParam['group'],
		// 		'id'		=> $deviceParam['id'],
		// 	], false);
		// } else {
			$this->modSQL($sql, [
				'oldManager'=> $deviceParam['oldOwner'],
				'manager'	=> $deviceParam['owner'],
				'group_name'=> $deviceParam['group'],
			], false);
		// }
		return $this->doGetDevicesAll();
	}

	function changeGroup_old($deviceParam) {
		$sql = "UPDATE devices_new SET group_name=:group_name WHERE group_name=:oldGroup AND platform=:platform";
		if ($deviceParam['locked'] == '1') {
			$sql .= " AND id=:id";
			$this->modSQL($sql, [
				'oldGroup'	=> $deviceParam['oldGroup'],
				'group_name'=> $deviceParam['group'],
				'platform'	=> $deviceParam['platform'],
				'id'		=> $deviceParam['id'],
			], false);
		} else {
			$this->modSQL($sql, [
				'oldGroup'	=> $deviceParam['oldGroup'],
				'group_name'=> $deviceParam['group'],
				'platform'	=> $deviceParam['platform'],
			], false);
		}
		return $this->doGetDevicesAll();
	}

	function changeGroup($deviceParam) {
		$sql = "UPDATE devices_platform SET group_name=:group_name WHERE group_name=:oldGroup AND platform=:platform";
		// if ($deviceParam['locked'] == '1') {
		// 	$sql .= " AND id=:id";
		// 	$this->modSQL($sql, [
		// 		'oldGroup'	=> $deviceParam['oldGroup'],
		// 		'group_name'=> $deviceParam['group'],
		// 		'platform'	=> $deviceParam['platform'],
		// 		'id'		=> $deviceParam['id'],
		// 	], false);
		// } else {
			$this->modSQL($sql, [
				'oldGroup'	=> $deviceParam['oldGroup'],
				'group_name'=> $deviceParam['group'],
				'platform'	=> $deviceParam['platform'],
			], false);
		// }
		return $this->doGetDevicesAll();
	}

	function loadData_old($rows)
	{
		$sql = "INSERT INTO devices_new (name,port,descr,platform,group_name,manager,tags,comments) VALUES (:name,:port,:descr,:platform,:group_name,:manager,:tags,:comments)";
		foreach ($rows as $row) {
			$result = $this->modSQLInsUpd(['ins' => $sql, 'upd' => null], [
				'name'		=> trim($row[0]),
				'port'		=> trim($row[1]),
				'descr' 	=> trim($row[2] ?? ''),
				'platform'	=> trim($row[3] ?? ''),
				'tags'		=> trim($row[4] ?? ''),
				'group_name' => trim($row[5] ?? ''),
				'manager'	=> trim($row[6] ?? ''),
				'comments'	=> trim($row[7] ?? ''),
			], false);
			if ($result === null) {
				$sql_descr = "SELECT id, descr FROM devices_new WHERE name=:name AND port=:port";
				if ($table_res = $this->getSQL($sql_descr, [
					'name' => trim($row[0]),
					'port' => trim($row[1]),
				])) {
					if (trim($row[2] ?? '') != trim($table_res[0]['descr'])) {
						$sql_upd = "UPDATE devices_new SET descr=:descr,platform='',group_name='',manager='',tags='',comments='' WHERE id=:id";
						$this->modSQL($sql_upd, [
							'descr'	=> trim($row[2] ?? ''),
							'id'	=> $table_res[0]['id'],
						], false);
					}
				}
			}
		}
		return $this->doGetDevicesAll();
	}
	
	function loadData($rows)
	{
		$sql = "INSERT INTO devices_new (name,port,descr,tags,comments,platform_id) VALUES (:name,:port,:descr,:tags,:comments,:platform_id)";
		$sql_get = "SELECT id,name,port,descr,tags,platform_id FROM devices_new WHERE name=:name AND port=:port AND descr<>:descr";
		$sql_get_tags = "SELECT id,name,port,descr,tags,platform_id FROM devices_new WHERE NOT (name=:name AND port=:port) AND tags<>''";
		$sql_upd = "UPDATE devices_new SET descr=:descr,tags=:tags,platform_id=:platform_id WHERE id=:id";
		$sql_upd_descr = "UPDATE devices_new SET descr=:descr WHERE id=:id";

		$row_ins = $this->pdo->prepare($sql);
		$row_get = $this->pdo->prepare($sql_get);
		$row_get_tags = $this->pdo->prepare($sql_get_tags);
		$row_upd = $this->pdo->prepare($sql_upd);
		$row_upd_descr = $this->pdo->prepare($sql_upd_descr);

		foreach ($rows as $row) {
			$new_device_id = 0;
			$new_platform_id = null;
			$new_tags = '';

			$name = trim($row[0] ?? '');
			$port = trim($row[1] ?? '');
			$descr = trim($row[2] ?? '');
			$platform = trim($row[3] ?? '');
			$tags = trim($row[4] ?? '');
			$group_name = trim($row[5] ?? '');
			$manager = trim($row[6] ?? '');
			$contacts = null;
			$platform_id = $this->getPlatformId([
				'platform'		=> $platform,
				'group_name'	=> $group_name,
				'manager'		=> $manager,
				'contacts'		=> $contacts,
			]);

			if (!$row[0] || trim($row[0] ?? '') == '' || !$row[1] || trim($row[1] ?? '') == '') {
				continue;
			}
			// $this->modSQLInsUpd(['ins' => $sql, 'upd' => null], [
			// 	'name'	=> trim($row[0]),
			// 	'port'	=> trim($row[1]),
			// 	'descr' => trim($row[2] ?? ''),
			// 	'tags'	=> trim($row[4] ?? ''),
			// 	'comments'		=> trim($row[7] ?? ''),
			// 	'platform_id'	=> $platform_id,
			// ]);
			try {
				// $row = $this->pdo->prepare($sqlInsUpd['ins']);
				// $row->execute($params_arr);
				$row_ins->execute([
						'name'	=> $name,
						'port'	=> $port,
						'descr' => $descr,
						'tags'	=> $tags,
						'comments'		=> trim($row[7] ?? ''),
						'platform_id'	=> $platform_id,
					]);
				$new_device_id = $this->pdo->lastInsertId();
			} catch (\PDOException $e) {
				if (preg_match('/Duplicate entry/i', $e->getMessage()) == 1) {
					$row_get->execute([
						'name'	=> $name,
						'port'	=> $port,
						'descr'	=> $descr,
					]);
					$result = $row_get->fetch();

					if (isset($result['id'])) {
						$new_device_id = $result['id'];
					}
				}
			} finally {
				if ($new_device_id) {
					$row_get_tags->execute([
						'name'	=> $name,
						'port'	=> $port,
					]);
					if ($table_res = $row_get_tags->fetchall())
					{
						foreach ($table_res as $result_tags)
						{
							if (stripos($descr, $result_tags['tags']) !== false) {
								$new_platform_id = $result_tags['platform_id'];
								$new_tags = trim($result_tags['tags']);
								break;
							}
						}
					}
					if ($new_platform_id === null){
						$new_platform_id = $this->getPlatformId([
							'platform'		=> '',
							'group_name'	=> '',
							'manager'		=> '',
							'contacts'		=> '',
						]);
					}
					if ($tags !== $new_tags) {
						$row_upd->execute([
							'descr'			=> $descr,
							'tags'			=> $new_tags,
							'platform_id'	=> $new_platform_id,
							'id'			=> $new_device_id,
						]);
					} else {
						$row_upd_descr->execute([
							'descr'			=> $descr,
							'id'			=> $new_device_id,
						]);
					}
				}
			}
		}
		return $this->doGetDevicesAll();
	}

	function getPlatformId($platform)
	{
		$platform_id = null;
		$platform_hash = md5($platform['platform']);
		if (array_key_exists($platform_hash, $this->platforms)) {
			$platform_id = $this->platforms[$platform_hash];
		} else {
			$sql_get_platform = "SELECT id,group_name,manager FROM devices_platform WHERE platform=:platform";
			$sql_add_platform = "INSERT INTO devices_platform (platform,group_name,manager,contacts) VALUES (:platform,:group_name,:manager,:contacts)";
			if ($table_res = $this->getSQL($sql_get_platform, [
				'platform' => $platform['platform'],
			])) {
				$platform_id = $table_res[0]['id'];
			} else {
				$platform_id = $this->insSQL($sql_add_platform, [
					'platform'		=> $platform['platform'],
					'group_name'	=> $platform['group_name'],
					'manager'		=> $platform['manager'],
					'contacts'		=> $platform['contacts'],
				], false);
			}
			$this->platforms[$platform_hash] = $platform_id;
		}
		return $platform_id;
	}

	function doDeleteDevice($id, $mode = null)
	{
		$sql = "DELETE FROM devices_new WHERE id=:id";
		if ($this->modSQL($sql, [
			'id' => $id
		], true)) {	
			if ($mode === 'fast') {
				return ['id' => $id];
			}
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