<?php
require 'vendor/autoload.php';
require_once 'db_conf.php';
require_once 'classDatabase.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$db_object = new mySQLDatabaseUtils\databaseUtilsMOP();

$templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor('template/mop_template.docx');
$efcrFile = file('template/eFCR.txt');
$efcrFile2 = false;
$efcr_res = false;
$pingtest_dgw = false;
$pingtest_cgw = false;
$pingtest_dgw_ver = false;
$pingtest_cgw_ver = false;
$exportDGWConfig = false;
$nodes_list = [];

$filename = tempnam(sys_get_temp_dir(), 'docx');
$resultFileName = "untitled";
$fileNamePart1 = $fileNamePart2 = "";
$implFile = false;
$efcrFieldsArr = [];
$efcrOutput = [];
$ercfProcess = [];
$activityID = 0;
$counterMode = 0;

$dgw_file = '';
$conf_handle = null;

function getLinesFromTextArea($taText) {
    return array_filter(explode("\r\n", trim($taText)), function ($arrStr) {
        // return strlen($arrStr);
        return true;
    });
}

function genCMDBlock($arr_str, $blockName) {
    $cmd_out = [];
    foreach ($arr_str as $line) {
        // 'implementationCommandList'
        $cmd_out[] = [ $blockName => htmlentities($line, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, "UTF-8")];
    }
    return $cmd_out;
}

// $db_object->errorLog(print_r($_POST, true));

if (isset($_POST['efcrFields']) && $efcrFile) {
    $efcrFieldsArr = json_decode($_POST['efcrFields'], true);
    if (!$efcrFieldsArr || !(is_array($efcrFieldsArr)) || count($efcrFieldsArr) == 0) {
        $efcrFile = false;
    }
} else {
    $efcrFile = false;
}

if (isset($_POST['efcrFields2'])) {
    $efcrFile2 = true;
}

//search textarea fields for clone block
$arrayBlocks = [];
foreach ($_POST as $param => $value) {
    if (strpos($param, ":") !== false) {
        list($taName, $blockName) = explode(":", $param);
        $arrayBlocks[$param] = [
            "blockName" => $blockName,
            "taName"    => $taName,
        ];
    }
}

foreach ($_POST as $param => $value) {
    if (in_array($param, $efcrFieldsArr)) {
        $ercfProcess[$param] = $value;
        continue;
    }
    if ($param === 'activityID') {
        $activityID = (int)$value;
        continue;
    }
    if ($param === 'counterMode') {
        $counterMode = $value;
        continue;
    }
    if (is_array($value)) {
        $values = json_decode($value[0], true);
        if ($param == 'ceilAreaEFCR2') {
            $efcr_res = $db_object->exportEFCR($values);
        } elseif ($param == 'ceilAreacSDE') {
            $arr_dgw_cgw = $db_object->createPingTestConfig($values);
            $pingtest_dgw = $arr_dgw_cgw['dgw_config'];
            $pingtest_cgw = $arr_dgw_cgw['cgw_config'];
            $pingtest_dgw_ver = $arr_dgw_cgw['dgw_verification'];
            $pingtest_cgw_ver = $arr_dgw_cgw['cgw_verification'];
            $nodes_list = $arr_dgw_cgw['nodes_list'];
        } else {
            foreach ($values as $val_idx => $val_arr) {
                foreach ($val_arr as $par_name => $par_value) {
                    $values[$val_idx][$par_name] = htmlentities($par_value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, "UTF-8");
                }
            }
            $templateProcessor->cloneRowAndSetValues($param, $values);
        }
    } elseif (!isset($arrayBlocks[$param])) {
        $templateProcessor->setValue($param, htmlentities($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, "UTF-8"));
    } else {
        $replacements = [];
        $lines = getLinesFromTextArea($value);
        foreach ($lines as $line) {
            $replacements[] = [
                $arrayBlocks[$param]["taName"] => htmlentities($line, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, "UTF-8")
            ];
        }
        if((($efcrFile || $efcrFile2 || $pingtest_dgw || $pingtest_cgw) && $arrayBlocks[$param]["blockName"] == 'implementationCheckList'))// || 
        // (($pingtest_dgw_ver || $pingtest_cgw_ver) && $arrayBlocks[$param]["blockName"] == 'finalCheckList'))
        {
            continue;
        }
        $templateProcessor->cloneBlock($arrayBlocks[$param]["blockName"], 0, true, false, $replacements);
    }
    if ($param === 'projectNumber') {
        if (trim($value) !== '') {
            $fileNamePart1 = htmlentities($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, "UTF-8");
        }
    }
    if ($param === 'projectName') {
        if (trim($value) !== '') {
            $fileNamePart2 = htmlentities($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, "UTF-8");
        }
    }
}
if ($fileNamePart1 !== "" || $fileNamePart2 !== "") {
    $resultFileName = $fileNamePart1."-".$fileNamePart2;
}

$resultFileName .= ".docx";

if ($efcrFile) {
    $ip_arrs = json_decode($ercfProcess['ceilIP'], true);
    foreach ($efcrFile as $efcr_str) {
        $dipStartIndex = intval($ercfProcess['dipStartIndex']);
        foreach ($ip_arrs as $ip_address) {
            $dipStartIndex++;
            $new_str = str_replace([
                '%dipeFCRNumber%',
                '%dipCompanyName%',
                '%dipCountryName%',
                '%dipStartIndex%',
                '%ceilIP%'
            ], [
                $ercfProcess['dipeFCRNumber'],
                $ercfProcess['dipCompanyName'],
                $ercfProcess['dipCountryName'],
                $dipStartIndex,
                $ip_address['ceilIP'],
            ], $efcr_str);
            $efcrOutput[] = ['implementationCommandList' => htmlentities($new_str, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, "UTF-8")];
            if (strpos($efcr_str, '%dipStartIndex%') === false)
                break;
        }
    }
    $templateProcessor->cloneBlock('implementationCheckList', 0, true, false, $efcrOutput);
} elseif ($efcrFile2 && $efcr_res) {
    $added_text = "Show | compare  (Please check before commit to make sure output only has 'add/+', don't have any 'delete/-' which mean overlay with existing setup. If we do have 'delete/-', stop commit and contact Engineer to double check)";
    foreach ($efcr_res as $line) {
        $efcrOutput[] = ['implementationCommandList' => htmlentities($line, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, "UTF-8")];
    }
    $templateProcessor->cloneBlock('implementationCheckList', 0, true, false, $efcrOutput);
    $templateProcessor->setValue('FCR_addedText', htmlentities($added_text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, "UTF-8"));
} 
if ($pingtest_dgw) {
    $templateProcessor->cloneRowAndSetValues('rcbinNode', $nodes_list);
    $templateProcessor->cloneBlock('testPingNewInterfaces', 1, true, true);

    $templateProcessor->cloneBlock('implementationCheckList', 0, true, false, genCMDBlock(array_merge($pingtest_dgw, $pingtest_cgw), 'implementationCommandList'));
}
// if ($pingtest_dgw_ver) {
//     $templateProcessor->cloneBlock('finalCheckList', 0, true, false, genCMDBlock(array_merge($pingtest_dgw_ver, $pingtest_cgw_ver), 'finalCommandList'));
// }

$templateProcessor->setValue('FCR_addedText', htmlentities('', ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, "UTF-8"));
$templateProcessor->cloneBlock('testPingNewInterfaces', 0, true, true);

$templateProcessor->setValues(array(
    'revisionDate' => date("F j, Y"), 
    'originalReleaseDate' => date("F j, Y")
));

if (isset($_FILES['diagram']) && is_uploaded_file($_FILES['diagram']['tmp_name'])) {
    try {
        $templateProcessor->setImageValue('diagram', array(
            'path' => $_FILES['diagram']['tmp_name'],
            'width' => 400, 'height' => 200,
        ));
    }
    catch (Exception $e) {
        $templateProcessor->setValue('diagram', '');
    }
} else {
    $templateProcessor->setValue('diagram', '');
}

if (isset($_FILES['implFile']) && is_uploaded_file($_FILES['implFile']['tmp_name'])) {
    $implFileType = strtolower(pathinfo($_FILES['implFile']['name'], PATHINFO_EXTENSION));
    if ($implFileType === 'txt') {
        $implFile = file($_FILES['implFile']['tmp_name']);
    } elseif ($implFileType === 'docx') {
        $implFile = true;
    }
}

$checkedBox = '☒';

$templateProcessor->setValues([
    'cb1_2' => $checkedBox,
    'cb2_2' => $checkedBox,
    'cb3_2' => $checkedBox,
    'cb4_2' => $checkedBox,
    'cb5_2' => $checkedBox,
    'cb6_2' => $checkedBox,
    'cb7_2' => $checkedBox,
    'cb8_2' => $checkedBox,
    'cb9_2' => $checkedBox,
    'cb10_2' => $checkedBox,
    'cb11_2' => $checkedBox,
    'cb12_2' => $checkedBox,
    'cb13_2' => $checkedBox,
    'cb14_2' => $checkedBox,
    ]);

$templateProcessor->saveAs($filename);

if ($counterMode !=0 ) {
    $db_object->incActivityCounter($activityID, $counterMode);
}
// $db_object->getActivitiesCounter($activityID);

// write file to embedded docx
if ($implFile != false) {
    $implFileDoc = tempnam(sys_get_temp_dir(), 'docx');
    
    if ($implFileType === 'txt') {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        foreach ($implFile as $txt_str) {
            $section->addText($txt_str);
        }
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($implFileDoc);
    } elseif ($implFileType === 'docx') {
        copy($_FILES['implFile']['tmp_name'], $implFileDoc);
    }

    $sourceZip = new \ZipArchive();
    $sourceZip->open($filename);
    $sourceZip->deleteName('word/embeddings/oleObject1.docx');
    $sourceZip->addFile($implFileDoc, 'word/embeddings/oleObject1.docx');

} else {
    $sourceZip = new \ZipArchive();
    $sourceZip->open($filename);
    $sourceDocument = $sourceZip->getFromName('word/document.xml');
    $sourceDom = new DOMDocument();
    $sourceDom->loadXML($sourceDocument);
    $sourceXPath = new \DOMXPath($sourceDom);

    $sourceXPath->registerNamespace("w", "http://schemas.openxmlformats.org/wordprocessingml/2006/main");
    try {
        $oleNodes = $sourceXPath->query('//o:OLEObject');
        $shapeNodes = $sourceXPath->query('//v:shape[v:imagedata]');
        
        $shapeID = $oleNodes[0]->getAttribute("ShapeID");
        $shape_id = $shapeNodes[0]->getAttribute("id");
        
        if ($shapeID == $shape_id) {
            $oleNodes[0]->parentNode->removeChild($oleNodes[0]);
            $shapeNodes[0]->parentNode->removeChild($shapeNodes[0]);
            $sourceZip->addFromString('word/document.xml', $sourceDom->saveXML());
            $sourceZip->deleteName('word/embeddings/_________Microsoft_Word.docx');
            $sourceZip->deleteName('word/embeddings/oleObject1.docx');
            $sourceZip->deleteName('word/media/image2.emf');
            $sourceZip->deleteName('word/media/image1.bmp');
        }
    } catch (Throwable $e) {

    }
}
$sourceZip->close();

if ($exportDGWConfig !== false) {
    header("Content-Type: text/plain; charset=utf-8");
    header("Content-Disposition: attachment; filename=dgw_config.txt");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private", false);
    $handle = fopen($dgw_file, "r");
    $contents = fread($handle, $exportDGWConfig);
    echo $contents;
} else {
    header("Content-Type: application/vnd.ms-word; charset=utf-8");
    header("Content-Disposition: attachment; filename=".$resultFileName);
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private", false);
    $handle = fopen($filename, "r");
    $contents = fread($handle, filesize($filename));
    echo $contents;
}
}
