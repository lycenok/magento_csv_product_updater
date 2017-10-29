<?php

if (isset($_SERVER['MAGE_IS_DEVELOPER_MODE'])) {
    Mage::setIsDeveloperMode(true);
}



/* group: common */

function logMessage($message) { 
	if (isset($_POST['outputMethod'])) {
		if ($_POST['outputMethod'] == 'all') { 
			echo $message . "\n";
		}
	}	
} 

/**
  * format price value
  */
function formatPrice($numberValue) { 
    if (!empty($numberValue)) { 
        return number_format(
            // delete thousands separator
            str_replace(',', '', $numberValue), 2, '.', ''
        );
    } else {
        return "";
    }
} 

/**
  * format percent value 
  */
function formatPercent($numberValue) { 
    if (!empty($numberValue)) { 
        return number_format($numberValue, 2, '.', '') . '%';
    } else {
        return "";
    }
} 

function outputSelect($fieldName, $valueList) { 
    $fieldValue = (isset($_REQUEST[$fieldName]) ? $_REQUEST[$fieldName] : "");
echo '	<select name="' . $fieldName . '">';
    foreach ($valueList as $value) { 
echo '		<option value="' . $value . '" ' . ($fieldValue == $value ? "selected" : "") . '>' 
   . strtoupper($value) . '</option>';
}        
echo '	</select>';
}     

function isProduction() { 
  if (strpos($_SERVER['SERVER_NAME'], '<prod domain>') !== false) {
      return true;
  } else {
      return false;
  }
} 


/**
  * Send mail with attachement.
**/
function sendMail($subject, $csvReportContents, $csvReportName, $textReportContents) {
    $mail = new Zend_Mail();
    $mail->setType(Zend_Mime::MULTIPART_RELATED);
    $mail->setBodyHtml($html_body);
    $mail->setFrom('datafeed@<prod domain>', 'Lycenok Datafeed');
    $mail->addTo((isProduction() ? 'sales' : 'eugene') . '@<prod domain>', 'Eugene');
    $mail->setSubject($subject);
    $dir = Mage::getBaseDir();
    $file = $mail->createAttachment($csvReportContents);
    $file ->type        = 'text/csv';
    $file ->disposition = Zend_Mime::DISPOSITION_INLINE;
    $file ->encoding    = Zend_Mime::ENCODING_BASE64;
    $file ->filename    = $csvReportName;
    $file = $mail->createAttachment($textReportContents);
    $file ->type        = 'text/html';
    $file ->disposition = Zend_Mime::DISPOSITION_INLINE;
    $file ->encoding    = Zend_Mime::ENCODING_BASE64;
    $file ->filename    = 'output.html';
    try {
        //Confimation E-Mail Send
        $mail->send();
    }
    catch(Exception $error) {
        Mage::getSingleton('core/session')->addError($error->getMessage());
        return false;
    }    
} 


/* group: product */

/*
  return array('product_data' => array('product_id','search_mask'))
*/  
function findProduct(
  $sku
, $name
, $allowSubstringFlag
, $baseSkuSearchFlag
) { 
    global $baseSkuAttributeId;
	$sku =  str_replace('\'', '', $sku);
	$sku =  str_replace('\\', '', $sku);
	$sku = trim($sku, '()');
	$resource = Mage::getSingleton('core/resource');
	$readConnection = $resource->getConnection('core_read');
	$table = $resource->getTableName('catalog/product');
    $findResult = array('search_mask' => null, 'product_data' => array());
	// try to match exact words
	if (strlen($sku) >=4) { 
        $findResult['search_mask'] = " " . $sku . " %";
        $findResult['product_data'] = $readConnection->fetchAll(
          "select entity_id as product_id, sku 
              from " . $table . " p
           where 
             -- website SKU is at the beginning
             concat(' ', replace(sku, '-', ' '), ' ') like replace(' " . $sku . " %', '-', ' ')
          "
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
                    '" . $sku . "' like concat('%', value, '%')
                    and v.attribute_id = " . $baseSkuAttributeId 
                );
            if (count($baseSkuResult) > 0) {
                $findResult['product_data'] = array_merge( $findResult['product_data'], $baseSkuResult);
                $findResult['search_mask'] = "% " . $sku . " % && base_sku";
                logMessage('baseSkuResult: count=' . count($baseSkuResult));
            }
        }
        // Try to find an sku containing the source sku as a substring
		if (count($findResult['product_data']) == 0 && $allowSubstringFlag) {
            $findResult['search_mask'] = "% " . $sku . " %";
			$findResult['product_data'] = $readConnection->fetchAll(
              'select entity_id as product_id, sku from ' . $table . ' where sku like \'%' . $sku . '%\''
            );
		}
	}	
	if (!empty($name) && count($findResult['product_data']) == 0) { 
	  $words = explode(' ', strtr(trim($name), '-:', '  '));
	  foreach ($words as $word) { 
		$findResult = findProduct($word, null, $allowSubstringFlag, $baseSkuSearchFlag);
		// For name words allow only one match
		if (count($findResult['product_data']) == 1) { 
		   return $findResult;
		} 
	  } 
	} 
	return $findResult;
} 

/*
  get product attribute integer value
*/
function getProductAttributeInt($productId, $attributeCode){
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
function getProductAttributeDecimal($productId, $attributeCode){
	$resource = Mage::getSingleton('core/resource');
	$readConnection = $resource->getConnection('core_read');
	return	
	  $readConnection->fetchOne(
	    'select value from catalog_product_entity_decimal where entity_id=' . $productId . '
		 and attribute_id = (select attribute_id from eav_attribute where attribute_code = \'' . $attributeCode . '\')'
      );
}

/**
 * get availability text 
 */
function getAvailabilityText($availability) {
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
function getAvailabilityId($text) { 
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

function deleteAttributeValue($attributeCode) { 
	$resource = Mage::getSingleton('core/resource');
	$writeConnection = $resource->getConnection('core_write');
    $writeConnection->delete(
      "catalog_product_entity_int"
    , 'attribute_id = (select attribute_id from eav_attribute where attribute_code = \'' . $attributeCode . '\')'
    );
    $writeConnection->delete(
      "catalog_product_entity_decimal"
    , 'attribute_id = (select attribute_id from eav_attribute where attribute_code = \'' . $attributeCode . '\')'
    );
    $writeConnection->delete(
      "catalog_product_entity_varchar"
    , 'attribute_id = (select attribute_id from eav_attribute where attribute_code = \'' . $attributeCode . '\')'
    );
} 

function updateProductStock($productId, $qty) { 
    $resource = Mage::getSingleton('core/resource');
    $write = $resource->getConnection('core_write');
    $write->update(
        "cataloginventory_stock_item"
        , array("qty" => $qty, 'is_in_stock' => ($qty > 0 ? 1 : 0))
        , "product_id = " . $productId
    );
} 

function updateProductAttribute($productId, $attributeCode, $value) { 
    logMessage('updating ' . $attributeCode . ': "' . $value . '" for product_id=' . $productId);
	Mage::getSingleton('catalog/product_action')->updateAttributes(
	  array($productId), array($attributeCode => $value), 0
	); 
} 
?>