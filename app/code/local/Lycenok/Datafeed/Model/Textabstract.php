<?php

/**
 * Class for processing text data.
 */ 
abstract class Lycenok_Datafeed_Model_Textabstract extends Lycenok_Datafeed_Model_Processabstract{
   
   
protected $recordNumber;
protected $fieldDelimiter = ",";
protected $localFilePath;
protected $minValuesCount = 3;


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
 * set local file path
*/ 
protected function calcLocalFilePath() { 
   $localFileName = 
      strtolower($this->getProcessName()) 
      . '_source_' 
      . Mage::getModel('core/date')->date('Y_m_d_H_i_s')
      . '.csv'
   ;
   $this->localFilePath = 
      $this->getProcessDirectory() . DIRECTORY_SEPARATOR . $localFileName;
} 

/**
  * Copy file to local process directory.
  */
protected function getFile($sourceFilePath) { 
    $this->calcLocalFilePath();
    $this->output("<pre>source file path=\"" . $sourceFilePath . "\"\n</pre>");
    if (!copy($sourceFilePath, $this->localFilePath)) { 
        throw new Exception('Could not copy source file ' 
          . $sourceFilePath . ' to ' . $this->localFilePath
        );
    } 
} 

/**
  * Get file by default.
  */
protected function getFileDefault() { 
  throw new Exception(
    'default file loading not implemented for this process: ' . $this->getProcessName() 
  );  
}   

/** 
 * Process. Supposing that we have already got localFilePath.
*/
protected function process() { 
    if (!is_file($this->localFilePath)) {
        $this->calcLocalFilePath();
        $this->getFileDefault();
    }
    if (($handle = fopen($this->localFilePath, "r")) !== FALSE) {
        $this->output("<pre>");
        $this->beforeLineProcess();
        $header = true;
        while (($data = fgetcsv($handle, 0, $this->fieldDelimiter)) !== FALSE) {
            if (!$header) { 
                if ($this->recordNumber % 1000 == 0) { 
                    Mage::log(get_class($this) . ': recordNumber=' . $this->recordNumber);
                } 
                $this->recordNumber++;
                if (count($data) > 0) {
                    if (trim($data[0], " \n\t") <> '') { 
                        if (count($data) < $this->minValuesCount) {
                            throw new Exception(
                            'Too few columns count (<' . $this->minValuesCount .'): csvLine=' 
                            . print_r($data, true)
                            );
                        }    
                        $this->processValues($data);
                    }
                }
            } else {
                $header = false;    
            } 
        }
        fclose($handle);
        $this->afterLineProcess();
        $this->output("</pre>");
    } else  {
        $this->output("Could not open file");
        throw new Exception("Could not open file");
    }
    $this->output("\n");
    $this->output("<pre>");
    $this->output('Process files URI: ' 
      . rtrim(Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB), '/') 
      . '/' . $this->relativeProcessUrl . "\n"
    );
    $this->output('Source file name: ' . basename($this->localFilePath) . "\n");
    $this->output("</pre>");
} 


/** 
 * parse given file
*/
public function processFile($loadedFilePath) { 
    if (!empty($loadedFilePath)) {
        $this->getFile($loadedFilePath);
    } else {    
        $this->getFileDefault();
    } 
    $this->run();
} 

    
}
?>