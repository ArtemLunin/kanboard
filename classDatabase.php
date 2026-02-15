<?php
namespace mySQLDatabaseUtils;

require_once 'classHelper.php';


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
	private const LOGFILE = 'import_log.txt';
	private const PATHLOGFILE = 'temp'; 
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
		'pageName' => 'Template cDIP',
		'sectionName' => 'templatecDIP',
		'sectionAttr'	=> 'templatecDIP',
		'accessType'	=> 'admin',
	],
	[
		'pageName' => 'cDIP',
		'sectionName' => 'cdip',
		'sectionAttr'	=> 'cdip',
		'accessType'	=> 'admin',
	],
	[
		'pageName' => 'Capacity',
		'sectionName' => 'capacity',
		'sectionAttr'	=> 'capacity',
		'accessType'	=> 'admin',
	],
	[
		'pageName' => 'cSDE Ping Test',
		'sectionName' => 'cSDEPingtest',
		'sectionAttr'	=> 'cSDEPingtest',
		'accessType'	=> 'admin',
	],
	[
		'pageName' => 'cSDE Bundle',
		'sectionName' => 'cSDEBundle',
		'sectionAttr'	=> 'cSDEBundle',
		'accessType'	=> 'admin',
	],
	[
		'pageName' => 'Inventory',
		'sectionName' => 'inventory',
		'sectionAttr'	=> 'inventory',
		'accessType'	=> 'admin',
	],
	[
		'pageName' => 'Projects',
		'sectionName' => 'projects',
		'sectionAttr'	=> 'projects',
		'accessType'	=> 'admin',
	],
	// [
	// 	'pageName' => 'Projects Info',
	// 	'sectionName' => 'projects-info',
	// 	'sectionAttr'	=> 'projects-info',
	// 	'accessType'	=> 'admin',
	// ],
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
				$this->row_ins->execute($params_arr);
				if (!$needCount || ($this->row_ins->rowCount())) {
					return true;
				}
			} catch (\PDOException $e){
				if (preg_match('/Duplicate entry/i', $e->getMessage()) == 1) {
					if ($this->row_upd !== null) {
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
			$new_rights = array_merge($this->initialRights, $rights);
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

	private function createFilterForPlatform($params_arr) {

		$add_perc = function(string $value): string {
			return $value.'%';
		};

		$search_par = $params_arr['search_par'];
		$virt_int = $params_arr['virt_int'];
		$filter = '';

		$sql_2nd = "SELECT id FROM devices_platform WHERE (platform LIKE :search_par OR group_name LIKE :search_par OR manager LIKE :search_par)";

		$filter_virt = implode('\' OR dev.port LIKE \'', array_map($add_perc,$virt_int));
		if ($filter_virt !== '') {
			$filter = 'AND NOT (dev.port LIKE \''.$filter_virt.'\')';
		}


		if ($search_par !== '') {
			$filter .= ' AND (dev.name LIKE :search_par OR dev.port LIKE :search_par OR dev.descr LIKE :search_par OR dev.tags LIKE :search_par _REPLACE_PLATFORM_)';
			// $search_par .= "%";
			$search_par = "%" . $search_par . "%";
			$row_2nd = $this->pdo->prepare($sql_2nd);
			$row_2nd->bindParam('search_par', $search_par);
			$row_2nd->execute();
			$platform_id = 'OR platform_id IN (';
			if ($table_res_2nd = $row_2nd->fetchall()) {
				foreach ($table_res_2nd as $result)
				{
					$platform_id .= "'".$result['id']."',";
				}
				$platform_id = rtrim($platform_id, ',');
				$platform_id .= ')';
			} else {
				$platform_id = '';
			}
			$filter = str_replace('_REPLACE_PLATFORM_', $platform_id, $filter);
		}

		return $filter;
	}

	private function createFilterForInventory($params_arr) {
		$search_par = trim($params_arr['search_par']);
		$vendor_par = trim($params_arr['vendor_par']);
		$date_par = trim($params_arr['date_par']);

		$filter = '';
		if ($vendor_par !== '') {
			$vendor_filter = ' AND vendor IN (';
			$vendor_arr = explode(';', $vendor_par);
			foreach ($vendor_arr as $value) {
				$vendor_filter .= "'" . $value . "',";
			}
			$vendor_filter = rtrim($vendor_filter, ',');
			$vendor_filter .= ')';
			$filter .= $vendor_filter;
		}
		if ($date_par !== '') {
			$date_filter = ' AND (YEAR(ca_year) IN (';
			$date_arr = explode(';', $date_par);
			foreach ($date_arr as $value) {
				$date_filter .= "'" . $value . "',";
			}
			$date_filter = rtrim($date_filter, ',');
			$date_filter .= ') OR ca_year is NULL)';
			$filter .= $date_filter;
		}
		if ($search_par !== '') {
			$filter .= ' AND (node_name LIKE :search_par OR vendor LIKE :search_par OR hw_model LIKE :search_par OR software LIKE :search_par OR serial LIKE :search_par)';
		}
		return $filter;
	}

	function countDevices($params_arr) {

		$search_par = $params_arr['search_par'];
		$virt_int = $params_arr['virt_int'];

		$count_dev = 0;

		$sql = "SELECT COUNT(dev.id) AS count_dev FROM devices_new AS dev, devices_platform AS p WHERE p.id=dev.platform_id _REPLACE_FILTER_";

		$filter = $this->createFilterForPlatform($params_arr);
		$sql = str_replace(
			'_REPLACE_FILTER_',
			$filter, 
			$sql);

		$row = $this->pdo->prepare($sql);
		if ($search_par !== '') {
			// $search_par .= "%";
			$search_par = "%" . $search_par . "%";
			$row->bindParam('search_par', $search_par);
		}
		$row->execute();
		if ($table_res = $row->fetch()) {
			$count_dev = (int)$table_res['count_dev'];
		}
		return $count_dev;
	}

	function countInventory($params_arr) {
		$count_dev = 0;
		
		$sql = "SELECT COUNT(id) AS count_dev FROM inventory WHERE id>0 _REPLACE_FILTER_";

		$filter = $this->createFilterForInventory($params_arr);
		
		$sql = str_replace(
			'_REPLACE_FILTER_',
			$filter, 
			$sql);

		$row = $this->pdo->prepare($sql);
		if ($params_arr['search_par'] !== '') {
			$params_arr['search_par'] = "%" . $params_arr['search_par'] . "%";
			$row->bindParam('search_par', $params_arr['search_par']);
		}
		$row->execute();
		if ($table_res = $row->fetch()) {
			$count_dev = (int)$table_res['count_dev'];
		}
		return $count_dev;
	}

	function doGetDevicesFiltered($params_arr) {
		$device_list = [];

		if ($params_arr['count'] == 0) {
			return $device_list;
		}

		$order = '';
		$limit_rows = '';

		$sql = "SELECT dev.id,dev.name,dev.port,dev.descr,p.platform,p.group_name,p.manager,p.contacts,dev.tags,dev.comments FROM devices_new AS dev, devices_platform AS p WHERE p.id=dev.platform_id _REPLACE_FILTER_ _REPLACE_ORDER_ _REPLACE_LIMIT_";

		$filter = $this->createFilterForPlatform($params_arr);

		if ($params_arr['column_name'] !== '') {
			$order = ' ORDER BY `'.$params_arr['column_name'].'` '.$params_arr['sort_dir'];
		}

		if ($params_arr['length'] != -1) {
			$limit_rows = "LIMIT :start, :length";
		}

		$sql = str_replace(
			['_REPLACE_FILTER_', '_REPLACE_ORDER_', '_REPLACE_LIMIT_'],
			[$filter, $order, $limit_rows], 
			$sql);

		$row = $this->pdo->prepare($sql);
		if ($params_arr['length'] != -1) {
			$row->bindParam('start', $params_arr['start'], \PDO::PARAM_INT);
			$row->bindParam('length', $params_arr['length'], \PDO::PARAM_INT);
		}

		if ($params_arr['search_par'] !== '') {
			$params_arr['search_par'] = "%" . $params_arr['search_par'] . "%";
			$row->bindParam('search_par', $params_arr['search_par']);
		}
		$row->execute();
		if ($table_res = $row->fetchall()) {
			foreach ($table_res as $result)
			{
				$device_list[] = [
					'DT_RowId' 	=> 'row_'.$result['id'],
					'DT_RowClass'	=> 'mosaic-table-row',
					'DT_RowAttr'	=> [
						'data-node_id' => $result['id'], 
						'data-locked' => '1'],
					'node'		=> $result['name'],
					'interface'		=> $result['port'] ?? '',
					'description'	=> $result['descr'] ?? '',
					'platform'	=> $result['platform'] ?? '',
					'tag'		=> $result['tags'],
					'group'		=> $result['group_name'],
					'owner'		=> $result['manager'],
					'note'		=> $this->removeBadSymbols($result['comments']),
					'actions'	=> '',
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
		$group_name = $deviceParam['group'];
		$manager = $deviceParam['owner'];
		$platform = $deviceParam['platform'];
		$oldPlatform = $deviceParam['oldPlatform'];

		$sql = "SELECT dev.id, dev.descr, p.platform,p.group_name,p.manager FROM devices_new AS dev, devices_platform AS p WHERE p.id=dev.platform_id";
		// p.platform=:filter AND
		
		$filter = null;
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

		if ($filter !== null) {
			$table_res = $this->getSQL($sql, [
				'filter' => $filter,
			]);
		} else {
			$table_res = $this->getSQL($sql, []);
		}
		if ($table_res) {
			foreach ($table_res as $result)
			{
				foreach ($tags as $tag) {
					if (strpos(strtolower($result['descr'] ?? ''), $tag) !== false) {
						$this->modSQL($sql_upd, [
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
		return true;
	}

	function updateInventoryData($deviceParam) {
		$sql_hw = "SELECT hw_model,serial FROM inventory WHERE id=:id";
		$row_get = $this->pdo->prepare($sql_hw);
		$row_get->execute(['id' => $deviceParam['id']]);
		$result = $row_get->fetch();

		if (isset($result['hw_model'])) {
			$sql = "UPDATE inventory SET _REPLACE_FIELDS_ WHERE hw_model=:hw_model _REPLACE_ID_";
			$fields = $values = $id_cond= "";
			if (isset($deviceParam['hw_eos'])) {
				$fields .= "`hw_eos`=:hw_eos,";
			}
			if (isset($deviceParam['hw_eol'])) {
				$fields .= "`hw_eol`=:hw_eol,";
			}
			if (isset($deviceParam['sw_eos'])) {
				$fields .= "`sw_eos`=:sw_eos,";
			}
			if (isset($deviceParam['sw_eol'])) {
				$fields .= "`sw_eol`=:sw_eol,";
			}
			if (isset($deviceParam['ca_year'])) {
				$fields .= "`ca_year`=:ca_year,";
			}
			$fields .= "`software`=:software";
			if ($deviceParam['locked'] == '1') {
				$id_cond = " AND id=:id";
			}

			$sql = str_replace(
				['_REPLACE_FIELDS_','_REPLACE_ID_'],
				[$fields, $id_cond], 
				$sql);	
			
			$row = $this->pdo->prepare($sql);
			$row->bindParam('hw_model', $result['hw_model']);
			$row->bindParam('software', $deviceParam['software']);
			if (isset($deviceParam['hw_eos'])) {
				$row->bindParam('hw_eos', $deviceParam['hw_eos']);
			}
			if (isset($deviceParam['hw_eol'])) {
				$row->bindParam('hw_eol', $deviceParam['hw_eol']);
			}
			if (isset($deviceParam['sw_eos'])) {
				$row->bindParam('sw_eos', $deviceParam['sw_eos']);
			}
			if (isset($deviceParam['sw_eol'])) {
				$row->bindParam('sw_eol', $deviceParam['sw_eol']);
			}
			if (isset($deviceParam['ca_year'])) {
				$row->bindParam('ca_year', $deviceParam['ca_year']);
			}
			if ($deviceParam['locked'] == '1') {
				$row->bindParam('id', $deviceParam['id']);
			}
			$row->execute();

			if ($result['serial'] !== trim($deviceParam['serial'])) {
				$sql_upd = "UPDATE inventory SET `serial`=:serial WHERE id=:id";
				$row_upd = $this->pdo->prepare($sql_upd);
				$row_upd->execute([
					'serial'	=> trim($deviceParam['serial']),
					'id'		=> $deviceParam['id'],
				]);
			}
			return true;
		}
	}

	function changeOwner($deviceParam) {
		$sql = "UPDATE devices_platform SET manager=:manager WHERE manager=:oldManager AND group_name=:group_name";
			$this->modSQL($sql, [
				'oldManager'=> $deviceParam['oldOwner'],
				'manager'	=> $deviceParam['owner'],
				'group_name'=> $deviceParam['group'],
			], false);
		return true;
	}

	function changeGroup($deviceParam) {
		$sql = "UPDATE devices_platform SET group_name=:group_name WHERE group_name=:oldGroup AND platform=:platform";
			$this->modSQL($sql, [
				'oldGroup'	=> $deviceParam['oldGroup'],
				'group_name'=> $deviceParam['group'],
				'platform'	=> $deviceParam['platform'],
			], false);
		return true;
	}
	
	function loadData($rows)
	{
		$sql = "INSERT INTO devices_new (name,port,descr,tags,comments,platform_id) VALUES (:name,:port,:descr,:tags,:comments,:platform_id)";
		$sql_get = "SELECT id,name,port,descr,tags,platform_id FROM devices_new WHERE name=:name AND port=:port AND descr<>:descr";
		// $sql_get_tags = "SELECT id,name,port,descr,tags,platform_id FROM devices_new WHERE NOT (name=:name AND port=:port) AND tags<>''";
		$sql_get_tags = "SELECT distinct(tags),platform_id FROM devices_new WHERE tags<>''";
		$sql_upd = "UPDATE devices_new SET descr=:descr,tags=:tags,platform_id=:platform_id WHERE id=:id";
		$sql_upd_descr = "UPDATE devices_new SET descr=:descr WHERE id=:id";
		$logFile = fopen(self::PATHLOGFILE.'/'.self::LOGFILE,'w');

		$arr_log = [];

		$row_ins = $this->pdo->prepare($sql);
		$row_get = $this->pdo->prepare($sql_get);
		$row_get_tags = $this->pdo->prepare($sql_get_tags);
		$row_upd = $this->pdo->prepare($sql_upd);
		$row_upd_descr = $this->pdo->prepare($sql_upd_descr);

		$new_devices = $mod_devices = $skip_devices = 0;

		$row_get_tags->execute();
		$table_tags = $row_get_tags->fetchall();

		$row_count = 0;

		foreach ($rows as $row) {
			$row_count++;
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
			$comments = trim($row[7] ?? '');
			$contacts = null;

			if ($name == '' || $port == '') {
				$skip_devices++;
				fwrite($logFile, $row_count.';Skipped: node='.$name.',interface:'.$port.PHP_EOL);
				$arr_log[] = $row_count.';Skipped: node='.$name.',interface:'.$port;
				continue;
			}

			$platform_id = $this->getPlatformId([
				'platform'		=> $platform,
				'group_name'	=> $group_name,
				'manager'		=> $manager,
				'contacts'		=> $contacts,
			]);

			if ($descr !== '' && strlen($descr) > 2 && $tags === '') {
				foreach ($table_tags as $result_tags)
				{
					if (stripos($descr, $result_tags['tags']) !== false) {
						$platform_id = $result_tags['platform_id'];
						$tags = trim($result_tags['tags']);
						break;
					}
				}
			}

			try {
				$row_ins->execute([
						'name'	=> $name,
						'port'	=> $port,
						'descr' => $descr,
						'tags'	=> $tags,
						'comments'		=> $comments,
						'platform_id'	=> $platform_id,
					]);
				$new_device_id = $this->pdo->lastInsertId();
				$new_devices++;
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
						// if ($result_tags['tags'] !== '' && stripos($descr, $result_tags['tags']) !== false) {
						// 	$new_platform_id = $result['platform_id'];
						// 	$new_tags = $result['tags'];
						// } else {
						// 	$new_platform_id = $this->getPlatformId([
						// 		'platform'		=> '',
						// 		'group_name'	=> '',
						// 		'manager'		=> '',
						// 		'contacts'		=> '',
						// 	]);
						// 	$new_tags = '';
						// }
						
						$mod_devices++;
						fwrite($logFile, $row_count.';Modified: node='.$name.',interface:'.$port.',descr:'.$descr.PHP_EOL);
						$arr_log[] = $row_count.';Modified: node='.$name.',interface:'.$port.',descr:'.$descr;

						// if ($tags !== $new_tags && $new_tags !== '') {
							$row_upd->execute([
								'descr'			=> $descr,
								'tags'			=> $tags,//$new_tags,
								'platform_id'	=> $platform_id,//$new_platform_id,
								'id'			=> $new_device_id,
							]);
						// } else {
						// 	$row_upd_descr->execute([
						// 		'descr'			=> $descr,
						// 		'id'			=> $new_device_id,
						// 	]);
						// }
					} else {
						$skip_devices++;
						fwrite($logFile, $row_count.';Skipped: node='.$name.',interface:'.$port.',descr:'.$descr.PHP_EOL);
						$arr_log[] = $row_count.';Skipped: node='.$name.',interface:'.$port.',descr:'.$descr;
					}
				} else {
					$skip_devices++;
					fwrite($logFile, $row_count.';Skipped by exception'.$e->getMessage().': node='.$name.',interface:'.$port.',descr:'.$descr.PHP_EOL);
					$arr_log[] = $row_count.';Skipped by exception'.$e->getMessage().': node='.$name.',interface:'.$port.',descr:'.$descr;
				}
			}
		}
		fwrite($logFile, $row_count.';Total processed'.PHP_EOL);
		fclose($logFile);
		return [
			'new_devices'	=> $new_devices,
			'mod_devices'	=> $mod_devices,
			'skip_devices'	=> $skip_devices,
			'rows_log'		=> $arr_log,
		];
	}

	function loadInventory($rows) {
		$sql = "INSERT INTO inventory (node_name,vendor,hw_model,software,serial) VALUES (:node_name,:vendor,:hw_model,:software,:serial)";

		$new_devices = $mod_devices = $skip_devices = 0;

		$arr_log = [];
		$row_count = 0;

		$row_ins = $this->pdo->prepare($sql);
		foreach ($rows as $row) {
			$row_count++;
			$node_name = trim($row[0] ?? '');
			$vendor = trim($row[1] ?? '');
			$hw_model = trim($row[2] ?? '');
			$software = trim($row[3] ?? '');
			$serial = trim($row[4] ?? '');

			if ($node_name == '') {
				$skip_devices++;

				$arr_log[] = $row_count.';Skipped: node='.$node_name.',vendor:'.$vendor.',serial:'.$serial;
				continue;
			}

			try {
				$row_ins->execute([
						'node_name'	=> $node_name,
						'vendor'	=> $vendor,
						'hw_model' => $hw_model,
						'software'	=> $software,
						'serial'		=> $serial,
					]);
				$new_device_id = $this->pdo->lastInsertId();
				$new_devices++;
			} catch (\PDOException $e) {
				if (preg_match('/Duplicate entry/i', $e->getMessage()) == 1) {
					$skip_devices++;
					$arr_log[] = $row_count.';Skipped: node='.$node_name.',vendor:'.$vendor.',serial:'.$serial;
				} else {
					$skip_devices++;
					$arr_log[] = $row_count.';Skipped by exception'.$e->getMessage().': node='.$node_name.',vendor:'.$vendor.',serial:'.$serial;
				}
			}
		}
		return [
			'new_devices'	=> $new_devices,
			'mod_devices'	=> $mod_devices,
			'skip_devices'	=> $skip_devices,
			'rows_log'		=> $arr_log,
		];
	}

	function loadEFCR($rows) {
		$efcr_lines = [];
		foreach ($rows as $row) {
			$eFCRnumber = $this->totalTrim($row[0] ?? '');
			$policyName = $this->totalTrim($row[1] ?? '');
			$sourceZone = $this->totalTrim($row[2] ?? '');
			$sourceSubnet = $this->totalTrim($row[3] ?? '');
			$destinationZone = $this->totalTrim($row[4] ?? '');
			$PHUBSites = $this->totalTrim($row[5] ?? '');
			$destinationSubnet = $this->totalTrim($row[6] ?? '');
			$protocol = $this->totalTrim($row[7] ?? '');
			$port = $this->totalTrim($row[8] ?? '');

			$efcr_lines[] = [
				'eFCRnumber'	=> $eFCRnumber,
				'policyName'	=> $policyName,
				'sourceZone'	=> $sourceZone,
				'sourceSubnet'	=> $sourceSubnet,
				'destinationZone'	=> $destinationZone,
				'PHUBSites'		=> $PHUBSites,
				'destinationSubnet'	=> $destinationSubnet,
				'protocol'		=> $protocol,
				'port'			=> $port,
			];
		}
		return $efcr_lines;
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
		return $this->doDeleteByID('devices_new', $id);
	}

	function doDeleteInventory($id, $mode = null)
	{
		$sql = "DELETE FROM inventory_comments WHERE inventory_id=:id;DELETE FROM `inventory` WHERE id=:id";
		$this->modSQL($sql, [
			'id' => $id
		], true);
		return ['id' => $id];
	}

	private function doDeleteByID($table_name, $id) {
		$sql = "DELETE FROM `".$table_name."` WHERE id=:id";
		if ($this->modSQL($sql, [
			'id' => $id
		], true)) {	
			return ['id' => $id];
		}
		return false;
	}
	function clearDevicesDataTemp() {
		$sql = "DELETE FROM devices_new WHERE id<>0";
		return $this->modSQL($sql, [], false);
	}

	function doGetInventory($params_arr) {
		$get_data = $params_arr['get_data'];
		$inventory_list = [];

		if ($params_arr['count'] == 0 || $get_data != '1') {
			return $inventory_list;
		}

		$order = '';
		$limit_rows = '';

		$filter = $this->createFilterForInventory($params_arr);

		if ($params_arr['column_name'] !== '') {
			$order = ' ORDER BY `'.$params_arr['column_name'].'` '.$params_arr['sort_dir'];
		}

		if ($params_arr['length'] != -1) {
			$limit_rows = "LIMIT :start, :length";
		}

		$sql = "SELECT id,node_name,vendor,hw_model,software,serial,hw_eos,hw_eol,sw_eos,sw_eol,ca_year FROM inventory WHERE id>0 _REPLACE_FILTER_ _REPLACE_ORDER_ _REPLACE_LIMIT_";
		$sql = str_replace(
			['_REPLACE_FILTER_', '_REPLACE_ORDER_', '_REPLACE_LIMIT_'],
			[$filter, $order, $limit_rows], 
			$sql);

		$row = $this->pdo->prepare($sql);
		if ($params_arr['length'] != -1) {
			$row->bindParam('start', $params_arr['start'], \PDO::PARAM_INT);
			$row->bindParam('length', $params_arr['length'], \PDO::PARAM_INT);
		}

		if ($params_arr['search_par'] !== '') {
			$params_arr['search_par'] = "%" . $params_arr['search_par'] . "%";
			$row->bindParam('search_par', $params_arr['search_par']);
		}
		$row->execute();
		if ($table_res = $row->fetchall()) {
			foreach ($table_res as $result)
			{
				$inventory_list[] = [
					'DT_RowId' 	=> 'row_'.$result['id'],
					'DT_RowClass'	=> 'inventory-table-row',
					'DT_RowAttr'	=> [
						'data-table'	=> 'inventory',
						'data-node_id' => $result['id'], 
						'data-locked' => '1'],
					'node'		=> $result['node_name'],
					'vendor'	=> $result['vendor'],
					'hw_model'	=> $result['hw_model'],
					'software'	=> $result['software'],
					'serial'	=> $result['serial'],
					'hw_eos'	=> $result['hw_eos'] ? date('Y-m', strtotime($result['hw_eos'])) : '',
					'hw_eol'	=> $result['hw_eol'] ? date('Y-m', strtotime($result['hw_eol'])) : '',
					'sw_eos'	=> $result['sw_eos'] ? date('Y-m', strtotime($result['sw_eos'])) : '',
					'sw_eol'	=> $result['sw_eol'] ? date('Y-m', strtotime($result['sw_eol'])) : '',
					'ca_year'	=> $result['ca_year'] ? date('Y', strtotime($result['ca_year'])) : '',
					'comments'	=> $this->doGetComments($result['id'], 1),
				];
			}
		}
		return $inventory_list;
	}

	function doGetComments($id, $oneRec = 0) {
		$comments_list = [];
		if ($id !== 0) {
			$sql = "SELECT id,comment,date_add FROM inventory_comments WHERE inventory_id=:id ORDER BY date_add DESC";
			if ($oneRec === 1) {
				$sql .= " LIMIT 1";
			}
			if ($table_res = $this->getSQL($sql, ['id' => $id])) {
				foreach ($table_res as $result)
				{
					$comments_list[] = [
						'id'	=> $result['id'],
						'comment'	=> ($oneRec === 1) ? substr($result['comment'], 0, 20) : $result['comment'],
						'date'	=> $result['date_add'],
					];
				}
			}
		}
		return $comments_list;
	}

	function doSetComments($id, $comment) {
		$sql_upd = "INSERT INTO inventory_comments (comment,date_add,inventory_id) VALUES (:comment,:date_add,:id)";
		$row_upd = $this->pdo->prepare($sql_upd);
		$row_upd->execute([
			'comment'	=> trim($comment),
			'date_add'	=> date("Y-m-d H:i:s"),
			'id'		=> $id,
		]);
		return $this->doGetComments($id);
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

	function totalTrim($str) {
		$string = htmlentities($str, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'utf-8');
		$string = str_replace("&nbsp;", " ", $string);
		return trim(html_entity_decode($string));
	}

	function setSQLError($pdo_exception, $error_text)
	{
		$error_txt_info = $error_text.' Text: '.$pdo_exception->getMessage().', file: '.$pdo_exception->getFile().', line: '.$pdo_exception->getLine();
		$this->errorLog($error_txt_info, 1);
	}
	// databaseUtils
	function errorLog($error_message, $debug_mode = 1)
	{
		if ($debug_mode === 1)
		{
			error_log(date("Y-m-d H:i:s") . " ". $error_message);
		}
		return TRUE;
	}
}

class databaseUtilsMOP extends \helperUtils\helperUtils {
    private $pdo = null;
	private const OGPAEXPORTFILE = 'ogpa_export.json';
	private const PATHEXPORTFILE = 'temp';
	private const APPLICATIONBLOCK = '_APPLICATION_BLOCK_';
	private const SECURITYMATCHBLOCK = '_SECURITYMATCH_BLOCK_';
	private const PHUBSitesHEADER = '';
	private const JUNOSICMPALL = 'junos-icmp-all';
	private const TEMPLATEEFCR2 = 'template/eFCR_2.txt';
	private const TEMPLATEEFCR2APP = 'template/eFCR_2_application.txt';
	private const TEMPLATEEFCR2SECMATCH = 'template/eFCR_2_securitymatch.txt';
	// private const TEMPLATEDGWPINGTESTED = 'template/dgw66-cgw01_ping_tested.txt';
	private const TEMPLATEDGWCGWCONFIG = 'template/dgw-cgw_ping_config.txt';
	private const TEMPLATECGWDGWCONFIG = 'template/cgw-dgw_ping_config.txt';
	private const TEMPLATEDGWCGWVERIFICATION = 'template/dgw-cgw_ping_verification.txt';
	private const TEMPLATECGWDGWVERIFICATION = 'template/cgw-dgw_ping_verification.txt';
	private const TEMPLATEDGWCGWUPGRADE = 'template/dgw-cgw_upgrade_config.txt';
	private const TEMPLATECGWDGWUPGRADE = 'template/cgw-dgw_upgrade_config.txt';
	// private const TEMPLATEDGWCGWVERIFICATION = 'template/dgw-cgw_ping_verification.txt';
	// private const TEMPLATECGWDGWVERIFICATION = 'template/cgw-dgw_ping_verification.txt';

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
	// databaseUtilsMOP
    function getSQL($sql_query, $params_arr) {
        try {
			$row = $this->pdo->prepare($sql_query);
			$row->execute($params_arr);
			return $row->fetchall(\PDO::FETCH_ASSOC);
		} catch (\PDOException $e){
			$this->setSQLError($e, 'SQL error. "'.$sql_query);
		}
		return null;
    }
	// databaseUtilsMOP
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
	// databaseUtilsMOP
	function insSQL($sql_query, $params_arr) 
	{
		if ($this->modSQL($sql_query, $params_arr, true)) {
			return $this->pdo->lastInsertId();
		} else {
			return null;
		}
	}
    function getOGPA($ogpa_group = 0) {
        if ($this->pdo) {
            $ogpa = [];
			$sql = "SELECT el.id AS id, el.element AS element FROM prime_element AS el WHERE ogpa_group=:ogpa_group";
			try {
				if ($table_res = $this->getSQL($sql, [
					'ogpa_group' => $ogpa_group,
				])) {
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

	function addPrimeElement($value, $ogpa_group = 0) {
		$sql = "INSERT into prime_element (element,ogpa_group) VALUES (:value,:ogpa_group)";
		$this->modSQL($sql, [
			'value'		=> $value,
			'ogpa_group' => $ogpa_group,
		], true);
		return $this->getOGPA($ogpa_group);
	}

	function modPrimeElement($value, $id, $ogpa_group = 0) {
		$sql = "UPDATE prime_element SET element=:value WHERE id=:id";
		$this->modSQL($sql, [
			'value'	=> $value,
			'id'	=> $id,
		], false);
		return $this->getOGPA($ogpa_group);
	}
	
	function delPrimeElement($value, $ogpa_group = 0) {
		$sql = "DELETE FROM prime_element WHERE element=:value AND ogpa_group=:ogpa_group";
		$this->modSQL($sql, [
			'value'		=> $value,
			'ogpa_group' => $ogpa_group
		], false);
		return $this->getOGPA($ogpa_group);
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

	function addObjectToTable($table_name, $fields_obj) {
		if (count($fields_obj) < 1) {
			return false;
		}
		$fields = "(";
		$values = "(";
		$values_arr = [];
		$param_id = 1;
		foreach ($fields_obj as $key => $value) {
			$param = "param" . $param_id;
			$fields .= "`" . $key ."`,";
			$values .= ":" . $param . ",";
			$values_arr[$param] = $value;
			$param_id++;
		}
		$fields = rtrim($fields, ",") . ")";
		$values = rtrim($values, ",") . ")";

		$sql = "INSERT IGNORE INTO `" . $table_name. "` ". $fields . " VALUES ". $values; 
		
		return $this->insSQL($sql, $values_arr, true);
	}

	function createFilterDB($table_name, $filters, $split_str) {
		$filter = "";
		$values_arr = [];
		$param_id = 1;
		foreach ($filters as $key => $value) {
			$param = "param" . $param_id;
			$filter .= "`" . $table_name . "`." . "`" . $key ."`" . "=:" . $param . ' ' . $split_str . ' ';
			$values_arr[$param] = $value;
			$param_id++;
		}
		$filter = rtrim(rtrim($filter), $split_str);
		$filter = '(' . $filter . ')';
		return [
			"filter" => $filter,
			"params" => $values_arr
		];
	}
	function runUpdateSQL($tableName, $setFields, $filters) {
        $fields_str = "";
        $field_idx = 1;
        $values_arr = [];
        foreach ($setFields as $field => $value) {
            $field_p = "pf" . $field_idx;
            $fields_str .= "`" . $field . "`=:" . $field_p . ",";
            $values_arr[$field_p] = $value;
            $field_idx++;
        }
        $fields_str = rtrim($fields_str, ', ');
        $filtered_arr = $this->createFilterDB($tableName, $filters, "AND");
        $sql_upd = "UPDATE `" .$tableName. "` SET " . $fields_str . " WHERE " . $filtered_arr['filter'];
		$this->errorLog($sql_upd);
		$this->errorLog(print_r(array_merge($values_arr, $filtered_arr['params']), true));
        // $this->modSQL($sql_upd , array_merge($values_arr, $filtered_arr['params']), false);
    }
	function runInsertSQL($tableName, $setFields) {
        $fields_str = "";
        $values_str = "";
        $field_idx = 1;
        $values_arr = [];
        foreach ($setFields as $field => $value) {
            $field_p = "pf" . $field_idx;
            $fields_str .= "`" . $field . "`,";
			$values_str .= ":" . $field_p . ",";
            $values_arr[$field_p] = $value;
            $field_idx++;
        }
        $fields_str = "(" . rtrim($fields_str, ', ') . ")";
        $values_str = " VALUES (" . rtrim($values_str, ', ') . ")";

        $sql_upd = "INSERT INTO `" .$tableName. "` " . $fields_str . $values_str;
        $this->modSQL($sql_upd , $values_arr, false);
    }
	function runInsertBulk($tableName, $tableFields, $rows, $refreshTable = true) {
		$chunkSize = 10;
		$chunks = array_chunk($rows, $chunkSize);
		try {
			$this->pdo->beginTransaction();
			if ($refreshTable === true) {
				$sql = "DELETE FROM `" . $tableName . "` " . " WHERE id>0";
				$stmt = $this->pdo->prepare($sql);
				$stmt->execute();
			}
			foreach ($chunks as $batch) {
				$columnCount = count($batch[0]);
				$rowPlaceholders = '(' . implode(',', array_fill(0, $columnCount, '?')) . ')';
				$allPlaceholders = implode(',', array_fill(0, count($batch), $rowPlaceholders));
				$allFields = '(' . implode(',', array_map(function($field) {
					return "`" . $field . "`";
				}, $tableFields)) . ')';
				$sql = "INSERT INTO `" . $tableName . "` " . $allFields . " VALUES " . $allPlaceholders;
				$params = [];
				foreach ($batch as $row) {
					foreach ($row as $value) {
						$params[] = $value;
					}
				}
				$stmt = $this->pdo->prepare($sql);
				$stmt->execute($params);
			}
			$this->pdo->commit();
		} catch (PDOException $e) 
		{
			$this->setSQLError($e, 'SQL error. "' . $sql);
			$this->pdo->rollBack();
		}
	}
	function selectObjectFromTable($table_name, $filters, $selected_fields = [], $order_by = []) {
		$filter = "";
		// $values_arr = [];
		$select_fields = "";
		$filtered_arr = [];
		if (count($filters) < 1) {
			$filtered_arr['filter'] = 'id<>:param1';
			$filtered_arr['params'] = [
				'param1'	=> 0
			];
		} else {
			$filtered_arr = $this->createFilterDB($table_name, $filters, "AND");
		}
		if (count($selected_fields) > 0) {
			$sanitize_fields = array_map(function($field) use ($table_name) {
				return "`" . $table_name . "`." . "`" . $field . "`";
			}, $selected_fields);
			$select_fields = implode(",", $sanitize_fields);
		} else {
			$select_fields = "`" . $table_name . "`." . "*";
		}
		$orderby = "";
		if (count($order_by) > 0) {
			$order_by = array_map(function($n) {
				return "`" . $n . "`";
			}, $order_by);
			$orderby = " ORDER BY " . implode(",", $order_by);
		}
		$sql = "SELECT " . $select_fields . " FROM `" . $table_name . "` WHERE " . $filtered_arr['filter'] . $orderby;
		return $this->getSQL($sql, $filtered_arr['params']);
	}

	function selectFieldFromTable($table_name, $filters, $field_name) {
		$first_row = $this->selectObjectFromTable($table_name, $filters, [$field_name]);
		return $first_row[0][$field_name];
	}

	function removeObjectFromTable($table_name, $id) {
		if ($id < 1) {
			return false;
		}
		$sql = "DELETE from `" . $table_name. "` ". "WHERE `id`=:id";
		return $this->modSQL($sql, ["id" => $id], true);
	}

	function removeObjectFromTableFilter($table_name, $filters = []) {
		if (count($filters) < 1) {
			return false;
		}
		$filtered_arr = $this->createFilterDB($table_name, $filters, "AND");

		$sql = "DELETE from `" . $table_name. "` ". "WHERE " . $filtered_arr['filter'];
		return $this->modSQL($sql, $filtered_arr['params'], false);
	}

	function getUserID($userName) {
		if ($table_res = $this->getSQL("SELECT id FROM users WHERE user_name=:user", [
			'user'	=> $userName,
		]))
		{
			return $table_res[0]['id'];
		}
		return 0;
	}

	function getOwnerProjectID($projectID) {
		if ($table_res = $this->getSQL("SELECT user_id FROM projects WHERE id=:project_id", [
			'project_id'	=> $projectID,
		]))
		{
			return $table_res[0]['user_id'];
		}
		return 0;
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

	function exportEFCR($efcr_arr) {
		$slash2ldash = function(string $value): string {
			return str_replace('/', '_', $this->totalTrim($value ?? ''));
		};
		$addMaskToSubnet = function(string $value): string {
			$add_mask = '';
			if (strpos($value, '.', 1) !== false) {
				if (strpos($value, '/') === false) {
					$add_mask = '/32';
				}
			} elseif (strpos($value, ':') !== false) {
				if (strpos($value, '/') === false) {
					$add_mask = '/128';
				}
			}
			return $value.$add_mask;
		};
		function deleteBlockHelper($blockTextName, $arrayStrings) {
			$pr_position = array_search($blockTextName, $arrayStrings);
			array_splice($arrayStrings, $pr_position, 1);
			return $arrayStrings;
		}

		$wireless_sites = ['VA2','ML02','To5','To3','MS1'];
		$site_prefix = 'FW66/67.';

		$efcrFile2 = file(self::TEMPLATEEFCR2);
		$efcr_applications = file(self::TEMPLATEEFCR2APP);
		$efcr_securitymatch = file(self::TEMPLATEEFCR2SECMATCH);
		$efcr_out = [];
		$application_arr = [];
		$first_row = true;
		$application_search = '';
		
		$sourceZone_pr = $destinationZone_pr = $eFCRnumber_pr = $EFCRPolicyName_pr = $PHUBSites_pr = null;
		$first_efcr_row = true;
		
		foreach ($efcr_arr as $efcr) {
			$efcr_storage = [];
			try {
				$sourceZone = $this->totalTrim($efcr['dipSourceZone'] ?? '');
				$destinationZone = $this->totalTrim($efcr['dipDestinationZone'] ?? '');
				$sourceSubnetArr = array_map($addMaskToSubnet, $efcr['dipSourceSubnet']);
				$sourceSubnetNameArr = array_map($slash2ldash, $sourceSubnetArr);
				$destinationSubnetArr = array_map($addMaskToSubnet, $efcr['dipDestinationSubnet']);
				$destinationSubnetNameArr = array_map($slash2ldash, $destinationSubnetArr);
				$dipPort = $this->totalTrim($efcr['dipPort'] ?? '');
				$protocolName = $this->totalTrim(strtoupper($efcr['dipProtocol'] ?? ''));
				$protocolDisplayName = $protocolName . '_' . $dipPort;
				if ($protocolName === 'ICMP') {
					$protocolDisplayName = self::JUNOSICMPALL;
				}
				$protocolName = strtolower($protocolName);
				$eFCRnumber = $this->totalTrim($efcr['dipeFCRNumber'] ?? '');
				$policyName = str_replace(' ', '_', $this->totalTrim($efcr['dipPolicyName'] ?? ''));
				
				$EFCRPolicyName = $eFCRnumber . '_' . $policyName;
				$PHUBSites = $this->totalTrim($efcr['dipPHUBSites'] ?? '');
				$wireless_status = in_array(str_replace($site_prefix, '', $PHUBSites), $wireless_sites) ? true : false;

				if (!$first_row) {
					$efcr_storage[] = '';
					$efcr_storage[] = '';
				} else {
					$first_row = false;
				}
				$efcr_storage[] = self::PHUBSitesHEADER."{$PHUBSites}:";
				foreach ($efcrFile2 as $efcr_str) {
					if (stripos($efcr_str, '_sourcezone_') !== false) {
						$tmp_str = str_replace('_sourcezone_', '', $efcr_str);
						foreach ($sourceSubnetArr as $key => $subnet_value) {
							$new_str = str_replace([
								'%SourceZone%',
								'%SourceSubnetName%',
								'%SourceSubnet%',
							], [
								$sourceZone,
								$sourceSubnetNameArr[$key],
								$subnet_value,
							], $tmp_str);
							$efcr_storage[] = $new_str;
						}
					} elseif (stripos($efcr_str, '_destinationzone_') !== false) {
						$tmp_str = str_replace('_destinationzone_', '', $efcr_str);
						foreach ($destinationSubnetArr as $key => $subnet_value) {
							$new_str = str_replace([
								'%DestinationZone%',
								'%DestinationSubnetName%',
								'%DestinationSubnet%',
							], [
								$destinationZone,
								$destinationSubnetNameArr[$key],
								$subnet_value,
							], $tmp_str);
							$efcr_storage[] = $new_str;
						}
					} elseif (stripos($efcr_str, '_policiessource-address_') !== false) {
						$tmp_str = str_replace('_policiessource-address_', '', $efcr_str);
						foreach ($sourceSubnetNameArr as $key => $subnet_name) {
							$new_str = str_replace([
								'%SourceZone%',
								'%DestinationZone%',
								'%EFCRPolicyName%',
								'%SourceSubnetName%',
							], [
								$sourceZone,
								$destinationZone,
								$EFCRPolicyName,
								$subnet_name,
							], $tmp_str);
							$efcr_storage[] = $new_str;
						}
					} elseif (stripos($efcr_str, '_policiesdestination-address_') !== false) {
						$tmp_str = str_replace('_policiesdestination-address_', '', $efcr_str);
						foreach ($destinationSubnetNameArr as $key => $subnet_name) {
							$new_str = str_replace([
								'%SourceZone%',
								'%DestinationZone%',
								'%EFCRPolicyName%',
								'%DestinationSubnetName%',
							], [
								$sourceZone,
								$destinationZone,
								$EFCRPolicyName,
								$subnet_name,
							], $tmp_str);
							$efcr_storage[] = $new_str;
						}
					} elseif (stripos($efcr_str, self::APPLICATIONBLOCK) !== false) {
						$efcr_storage[] = self::APPLICATIONBLOCK;
						$storage_temp = [];
						foreach ($efcr_applications as $efcr_application) {
							if ($protocolName === 'icmp') {
								break;
							}
							$new_str = str_replace([
								'%ProtocolDisplayName%',
								'%ProtocolName%',
								'%ProtocolPort%'
							], [
								$protocolDisplayName,
								$protocolName,
								$dipPort,
							], $efcr_application);
							$storage_temp[] = $new_str;
						}
					} elseif (stripos($efcr_str, self::SECURITYMATCHBLOCK) !== false) {
						$efcr_storage[] = self::SECURITYMATCHBLOCK;
						$storage_match = [];
						foreach ($efcr_securitymatch as $efcr_match) {
							$new_str = str_replace([
								'%SourceZone%',
								'%DestinationZone%',
								'%EFCRPolicyName%',
								'%ProtocolDisplayName%',
							], [
								$sourceZone,
								$destinationZone,
								$EFCRPolicyName,
								$protocolDisplayName,
							], $efcr_match);
							$storage_match[] = $new_str;
						}
					} else {
						$new_str = str_replace([
							'%SourceZone%',
							'%DestinationZone%',
							'%ProtocolDisplayName%',
							'%ProtocolName%',
							'%ProtocolPort%',
							'%EFCRPolicyName%',
						], [
							$sourceZone,
							$destinationZone,
							$protocolDisplayName,
							$protocolName,
							$dipPort,
							$EFCRPolicyName,
						], $efcr_str);
						if (stripos($efcr_str,'_PHUBWIRELESSCHECK_') !== false) {
							if ($wireless_status) { 
								continue;
							}
							$new_str = str_replace('_PHUBWIRELESSCHECK_', '', $new_str);
						}
						$efcr_storage[] = $new_str;
					}
				}
			} catch (Throwable $e) {
			}
			if ($sourceZone_pr === $sourceZone &&  
				$destinationZone_pr === $destinationZone && 
				$eFCRnumber_pr === $eFCRnumber &&
				$EFCRPolicyName_pr ===  $EFCRPolicyName &&
				$PHUBSites_pr === $PHUBSites) {
				$efcr_storage = [];
			}
			else {
				$sourceZone_pr = $sourceZone;
				$destinationZone_pr = $destinationZone;
				$eFCRnumber_pr = $eFCRnumber;
				$EFCRPolicyName_pr =  $EFCRPolicyName;
				$PHUBSites_pr = $PHUBSites;
				if (!$first_efcr_row) {
					$efcr_out = deleteBlockHelper(self::APPLICATIONBLOCK, $efcr_out);
					$efcr_out = deleteBlockHelper(self::SECURITYMATCHBLOCK, $efcr_out);
				}
				$first_efcr_row = false;
				$efcr_out = array_merge($efcr_out, $efcr_storage);
			}
				
			$position = array_search(self::APPLICATIONBLOCK, $efcr_out);
			array_splice($efcr_out, $position, 0, $storage_temp);
			$position = array_search(self::SECURITYMATCHBLOCK, $efcr_out);
			array_splice($efcr_out, $position, 0, $storage_match);
		}
		$efcr_out = deleteBlockHelper(self::APPLICATIONBLOCK, $efcr_out);
		$efcr_out = deleteBlockHelper(self::SECURITYMATCHBLOCK, $efcr_out);

		return $efcr_out;
	}

	function createPingTestUpgradeConfig($nodes_arr, $csde_type) {
		$templatedgwcgwconfig = [];
		$templatecgwdgwconfig = [];
		$templatedgwcgwverification = [];
		$templatecgwdgwverification = [];
		if ($csde_type == "pingtest") {
			$templatedgwcgwconfig = file(self::TEMPLATEDGWCGWCONFIG);
			$templatecgwdgwconfig = file(self::TEMPLATECGWDGWCONFIG);
			// $templatedgwcgwverification = file(self::TEMPLATEDGWCGWVERIFICATION);
			// $templatecgwdgwverification = file(self::TEMPLATECGWDGWVERIFICATION);
			
		} else {
			$templatedgwcgwconfig = file(self::TEMPLATEDGWCGWUPGRADE);
			$templatecgwdgwconfig = file(self::TEMPLATECGWDGWUPGRADE);
			// $templatedgwcgwverification = file(self::TEMPLATEDGWCGWVERIFICATION);
			// $templatecgwdgwverification = file(self::TEMPLATECGWDGWVERIFICATION);
		}
		
		function configGen($templateStr, $arr_search, $arr_values) {
			$out_str = [];
			foreach ($arr_values as $value) {
				$out_str[] = str_replace($arr_search, $value, $templateStr);
			}
			return $out_str;
		}
		function configGenMulti($templateStrArr, $arr_search, $arr_values) {
			$out_str = [];
			foreach ($arr_values as $value) {
				$out_str[] = str_replace($arr_search, $value, $templateStrArr);
			}
			return $out_str;
		}

		function getTemplatesValue($node_name, $templateStr, $node_arr) {
			$out_str = [];
			$node_val_res = [];
			$arr_coresponds = [
				'%NODE1%'		=> 'rcbinNode',
				'%INTERFACE11%' => 'rcbinIntName',
				'%INTERFACE%'	=> 'rcbinIntName',
				'%NODE2%'		=> 'csdeNode',
				'%INTERFACE21%'	=> 'csdeIntName',
				'%DGWNUMBER%'	=> 'rcbinIntSuff',
			];
			if (preg_match_all('/\%\w+\%/m', $templateStr, $matches, PREG_SET_ORDER) !== false) {
				// $out_str = array_column($matches, 0);
				$out_str = array_keys($arr_coresponds);
				foreach ($node_arr as $node_row) {
					$node_one_value = [];
					foreach ($out_str as $template_name) {
					// foreach ($arr_coresponds as $template_name => $unusedval) {
						if(!array_key_exists($template_name, $arr_coresponds)) {
							continue;
						}
						if (array_key_exists($arr_coresponds[$template_name], $node_row)) {
							$node_one_value[] = $node_row[$arr_coresponds[$template_name]];
						} else {
							$node_one_value[] = 'unset';
						}
					}
					$node_val_res[] = $node_one_value;
				}
			}
			return [$out_str, $node_val_res];
		}

		function createConfigOnTemplate($node_name, $node_arr, $config_file) {
			$one_line_template = '_ONE_LINE_';
			$multi_line_template = '_MULTI_LINE_';
			$uniq_line_template = '_UNIQ_LINE_';
			$out_str_dgw = [];
			$multiLineFlag = false;
			$multiLineStr = [];
			$templatesArr = [];
			$nodeArrValues = [];
			foreach ($config_file as $dgw_cgw_conf) {
				$dgw_cgw_conf_upper = strtoupper($dgw_cgw_conf);
				if ((strpos($dgw_cgw_conf_upper, $one_line_template) !== false) || (strpos($dgw_cgw_conf_upper, $uniq_line_template) !== false)) {
					$template_replace = $one_line_template;
					if (strpos($dgw_cgw_conf_upper, $uniq_line_template) !== false) {
						$template_replace = $uniq_line_template;
						[$templates_arr, $node_arr_values] = getTemplatesValue($node_name, $dgw_cgw_conf, array_slice($node_arr, 0, 1));
					} else {
						[$templates_arr, $node_arr_values] = getTemplatesValue($node_name, $dgw_cgw_conf, $node_arr);
					}
					
					$res_str = configGen(str_replace($template_replace, '', $dgw_cgw_conf), $templates_arr, $node_arr_values);
					array_push($out_str_dgw, $res_str);
				}
				elseif (strpos($dgw_cgw_conf_upper, $multi_line_template) !== false) {
					$multiLineFlag = true;
					[$templates_arr, $node_arr_values] = getTemplatesValue($node_name, $dgw_cgw_conf, $node_arr);
					if (count($templates_arr) != 0 && count($node_arr_values) != 0) {
						if (count(array_diff($templates_arr, $templatesArr)) != 0) {
							$templatesArr = array_merge($templatesArr, $templates_arr);
							$nodeArrValues = array_merge($nodeArrValues, $node_arr_values);
						}
					}
					$multiLineStr[] = str_replace($multi_line_template, '', $dgw_cgw_conf);
				} elseif ($multiLineFlag) {
					$res_str = configGen($multiLineStr, $templatesArr, $nodeArrValues);
					array_push($out_str_dgw, $res_str);
					$out_str_dgw[] = $dgw_cgw_conf;
					$multiLineFlag = false;
					$multiLineStr = [];
				} else {
					$out_str_dgw[] = $dgw_cgw_conf;
					$multiLineFlag = false;
					$multiLineStr = [];
				}
			}
			return $out_str_dgw;
		}

		$nodesList = [];
		foreach ($nodes_arr as $key => $value) {
			$cisco_interface = (($value['csde_int_type'] == '10') ? 'Te' : 'Hu') . $value['csde_int_number'];
			$jun_interface = (($value['rcbin_int_type'] == '10') ? 'xe' : 'et') . '-' . $value['rcbin_int_number'];
			$rcbinIntSuff = '00';
			if (preg_match('/DGW(\d+)B/', $value['rcbin_node'], $matches) == 1) {
				$rcbinIntSuff = $matches[1];
			}

			$nodesList[$value['rcbin_node']][] = [
				'rcbinNode'			=> $value['rcbin_node'],
				'rcbin_int_number'	=> $value['rcbin_int_number'],
				'rcbin_int_type'	=> $value['rcbin_int_type'],
				'csdeNode'			=> $value['csde_node'],
				'csde_int_number'	=> $value['csde_int_number'],
				'csde_int_type'		=> $value['csde_int_type'],
				'rcbinIntSuff'		=> $rcbinIntSuff,
				'rcbinIntName'		=> $jun_interface,
				'csdeIntName'		=> $cisco_interface,
			];
			$nodesList[$value['csde_node']][] = [
				'csdeNode'			=> $value['csde_node'],
				'csde_int_number'	=> $value['csde_int_number'],
				'csde_int_type'		=> $value['csde_int_type'],
				'rcbinNode'			=> $value['rcbin_node'],
				'rcbin_int_number'	=> $value['rcbin_int_number'],
				'rcbin_int_type'	=> $value['rcbin_int_type'],
				'rcbinIntSuff'		=> $rcbinIntSuff,
				'rcbinIntName'		=> $jun_interface,
				'csdeIntName'		=> $cisco_interface,
			];
		}
		
		$out_str_dgw_config = [];
		$out_str_cgw_config = [];
		$out_str_dgw_verification = [];
		$out_str_cgw_verification = [];
		$out_nodes_dgw = [];
		$out_nodes_cgw = [];
		foreach ($nodesList as $node_name => $node_arr) {
			if (strpos(strtolower($node_name), 'dgw') !== false) {
				$out_nodes_dgw = array_merge($out_nodes_dgw, $node_arr);
				array_push($out_str_dgw_config, createConfigOnTemplate($node_name, $node_arr, $templatedgwcgwconfig), ['', '']);
				array_push($out_str_dgw_verification, createConfigOnTemplate($node_name, $node_arr, $templatedgwcgwverification), ['', '']);
			} elseif (strpos(strtolower($node_name), 'cgw') !== false) {
				// $this->errorLog($node_name."\n".print_r($node_arr, true));
				$out_nodes_cgw = array_merge($out_nodes_cgw, $node_arr);
				array_push($out_str_cgw_config, createConfigOnTemplate($node_name, $node_arr, $templatecgwdgwconfig), ['', '']);
				array_push($out_str_cgw_verification, createConfigOnTemplate($node_name, $node_arr, $templatecgwdgwverification), ['', '']);
			}
		}

		function objectToArrayIterator($complexObject) {
			$rai_config = new \RecursiveArrayIterator($complexObject);
			$rii_config = new \RecursiveIteratorIterator($rai_config);
			$arr_from_iterator = [];
			foreach($rii_config as $value) {
				$arr_from_iterator[] = $value;
			}
			return $arr_from_iterator;
		}

		$arr_dgw_config = objectToArrayIterator($out_str_dgw_config);
		$arr_cgw_config = objectToArrayIterator($out_str_cgw_config);
		$arr_dgw_verification = objectToArrayIterator($out_str_dgw_verification);
		$arr_cgw_verification = objectToArrayIterator($out_str_cgw_verification);

		return [
			'dgw_config'	=> $arr_dgw_config,
			'dgw_verification'	=> $arr_dgw_verification,
			'cgw_config'		=> $arr_cgw_config,
			'cgw_verification'	=> $arr_cgw_verification,
			'nodes_list'	=> $out_nodes_dgw,
		];
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