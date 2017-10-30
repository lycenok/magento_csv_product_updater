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
      . ',' . str_pad('SKU', 20)
      . ',' . str_pad('Old price', 10)
      . ',' . str_pad('New price', 10)
      . ',' . str_pad('Old availability', 30)
      . ',' . str_pad('New availability', 30)
      . ',' . str_pad('Old cost', 10)
      . ',' . str_pad('New cost', 10)
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
 * Process values. 
 */ 
protected function processValues($values) {
    if (count($values) > 0 && count($values) < 2) {
        throw new Exception('Too few values (<2)');
    };
    $productId = null;
    $sku = $values[0];
    $newAvailabilityId = 
      (isset($values[1]) ? getAvailabilityId($values[1]) : null);
    $oldAvailabilityId = null;      
    $newPrice = $this->getPriceValue($values, 2);
    $oldPrice = null;
    $newCost = $this->getPriceValue($values, 3);
    $oldCost = null;
    $sku = trim($sku, " \t\n\r\0\x0B*");
    $findResult = findProduct(
      $sku
    , null
    , false // $allowSubstringFlag
    , false // $baseSkuSearch
    );
    $productData = $findResult['product_data'];
    $searchMask = $findResult['search_mask'];
    if (count($productData) == 1) {
        $productId = $productData[0]['product_id'];
        $product = Mage::getModel('catalog/product')->load($productId);
        $oldAvailabilityId = getProductAttributeInt($productId, 'availability');          
        $oldCost = getProductAttributeDecimal($productId, 'cost');          
        $specialPrice = $product->getSpecialPrice();
        if ($product->getStatus() == 2) { 
            $result = 'DISABLED';
            $this->disabledCount++;
        } else if (empty($specialPrice) || $this->updateSpecialPrice) { 
            if (empty($specialPrice)) { 
                $oldPrice = $product->getPrice();    
            }    
            if (
                !empty($newPrice) && $newPrice <> $oldPrice 
                || !empty($newAvailabilityId) && $newAvailabilityId <> $oldAvailabilityId
                || !empty($newCost) && $newCost <> $oldCost
            )   
            { 
                $this->changeCount++;
                if (strtoupper($this->mode) == 'UPDATE') {
                    if (!empty($newPrice)) { 
                        updateProductAttribute($productId, (!empty($specialPrice) ? 'special_' : '') . 'price', $newPrice);   
                    }
                    if (!empty($newAvailabilityId)) { 
                        updateProductAttribute($productId, 'availability', $newAvailabilityId);
                    }    
                    if (!empty($newCost)) { 
                        updateProductAttribute($productId, 'cost', $newCost);
                    }    
                    $result = 'UPDATED';
                    $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($productId);
                    $inventoryQty = $stock->getQty();
                    if ($inventoryQty = 0) { 
                        updateProductStock($productId, 10);   
                        $result = 'UPDATED_STOCK';
                    }    
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
      . ',' . str_pad($sku, 20)
      . ',' . str_pad(formatPrice($oldPrice), 10)
      . ',' . str_pad(formatPrice($newPrice), 10)
      . ',' . str_pad(getAvailabilityText($oldAvailabilityId), 30)
      . ',' . str_pad(getAvailabilityText($newAvailabilityId), 30)
      . ',' . str_pad(formatPrice($oldCost), 10)
      . ',' . str_pad(formatPrice($newCost), 10)
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