<?php

/**
 * Class for processing text data.
 */ 
abstract class Lycenok_Datafeed_Model_Textabstract extends Lycenok_Datafeed_Model_Processabstract{
   
   
protected $recordNumber;
protected $fieldDelimiter = ",";
protected $filePath;

/**
 * Handler, run before process first line for non-empty data.
 */ 
protected abstract function beforeLineProcess();    

/**
 * Handler, run after process last line for non-empty data.
 */ 
protected abstract function afterLineProcess();    

/**
 * Process values. 
 */ 
protected abstract function processValues($values);

/** 
 * Process.
*/
protected function process() { 
	$csv = trim(file_get_contents($this->filePath));
    $this->output("<pre>contents length: " . strlen($csv) . " ");
    $this->output("file path=\"" . $this->filePath . "\"\n</pre>");
	if (!empty($csv)) {
		$this->output("<pre>");
        $this->beforeLineProcess();
		$csvLines = explode("\n", $csv);
		$csvLine = array_shift($csvLines);
		$csvLine = str_getcsv($csvLine, $this->fieldDelimiter);
		if (count($csvLine) < 3) {
			throw new Exception('Too few columns count (<3): csvLine=' . print_r($csvLine, true));
		}
		$this->recordNumber = 0;
		foreach ($csvLines as $k=>$csvLine) {
			$this->recordNumber++;
			$csvLine = str_getcsv($csvLine, $this->fieldDelimiter);
            $this->processValues($csvLine);
		}
        $this->afterLineProcess();
		$this->output("</pre>");
	} else { 
        $this->output("No file contents");
        throw new Exception("No file contents");
    }
} 


/** 
 * parse file
*/
public function processFile($filePath) { 
    $this->filePath = $filePath;
    $this->run();
} 

    
}
?>