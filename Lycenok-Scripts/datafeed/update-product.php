<!DOCTYPE html>
<html>
<body>
<?php
require_once '../../app/Mage.php';
Mage::app();
?>
<form method="post" enctype="multipart/form-data">
    <p>Select CSV-file to upload 
    (sku,availability,price(ignored if empty),cost(ignored if empty)quantity(ignored if empty),[enabled|disabled](ingored if empty)</p>
    <p>availability=["back order","in stock","not available","suppliers"]</p>
    <input type="file" name="fileToUpload" id="fileToUpload">  
    <input type="submit" value="Update products" name="submit">
    <?php $uploadMode = (isset($_REQUEST['uploadMode']) ? $_REQUEST['uploadMode'] : ""); ?>
    <?php Mage::helper('lycenok_datafeed')->outputSelect('uploadMode', array('show_change', 'update'));?>
    <div>
    <input type="checkbox" name="updateSpecialPrice"/>
    <label for="updateSpecialPrice">Override special price</label>
    </div>
</form>
<?php
 if(isset($_POST["submit"])) {
    $filePath=$_FILES["fileToUpload"]["tmp_name"];
	Mage::app();
    $updateProcessor = Mage::getModel('lycenok_datafeed/updateproduct');
    $updateProcessor->mode = $uploadMode;
    $updateProcessor->updateSpecialPrice = false;
    if (isset($_REQUEST['updateSpecialPrice'])) {
        if ($_REQUEST['updateSpecialPrice'] == 'on') { 
            $updateProcessor->updateSpecialPrice = true;
        } 
    }
    $updateProcessor->processFile($filePath);
    $updateProcessor->outputReportDownloadLink();
    unset($updateProcessor);
}
?>
</body>
</html>
