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
			foreach ($val_arr as $par_name => $par_value) {
				$values[$val_idx][$par_name] = htmlentities($par_value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, "UTF-8");
			}
		}
		$extTemplateProcessor->cloneRowAndSetValues($param, $values);
	}
}

class DocxProcessor {
    /** @var DOMDocument */
    protected $dom;
    /** @var DOMDocument */
    protected $relsDom;
    /** @var DOMXPath */
    protected $xpath;
    /** @var ZipArchive */
    protected $zip;
    /** @var object Database/Utility object */
    protected $db_object;

    /**
     * DocxProcessor constructor.
     */
    public function __construct($dom, $relsDom, $xpath, $zip, $db_object) {
        $this->dom = $dom;
        $this->relsDom = $relsDom;
        $this->xpath = $xpath;
        $this->zip = $zip;
        $this->db_object = $db_object;
    }

    /**
     * Normalizes $_FILES array into a flat list of successful uploads.
     * * @param array $filesInput The raw $_FILES['input_name'] array.
     * @return array List of files: [['tmp_name' => '...', 'name' => '...'], ...]
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
     * Method for dynamic embedding of various file types as OLE objects (docx, xlsx, pdf).
     * * @param array $files Normalized file array: [['tmp_name' => '...', 'name' => '...'], ...]
     * @param string $iconFileName Name of the icon file in the assets/img folder
     * @param string $fileType Expected file extension ('docx', 'xlsx', 'pdf')
     * @param DOMNode $targetParagraph The paragraph node found via bookmark search
     */
    public function embedOleAttachments($files, $iconFileName, $fileType, $targetParagraph) {
        if (!$targetParagraph || !$targetParagraph->parentNode) {
            return;
        }
        /** @var DOMNode $documentBody Parent node where new paragraphs will be inserted */
        $documentBody = $targetParagraph->parentNode;
        $iconPath = 'img/' . $iconFileName;

        if (!empty($files)) {
            foreach ($files as $i => $file) {
                $tempFilePath = $file['tmp_name'];
                $originalFileName = $file['name'];

                // Validate file extension using the utility object
                if ($this->db_object->getUploadedFileExt($originalFileName) !== $fileType) {
                    continue;
                }
                /** @var string $uniqueIndex Identifier generated to avoid XML ID collisions */
                $uniqueIndex = time() . "_" . $i . "_" . uniqid(); 
                
                /** Define internal paths within the OpenXML archive structure */
                $internalOlePath = "word/embeddings/oleObject{$uniqueIndex}.{$fileType}";
                $internalImagePath = "word/media/image_icon_{$uniqueIndex}.png";

                // Inject binary file and icon into the ZIP archive
                $this->zip->addFile($tempFilePath, $internalOlePath);
                if (file_exists($iconPath)) {
                    $this->zip->addFile($iconPath, $internalImagePath);
                }

                /** @var string $relIdOle Relationship ID for the OLE binary */
                $relIdOle = "rIdOle{$uniqueIndex}";
                /** @var string $relIdImg Relationship ID for the visual icon */
                $relIdImg = "rIdImg{$uniqueIndex}";

                // Register relationships in word/_rels/document.xml.rels
                $this->addRel($this->relsDom, $relIdOle, 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/oleObject', $internalOlePath);
                $this->addRel($this->relsDom, $relIdImg, 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image', $internalImagePath);

                /**
                 * XML STRUCTURE GENERATION
                 */
                $newParagraph = $this->dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:p");
                
                // Clone formatting properties from the template placeholder paragraph
                $paragraphProperties = $this->xpath->query("w:pPr", $targetParagraph)->item(0);
                if ($paragraphProperties) {
                    $newParagraph->appendChild($paragraphProperties->cloneNode(true));
                }

                /** ICON RUN: Contains the clickable OLE object */
                $iconRun = $this->dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:r");
                $objectContainer = $this->dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:object");
                
                // VML Shape: Defines the visual boundaries of the icon
                $vmlShape = $this->dom->createElementNS("urn:schemas-microsoft-com:vml", "v:shape");
                $vmlShape->setAttribute('id', "_x0000_i" . $uniqueIndex);
                $vmlShape->setAttribute('style', "width:72pt;height:72pt"); 
                $vmlShape->setAttribute('o:ole', ""); 
                $vmlShape->setAttribute('alt', $originalFileName); 

                $imageData = $this->dom->createElementNS("urn:schemas-microsoft-com:vml", "v:imagedata");
                $imageData->setAttributeNS("http://schemas.openxmlformats.org/officeDocument/2006/relationships", "r:id", $relIdImg);
                $imageData->setAttribute('o:title', $originalFileName);
                
                $vmlShape->appendChild($imageData);
                $objectContainer->appendChild($vmlShape);
                
                // Determine ProgID based on file type
                switch ($fileType) {
                    case 'docx':
                        $progId = 'Word.Document.12';
                        break;
                    case 'xlsx':
                        $progId = 'Excel.Sheet.12';
                        break;
                    case 'pdf':
                    default:
                        $progId = 'Acrobat.Document.DC';
                        break;
                }

                // OLEObject: Links the shape to the embedded binary content
                $oleObject = $this->dom->createElementNS("urn:schemas-microsoft-com:office:office", "o:OLEObject");
                $oleObject->setAttribute('Type', 'Embed');
                $oleObject->setAttribute('ProgID', $progId);
                $oleObject->setAttribute('ShapeID', "_x0000_i" . $uniqueIndex);
                $oleObject->setAttribute('DrawAspect', 'Icon');
                $oleObject->setAttribute('ObjectID', "_" . $uniqueIndex);
                $oleObject->setAttributeNS("http://schemas.openxmlformats.org/officeDocument/2006/relationships", "r:id", $relIdOle);
                
                $objectContainer->appendChild($oleObject);
                $iconRun->appendChild($objectContainer);
                $newParagraph->appendChild($iconRun);

                /** CAPTION RUN: Displays the filename below the icon */
                $captionRun = $this->dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:r");
                $lineBreak = $this->dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:br");
                $captionRun->appendChild($lineBreak);
                
                $runProperties = $this->dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:rPr");
                $fontSize = $this->dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:sz");
                $fontSize->setAttribute('w:val', '18'); 
                $runProperties->appendChild($fontSize);
                $captionRun->appendChild($runProperties);

                $textNode = $this->dom->createElementNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "w:t");
                $textNode->nodeValue = $originalFileName;
                $captionRun->appendChild($textNode);
                
                $newParagraph->appendChild($captionRun);
                // Insert the new paragraph into the document
                $documentBody->insertBefore($newParagraph, $targetParagraph);
            }
        }

        // Always clean up: Remove the original template placeholder paragraph
        $documentBody->removeChild($targetParagraph);
    }
	public function saveToZip() {
		$this->zip->addFromString('word/document.xml', $this->dom->saveXML());
		$this->zip->addFromString('word/_rels/document.xml.rels', $this->relsDom->saveXML());
	}

    /**
     * Helper method to add a relationship to the .rels DOM.
     */
    protected function addRel($relsDom, $id, $type, $target) {
        $root = $relsDom->documentElement;
        $rel = $relsDom->createElement('Relationship');
        $rel->setAttribute('Id', $id);
        $rel->setAttribute('Type', $type);
        $rel->setAttribute('Target', str_replace('word/', '', $target));
        $root->appendChild($rel);
    }
}