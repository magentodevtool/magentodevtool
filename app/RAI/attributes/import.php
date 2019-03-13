<?php

require_once __DIR__ . '/../init.php';
require_once MAGE_ROOT . 'app/Mage.php';

Mage::app('admin');

if (!checkImportFiles(array('var/attributes.csv', 'var/attribute_sets.csv'))) {
    die(json_encode(
        array(
            'success' => false,
            'message' => "Input files not found or aren't readable"
        )
    ));
}

try {
    $success = importAttributes();
} catch (Exception $e) {
    $success = false;
    Mage::log($e->getMessage(), null, 'attributesImport.log');
}

die(json_encode(
    array(
        'success' => $success,
        'message' => "For details please check /var/log/attributesImport.log"
    )
));

function loadCSV($file, $required)
{
    $handle = fopen(MAGE_ROOT . $file, 'r');
    $header = fgetcsv($handle);
    foreach ($header as $key => $title) {
        $header[$key] = strtolower(str_replace(' ', '_', $title));
    }
    foreach ($required as $title) {
        if (!in_array($title, $header)) {
            Mage::log(
                'Please check input file ' . $file . ', missed required field: '
                . ucwords(str_replace('_', ' ', $title)),
                null,
                'attributesImport.log'
            );
            return false;
        }
    }
    $data = array();
    while ($row = fgetcsv($handle)) {
        $row_assoc = array();
        foreach ($row as $key => $field) {
            $row_assoc[$header[$key]] = $field;
        }
        $data[] = $row_assoc;
    }
    fclose($handle);
    return $data;
}

function checkImportFiles($files)
{
    foreach ($files as $file) {
        if (!is_readable(MAGE_ROOT . $file)) {
            Mage::log('Input file ' . $file . ', isn\'t readable or don\'t exist.', null, 'attributesImport.log');
            return false;
        }
    }
    return true;
}

function importAttributes()
{
    $requiredFields = [
        'attribute_code',
        'input_type',
        'attribute_label_(nl)',
        'scope',
        'is_unique',
        'is_required',
        'apply_to',
        'is_configurable',
        'is_searchable',
        'is_visible_in_advanced_search',
        'is_comparable',
        'is_filterable',
        'is_filterable_in_search',
        'is_used_for_promo_rules',
        'is_visible_on_front',
        'used_in_product_listing',
        'used_for_sort_by'
    ];

    $attributes = loadCSV('var/attributes.csv', $requiredFields);
    if ($attributes === false) {
        return false;
    }

    Mage::log('Start attributes import', null, 'attributesImport.log');

    foreach ($attributes as $attribute) {
        $action = 'Updated';
        $model = getAttributeByCode($attribute['attribute_code']);
        if (is_null($model)) {
            $action = 'Created';
            $model = Mage::getModel('catalog/resource_eav_attribute');
        }
        $productEntityTypeID = Mage::getModel('catalog/product')->getResource()->getTypeId();
        $attribute_data = array(
            'attribute_code' => $attribute['attribute_code'],
            'frontend_input' => transformFrontendInput(strtolower($attribute['input_type'])),
            'frontend_label' => $attribute['attribute_label_(nl)'],
            'is_global' => transformScope(strtolower($attribute['scope'])),
            'is_visible' => '1',
            'is_user_defined' => '1',
            'is_searchable' => strtolower($attribute['is_searchable']) == 'yes' ? '1' : '0',
            'is_filterable' => strtolower($attribute['is_filterable']) == 'yes' ? '1' : '0',
            'is_comparable' => strtolower($attribute['is_comparable']) == 'yes' ? '1' : '0',
            'is_visible_on_front' => strtolower($attribute['is_visible_on_front']) == 'yes' ? '1' : '0',
            'is_html_allowed_on_front' => '0',
            'is_used_for_price_rules' => strtolower($attribute['is_used_for_promo_rules']) == 'yes' ? '1' : '0',
            'is_filterable_in_search' => strtolower($attribute['is_filterable_in_search']) == 'yes' ? '1' : '0',
            'used_in_product_listing' => strtolower($attribute['used_in_product_listing']) == 'yes' ? '1' : '0',
            'used_for_sort_by' => strtolower($attribute['used_for_sort_by']) == 'yes' ? '1' : '0',
            'is_configurable' => strtolower($attribute['is_configurable']) == 'yes' ? '1' : '0',
            'is_visible_in_advanced_search' => strtolower($attribute['is_visible_in_advanced_search']) == 'yes' ? '1' : '0',
            'position' => '0',
            'is_wysiwyg_enabled' => '0',
            'search_weight' => '1',
            'is_unique' => strtolower($attribute['is_unique']) == 'yes' ? '1' : '0',
            'is_required' => strtolower($attribute['is_required']) == 'yes' ? '1' : '0',
        );
        $attribute_data['backend_type'] = $model->getBackendTypeByInput($attribute_data['frontend_input']);

        if ($applyTo = transformApplyTo($attribute['apply_to'])) {
            $attribute_data['apply_to'] = $applyTo;
        }

        $model->addData($attribute_data);
        $model->setEntityTypeId($productEntityTypeID);
        try {
            $model->save();
            global $ATTRIBUTES;
            $ATTRIBUTES[$model->getAttributeCode()] = $model;
            Mage::log(
                $action . ' attribute: id ' . $model->getId() . ' ' . $model->getAttributeCode(),
                null,
                'attributesImport.log'
            );
        } catch (Exception $e) {
            Mage::log($e->getMessage(), null, 'attributesImport.log');
        }
    }
    Mage::log('End attributes import', null, 'attributesImport.log');

    return importAttributeSets();
}

function transformFrontendInput($value)
{
    switch ($value) {
        case 'text field':
            return 'text';
        case 'text area':
            return 'textarea';
        case 'date':
            return 'date';
        case 'yes/no':
            return 'boolean';
        case 'multiple select':
            return 'multiselect';
        case 'dropdown':
            return 'select';
        case 'price':
            return 'price';
        case 'media image':
            return 'media_image';
        case 'fixed product tax':
            return 'weee';
        default:
            return 'text';
    }
}


function transformScope($value)
{
    switch ($value) {
        case 'store view':
            return 0;
        case 'website':
            return 2;
        case 'global':
            return 1;
        default:
            return 0;
    }
}

function transformApplyTo($value)
{
    $applyValues = array();
    $values = explode(',', $value);
    $values = array_map("trim", $values);
    $lowerCaseValues = array_map("strtolower", $values);
    foreach ($lowerCaseValues as $apply) {
        switch ($apply) {
            case 'simple':
                $applyValues[] = 'simple';
                break;
            case 'grouped':
                $applyValues[] = 'grouped';
                break;
            case 'configurable':
                $applyValues[] = 'configurable';
                break;
            case 'virtual':
                $applyValues[] = 'virtual';
                break;
            case 'bundle':
                $applyValues[] = 'bundle';
                break;
            case 'downloadable':
                $applyValues[] = 'downloadable';
                break;
            case 'giftcard':
                $applyValues[] = 'giftcard';
                break;
        }
    }
    return $applyValues;
}

function importAttributeSets()
{
    $requiredFields = array('base', 'attribute_set', 'group', 'code');
    $attrSets = loadCSV('var/attribute_sets.csv', $requiredFields);

    if ($attrSets == false) {
        Mage::log(
            'Please check input file /var/attribute_sets.csv, missed required fields.',
            null,
            'attributesImport.log'
        );
        return false;
    }

    if (!checkAttributeBaseSet($attrSets)) {
        Mage::log(
            'Please check input file /var/attribute_sets.csv, : it contains nonexistent Base Attribute Set.',
            null,
            'attributesImport.log'
        );
        return false;
    }


    Mage::log('Start attribute sets import', null, 'attributesImport.log');

    foreach ($attrSets as $attrSet) {
        $attributeSet = createAttributeSet($attrSet['attribute_set'], getAttributeSetByName($attrSet['base']));
        $group = createAttributeGroup($attrSet['group'], $attributeSet);
        $attribute = getAttributeByCode($attrSet['code']);
        if (is_object($attributeSet) && is_object($group) && is_object($attribute)) {
            assignAttributeToGroup($attributeSet, $group, $attribute);
        }
    }

    Mage::log('End of attribute sets import', null, 'attributesImport.log');

    return true;
}

function assignAttributeToGroup(
    Mage_Eav_Model_Entity_Attribute_Set $set,
    Mage_Eav_Model_Entity_Attribute_Group $group,
    Mage_Catalog_Model_Resource_Eav_Attribute $attribute
) {
    $model = Mage::getModel('eav/entity_setup', 'core_setup');
    if ($model instanceof Mage_Eav_Model_Entity_Setup) {
        $model->addAttributeToSet('catalog_product', $set->getId(), $group->getId(), $attribute->getId());
    }
    Mage::log(
        'Assigning attribute ' . $attribute->getAttributeCode() . ' to group ' . $set->getAttributeSetName() . ' > ' . $group->getAttributeGroupName(),
        null,
        'attributesImport.log'
    );
}

/**
 * Create a new Attribute Set
 *
 * @param   String $setName The name of the attribute set to be created
 * @param   Mage_Eav_Model_Entity_Attribute_Set|String $baseSet The attribute set to copy the groups from
 * @return  Mage_Eav_Model_Entity_Attribute_Set
 */
function createAttributeSet($setName, $baseSet)
{
    $setName = trim($setName);
    if (strlen($setName) <= 0) {
        return false;
    }

    //Check if the set already exists
    $model = getAttributeSetByName($setName);
    if (!is_null($model)) {
        Mage::log(
            "Found attribute set: id " . $model->getId() . ' ' . $model->getAttributeSetName(),
            null,
            'attributesImport.log'
        );
        return $model;
    }

    //First create AttributeSet
    $model = Mage::getModel('eav/entity_attribute_set');
    if ($model instanceof Mage_Eav_Model_Entity_Attribute_Set) {

        //Create a new set
        $productEntityTypeID = Mage::getModel('catalog/product')->getResource()->getTypeId();
        $model->setEntityTypeId($productEntityTypeID);
        $model->setAttributeSetName($setName);
        $model->validate();
        $model->save();
        $model->initFromSkeleton($baseSet->getId());
        $model->save();
        Mage::log(
            "Created attribute set: id" . $model->getId() . ' ' . $model->getAttributeSetName(),
            null,
            'attributesImport.log'
        );

        global $ATTRIBUTE_SETS;
        $ATTRIBUTE_SETS[$model->getAttributeSetName()] = $model;
    }
    return $model;
}

function createAttributeGroup($groupName, Mage_Eav_Model_Entity_Attribute_Set $attributeSet)
{
    $groupName = trim($groupName);
    if (strlen($groupName) <= 0) {
        return false;
    }

    //Check if the set already exists
    $model = getAttributeGroupByName($groupName, $attributeSet->getId());
    if (!is_null($model)) {
        Mage::log(
            "Found attribute group: id " . $model->getId() . ' ' . $model->getAttributeGroupName(),
            null,
            'attributesImport.log'
        );
        return $model;
    }

    $model = Mage::getModel('eav/entity_attribute_group');
    if ($model instanceof Mage_Eav_Model_Entity_Attribute_Group) {
        $model->setAttributeSetId($attributeSet->getId());
        $model->setAttributeGroupName($groupName);
        $sortOrder =
            intval(
                $model->getCollection()
                    ->addFilter('attribute_set_id', $attributeSet->getId())
                    ->addOrder('sort_order', 'DESC')
                    ->getFirstItem()
                    ->getSortOrder()
            ) + 1;
        $model->setSortOrder($sortOrder);
        $model->save();


        global $ATTRIBUTE_GROUPS;
        $ATTRIBUTE_GROUPS[$attributeSet->getId() . ':' . $model->getAttributeGroupName()] = $model;
    }
    Mage::log(
        "Created attribute group: id " . $model->getId() . ' ' . $model->getAttributeGroupName(),
        null,
        'attributesImport.log'
    );
    return $model;
}

function getAttributeGroupByName($groupName, $attributeSetId)
{
    global $ATTRIBUTE_GROUPS;
    if (!is_array($ATTRIBUTE_GROUPS)) {
        $ATTRIBUTE_GROUPS = [];
    }

    $groupName = trim($groupName);


    if (!isset($ATTRIBUTE_GROUPS[strval($attributeSetId) . ':' . $groupName])) {
        if (strlen($groupName) == 0) {
            $ATTRIBUTE_GROUPS[strval($attributeSetId) . ':' . $groupName] = null;
        } else {
            $groupCollection = Mage::getModel('eav/entity_attribute_group')->getCollection();
            $groupCollection->addFilter('attribute_set_id', $attributeSetId);
            $groupCollection->addFilter('attribute_group_name', $groupName);
            $ATTRIBUTE_GROUPS[strval($attributeSetId) . ':' . $groupName] = $groupCollection->count() > 0 ? $groupCollection->getFirstItem() : null;
        }
    }
    return $ATTRIBUTE_GROUPS[strval($attributeSetId) . ':' . $groupName];
}

/**
 *
 * @param   String $setName
 * @return  Mage_Eav_Model_Entity_Attribute_Set
 */
function getAttributeSetByName($setName)
{

    global $ATTRIBUTE_SETS;
    if (!is_array($ATTRIBUTE_SETS)) {
        $ATTRIBUTE_SETS = array();
    }

    $setName = trim($setName);

    if (!isset($ATTRIBUTE_SETS[$setName])) {
        if (strlen($setName) == 0) {
            $ATTRIBUTE_SETS[$setName] = null;
        } else {
            $productEntityTypeID = Mage::getModel('catalog/product')->getResource()->getTypeId();
            $modelCollection = Mage::getModel('eav/entity_attribute_set')->getCollection();
            $modelCollection->addFilter('attribute_set_name', $setName);
            $modelCollection->addFilter('entity_type_id', $productEntityTypeID);
            $ATTRIBUTE_SETS[$setName] = $modelCollection->count() > 0 ? $modelCollection->getFirstItem() : null;
        }
    }
    return $ATTRIBUTE_SETS[$setName];
}

function getAttributeByCode($attributeCode)
{

    global $ATTRIBUTES;
    if (!is_array($ATTRIBUTES)) {
        $ATTRIBUTES = array();
    }

    $attributeCode = trim($attributeCode);
    if (!array_key_exists($attributeCode, $ATTRIBUTES)) {
        if (strlen($attributeCode) == 0) {
            $ATTRIBUTES[$attributeCode] = null;
        } else {
            $modelCollection = Mage::getResourceModel('catalog/product_attribute_collection');
            $modelCollection->addFilter('attribute_code', $attributeCode);
            if ($modelCollection->count() > 0) {
                $ATTRIBUTES[$attributeCode] = $modelCollection->getFirstItem();
            } else {
                $ATTRIBUTES[$attributeCode] = null;
            }
        }
    }
    return $ATTRIBUTES[$attributeCode];
}


function checkAttributeBaseSet($attrSets, $baseSetName = 'base')
{
    if (is_array($attrSets) && is_string($baseSetName)) {
        foreach ($attrSets as $attrSet) {
            if (is_null(getAttributeSetByName($attrSet[$baseSetName]))) {
                return false;
            }
        }

        return true;
    } else {
        return false;
    }
}