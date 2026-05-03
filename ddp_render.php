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
    if (is_array($value)) {
        \helperUtils\helperUtils::setArrayValuesToDoc($templateProcessor, $param, json_decode($value[0], true));
    } else {
        \helperUtils\helperUtils::setSimpleValueToDoc($templateProcessor, $param, $value);
    }
    if ($param === 'ddpNumber') {
        if (trim($value) !== '') {
            $fileNamePart1 = $db_object->totalTrim($value);
        }
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
    'noFileMsg' => '']);


if ($fileNamePart1 !== "") {
    $resultFileName = $fileNamePart1;
}

$resultFileName .= ".docx";
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

$contentTypesXml = $zip->getFromName('[Content_Types].xml');
if (strpos($contentTypesXml, 'Extension="bin"') === false) {
    $ctDom = new DOMDocument();
    $ctDom->loadXML($contentTypesXml);
    $newNode = $ctDom->createElement('Default');
    $newNode->setAttribute('Extension', 'bin');
    $newNode->setAttribute('ContentType', 'application/vnd.openxmlformats-officedocument.oleObject');
    $ctDom->documentElement->appendChild($newNode);
    $zip->addFromString('[Content_Types].xml', $ctDom->saveXML());
}

$zip->close();

header("Content-Type: application/vnd.ms-word; charset=utf-8");
header("Content-Disposition: attachment; filename=".$resultFileName);
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private", false);
$handle = fopen($filename, "r");
$contents = fread($handle, filesize($filename));
echo $contents;
}

// function addRel($dom, $id, $type, $target) {
//     $root = $dom->documentElement;
//     $rel = $dom->createElement('Relationship');
//     $rel->setAttribute('Id', $id);
//     $rel->setAttribute('Type', $type);
//     $rel->setAttribute('Target', str_replace('word/', '', $target));
//     $root->appendChild($rel);
// }

// /**
//  * Function for dynamic embedding of various file types as OLE objects.
//  * * @param array $uploadedFiles Array from $_FILES['input_name']
//  * @param string $iconFileName Name of the icon file in the assets folder
//  * @param string $fileType Expected file extension (e.g., 'docx')
//  * @param DOMNode $targetParagraph The paragraph node found via bookmark search
//  */
// function embedOleAttachments($uploadedFiles, $iconFileName, $fileType, $targetParagraph) {
//     global $dom, $relsDom, $xpath, $zip, $db_object;
//     if (!$targetParagraph || !$targetParagraph->parentNode) {
//         return;
//     }
//     /** @var DOMNode $documentBody Parent node where new paragraphs will be inserted */
//     $documentBody = $targetParagraph->parentNode;
//     $count = isset($uploadedFiles['name']) ? count($uploadedFiles['name']) : 0;
//     $iconPath = 'img/' . $iconFileName;
//     if ($count > 0) {
//         for ($i = 0; $i < $count; $i++) {
//             // Skip files with upload errors
//             if ($uploadedFiles['error'][$i] !== UPLOAD_ERR_OK) {
//                 continue;
//             }
//             // Validate file extension
//             if ($db_object->getUploadedFileExt($uploadedFiles['name'][$i]) !== $fileType) {
//                 continue;
//             }

//             /** @var string $uniqueIndex Identifier generated to avoid XML ID collisions */
//             $uniqueIndex = time() . "_" . $i . "_" . uniqid(); 
//             $tempFilePath = $uploadedFiles['tmp_name'][$i];
//             $originalFileName = $uploadedFiles['name'][$i];
            
//             /** Define internal paths within the OpenXML archive structure */
//             $internalOlePath = "word/embeddings/oleObject{$uniqueIndex}.{$fileType}";
//             $internalImagePath = "word/media/image_icon_{$uniqueIndex}.png";

//             // Inject files into the ZIP archive
//             $zip->addFile($tempFilePath, $internalOlePath);
//             if (file_exists($iconPath)) {
//                 $zip->addFile($iconPath, $internalImagePath);
//             }

//             /** @var string $relIdOle Relationship ID for the OLE binary */
//             $relIdOle = "rIdOle{$uniqueIndex}";
//             /** @var string $relIdImg Relationship ID for the visual icon */
//             $relIdImg = "rIdImg{$uniqueIndex}";

//             // Register relationships in word/_rels/document.xml.rels
//             // addRel function must be defined globally to work here
//             addRel($relsDom, $relIdOle, 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/oleObject', $internalOlePath);
//             addRel($relsDom, $relIdImg, 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image', $internalImagePath);

//             /**
//              * XML STRUCTURE GENERATION
//              * Create a dedicated paragraph to house the icon and the filename caption.
//              */
//             $newParagraph = $dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:p");
            
//             // Clone formatting properties from the template placeholder paragraph
//             $paragraphProperties = $xpath->query("w:pPr", $targetParagraph)->item(0);
//             if ($paragraphProperties) {
//                 $newParagraph->appendChild($paragraphProperties->cloneNode(true));
//             }

//             /** ICON RUN: Contains the clickable OLE object */
//             $iconRun = $dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:r");
//             $objectContainer = $dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:object");
            
//             // VML Shape: Defines the visual boundaries of the icon
//             $vmlShape = $dom->createElementNS("urn:schemas-microsoft-com:vml", "v:shape");
//             $vmlShape->setAttribute('id', "_x0000_i" . $uniqueIndex);
//             $vmlShape->setAttribute('style', "width:72pt;height:72pt"); 
//             $vmlShape->setAttribute('o:ole', ""); 
//             $vmlShape->setAttribute('alt', $originalFileName); 

//             $imageData = $dom->createElementNS("urn:schemas-microsoft-com:vml", "v:imagedata");
//             $imageData->setAttributeNS("http://schemas.openxmlformats.org/officeDocument/2006/relationships", "r:id", $relIdImg);
//             $imageData->setAttribute('o:title', $originalFileName);
            
//             $vmlShape->appendChild($imageData);
//             $objectContainer->appendChild($vmlShape);
            
//             // OLEObject: Links the shape to the embedded binary content
//             $oleObject = $dom->createElementNS("urn:schemas-microsoft-com:office:office", "o:OLEObject");
//             $oleObject->setAttribute('Type', 'Embed');
//             // Set ProgID based on file type
//             $progId = ($fileType === 'docx') ? 'Word.Document.12' : 'Acrobat.Document.DC';
//             $oleObject->setAttribute('ProgID', $progId);
//             $oleObject->setAttribute('ShapeID', "_x0000_i" . $uniqueIndex);
//             $oleObject->setAttribute('DrawAspect', 'Icon');
//             $oleObject->setAttribute('ObjectID', "_" . $uniqueIndex);
//             $oleObject->setAttributeNS("http://schemas.openxmlformats.org/officeDocument/2006/relationships", "r:id", $relIdOle);
            
//             $objectContainer->appendChild($oleObject);
//             $iconRun->appendChild($objectContainer);
//             $newParagraph->appendChild($iconRun);

//             /** CAPTION RUN: Displays the filename below the icon */
//             $captionRun = $dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:r");
            
//             // Insert a line break to separate the text from the icon
//             $lineBreak = $dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:br");
//             $captionRun->appendChild($lineBreak);
            
//             // Apply formatting to the caption text
//             $runProperties = $dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:rPr");
//             $fontSize = $dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:sz");
//             $fontSize->setAttribute('w:val', '18'); // Equivalent to 9pt
//             $runProperties->appendChild($fontSize);
//             $captionRun->appendChild($runProperties);

//             $textNode = $dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:t");
//             $textNode->nodeValue = $originalFileName;
//             $captionRun->appendChild($textNode);
            
//             $newParagraph->appendChild($captionRun);
            
//             // Place the newly constructed paragraph into the document structure
//             $documentBody->insertBefore($newParagraph, $targetParagraph);
//         }
//     }

//     // Always clean up: Remove the original template bookmark/placeholder paragraph
//     $documentBody->removeChild($targetParagraph);
// }