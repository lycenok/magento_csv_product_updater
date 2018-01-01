<?php

/** 
 * Class for processing price.
 */
class Lycenok_Datafeed_Model_Updateproduct extends Lycenok_Datafeed_Model_Textabstract { 


public $changeCount = 0;
public $noChangeCount = 0;
public $specialPriceCount = 0;
public $disabledCount = 0;
public $skuNotFound = 0;

public $mode;
public $updateSpecialPrice;

/**
 * Handler, run before process first line for non-empty data.
 */ 
protected function beforeLineProcess() {
    $this->outputCsv(
      str_pad('Rec Number', 10)
      . ',' . str_pad('Result', 20)
      . ',' . str_pad('Product Id', 20)
      . ',' . str_pad('SKU', 30)
      . ',' . str_pad('Old price', 10)
      . ',' . str_pad('New price', 10)
      . ',' . str_pad('Old availability', 30)
      . ',' . str_pad('New availability', 30)
      . ',' . str_pad('Old cost', 10)
      . ',' . str_pad('New cost', 10)
      . ',' . str_pad('Old qty', 10)
      . ',' . str_pad('New qty', 10)
      . ',' . str_pad('Old status', 10)
      . ',' . str_pad('New status', 10)
      . "\n"
    );      
}     

/**
  * Get csv number value
*/
private function getPriceValue($values, $valueNumber) {
  return str_replace(
    ',', ''
  , trim((isset($values[$valueNumber]) ? $values[$valueNumber] : null))
  );  
} 

/**
  * Get status string 
  */
private function getStatusString($statusId) {
    if ($statusId == 1) {
        return 'enabled';
    } else if ($statusId == 2) {
        return 'disabled';
    } 
}     

/**
 * Process values. 
 */ 
protected function processValues($values) {
    if (count($values) > 0 && count($values) < 2) {
        throw new Exception('Too few values (<2)');
    };
    $productId = null;
    $sku = $values[0];
    $newAvailabilityId = null;
    if (isset($values[1])) { 
        if ($values[1] <> "") {
            $newAvailabilityId = $this->helper->getAvailabilityId($values[1]);
        } 
    } 
    $oldAvailabilityId = null;      
    $newPrice = $this->getPriceValue($values, 2);
    $oldPrice = null;
    $newCost = $this->getPriceValue($values, 3);
    $oldCost = null;
    $newQuantity = (isset($values[4]) ? $values[4] : null);
    $oldQuantity = null;
    $newStatusId = null;
    if (isset($values[5])) { 
        if (strtolower($values[5]) == 'enabled') {
            $newStatusId = 1;
        } else if (strtolower($values[5]) == 'disabled') {
            $newStatusId = 2;
        } 
    } 
    $oldStatusId = null;
    $sku = trim($sku, " \t\n\r\0\x0B*");
    $findResult = $this->helper->findProduct(
      $sku
    , null // name
    , 'EXACT' // $skuMatchMode
    , false // $baseSkuSearchFlag
    );
    $productData = $findResult['product_data'];
    $searchMask = $findResult['search_mask'];
    if (count($productData) == 1) {
        $productId = $productData[0]['product_id'];
        $product = Mage::getModel('catalog/product')->load($productId);
        $oldAvailabilityId = $this->helper->getProductAttributeInt($productId, 'availability');          
        $oldCost = $this->helper->getProductAttributeDecimal($productId, 'cost');          
        $specialPrice = $product->getSpecialPrice();
        $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($productId);
        $oldQuantity = $stock->getQty();        
        $oldStatusId = $product->getStatus();
        if (empty($specialPrice) || $this->updateSpecialPrice) { 
            if (empty($specialPrice)) { 
                $oldPrice = $product->getPrice();    
            }    
            if (
                !empty($newPrice) && $newPrice <> $oldPrice 
                || !empty($newAvailabilityId) && $newAvailabilityId <> $oldAvailabilityId
                || !empty($newCost) && $newCost <> $oldCost
                || !empty($newQuantity) && $newQuantity <> $oldQuantity
                || !empty($newStatusId) && $newStatusId <> $oldStatusId
            )   
            { 
                $this->changeCount++;
                if (strtoupper($this->mode) == 'UPDATE') {
                    if (!empty($newPrice)) { 
                        $this->helper->updateProductAttribute(
                            $productId
                        , (!empty($specialPrice) ? 'special_' : '') 
                           . 'price', $newPrice
                        );   
                    }
                    if (!empty($newAvailabilityId)) { 
                        $this->helper->updateProductAttribute($productId, 'availability', $newAvailabilityId);
                    }    
                    if (!empty($newCost)) { 
                        $this->helper->updateProductAttribute($productId, 'cost', $newCost);
                    }    
                    if (!empty($newQuantity)) { 
                        $this->helper->updateProductStock($productId, $newQuantity);
                    }    
                    if (!empty($newStatusId)) {
                        $this->helper->updateProductAttribute($productId, 'status', $newStatusId);
                    } 
                    $result = 'UPDATED';
                } else {                                             
                    $result = 'FOR CHANGE';
                } 
            } else { 
                $this->noChangeCount++;
                $result = 'NO CHANGE';
            } 
        } else {
            $oldPrice = $specialPrice;
            $result = 'SPECIAL';
            $this->specialPriceCount++;
        } 
    } elseif (count($productData) > 1) { 
        $this->skuNotFound++;
        $result = 'TOO MANY(' . count($productData) . ')';
    } else {    
        $this->skuNotFound++;
        $result = 'NOT FOUND';
    } 
    $this->outputCsv(
      str_pad($this->recordNumber, 10) 
      . ',' . str_pad($result, 20) 
      . ',' . str_pad($productId, 20) 
      . ',' . str_pad($sku, 30)
      . ',' . str_pad($this->helper->formatPrice($oldPrice), 10)
      . ',' . str_pad($this->helper->formatPrice($newPrice), 10)
      . ',' . str_pad($this->helper->getAvailabilityText($oldAvailabilityId), 30)
      . ',' . str_pad($this->helper->getAvailabilityText($newAvailabilityId), 30)
      . ',' . str_pad($this->helper->formatPrice($oldCost), 10)
      . ',' . str_pad($this->helper->formatPrice($newCost), 10)
      . ',' . str_pad($oldQuantity, 10)
      . ',' . str_pad($newQuantity, 10)
      . ',' . str_pad($this->getStatusString($oldStatusId), 10)
      . ',' . str_pad($this->getStatusString($newStatusId), 10)
      . "\n"
    );  
}   

/**
 * Handler, run after process last line for non-empty data.
 */ 
protected function afterLineProcess() {
	$this->output("<pre>");
    $this->output("\n");
	$this->output("changeCount: " . $this->changeCount . "\n");
	$this->output("noChangeCount: " . $this->noChangeCount . "\n");
    $this->output("specialPriceCount: " . $this->specialPriceCount . "\n");
	$this->output("disabledCount: " . $this->disabledCount . "\n");
	$this->output("skuNotFound: " . $this->skuNotFound . "\n");
	$this->output("</pre>");
}     

} 