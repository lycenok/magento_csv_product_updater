<html>
<?php 
  require_once '../../app/Mage.php';
  Mage::app();
  $resource = Mage::getSingleton('core/resource');  
  $writeConnection = $resource->getConnection('core_write');
  $result = $writeConnection->query("delete from core_resource where code='lycenok_datafeed_setup'");
  if ($result){
    echo "information about Lycenok Datafeed extension deleted: " . $result->rowCount();
  } 
?>
</html>