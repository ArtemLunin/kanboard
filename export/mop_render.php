<?php
require 'vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor('template/mop_template.docx');

$filename = tempnam(sys_get_temp_dir(), 'docx');
$resultFileName = "work_mop.docx";
$implFile = false;

function getLinesFromTextArea($taText) {
    return array_filter(explode("\r\n", trim($taText)), function ($arrStr) {
        return strlen($arrStr);
    });
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
    if (is_array($value)) {
        $values = json_decode($value[0], true);
        $templateProcessor->cloneRowAndSetValues($param, $values);
    } elseif (!isset($arrayBlocks[$param])) {
        $templateProcessor->setValue($param, $value);
    } else {
        $replacements = [];
        $lines = getLinesFromTextArea($value);
        foreach ($lines as $line) {
            $replacements[] = [
                $arrayBlocks[$param]["taName"] => $line
            ];
        }
        $templateProcessor->cloneBlock($arrayBlocks[$param]["blockName"], 0, true, false, $replacements);
    }
    if ($param === 'projectDetail') {
        if (trim($value) !== '') {
            $resultFileName = $value.".docx";
        } else {
            $resultFileName = "untitled.docx";
        }
    }
}


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

header("Content-Type: application/vnd.ms-word; charset=utf-8");
header("Content-Disposition: attachment; filename=".$resultFileName);
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private", false);
$handle = fopen($filename, "r");
$contents = fread($handle, filesize($filename));
echo $contents;
}
