<?php
require_once 'db_conf.php';
mb_internal_encoding("UTF-8");

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}

set_error_handler('exceptions_error_handler');

$out_res = [];
$param_error_msg['answer'] = [];

class databaseUtils {
    private $pdo = null;

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
	
}

$paramJSON = json_decode(file_get_contents("php://input"), TRUE);
$method = $paramJSON['method'] ?? $_REQUEST['method'] ?? 0;
$value = $paramJSON['value'] ?? $_REQUEST['value'] ?? 0;
$parentId = $paramJSON['parentId'] ?? $_REQUEST['parentId'] ?? 0;
$id = $paramJSON['id'] ?? $_REQUEST['id'] ?? 0;

$db_object = new databaseUtils();

if ($method !== 0)
{
    if ($method === 'getOGPA') {
        $param_error_msg['answer'] = $db_object->getOGPA();
    } elseif ($method === 'getOGPAActivity' && $value && is_string($value)) {
		$param_error_msg['answer'] = $db_object->getOGPAActivity($value);
	} elseif ($method === 'addPrimeElement' && $value && is_string($value)) {
		$param_error_msg['answer'] = $db_object->addPrimeElement($value);
	} elseif ($method === 'modPrimeElement' && $value && is_string($value) && $id) {
		$param_error_msg['answer'] = $db_object->modPrimeElement($value, $id);
	} elseif ($method === 'addActivity' && $value && $parentId) {
		$param_error_msg['answer'] = $db_object->addActivity($value, $parentId);
	} elseif ($method === 'modActivity' && $value && is_string($value) && $id && $parentId) {
		$param_error_msg['answer'] = $db_object->modActivity($value, $id,  $parentId);
	} elseif ($method === 'delPrimeElement' && $value && is_string($value)) {
		$param_error_msg['answer'] = $db_object->delPrimeElement($value);
	} elseif ($method === 'delActivity' && $value && is_string($value)) {
		$param_error_msg['answer'] = $db_object->delActivity($value);
	} elseif ($method === 'getActivityFields' && $id) {
		$param_error_msg['answer'] = $db_object->getActivityFields($id);
	} elseif ($method === 'setActivityFields' && $id && is_array($value)) {
		$param_error_msg['answer'] = $db_object->setActivityFields($value, $id);
	}
    $out_res = ['success' => $param_error_msg];
}
header('Content-type: application/json');
echo json_encode($out_res);
?>