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
	function writeErrorMessage($errorObject) {

		$error_txt_info = $errorObject->getMessage().', file: '.$errorObject->getFile().', line: '.$errorObject->getLine();
		$this->errorLog($error_txt_info, 1);
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
				$targetWidth = (int)round($sourceWidth * $scale); 
				$targetHeight = (int)round($sourceHeight * $scale);
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
			foreach ($val_arr as $par_name => $par_value) {
				$values[$val_idx][$par_name] = htmlentities($par_value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, "UTF-8");
			}
		}
		$extTemplateProcessor->cloneRowAndSetValues($param, $values);
	}
}

class DocxProcessor {
    /** @var \DOMDocument */
    protected $dom;
    /** @var \DOMDocument */
    protected $relsDom;
    /** @var \DOMXPath */
    protected $xpath;
    /** @var \ZipArchive */
    protected $zip;
    /** @var object Utility object for extensions */
    protected $db_object;

    /**
     * Constructor using global namespaces for DOM classes.
     */
    public function __construct($dom, $relsDom, $xpath, $zip, $db_object) {
        $this->dom = $dom;
        $this->relsDom = $relsDom;
        $this->xpath = $xpath;
        $this->zip = $zip;
        $this->db_object = $db_object;
    }

    /**
     * Normalizes $_FILES into a flat array.
     */
    public function prepareUploads($filesInput) {
        $prepared = [];
        if (!isset($filesInput['name']) || !is_array($filesInput['name'])) {
            return $prepared;
        }

        foreach ($filesInput['name'] as $i => $name) {
            if ($filesInput['error'][$i] === UPLOAD_ERR_OK) {
                $prepared[] = [
                    'tmp_name' => $filesInput['tmp_name'][$i],
                    'name'     => $name
                ];
            }
        }
        return $prepared;
    }

    /**
     * Strict OLE Embedding for MS Word 2016.
     * Fixes activation issue where objects appear as static images.
     */
    public function embedOleAttachments($files, $iconFileName, $fileType, $targetParagraph) {
        if (!$targetParagraph || !$targetParagraph->parentNode) {
            return;
        }

        $documentBody = $targetParagraph->parentNode;
        $iconPath = 'img/' . $iconFileName;
        $expectedExt = strtolower(trim($fileType, '. '));

        if (!empty($files)) {
            foreach ($files as $i => $file) {
                $tempFilePath = $file['tmp_name'];
                $originalFileName = $file['name'];
                $actualExt = strtolower(trim($this->db_object->getUploadedFileExt($originalFileName), '. '));

                if ($actualExt !== $expectedExt) continue;

                // Unique IDs for Word 2016 internal tracking
                $uniqueId = time() . $i . mt_rand(100, 999);
                $internalOlePath = "word/embeddings/oleObject{$uniqueId}.{$actualExt}";
                $internalImagePath = "word/media/image_ole_{$uniqueId}.png";
                
                // Activation ID must match exactly between Shape and OLEObject
                $shapeId = "_x0000_i" . $uniqueId;
                $objId = "_OLE_" . $uniqueId;

                // 1. Add files to ZIP
                $this->zip->addFile($tempFilePath, $internalOlePath);
                if (file_exists($iconPath)) {
                    $this->zip->addFile($iconPath, $internalImagePath);
                }

                // 2. Register relationships (Relative paths)
                /** 
                 * CRITICAL FIX FOR WORD 2016:
                 * Use 'package' relationship type for Office documents (docx, xlsx)
                 * Use 'oleObject' for others (pdf, etc.)
                 */
                $relType = ($actualExt === 'docx' || $actualExt === 'xlsx') 
                    ? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/package'
                    : 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/oleObject';

                $relIdOle = "rIdOle{$uniqueId}";
                $relIdImg = "rIdImg{$uniqueId}";
                
                $this->addRel($this->relsDom, $relIdOle, $relType, $internalOlePath);
                $this->addRel($this->relsDom, $relIdImg, 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image', $internalImagePath);

                // 3. XML Structure
                $newParagraph = $this->dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:p");
                
                // Inherit formatting from template
                $pPr = $this->xpath->query("w:pPr", $targetParagraph)->item(0);
                if ($pPr) $newParagraph->appendChild($pPr->cloneNode(true));

                $run = $this->dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:r");
                $object = $this->dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:object");
                
                // VML Shape: The "Clickable" container
                $vmlShape = $this->dom->createElementNS("urn:schemas-microsoft-com:vml", "v:shape");
                $vmlShape->setAttribute('id', $shapeId);
                $vmlShape->setAttribute('style', "width:72pt;height:72pt"); 
                $vmlShape->setAttribute('o:ole', ""); // Critical for Word 2016 activation
                $vmlShape->setAttribute('alt', $originalFileName); 

                $vmlImg = $this->dom->createElementNS("urn:schemas-microsoft-com:vml", "v:imagedata");
                $vmlImg->setAttributeNS("http://schemas.openxmlformats.org/officeDocument/2006/relationships", "r:id", $relIdImg);
                $vmlImg->setAttribute('o:title', $originalFileName);
                
                $vmlShape->appendChild($vmlImg);
                $object->appendChild($vmlShape);
                
                // Determine correct ProgID
                switch ($actualExt) {
                    case 'xlsx': case 'xls': $progId = 'Excel.Sheet.12'; break;
                    case 'docx': case 'doc': $progId = 'Word.Document.12'; break;
                    default: $progId = 'Package';
                }

                // OLEObject: The link to binary data
                $oleObject = $this->dom->createElementNS("urn:schemas-microsoft-com:office:office", "o:OLEObject");
                $oleObject->setAttribute('Type', 'Embed');
                $oleObject->setAttribute('ProgID', $progId);
                $oleObject->setAttribute('ShapeID', $shapeId); // Must match v:shape id
                $oleObject->setAttribute('DrawAspect', 'Icon');
                $oleObject->setAttribute('ObjectID', $objId);
                $oleObject->setAttributeNS("http://schemas.openxmlformats.org/officeDocument/2006/relationships", "r:id", $relIdOle);
                
                $object->appendChild($oleObject);
                $run->appendChild($object);
                $newParagraph->appendChild($run);

                // Add Caption (Filename)
                $capRun = $this->dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:r");
                $capRun->appendChild($this->dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:br"));
                $text = $this->dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:t");
                $text->nodeValue = $originalFileName;
                $capRun->appendChild($text);
                $newParagraph->appendChild($capRun);
                
                $documentBody->insertBefore($newParagraph, $targetParagraph);

                // 4. Update Content_Types
                $this->ensureContentType($actualExt);
            }
        }

        $documentBody->removeChild($targetParagraph);
    }

    /**
     * Strictly formatted relationships for Word 2016.
     */
    protected function addRel($relsDom, $id, $type, $target) {
        $root = $relsDom->documentElement;
        $rel = $relsDom->createElement('Relationship');
        $rel->setAttribute('Id', $id);
        $rel->setAttribute('Type', $type);
        $rel->setAttribute('Target', str_replace('word/', '', $target));
        $root->appendChild($rel);
    }

    /**
     * Registers binary extensions in [Content_Types].xml using global DOM namespace.
     */
    protected function ensureContentType($extension) {
        $contentTypesXml = $this->zip->getFromName('[Content_Types].xml');
        if (!$contentTypesXml) return;

        $map = [
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'png'  => 'image/png'
        ];

        $contentType = isset($map[$extension]) ? $map[$extension] : 'application/vnd.openxmlformats-officedocument.oleObject';

        if (strpos($contentTypesXml, 'Extension="' . $extension . '"') === false) {
            $ctDom = new \DOMDocument();
            $ctDom->loadXML($contentTypesXml);
            
            $newNode = $ctDom->createElement('Default');
            $newNode->setAttribute('Extension', $extension);
            $newNode->setAttribute('ContentType', $contentType);
            
            $ctDom->documentElement->appendChild($newNode);
            $this->zip->addFromString('[Content_Types].xml', $ctDom->saveXML());
        }
    }

	public function saveToZip() {
		$this->zip->addFromString('word/document.xml', $this->dom->saveXML());
		$this->zip->addFromString('word/_rels/document.xml.rels', $this->relsDom->saveXML());
	}
	// public function getZipContent($extZip = null) {
	// 	$zip_files = [];
	// 	$local_zip = ($extZip) ?? $this->zip;
	// 	for ($i = 0; $i < $local_zip->numFiles; $i++) {
	// 		$zip_files[] = $local_zip->getNameIndex($i);
	// 	}
	// 	return $zip_files;
	// }
	// function errorLog($error_message, $debug_mode = 1)
	// {
	// 	if ($debug_mode === 1)
	// 	{
	// 		error_log(date("Y-m-d H:i:s") . " ". $error_message);
	// 	}
	// 	return true;
	// }
}
// class DocxProcessor {
//     /** @var DOMDocument */
//     protected $dom;
//     /** @var DOMDocument */
//     protected $relsDom;
//     /** @var DOMXPath */
//     protected $xpath;
//     /** @var ZipArchive */
//     protected $zip;
//     /** @var object Database/Utility object */
//     protected $db_object;

//     /**
//      * DocxProcessor constructor.
//      */
//     public function __construct($dom, $relsDom, $xpath, $zip, $db_object) {
//         $this->dom = $dom;
//         $this->relsDom = $relsDom;
//         $this->xpath = $xpath;
//         $this->zip = $zip;
//         $this->db_object = $db_object;
//     }

//     /**
//      * Normalizes $_FILES array into a flat list of successful uploads.
//      * * @param array $filesInput The raw $_FILES['input_name'] array.
//      * @return array List of files: [['tmp_name' => '...', 'name' => '...'], ...]
//      */
//     public function prepareUploads($filesInput) {
//         $prepared = [];
//         if (!isset($filesInput['name']) || !is_array($filesInput['name'])) {
//             return $prepared;
//         }

//         foreach ($filesInput['name'] as $i => $name) {
//             if ($filesInput['error'][$i] === UPLOAD_ERR_OK) {
//                 $prepared[] = [
//                     'tmp_name' => $filesInput['tmp_name'][$i],
//                     'name'     => $name
//                 ];
//             }
//         }
//         return $prepared;
//     }

//     /**
//      * Strict OLE Embedding for MS Word 2016.
//      * Fixes activation issue where objects appear as static images.
//      */
//     public function embedOleAttachments($files, $iconFileName, $fileType, $targetParagraph) {
//         if (!$targetParagraph || !$targetParagraph->parentNode) {
//             return;
//         }

//         $documentBody = $targetParagraph->parentNode;
//         $iconPath = 'img/' . $iconFileName;
//         $expectedExt = strtolower(trim($fileType, '. '));

//         if (!empty($files)) {
//             foreach ($files as $i => $file) {
//                 $tempFilePath = $file['tmp_name'];
//                 $originalFileName = $file['name'];
//                 $actualExt = strtolower(trim($this->db_object->getUploadedFileExt($originalFileName), '. '));

//                 if ($actualExt !== $expectedExt) continue;

//                 // Unique IDs for Word 2016 internal tracking
//                 $uniqueId = time() . $i . mt_rand(100, 999);
//                 $internalOlePath = "word/embeddings/oleObject{$uniqueId}.{$actualExt}";
//                 $internalImagePath = "word/media/image_ole_{$uniqueId}.png";
                
//                 // Activation ID must match exactly between Shape and OLEObject
//                 $shapeId = "_x0000_i" . $uniqueId;
//                 $objId = "_OLE_" . $uniqueId;

//                 // 1. Add files to ZIP
//                 $this->zip->addFile($tempFilePath, $internalOlePath);
//                 if (file_exists($iconPath)) {
//                     $this->zip->addFile($iconPath, $internalImagePath);
//                 }

//                 // 2. Register relationships (Relative paths)
//                 $relIdOle = "rIdOle{$uniqueId}";
//                 $relIdImg = "rIdImg{$uniqueId}";
//                 $this->addRel($this->relsDom, $relIdOle, 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/oleObject', $internalOlePath);
//                 $this->addRel($this->relsDom, $relIdImg, 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image', $internalImagePath);

//                 // 3. XML Structure
//                 $newParagraph = $this->dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:p");
                
//                 // Inherit formatting from template
//                 $pPr = $this->xpath->query("w:pPr", $targetParagraph)->item(0);
//                 if ($pPr) $newParagraph->appendChild($pPr->cloneNode(true));

//                 $run = $this->dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:r");
//                 $object = $this->dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:object");
                
//                 // VML Shape: The "Clickable" container
//                 $vmlShape = $this->dom->createElementNS("urn:schemas-microsoft-com:vml", "v:shape");
//                 $vmlShape->setAttribute('id', $shapeId);
//                 $vmlShape->setAttribute('style', "width:72pt;height:72pt"); 
//                 $vmlShape->setAttribute('o:ole', ""); // Critical for Word 2016 activation
//                 $vmlShape->setAttribute('alt', $originalFileName); 

//                 $vmlImg = $this->dom->createElementNS("urn:schemas-microsoft-com:vml", "v:imagedata");
//                 $vmlImg->setAttributeNS("http://schemas.openxmlformats.org/officeDocument/2006/relationships", "r:id", $relIdImg);
//                 $vmlImg->setAttribute('o:title', $originalFileName);
                
//                 $vmlShape->appendChild($vmlImg);
//                 $object->appendChild($vmlShape);
                
//                 // Determine correct ProgID
//                 switch ($actualExt) {
//                     case 'xlsx': case 'xls': $progId = 'Excel.Sheet.12'; break;
//                     case 'docx': case 'doc': $progId = 'Word.Document.12'; break;
//                     default: $progId = 'Package';
//                 }

//                 // OLEObject: The link to binary data
//                 $oleObject = $this->dom->createElementNS("urn:schemas-microsoft-com:office:office", "o:OLEObject");
//                 $oleObject->setAttribute('Type', 'Embed');
//                 $oleObject->setAttribute('ProgID', $progId);
//                 $oleObject->setAttribute('ShapeID', $shapeId); // Must match v:shape id
//                 $oleObject->setAttribute('DrawAspect', 'Icon');
//                 $oleObject->setAttribute('ObjectID', $objId);
//                 $oleObject->setAttributeNS("http://schemas.openxmlformats.org/officeDocument/2006/relationships", "r:id", $relIdOle);
                
//                 $object->appendChild($oleObject);
//                 $run->appendChild($object);
//                 $newParagraph->appendChild($run);

//                 // Add Caption (Filename)
//                 $capRun = $this->dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:r");
//                 $capRun->appendChild($this->dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:br"));
//                 $text = $this->dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:t");
//                 $text->nodeValue = $originalFileName;
//                 $capRun->appendChild($text);
//                 $newParagraph->appendChild($capRun);
                
//                 $documentBody->insertBefore($newParagraph, $targetParagraph);

//                 // 4. Update Content_Types
//                 $this->ensureContentType($actualExt);
//             }
//         }

//         $documentBody->removeChild($targetParagraph);
//     }

//     /**
//      * Strictly formatted relationships for Word 2016.
//      */
//     protected function addRel($relsDom, $id, $type, $target) {
//         $root = $relsDom->documentElement;
//         $rel = $relsDom->createElement('Relationship');
//         $rel->setAttribute('Id', $id);
//         $rel->setAttribute('Type', $type);
//         $rel->setAttribute('Target', str_replace('word/', '', $target));
//         $root->appendChild($rel);
//     }

//     /**
//      * Registers binary extensions in [Content_Types].xml using global DOM namespace.
//      */
//     protected function ensureContentType($extension) {
//         $contentTypesXml = $this->zip->getFromName('[Content_Types].xml');
//         if (!$contentTypesXml) return;

//         $map = [
//             'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
//             'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
//             'png'  => 'image/png'
//         ];

//         $contentType = isset($map[$extension]) ? $map[$extension] : 'application/vnd.openxmlformats-officedocument.oleObject';

//         if (strpos($contentTypesXml, 'Extension="' . $extension . '"') === false) {
//             $ctDom = new \DOMDocument();
//             $ctDom->loadXML($contentTypesXml);
            
//             $newNode = $ctDom->createElement('Default');
//             $newNode->setAttribute('Extension', $extension);
//             $newNode->setAttribute('ContentType', $contentType);
            
//             $ctDom->documentElement->appendChild($newNode);
//             $this->zip->addFromString('[Content_Types].xml', $ctDom->saveXML());
//         }
//     }
// 	public function saveToZip() {
// 		$this->zip->addFromString('word/document.xml', $this->dom->saveXML());
// 		$this->zip->addFromString('word/_rels/document.xml.rels', $this->relsDom->saveXML());
// 	}

//     /**
//      * Helper method to add a relationship to the .rels DOM.
//      */
//     // protected function addRel($relsDom, $id, $type, $target) {
//     //     $root = $relsDom->documentElement;
//     //     $rel = $relsDom->createElement('Relationship');
//     //     $rel->setAttribute('Id', $id);
//     //     $rel->setAttribute('Type', $type);
//     //     $rel->setAttribute('Target', str_replace('word/', '', $target));
//     //     $root->appendChild($rel);
//     // }
// }