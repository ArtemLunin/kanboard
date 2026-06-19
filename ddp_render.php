<?php
// ini_set('session.use_cookies',1);
// ini_set('session.use_only_cookies',0);
// ini_set('session.use_trans_sid',1);
// session_name('kanboardSession');
// session_start();

require 'vendor/autoload.php';
require_once 'db_conf.php';
require_once 'classDatabase.php';
// require_once 'classProjects.php';

// $project_object = new myProjectUtils\ProjectUtils();
// if (!isset($_SESSION['logged_user'])) {
//     $param_error_msg['answer'] = 'Unauthorized';
//     $out_res = ['error' => $param_error_msg];
//     header('Content-type: application/json');
//     echo json_encode($out_res);
//     exit;
// } else {
//     $userID = $project_object->getUserID($_SESSION['logged_user']);
//     if (isset($_SESSION['SUPER_USER']) && $_SESSION['SUPER_USER']) {
//         $project_object->setRootAccess(true);
//     } else {
//         $project_object->setRootAccess(false);
//     }
// }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_object = new mySQLDatabaseUtils\databaseUtilsMOP();

    $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor('template/ddp_template.docx');
    $filename = tempnam(sys_get_temp_dir(), 'docx');
    $resultFileName = "untitled";
    $fileNamePart1 = $fileNamePart2 = "";
    $groupID = $projectID = 0;
    $fieldGroups = [];

    foreach ($_POST as $param => $value) {
        // $fieldsArr = [];
        if ($param === 'ddpGroupID') {
            $groupID = $db_object->isInt($value);
            continue;
        }
        if ($param === 'ddpProjectID') {
            $projectID = $db_object->isInt($value);
            continue;
        }
        if ($param === 'ddpProjectDate') {
            $ddpProjectDate = $value;
            continue;
        }
        if ($param === 'ddpNumber') {
            if (trim($value) !== '') {
                $fileNamePart1 = $db_object->totalTrim($value);
            }
        }
        if (is_array($value)) {
            \helperUtils\helperUtils::setArrayValuesToDoc($templateProcessor, $param, json_decode($value[0], true));
            $fieldGroups[] = ['fields' => [
                "fieidID"   => $param,
                "fieldName" => $param,
                "default"   => json_decode($value[0], true),
            ]];
        } else {
            \helperUtils\helperUtils::setSimpleValueToDoc($templateProcessor, $param, $value);
            $fieldGroups[] = ['fields' => [
                "fieidID"   => $param,
                "fieldName" => $param,
                "default"   => $value,
            ]];
        }
    }
    //add diagrams to out
    \helperUtils\helperUtils::addImageToDoc($templateProcessor, [
        'field_name' => 'diagram_hl',
        'width' => 600,
        'height' => 200,
        'noFileMsg' => 'N/A']);
    \helperUtils\helperUtils::addImageToDoc($templateProcessor, [
        'field_name' => 'diagram_sl',
        'width' => 600,
        'height' => 200,
        'noFileMsg' => 'N/A']);
    \helperUtils\helperUtils::addImageToDoc($templateProcessor, [
        'field_name' => 'diagram_hw',
        'width' => 600,
        'height' => 400,
        'noFileMsg' => 'N/A']);


    if ($fileNamePart1 !== "") {
        $resultFileName = $fileNamePart1;
    }

    // $resultFileName .= ".docx";
    $resultFileName = "Pre-DDP.docx";
    $templateProcessor->saveAs($filename);

    $uploadedFiles = $_FILES['rack_layout'];
    $count = isset($uploadedFiles['name']) ? count($uploadedFiles['name']) : 0;

    $zip = new \ZipArchive();
    if ($zip->open($filename) !== TRUE) {
        die("Не удалось открыть файл.");
    }

    $docXml = $zip->getFromName('word/document.xml');
    $dom = new DOMDocument();
    $dom->loadXML($docXml);
    $xpath = new DOMXPath($dom);

    $xpath->registerNamespace("w", "http://schemas.openxmlformats.org/wordprocessingml/2006/main");
    $xpath->registerNamespace("v", "urn:schemas-microsoft-com:vml");
    $xpath->registerNamespace("o", "urn:schemas-microsoft-com:office:office");
    $xpath->registerNamespace("r", "http://schemas.openxmlformats.org/officeDocument/2006/relationships");

    $bookmarkDoc = $xpath->query("//w:bookmarkStart[@w:name='RACK_LAYOUT_PREVIEW']")->item(0)->parentNode;
    $flooPlan = $xpath->query("//w:bookmarkStart[@w:name='FLOOR_PLAN']")->item(0)->parentNode;
    $edsFile = $xpath->query("//w:bookmarkStart[@w:name='EDS_FILE']")->item(0)->parentNode;
    $basicMopFile = $xpath->query("//w:bookmarkStart[@w:name='BASIC_MOP_FILE']")->item(0)->parentNode;

    $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
    $relsDom = new DOMDocument();
    $relsDom->loadXML($relsXml);

    $docx_object = new helperUtils\DocxProcessor($dom, $relsDom, $xpath, $zip, $db_object);
    $docx_object->embedOleAttachments($docx_object->prepareUploads($_FILES['rack_layout']), 'word-48.png', 'docx', $bookmarkDoc);
    $docx_object->embedOleAttachments($docx_object->prepareUploads($_FILES['floor_plan']), 'word-48.png', 'docx', $flooPlan);
    $docx_object->embedOleAttachments($docx_object->prepareUploads($_FILES['edsFile']), 'word-48.png', 'docx', $edsFile);
    $docx_object->embedOleAttachments($docx_object->prepareUploads($_FILES['basicMopFile']), 'word-48.png', 'docx', $basicMopFile);
    $docx_object->saveToZip();

    $zip->close();

    // if ($groupID !== 0 && $projectID !== 0) { //save file to base
    //     $project_object->changeProjectActivity($projectID, $groupID, [
    //         'field_json_props' => $fieldGroups,
    //         'target_date'       => $ddpProjectDate,
    //         ], '', '');
    //     $out_res = [];
    //     $param_error_msg['answer'] = ['OK'];
    //     $out_res = ['success' => $param_error_msg];
    //     header('Content-type: application/json');
    //     echo json_encode($out_res);
    // } else { // download file
        header("Content-Type: application/vnd.ms-word; charset=utf-8");
        header("Content-Disposition: attachment; filename=".$resultFileName);
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Cache-Control: private", false);
        $handle = fopen($filename, "r");
        $contents = fread($handle, filesize($filename));
        echo $contents;
    // }
}
