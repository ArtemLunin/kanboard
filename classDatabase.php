<?php
namespace mySQLDatabaseUtils;

class databaseUtils {
	private $unauthorized = true;
	private $root_access = false;
	private $pdo = null;
	private $initialRights = [[
		'pageName' => 'Main',
		'sectionName' => 'main',
		'sectionAttr'	=> 'main',
		'accessType'	=> 'admin',
	]];
	private $superRights = [[
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
		$sql = "SELECT id, user_name, password, user_rights FROM users WHERE user_name=:user";
		$row = $this->pdo->prepare($sql);
		$row->execute(['user' => $user]);
		$result = $row->fetch();
		if (isset($result['id']) && 
			(password_verify($password, $result['password']) || $storedSession === true))
		{
			$rights = json_decode($result['user_rights'], true);
			if ($rights) {
				$rights = array_filter($rights, array($this, 'hideNoAccessRights'));
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
	function hideNoAccessRights($user_rights) {
			return $user_rights['accessType'] != '';
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