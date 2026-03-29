<?php
require 'vendor/autoload.php';
require_once 'db_conf.php';
require_once 'classDatabase.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$db_object = new mySQLDatabaseUtils\databaseUtilsMOP();

$templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor('template/ddp_template.docx');
$filename = tempnam(sys_get_temp_dir(), 'docx');
$resultFileName = "untitled";
$fileNamePart1 = $fileNamePart2 = "";

foreach ($_POST as $param => $value) {
    // $templateProcessor->setValue($param, htmlentities($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, "UTF-8"));
   
    if (is_array($value)) {
        // $values = json_decode($value[0], true);
        \helperUtils\helperUtils::setArrayValuesToDoc($templateProcessor, $param, json_decode($value[0], true));
    } else {
        \helperUtils\helperUtils::setSimpleValueToDoc($templateProcessor, $param, $value);
        $db_object->errorLog(print_r($param, true));
    }
    if ($param === 'ddpNumber') {
        if (trim($value) !== '') {
            $fileNamePart1 = $db_object->totalTrim($value);
            // $fileNamePart1 = htmlentities($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, "UTF-8");
        }
    }
}
//add diagrams to out
\helperUtils\helperUtils::addImageToDoc($templateProcessor, [
    'field_name' => 'diagram_hl',
    'width' => 400,
    'height' => 200,
    'noFileMsg' => 'N/A']);
\helperUtils\helperUtils::addImageToDoc($templateProcessor, [
    'field_name' => 'diagram_sl',
    'width' => 400,
    'height' => 200,
    'noFileMsg' => 'N/A']);
\helperUtils\helperUtils::addImageToDoc($templateProcessor, [
    'field_name' => 'diagram_hw',
    'width' => 800,
    'height' => 400,
    'noFileMsg' => '']);

if ($fileNamePart1 !== "") {
    $resultFileName = $fileNamePart1;
}

$resultFileName .= ".docx";
$templateProcessor->saveAs($filename);

header("Content-Type: application/vnd.ms-word; charset=utf-8");
    header("Content-Disposition: attachment; filename=".$resultFileName);
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private", false);
    $handle = fopen($filename, "r");
    $contents = fread($handle, filesize($filename));
    echo $contents;

}