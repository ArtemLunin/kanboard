<?php
session_start();
require_once "db_automator.php";
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$param_error_count=0;
$param_error_msg=[];
$output_zip=FALSE;

$out_res=[];
if (isset ($_REQUEST['doUploadTemplate']))
{
	if (isset($_FILES['templateFile']))
	{
		if  (is_uploaded_file($_FILES['templateFile']['tmp_name']))
		{
			try
			{
				move_uploaded_file($_FILES['templateFile']['tmp_name'], 'templates/'.$_FILES['templateFile']['name']);
				$sql_ins="INSERT INTO templates (template_file) VALUES (:template);";
				$row_ins=$pdo->prepare($sql_ins);
				$row_ins->execute(['template'=>$_FILES['templateFile']['name']]);
				$out_res=array('answer'=>'template uploaded:'.$_FILES['templateFile']['name']);
			}
			catch (PDOException $e) {
				if(preg_match('/Duplicate entry/i', $e->getMessage())==1)
				{
					//setError('sql', 'such template already loaded');
					$out_res=array('answer'=>'template uploaded:'.$_FILES['templateFile']['name']);
				}
				else
				{
					setError('sql', $e->getMessage());
					$out_res=array('error'=>$param_error_msg);
				}
			}
		}
	}
}
if (isset ($_REQUEST['doUploadDevices']))
{
	if (isset($_FILES['devicesFile']))
	{
		if  (is_uploaded_file($_FILES['devicesFile']['tmp_name']))
		{
			try
			{
				$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
				$reader->setReadDataOnly(true);
				$spreadsheet = $reader->load($_FILES['devicesFile']['tmp_name']);
				$worksheet = $spreadsheet->getActiveSheet();
				$amount_cells=$count_cells=0;
				$main_row=0;
				$arr_param_ids=[];
				$arr_devices=[];
				$arr_param_ids_base=[];
				$no_param_check=FALSE;
				$arr_no_params=[];
				//get parameters name from database 
				$params_not_exists=FALSE;
				$sql_get_params = "SELECT distinct(parameter_id) FROM devices ORDER BY parameter_id;";
				$row_sel_param = $pdo->prepare($sql_get_params);
				$row_sel_param->execute();
				
				if ($table_res=$row_sel_param->fetchall())
				{
					foreach ($table_res as $row_)
					{
						$arr_param_ids_base[]=$row_['parameter_id'];
					}
				}
				foreach ($worksheet->getRowIterator() as $row) 
				{
					$count_cells=0;
					$cellIterator = $row->getCellIterator();
					$cellIterator->setIterateOnlyExistingCells(FALSE);
					foreach ($cellIterator as $cell)
					{
						$cell_val=@$cell->getValue();
						$count_cells++;
						if (!$main_row) 
						{
							if (isset($cell_val) && $cell_val!=='')
							{
								if (preg_match('/^#?(\d+)$/',$cell_val,$res_ids))
								{
									$amount_cells++;
									if (array_search($res_ids[1], $arr_param_ids)===FALSE)
									{
										$arr_param_ids[]=$res_ids[1];
									}
									else
									{
										$no_param_check=TRUE;
										$arr_no_params[]=$res_ids[1].": repeat";							    			
									}
									$key_id_in_base=array_search($res_ids[1], $arr_param_ids_base);
									if ($key_id_in_base===FALSE) 
									{
										addParam($res_ids[1]);
										$arr_param_ids_base[]=$res_ids[1];
									}
									
								}
								else
								{
									$no_param_check=TRUE;
									$arr_no_params[]=$cell_val.": ID missing";
								}
							}
						}
						elseif ($count_cells<=$amount_cells)
						{
							$arr_devices[$main_row-1][]=$cell_val; // start from 0 idx
						}
					}
					$main_row++;
				}
				if (!$no_param_check)
				{
					$pdo->beginTransaction();
					$nextDeviceID=1;
					$device_id_base=[];
					$param_value_base=[];
					$sql_get_max_dev_id="SELECT device_id AS netx_id FROM devices ORDER BY device_id DESC LIMIT 1;";
					$row_get_max=$pdo->prepare($sql_get_max_dev_id);
					$row_get_max->execute();
					if ($table_res=$row_get_max->fetch())
					{
						$nextDeviceID=(int)$table_res['netx_id']+1;
					}
					//for check if device with hostname exists (parameter_id==1)
					$sql_get="SELECT device_id, parameter_value FROM devices WHERE parameter_id=1 ORDER BY device_id;";
					$row_get=$pdo->prepare($sql_get);
					$row_get->execute();
					// get devices from database
					if ($table_res=$row_get->fetchall())
					{
						foreach ($table_res as $row_)
						{
							$device_id_base[]=$row_['device_id'];
							$param_value_base[]=$row_['parameter_value'];
						}
					}
					$sql_ins_device="INSERT INTO devices (device_id, parameter_id, parameter_value) VALUES (:device_id, :param_id, :param_value);";
					$sql_upd_device="UPDATE devices SET parameter_value=:param_value WHERE device_id=:device_id AND parameter_id=:param_id;";
					$row_add=$pdo->prepare($sql_ins_device);
					$row_upd=$pdo->prepare($sql_upd_device);
					$update_flag=0;
					$device_update_id=0;
					foreach ($arr_devices as $key => $data_device) {
						foreach ($data_device as $param_idx => $value) 
						{
							if ($arr_param_ids[$param_idx]==1 && ($find_key=array_search($value, $param_value_base))!==FALSE)
							{
								$update_flag=1;
								$device_update_id=$device_id_base[$find_key];
							}
							if ($update_flag)
							{
									$row_upd->execute([
										'param_value'	=> $value,
										'device_id'		=> $device_update_id, 
										'param_id'		=> $arr_param_ids[$param_idx],
									
									]);
							}
							else
							{
								$row_add->execute([
									'device_id'		=> $nextDeviceID, 
									'param_id'		=> $arr_param_ids[$param_idx],
									'param_value'	=> $value
								]);
							}
						}
						if (!$update_flag) $nextDeviceID++;
						$update_flag=0;
						$device_update_id=0;
					}
					$out_res=array('answer'=>'1');
					$pdo->commit();
				}
				else
				{
					$out_res=array('error'=>array('no param in database' => $arr_no_params));
				}
			}
			catch (PDOException $e) 
			{
				setError('sql', $e->getMessage());
				$out_res=array('error'=>$param_error_msg);
				$pdo->rollBack();
			}
		}
	}
}
elseif (isset($_REQUEST['doGetTemplates']))
{
	$arr_templates=[];
	$sql_get="SELECT id, template_file FROM templates ORDER BY template_file;";
	$row_get=$pdo->prepare($sql_get);
	$row_get->execute();
	if ($table_res=$row_get->fetchall())
	{
		foreach ($table_res as $row_)
		{
			$arr_templates[]=array($row_['id'] , $row_['template_file']);
		}
	}
	$out_res=array('answer'=>$arr_templates);
}
elseif (isset($_REQUEST['doShowAllDevives']))
{
	$arr_devices_param_name = [];
	$arr_devices_param_ids = [];
	$arr_devices_param_value = [];
	$min_device_id = 0;
	$first_device = true;
	$sql_get = "SELECT dev.id, dev.device_id, dev.parameter_id, dev.parameter_value FROM devices AS dev ORDER BY dev.device_id ASC, dev.parameter_id ASC;";
	$row_get = $pdo->prepare($sql_get);
	$row_get->execute();
	if ($table_res = $row_get->fetchall())
	{
		foreach ($table_res as $row_)
		{
			if ($first_device)
			{
				$min_device_id = (int)$row_['device_id'];
				$first_device = false;
			}
			if ($row_['device_id'] == $min_device_id)
			{
				$arr_devices_param_ids[] = '#'.$row_['parameter_id'];
			}
			$arr_devices_param_value[$row_['device_id']][] = $row_['parameter_value'];
		}
	}
	$out_res['answer'] = array($arr_devices_param_ids, $arr_devices_param_value);
}
elseif (isset($_REQUEST['doGetMaxParamID']))
{
	$maxParamID=0;
	$sql_get="SELECT parameter_id FROM devices ORDER BY parameter_id DESC LIMIT 1";
	$row_get=$pdo->prepare($sql_get);
	$row_get->execute();
	if ($table_res=$row_get->fetch())
	{
		$maxParamID=(int)$table_res['parameter_id']+1;
	}
	$out_res['answer']=$maxParamID;
}
elseif (isset($_REQUEST['doAddParam']))
{
	$param_id=0;
	$param_name='';
	if (isset($_REQUEST['paramID']) && preg_match('/^#*(\d+)$/', $_REQUEST['paramID'],$res_ids))
	{
		$param_id=$res_ids[1];
	}
	/*
	if (isset($_REQUEST['paramName']) && strlen(trim($_REQUEST['paramName']))>0)
	{
		$param_name=trim($_REQUEST['paramName']);
	}
	*/
	if ($param_id)// && $param_name!='')
	{
		try 
		{
			$pdo->beginTransaction();
			//$sql_ins="INSERT INTO parameters (parameter_id, parameter_name) VALUES (:param_id, :param_name);";
			//$sql_ins="INSERT INTO parameters (parameter_id) VALUES (:param_id);";
			//$row_ins=$pdo->prepare($sql_ins);
			//$row_ins->execute([
			//	'param_id'		=> $param_id,
			////	'param_name'	=> htmlentities($param_name, ENT_QUOTES),
			//]);
			$sql_proc="CALL add_new_parameter(:param_id);";
			$row_proc=$pdo->prepare($sql_proc);
			$row_proc->execute(['param_id'=>$param_id]);
			$pdo->commit();
			$out_res['answer']='success';
			
		} catch (PDOException $e) {
			$pdo->rollBack();
			if(preg_match('/Duplicate entry/i', $e->getMessage())==1)
			{
				//skip this error
				//$out_res['answer']='success';
				setError('sql', 'such parameter ID already exists');
				$out_res=array('error'=>$param_error_msg);
			}
			else 
			{
				setError('sql', $e->getMessage());
				$out_res=array('error'=>$param_error_msg);
			}
		}
	}
	else
	{
		setError('parameters','do not satisfy the requirements');
		$out_res=array('error'=>$param_error_msg);
	}
}
elseif (isset($_REQUEST['doDeleteDevices']))
{
	$sql_del="DELETE FROM devices WHERE device_id=:device_id;";
	$row_del=$pdo->prepare($sql_del);
	if (isset($_REQUEST['devices_id']))
	{
		$devices_deleted=[];
		$arr_id=json_decode($_REQUEST['devices_id']);
		try
		{
			foreach ($arr_id as $key => $value) {
				if(preg_match('/^\d+$/', $value)==1)
				{
					$row_del->execute(['device_id' => $value]);
					$devices_deleted[]=$value;
				}
			}
			$out_res['answer']=$devices_deleted;
		}
		catch (PDOException $e) {
				setError('sql', $e->getMessage());
			$out_res=array('error'=>$param_error_msg);
		}
	}
}
elseif (isset($_REQUEST['doDownloadTemplate']))
{
	$arr_param=[];
	//$sql_sel="SELECT devices.device_id, devices.parameter_value FROM devices, parameters WHERE parameters.parameter_name='template_file' AND parameters.parameter_id=devices.parameter_id AND devices.device_id=:device_id;";
	//parameter_id=1 - template_file_name
	//parameter_id=2 - hostname
	$sql_sel="SELECT devices.device_id, devices.parameter_value FROM devices WHERE devices.parameter_id=2 AND devices.device_id=:device_id;";
	$sql_sel_params="SELECT parameter_id, parameter_value FROM devices WHERE device_id=:device_id ORDER BY parameter_id ASC;";
	$row_sel=$pdo->prepare($sql_sel);
	if (isset($_REQUEST['devices_id']) && $_REQUEST['devices_id']!="")
	{
		$devices_config=[];
		$arr_id=json_decode($_REQUEST['devices_id']);
		try
		{
			foreach ($arr_id as $key => $value) 
			{
				if(preg_match('/^\d+$/', $value)==1)
				{
					$row_sel_param=$pdo->prepare($sql_sel_params);
					$row_sel_param->execute(['device_id' => $value]);
					if ($table_param=$row_sel_param->fetchall())
					{
						foreach ($table_param as $row_param) {
							$arr_param[$row_param['parameter_id']]=$row_param['parameter_value'];
						}
					}
					$row_sel->execute(['device_id' => $value]);
					if ($table_res=$row_sel->fetchall())
					{
						foreach ($table_res as $row_)
						{
							$file_template=@fopen('templates/'.$row_['parameter_value'],'r');
							if ($file_template) 
							{
								$file_config=fopen('config_files/config_'.$arr_param[1].".txt",'w');
							    while (($buffer = fgets($file_template)) !== false) {
							        if (preg_match_all('/(\{#(\d+)\})/', $buffer, $matches))
							        {
							        	foreach ($matches[2] as $key => $value) 
							        	{
							        		if (isset($arr_param[$value]))
							        		{
							        			$buffer=str_replace($matches[1][$key], $arr_param[$value], $buffer);	
							        		}
							        	}
							        }
							        fwrite($file_config, $buffer);
							    }
							    fclose($file_config);
							    $devices_config[]='config_files/config_'.$arr_param[1].".txt";
							    fclose($file_template);
							}
							else
							{
								setError('no template file', $row_['parameter_value']);
								$out_res=array('error'=>'no template file:'.$row_['parameter_value']);
							}
							

						}
					}
				}
				//$output_zip=TRUE;
			}
			if (!$param_error_count)
			{
							$zip = new ZipArchive();
							$filename_zip = 'config_files/configs.zip';
							@unlink($filename_zip);
							if ($zip->open($filename_zip, ZipArchive::CREATE)===TRUE) 
							{
								foreach ($devices_config as $value) {
									$zip->addFile($value);
									//@unlink($value);
								}
								$zip->close();
								$out_res=array('answer'=>'./'.$filename_zip);
								//$output_zip=TRUE;
								foreach ($devices_config as $value) {
								//	$zip->addFile($value);
									@unlink($value);
								}
							}
							else
							{
								setError('zip', 'unable to create zip file');
							}
			}
			
    	}
		catch (PDOException $e) 
		{
			setError('sql', $e->getMessage());
			$out_res=array('error'=>$param_error_msg);
		}
	}
	else
	{
		setError('no devices selected', '');
	}
}
elseif (isset($_REQUEST['doDeleteTemplates']))
{
	if (isset($_REQUEST['templates_id']) && $_REQUEST['templates_id']!="")
	{
		$arr_templates=[];
		$arr_id=json_decode($_REQUEST['templates_id']);
		$sql_del="DELETE FROM templates WHERE id=:template_id;";
		$sql_sel="SELECT template_file FROM templates WHERE id=:template_id;";
		$row_sel=$pdo->prepare($sql_sel);
		$row_del=$pdo->prepare($sql_del);
		try
		{
			foreach ($arr_id as $key => $value) 
			{
				if(preg_match('/^\d+$/', $value)==1)
				{
					$row_sel->execute(['template_id'=>$value]);
					if ($row_=$row_sel->fetch())
					{
						@unlink('templates/'.$row_['template_file']);
						$row_del->execute(['template_id'=>$value]);
					}
				}
			}
			$out_res=array('answer'=>'1');
		}
		catch (PDOException $e) 
		{
			setError('sql', $e->getMessage());
			$out_res=array('error'=>$param_error_msg);
		}
	}
	else
	{
		setError('no templates selected', '');
		$out_res=array('error'=>$param_error_msg);
	}
}

header('Content-type: application/json');
echo json_encode($out_res);


function setError($error_key, $error_value)
{
	global $param_error_count, $param_error_msg;
	$param_error_count++;
	$param_error_msg[]=array($error_key => $error_value);
}

function addParam($new_param_id)
{
	global $pdo;
	global $param_error_count, $param_error_msg;
	try 
	{
		$pdo->beginTransaction();
		$sql_proc="CALL add_new_parameter(:param_id);";
		$row_proc=$pdo->prepare($sql_proc);
		$row_proc->execute(['param_id'=>$new_param_id]);
		$pdo->commit();
		$out_res['answer']='success';
	} 
	catch (PDOException $e) {
			$pdo->rollBack();
			if(preg_match('/Duplicate entry/i', $e->getMessage())==1)
			{
				//skip this error
				$out_res['answer']='success';
			}
			else 
			{
				setError('sql', $e->getMessage());
				$out_res=array('error'=>$param_error_msg);
			}
	}
	return $out_res;
}
?>
