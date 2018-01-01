<?php

class Lycenok_Datafeed_Helper_Data extends Mage_Core_Helper_Abstract { 


public $domain;

/* group: common */

/**
 * Constructor. 
*/  
function __construct() { 
    // We take domain from order copy email config value
    $this->domain = substr(
        strstr(
            Mage::getStoreConfig(
                'sales_email/order/copy_to'
                , Mage::app()->getStore()
            ), '@')
            , 1
        );
} 

public function logMessage($message) { 
	if (isset($_POST['outputMethod'])) {
		if ($_POST['outputMethod'] == 'all') { 
			echo $message . "\n";
		}
	}	
} 

/**
  * format price value
  */
public function formatPrice($numberValue) { 
    // delete thousands separator
    $tempString = str_replace(',', '', $numberValue);
    if (!empty($tempString) && is_numeric($tempString)) { 
        return number_format($tempString , 2, '.', '');
    } else {
        return "";
    }
} 

/**
  * format percent value 
  */
public function formatPercent($numberValue) { 
    if (!empty($numberValue)) { 
        return number_format($numberValue, 2, '.', '') . '%';
    } else {
        return "";
    }
} 

public function outputSelect($fieldName, $valueList) { 
    $fieldValue = (isset($_REQUEST[$fieldName]) ? $_REQUEST[$fieldName] : "");
echo '	<select name="' . $fieldName . '">';
    foreach ($valueList as $value) { 
echo '		<option value="' . $value . '" ' . ($fieldValue == $value ? "selected" : "") . '>' 
   . strtoupper($value) . '</option>';
}        
echo '	</select>';
}     

public function isProduction() { 
  if (strpos(strtolower(Mage::getBaseUrl()), $this->domain) !== false) {
      return true;
  } else {
      return false;
  }
} 

/**
  * Get Goutte Client for navigating as a browser
  */
public function initGoutteClient() { 
    // require Goutte library autoload
    require_once Mage::getBaseDir('lib') 
      . DIRECTORY_SEPARATOR 
      . 'Goutte'
      . DIRECTORY_SEPARATOR 
      . 'composer'
      . DIRECTORY_SEPARATOR 
      . 'vendor'
      . DIRECTORY_SEPARATOR 
      . 'autoload.php'
    ;
    $client = new \Goutte\Client();
    // Create and use a guzzle client instance 
    // that will time out after 90 seconds
    $guzzleClient = new \GuzzleHttp\Client(array(
      'timeout' => 90
    , 'verify'  => false
    , 'cookies' => true
    ));
    $client->setClient($guzzleClient);
    return $client;
} 

/**
  * Send mail with attachement.
**/
public function sendMail($subject, $bodyHtml, $csvReportContents = null, $csvReportName = null) {
    $mail = new Zend_Mail();
    $mail->setType(Zend_Mime::MULTIPART_RELATED);
    $mail->setBodyHtml($bodyHtml);
    $mail->setFrom('datafeed@' . $this->domain, 'Lycenok Datafeed');
    $mailRecipient = ($this->isProduction() ? 'sales' : 'eugene') . '@' . $this->domain;
    $mail->addTo($mailRecipient, 'Eugene');
    $mail->setSubject($subject);
    if (!empty($csvReportName)) { 
        $dir = Mage::getBaseDir();
        $file = $mail->createAttachment($csvReportContents);
        $file ->type        = 'text/csv';
        $file ->disposition = Zend_Mime::DISPOSITION_INLINE;
        $file ->encoding    = Zend_Mime::ENCODING_BASE64;
        $file ->filename    = $csvReportName;
    }    
    try {
        //Confimation E-Mail Send
        $mail->send();
        Mage::log('mail sent to: ' . $mailRecipient);
    }
    catch(Exception $error) {
        Mage::getSingleton('core/session')->addError($error->getMessage());
        return false;
    }    
} 


/* group: product */

/*
  return array(
    'search_mask'
  , 'base_sku_count' 
  , 'no_base_sku_count'
  , 'product_data' => array('product_id', 'base_sku')
  )
  
  skuMatchMode in ('LEFT', 'IGNORE_DASH', 'EXACT', 'LEFT_IGNORE_DASH')
*/  
public function findProduct(
  $sku
, $name = null
, $skuMatchMode = 'IGNORE_DASH'
, $baseSkuSearchFlag = true
) { 
    global $baseSkuAttributeId;
	$sku =  str_replace('\'', '', $sku);
	$sku =  str_replace('\\', '', $sku);
	$sku = trim($sku, '()');
	$resource = Mage::getSingleton('core/resource');
	$readConnection = $resource->getConnection('core_read');
	$table = $resource->getTableName('catalog/product');
    $findResult = array('search_mask' => null, 'product_data' => array(), 
      'no_base_sku_count' => 0, 'base_sku_count' => 0
    );
	// try to match words
	if (strlen($sku) >=4) { 
        if ($skuMatchMode == 'LEFT') { 
            $findResult['search_mask'] = " " . $sku . " %";
            $searchExpression = "concat(' ', sku, ' ') like '" . $findResult['search_mask'] . "'";
        } else if ($skuMatchMode == 'LEFT_IGNORE_DASH') { 
            $findResult['search_mask'] = str_replace('-', ' ', " " . $sku . " %");
            $searchExpression = "concat(' ', replace(sku, '-', ' '), ' ') like '" . $findResult['search_mask'] . "'";
        } else if ($skuMatchMode == 'EXACT') {        
            $findResult['search_mask'] = $sku;
            $searchExpression = "sku = '" . $findResult['search_mask'] . "'";
        } else if ($skuMatchMode == 'IGNORE_DASH') {        
            $findResult['search_mask'] = str_replace('-', ' ', $sku);
            $searchExpression = "replace(sku, '-', ' ') = '" . $findResult['search_mask'] . "'";
        } else {
            throw new Exception('Uknown skuMatchMode: "' . $skuMatchMode . '"');
        } 
        $findResult['product_data'] = $readConnection->fetchAll(
          "select entity_id as product_id, sku 
              from " . $table . " p
           where 
             -- website SKU is at the beginning
          " . $searchExpression
        );
        if ($baseSkuSearchFlag) { 
            // add base sku search
            if (!isset($baseSkuAttributeId)) {
                $baseSkuAttributeId = $readConnection->fetchOne(
                    "select attribute_id from eav_attribute where attribute_code = 'base_sku'"
                );
            } 
            $baseSkuResult = 
                $readConnection->fetchAll(            
                "select   
                    entity_id as product_id
                    , (select sku from catalog_product_entity p where p.entity_id = v.entity_id) as sku
                    , value as base_sku
                from
                    catalog_product_entity_varchar v
                where  
                    -- source sku is like (contains) base sku
                    -- replace dashes with spaces
                    ' " . str_replace('-', ' ', $sku) . " ' 
                    like concat('% ', replace(value, '-', ' '), ' %')
                    and v.attribute_id = " . $baseSkuAttributeId 
                );
            $findResult['no_base_sku_count'] = count($findResult['product_data']);    
            if (count($baseSkuResult) > 0) {
                $findResult['base_sku_count'] = count($baseSkuResult);
                $findResult['product_data'] = array_merge($findResult['product_data'], $baseSkuResult);
                $findResult['search_mask'] = $findResult['search_mask']  . " && base_sku";
                $this->logMessage('baseSkuResult: count=' . count($baseSkuResult));
            }
        }
	}	
	if (!empty($name) && count($findResult['product_data']) == 0) { 
	  $words = explode(' ', strtr(trim($name), '-:', '  '));
	  foreach ($words as $word) { 
		$findResult = findProduct($word, null, $skuMatchMode, $baseSkuSearchFlag);
		// For name words allow only one match
		if (count($findResult['product_data']) == 1) { 
		   return $findResult;
		} 
	  } 
	} 
	return $findResult;
} 

/*
  get product attribute decimal value
*/
public function getProductAttributeDecimal($productId, $attributeCode){
	$resource = Mage::getSingleton('core/resource');
	$readConnection = $resource->getConnection('core_read');
	return	
	  $readConnection->fetchOne(
	    'select value from catalog_product_entity_decimal where entity_id=' . $productId . '
		 and attribute_id = (select attribute_id from eav_attribute where attribute_code = \'' . $attributeCode . '\')'
      );
}

/*
  get product attribute integer value
*/
public function getProductAttributeInt($productId, $attributeCode){
	$resource = Mage::getSingleton('core/resource');
	$readConnection = $resource->getConnection('core_read');
	return	
	  $readConnection->fetchOne(
	    'select value from catalog_product_entity_int where entity_id=' . $productId . '
		 and attribute_id = (select attribute_id from eav_attribute where attribute_code = \'' . $attributeCode . '\')'
      );
}

/*
  get product attribute decimal value
*/
public function getProductAttributeVarchar($productId, $attributeCode) {
	$resource = Mage::getSingleton('core/resource');
	$readConnection = $resource->getConnection('core_read');
	return	
	  $readConnection->fetchOne(
	    'select value from catalog_product_entity_varchar where entity_id=' . $productId . '
		 and attribute_id = (select attribute_id from eav_attribute where attribute_code = \'' . $attributeCode . '\')'
      );
}

/*
  get product attribute datetime value
*/
public function getProductAttributeDatetime($productId, $attributeCode) {
	$resource = Mage::getSingleton('core/resource');
	$readConnection = $resource->getConnection('core_read');
	return	
	  $readConnection->fetchOne(
	    'select value from catalog_product_entity_datetime where entity_id=' . $productId . '
		 and attribute_id = (select attribute_id from eav_attribute where attribute_code = \'' . $attributeCode . '\')'
      );
}

public function deleteAttributeValue($productId, $attributeCode) { 
	$resource = Mage::getSingleton('core/resource');
	$writeConnection = $resource->getConnection('core_write');
    $writeConnection->delete(
      "catalog_product_entity_int"
    , 'attribute_id = (select attribute_id from eav_attribute where attribute_code = \'' . $attributeCode . '\')'
       . (!empty($productId) ? ' and entity_id = ' . $productId : '')
    );
    $writeConnection->delete(
      "catalog_product_entity_decimal"
    , 'attribute_id = (select attribute_id from eav_attribute where attribute_code = \'' . $attributeCode . '\')'
       . (!empty($productId) ? ' and entity_id = ' . $productId : '')
    );
    $writeConnection->delete(
      "catalog_product_entity_varchar"
    , 'attribute_id = (select attribute_id from eav_attribute where attribute_code = \'' . $attributeCode . '\')'
       . (!empty($productId) ? ' and entity_id = ' . $productId : '')
    );
    $writeConnection->delete(
      "catalog_product_entity_datetime"
    , 'attribute_id = (select attribute_id from eav_attribute where attribute_code = \'' . $attributeCode . '\')'
       . (!empty($productId) ? ' and entity_id = ' . $productId : '')
    );
} 


/**
 * get availability text 
 */
public function getAvailabilityText($availability) {
	 switch($availability) {
		case 251: return "back order";
		case 252: return "in stock";
		case 254: return "not available";
		case 253: return "suppliers";
	    return 'unknown(' . $availability . ')';
	 }
}

/**
  * get availability id by text
*/  
public function getAvailabilityId($text) { 
    $ltext = strtolower($text);
    if (strpos($ltext, 'back') !== false) {
        return 251;
    } else if (strpos($ltext, 'supplier') !== false) {
        return 253;
    } else if (strpos($ltext, 'in stock') !== false) {
        return 252;
    } else if (strpos($ltext, 'not avail') !== false) { 
        return 254;
    }
    throw new Exception('Unknown availability text: "' . $text . '"');
} 


public function updateProductStock($productId, $qty) { 
    $resource = Mage::getSingleton('core/resource');
    $write = $resource->getConnection('core_write');
    $write->update(
        "cataloginventory_stock_item"
        , array("qty" => $qty, 'is_in_stock' => ($qty > 0 ? 1 : 0))
        , "product_id = " . $productId
    );
} 

public function updateProductAttribute($productId, $attributeCode, $value) { 
    $this->logMessage('updating ' . $attributeCode . ': "' . $value . '" for product_id=' . $productId);
	Mage::getSingleton('catalog/product_action')->updateAttributes(
	  array($productId), array($attributeCode => $value), 0
	); 
} 

}
?>