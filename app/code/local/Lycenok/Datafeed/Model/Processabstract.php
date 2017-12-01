<?php

/**
 * Class for processing text data.
 */ 
abstract class Lycenok_Datafeed_Model_Processabstract {  


public $csvReportNamePrefix = '';

protected $relativeProcessUrl;
   
/* report contents */   
protected $csvReportContents = '';
protected $textReportContents = '';
protected $automaticRun = false;
protected $csvReportFileName = null;
protected $csvReportFilePath = null;
protected $helper;
protected $processStartTime;


/**
 * Constructor. initialise attribute codes.
*/  
function __construct() { 
    $this->helper = Mage::helper('lycenok_datafeed');
} 

/**
 * Process. 
 */ 
protected abstract function process();


/**
  * Name of the process. Used for report names etc.
  */
public function getProcessName() {
    $classWordList = explode('_', get_class($this));
    if (count($classWordList) >= 1) {
        return $classWordList[count($classWordList) - 1];
    } else {
        return 'datafeed_process';
    }
} 

/**
 * Output report file download link.
*/
public function outputReportDownloadLink($linkText=null) {
    $_SESSION['download_file_path'] = $this->csvReportFilePath;
    echo '<a href="./download_file.php">' . (!empty($linkText) ? $linkText : 'download report') . '</a>';
} 

/* 
 * Output to the text report 
 */
protected function output($string) {
    if ($this->automaticRun) { 
        $this->textReportContents .= $string;
    } else {
        echo $string;
    } 
} 

/* 
 * Output a string to the csv report as well as to text report
 */
protected function outputCsv($string) { 
    if (!($this->automaticRun)) { 
        echo $string;
    }
    $this->csvReportContents .= $string;
} 

/**
  *  Get working directory
  */ 
protected function getProcessDirectory() {
    $processDirName = str_replace(' ', '_', $this->getProcessName());
    $dateDirName = Mage::getModel('core/date')->date('Y-m-d');
    $this->relativeProcessUrl = 
      'var/lycenok/' . $dateDirName  . '/' . $processDirName;
    $extensionDir = 
       Mage::getBaseDir('var') . DIRECTORY_SEPARATOR . 'lycenok';
    $dirPath = $extensionDir . DIRECTORY_SEPARATOR . $dateDirName 
        . DIRECTORY_SEPARATOR . $processDirName;
    if (!is_dir($dirPath)) {
        mkdir($dirPath, 0777, true);
    }   
    $htaccessPath = $extensionDir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($htaccessPath)) {
        file_put_contents($htaccessPath, 'Allow from all
IndexOptions NameWidth=*');
    }
    return $dirPath;    
}     

/** 
 * Run process (launches process() method inside)
*/
public function run() { 
    Mage::log(get_class($this) . ': run: start');
    $this->processStartDatetime = 
      Mage::getModel('core/date')->date('Y-m-d H:i:s');
    if (!session_id() && !headers_sent()) {
       session_start();
    }  
    $this->csvReportContents = null;
    if (empty($this->csvReportNamePrefix)) { 
        $this->csvReportNamePrefix = strtolower($this->getProcessName());
    } 
    if (empty($this->csvReportFileName)) { 
        $this->csvReportFileName = 
            $this->csvReportNamePrefix . '_report_' 
            . Mage::getModel('core/date')->date('Y_m_d_H_i_s') . '.csv';
    } 
    $this->csvReportFilePath = 
      $this->getProcessDirectory() . DIRECTORY_SEPARATOR . $this->csvReportFileName;
    $fileHandle = fopen($this->csvReportFilePath, 'w');
    $this->process();
    fwrite($fileHandle, $this->csvReportContents);
    fclose($fileHandle);
    Mage::log(get_class($this) . ': run: finish');
} 

public function crontask()
{
    $this->automaticRun = true;
    $this->run();
    // send email
    $this->helper->sendMail(
      'Datafeed report: ' . $this->getProcessName()
    , $this->textReportContents // bodyHtml
. '<pre>' . "\n"
. 'Base Url: ' .  rtrim(Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB), '/')  . '
Have a wonderful day!
Sincerely yours, Datafeed.'
. '</pre>'
    , $this->csvReportContents  // report contents
    , $this->csvReportFileName // report file name 
    );
}

}
?>