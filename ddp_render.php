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
    $templateProcessor->setValue($param, htmlentities($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, "UTF-8"));
    if ($param === 'ddpNumber') {
            if (trim($value) !== '') {
                $fileNamePart1 = htmlentities($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, "UTF-8");
            }
        }
}
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