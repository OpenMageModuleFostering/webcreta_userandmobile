<?php
$installer = $this;

$store = Mage::app()->getStore(Mage_Core_Model_App::ADMIN_STORE_ID);

$eavConfig = Mage::getSingleton('eav/config');
$attribute = array(
    'label'        => 'mobile',
    'visible'      => true,
	'unique'       => true,
    'required'     => false,
    'type'         => 'varchar',
    'input'        => 'text',
    'sort_order'    => 65,
    'validate_rules'    => array(
            'max_text_length'   => 30,
            'min_text_length'   => 1
    ),
    'used_in_forms' => array('adminhtml_customer','adminhtml_checkout','customer_account_edit', 'customer_account_create', 'checkout_register'),
);

$installer->addAttribute('customer','mobile', $attribute);

$attributes  = array('mobile' => $attribute);

foreach ($attributes as $attributeCode => $data) {
    $attribute = $eavConfig->getAttribute('customer', $attributeCode);
    $attribute->setWebsite( (($store->getWebsite())?$store->getWebsite():0));
    $attribute->addData($data);
    if (false === ($attribute->getIsSystem() == 1 && $attribute->getIsVisible() == 0)) {
        $usedInForms = array(
            'customer_account_create',
            'customer_account_edit',
            'checkout_register',
        );
        if (!empty($data['adminhtml_only'])) {
            $usedInForms = array('adminhtml_customer');
        } else {
            $usedInForms[] = 'adminhtml_customer';
        }
        if (!empty($data['adminhtml_checkout'])) {
            $usedInForms[] = 'adminhtml_checkout';
        }else {
            $usedInForms[] = 'adminhtml_checkout';
        }

        $attribute->setData('used_in_forms', $usedInForms);
    }
    $attribute->save();
}

$installer->startSetup();

$result = $installer->getConnection()->raw_fetchRow("SHOW COLUMNS from {$this->getTable('sales_flat_quote')} like '%customer_mobile%'");
if(!is_array($result) || !in_array('customer_mobile', $result)){
$installer->run("
    ALTER TABLE  `{$this->getTable('sales_flat_quote')}`
        ADD  `customer_mobile` VARCHAR( 255 ) NULL AFTER  `customer_taxvat`
    ");
}

$installer->endSetup();

?>