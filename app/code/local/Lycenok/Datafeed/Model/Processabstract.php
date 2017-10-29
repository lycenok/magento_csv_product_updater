<?php

/**
 * Class for processing text data.
 */ 
abstract class Lycenok_Datafeed_Model_Processabstract {  


public $csvReportNamePrefix = '';

   
/* report contents */   
protected $csvReportContent = '';
protected $textReportContent = '';
protected $standardOutputFlag = true;
protected $csvReportFileName = null;
protected $csvReportFilePath = null;


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
    if ($this->standardOutputFlag) { 
        echo $string;
    } else {
        $this->textReportContent .= $string;
    } 
} 

/* 
 * Output a string to the csv report as well as to text report
 */
protected function outputCsv($string) { 
    if ($this->standardOutputFlag) { 
        echo $string;
    }
    $this->csvReportContent .= $string;
} 

/** 
 * Run process
*/
public function run() { 
    if (!session_id() && !headers_sent()) {
       session_start();
    }  
    $this->csvReportContent = null;
    $baseDir = Mage::getBaseDir('var');
    $dirPath = $baseDir . DIRECTORY_SEPARATOR . 'lycenok' . DIRECTORY_SEPARATOR . date('Y-m-d');
    if (!is_dir($dirPath)) {
        echo $dirPath;
        mkdir($dirPath, 0777, true);
    }
    if (empty($this->csvReportNamePrefix)) { 
        $this->csvReportNamePrefix = strtolower($this->getProcessName());
    } 
    if (empty($this->csvReportFileName)) { 
        $this->csvReportFileName = 
            $this->csvReportNamePrefix . '_report_' . date('Y_m_d_H_i_s') . '.csv';
    } 
    $this->csvReportFilePath = $dirPath . DIRECTORY_SEPARATOR . $this->csvReportFileName;
    $fileHandle = fopen($this->csvReportFilePath, 'w');
    $this->process();
    fwrite($fileHandle, $this->csvReportContent);
    fclose($fileHandle);
} 

public function crontask()
{
    require_once Mage::getBaseDir() . '/Lycenok-Scripts/datafeed/common.datafeed.lib.php';
    $this->standardOutputFlag = false;
    $this->run();
    // send email
    sendMail(
      'Datafeed report: ' . $this->getProcessName()
    , $this->csvReportContent
    , $this->csvReportFileName
    , $this->textReportContent
    );
}

}
?>