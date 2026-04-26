<?php
namespace helperUtils;

class helperUtils {
	protected $superRights = [
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
	[
		'pageName' => 'MOR',
		'sectionName' => 'mor',
		'sectionAttr'	=> 'mor',
		'accessType'	=> 'admin',
	],
	[
		'pageName' => 'Settings',
		'sectionName' => 'settings',
		'sectionAttr'	=> 'settings',
		'accessType'	=> 'admin',
	]];
    function errorLog($error_message, $debug_mode = 1)
	{
		if ($debug_mode === 1)
		{
			error_log(date("Y-m-d H:i:s") . " ". $error_message);
		}
		return true;
	}
    function totalTrim($str) {
		$string = htmlentities($str, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'utf-8');
		$string = str_replace("&nbsp;", " ", $string);
		return trim(html_entity_decode($string));
	}

	function backquoteForTables($objTables) {
		$backquoted_fields = [];
		foreach ($objTables as $tableName => $tableFields) {
			$arr = array_map(function($field) use ($tableName) {
				return "`" . $tableName . "`." . "`" . $field . "`";
			}, $tableFields);
			$backquoted_fields[] = implode(',', $arr);
		}
		return implode(',', $backquoted_fields);
	}

	function isInt($val) {
		return filter_var($val, FILTER_VALIDATE_INT, ["flags" => FILTER_NULL_ON_FAILURE, "options" => ["min_range" => 1]]) ?? 0;
	}

	function getUploadedFileExt($uploadedFile) {
		return strtolower(pathinfo($uploadedFile, PATHINFO_EXTENSION));
	}

	//template methods

	static function addImageToDoc($extTemplateProcessor, $fieldImage) {
		if (isset($_FILES[$fieldImage['field_name']]) && is_uploaded_file($_FILES[$fieldImage['field_name']]['tmp_name'])) {
			try {
				$maxWidth = 600; 
				$maxHeight = 700;
				$size = getimagesize($_FILES[$fieldImage['field_name']]['tmp_name']);
				
				$sourceWidth = $size[0];
				$sourceHeight = $size[1];
				$ratioW = $maxWidth / $sourceWidth;
				$ratioH = $maxHeight / $sourceHeight;
				$scale = min($ratioW, $ratioH);
				if ($scale > 1) {
					$scale = 1;
				}
				$targetWidth = $sourceWidth * $scale; 
				$targetHeight = $sourceHeight * $scale;
				// $targetHeight = $targetWidth * ($sourceHeight / $sourceWidth);
				$extTemplateProcessor->setImageValue($fieldImage['field_name'], [
					'path' => $_FILES[$fieldImage['field_name']]['tmp_name'],
					'width' => $targetWidth, 
					'height' => $targetHeight,
					'ratio'  => true
				]);
			}
			catch (Exception $e) {
				$extTemplateProcessor->setValue($fieldImage['field_name'], $fieldImage['noFileMsg']);
			}
		} else {
			$extTemplateProcessor->setValue($fieldImage['field_name'], $fieldImage['noFileMsg']);
		}
	}

	static function setSimpleValueToDoc($extTemplateProcessor, $param, $value) {
		$extTemplateProcessor->setValue($param, htmlentities($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, "UTF-8"));
	}
	static function setArrayValuesToDoc($extTemplateProcessor, $param, $values) {
		foreach ($values as $val_idx => $val_arr) {

			// $instance = new self(); 
			// $instance->errorLog(print_r($val_arr, true));

			foreach ($val_arr as $par_name => $par_value) {
				$values[$val_idx][$par_name] = htmlentities($par_value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, "UTF-8");
			}
		}
		$extTemplateProcessor->cloneRowAndSetValues($param, $values);
	}
}