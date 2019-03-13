<?php

class Magento_Resource_Customer extends Magento_Service_Api
{
    public $db;
    public $suffix;
    public $skipEmails;
    public $isEnterprise;
    public $config = array();
    public $skip = array(
        'customers' => array(),
        'addresses' => array(),
        'orders' => array(),
        'invoices' => array(),
        'shipments' => array(),
        'creditmemos' => array()
    );

    public function __construct($data)
    {
        $this->db = $this->getConnection();
        $this->isEnterprise = $this->isEnterprise();
        $this->collectConfigData($data);

    }

    public function collectConfigData($data)
    {
        $this->config = $this->_mergeDefaults($data, array(
            'is_fake_emails' => false,
            'is_fake_names' => false,
            'delete_customers' => false,
            'suffix' => ' . fake',
            'skip' => '',
        ));

        $this->suffix = $this->config['suffix'];
        $this->skipEmails = $this->config['skip'];

        return $this->config;
    }

    public function isEnterprise()
    {
        return count($this->db->query("SHOW TABLES LIKE '{{{prefix}}}enterprise%'")->fetchColumn(0));
    }

    public function collectSkipData()
    {
        $this->skip['customers'] = $this->db->query('SELECT `entity_id` FROM `{{{prefix}}}customer_entity` WHERE ' . $this->_skipEmails('email', $this->skipEmails, 'LIKE', 'OR'))->fetchColumn(0);
        if (!empty($this->skip['customers'])) {
            $this->skip['addresses'] = $this->db->query('SELECT `entity_id` FROM `{{{prefix}}}customer_address_entity` WHERE `parent_id` IN(' . implode(', ', $this->skip['customers']) . ')')->fetchColumn(0);
        }

        $this->skip['orders'] = $this->db->query('SELECT `entity_id` FROM `{{{prefix}}}sales_flat_order` WHERE ' . $this->_skipEmails('customer_email', $this->skipEmails, 'LIKE', 'OR'))->fetchColumn(0);
        if (!empty($this->skip['orders'])) {
            $this->skip['invoices'] = $this->db->query('SELECT `entity_id` FROM `{{{prefix}}}sales_flat_invoice` WHERE `order_id` IN(' . implode(', ', $this->skip['orders']) . ')')->fetchColumn(0);
            $this->skip['shipments'] = $this->db->query('SELECT `entity_id` FROM `{{{prefix}}}sales_flat_shipment` WHERE `order_id` IN(' . implode(', ', $this->skip['orders']) . ')')->fetchColumn(0);
            $this->skip['creditmemos'] = $this->db->query('SELECT `entity_id` FROM `{{{prefix}}}sales_flat_creditmemo` WHERE `order_id` IN(' . implode(', ', $this->skip['orders']) . ')')->fetchColumn(0);
        }
    }

    public function fakeEmails()
    {
        $this->db->query("UPDATE `{{{prefix}}}customer_entity` SET `email` = CONCAT(`email`, '{$this->suffix}') WHERE " . $this->_skipEmails('email', $this->skipEmails) . " AND `email` NOT LIKE '%{$this->suffix}';");
        $this->db->query("UPDATE `{{{prefix}}}customer_address_entity_varchar` SET `value` = CONCAT(`value`, '{$this->suffix}') WHERE " . $this->_skipEmails('value', $this->skipEmails) . " AND `value` NOT LIKE '%{$this->suffix}' AND `value` LIKE '%@%';");
        $this->db->query("UPDATE `{{{prefix}}}customer_entity_varchar` SET `value` = CONCAT(`value`, '{$this->suffix}') WHERE " . $this->_skipEmails('value', $this->skipEmails) . " AND `value` NOT LIKE '%{$this->suffix}' AND `value` LIKE '%@%';");
        $this->db->query("UPDATE `{{{prefix}}}sales_flat_quote_address` SET `email` = CONCAT(`email`, '{$this->suffix}') WHERE " . $this->_skipEmails('email', $this->skipEmails) . " AND `email` NOT LIKE '%{$this->suffix}';");
        $this->db->query("UPDATE `{{{prefix}}}sales_flat_order_address` SET `email` = CONCAT(`email`, '{$this->suffix}') WHERE " . $this->_skipEmails('email', $this->skipEmails) . " AND `email` NOT LIKE '%{$this->suffix}';");
        $this->db->query("UPDATE `{{{prefix}}}sales_flat_order` SET `customer_email` = CONCAT(`customer_email`, '{$this->suffix}') WHERE " . $this->_skipEmails('customer_email', $this->skipEmails) . " AND `customer_email` NOT LIKE '%{$this->suffix}';");
        $this->db->query("UPDATE `{{{prefix}}}sales_flat_quote` SET `customer_email` = CONCAT(`customer_email`, '{$this->suffix}') WHERE " . $this->_skipEmails('customer_email', $this->skipEmails) . " AND `customer_email` NOT LIKE '%{$this->suffix}';");
    }

    public function fakeNames()
    {
        $ids = $this->db->query('SELECT `entity_type_id` FROM  `eav_entity_type` WHERE entity_type_code = "customer"')->fetchColumn(0);
        if (isset($ids[0])) {
            $entity_type_id = $ids[0];
            $attributes = $this->db->query('SELECT `attribute_id` FROM  `eav_attribute` WHERE entity_type_id = "' . $entity_type_id . '" AND attribute_code IN("firstname", "lastname", "middlename", "prefix", "suffix")')->fetchColumn(0);
            $this->db->query("UPDATE `{{{prefix}}}customer_entity_varchar` cev JOIN `{{{prefix}}}customer_entity` ce ON ce.entity_id = cev.entity_id SET `value` = CONCAT(`value`, '{$this->suffix}') WHERE `attribute_id` IN (" . implode(',', $attributes) . ") AND " . $this->_skipEmails('ce`.`email', $this->skipEmails));
        }
    }

    public function deleteCustomers()
    {
        $this->db->query('DELETE FROM `{{{prefix}}}customer_entity` WHERE entity_id NOT IN(' . implode(', ', $this->skip['customers']) . ')');
        $tables = $this->db->query("SHOW TABLES LIKE '{{{prefix}}}customer\_entity\_%'")->fetchColumn(0);

        foreach ($tables as $table) {
            $this->db->query('DELETE FROM `' . $table . '` WHERE entity_id NOT IN(' . implode(', ', $this->skip['customers']) . ')');
        }

        if ($this->isEnterprise) {
            $this->db->query('DELETE FROM `{{{prefix}}}enterprise_customersegment_customer` WHERE customer_id NOT IN(' . implode(', ', $this->skip['customers']) . ')');
            $this->db->query('DELETE FROM `{{{prefix}}}enterprise_customerbalance` WHERE customer_id NOT IN(' . implode(', ', $this->skip['customers']) . ')');
        }
    }

    public function truncateCustomers()
    {
        $this->db->query('TRUNCATE `{{{prefix}}}customer_entity`');
        $tables = $this->db->query("SHOW TABLES LIKE '{{{prefix}}}customer\_entity\_%'")->fetchColumn(0);

        foreach ($tables as $table) {
            $this->db->query('TRUNCATE `' . $table . '`');
        }

        if ($this->isEnterprise) {
            $this->db->query('TRUNCATE `{{{prefix}}}enterprise_customersegment_customer`');
            $this->db->query('TRUNCATE `{{{prefix}}}enterprise_customerbalance`');
        }
    }

    public function deleteAddresses()
    {
        $this->db->query('DELETE FROM `{{{prefix}}}customer_address_entity` WHERE entity_id NOT IN(' . implode(', ', $this->skip['addresses']) . ')');
        $tables = $this->db->query("SHOW TABLES LIKE '{{{prefix}}}customer_address_entity_%'")->fetchColumn(0);

        foreach ($tables as $table) {
            $this->db->query('DELETE FROM `' . $table . '` WHERE entity_id NOT IN(' . implode(', ', $this->skip['addresses']) . ')');
        }
    }

    public function truncateAddresses()
    {
        $this->db->query('TRUNCATE `{{{prefix}}}customer_address_entity`');
        $tables = $this->db->query("SHOW TABLES LIKE '{{{prefix}}}customer_address_entity_%'")->fetchColumn(0);

        foreach ($tables as $table) {
            $this->db->query('TRUNCATE `' . $table . '`');
        }
    }

    public function deleteOrders()
    {
        $skipOrders = !empty($this->skip['orders']) ? implode(', ', $this->skip['orders']) : "''";
        $this->db->query('DELETE FROM `{{{prefix}}}sales_flat_order` WHERE entity_id NOT IN(' . $skipOrders . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_flat_order_address` WHERE parent_id NOT IN(' . $skipOrders . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_flat_order_grid` WHERE entity_id NOT IN(' . $skipOrders . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_flat_order_item` WHERE order_id NOT IN(' . $skipOrders . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_flat_order_payment` WHERE parent_id NOT IN(' . $skipOrders . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_flat_order_status_history` WHERE parent_id NOT IN(' . $skipOrders . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_order_aggregated_created` WHERE id NOT IN(' . $skipOrders . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_order_aggregated_updated` WHERE id NOT IN(' . $skipOrders . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_order_tax` WHERE order_id NOT IN(' . $skipOrders . ')');
        $this->db->query('DELETE `{{{prefix}}}sales_order_tax_item`.* FROM `{{{prefix}}}sales_order_tax_item`
                            JOIN `{{{prefix}}}sales_order_tax` ON `{{{prefix}}}sales_order_tax`.`tax_id` = `{{{prefix}}}sales_order_tax_item`.`tax_id`
                            WHERE `{{{prefix}}}sales_order_tax`.`order_id` NOT IN(' . $skipOrders . ')');

        if ($this->isEnterprise) {
            $this->db->query('DELETE FROM `{{{prefix}}}enterprise_sales_order_grid_archive` WHERE entity_id NOT IN(' . $skipOrders . ')');
            $this->db->query('DELETE FROM `{{{prefix}}}enterprise_sales_invoice_grid_archive` WHERE order_id NOT IN(' . $skipOrders . ')');
            $this->db->query('DELETE FROM `{{{prefix}}}enterprise_sales_shipment_grid_archive` WHERE order_id NOT IN(' . $skipOrders . ')');
            $this->db->query('DELETE FROM `{{{prefix}}}enterprise_sales_creditmemo_grid_archive` WHERE order_id NOT IN(' . $skipOrders . ')');
        }

        // delete payment transaction
        $this->db->query('DELETE FROM `{{{prefix}}}sales_payment_transaction` WHERE order_id NOT IN(' . $skipOrders . ')');
    }

    public function truncateOrders()
    {
        $this->db->query('TRUNCATE `{{{prefix}}}sales_flat_order`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_flat_order_address`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_flat_order_grid`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_flat_order_item`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_flat_order_payment`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_flat_order_status_history`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_order_aggregated_created`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_order_aggregated_updated`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_order_tax`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_order_tax_item`');

        if ($this->isEnterprise) {
            $this->db->query('TRUNCATE `{{{prefix}}}enterprise_sales_order_grid_archive`');
            $this->db->query('TRUNCATE `{{{prefix}}}enterprise_sales_invoice_grid_archive`');
            $this->db->query('TRUNCATE `{{{prefix}}}enterprise_sales_shipment_grid_archive`');
            $this->db->query('TRUNCATE `{{{prefix}}}enterprise_sales_creditmemo_grid_archive`');
        }

        // delete payment transaction
        $this->db->query('TRUNCATE `{{{prefix}}}sales_payment_transaction`');
    }

    public function deleteShipments()
    {
        $this->db->query('DELETE FROM `{{{prefix}}}sales_flat_shipment` WHERE entity_id NOT IN(' . implode(', ', $this->skip['shipments']) . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_flat_shipment_comment` WHERE parent_id NOT IN(' . implode(', ', $this->skip['shipments']) . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_flat_shipment_grid` WHERE entity_id NOT IN(' . implode(', ', $this->skip['shipments']) . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_flat_shipment_item` WHERE parent_id NOT IN(' . implode(', ', $this->skip['shipments']) . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_flat_shipment_track` WHERE parent_id NOT IN(' . implode(', ', $this->skip['shipments']) . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_shipping_aggregated` WHERE id NOT IN(' . implode(', ', $this->skip['shipments']) . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_shipping_aggregated_order` WHERE id NOT IN(' . implode(', ', $this->skip['shipments']) . ')');
    }

    public function truncateShipments()
    {
        $this->db->query('TRUNCATE `{{{prefix}}}sales_flat_shipment`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_flat_shipment_comment`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_flat_shipment_grid`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_flat_shipment_item`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_flat_shipment_track`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_shipping_aggregated`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_shipping_aggregated_order`');
    }

    public function deleteInvoices()
    {
        $this->db->query('DELETE FROM `{{{prefix}}}sales_flat_invoice` WHERE entity_id NOT IN(' . implode(', ', $this->skip['invoices']) . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_flat_invoice_comment` WHERE parent_id NOT IN(' . implode(', ', $this->skip['invoices']) . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_flat_invoice_grid` WHERE entity_id NOT IN(' . implode(', ', $this->skip['invoices']) . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_flat_invoice_item` WHERE parent_id NOT IN(' . implode(', ', $this->skip['invoices']) . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_invoiced_aggregated` WHERE id NOT IN(' . implode(', ', $this->skip['invoices']) . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_invoiced_aggregated_order` WHERE id NOT IN(' . implode(', ', $this->skip['invoices']) . ')');
    }

    public function truncateInvoices()
    {
        $this->db->query('TRUNCATE `{{{prefix}}}sales_flat_invoice`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_flat_invoice_comment`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_flat_invoice_grid`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_flat_invoice_item`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_invoiced_aggregated`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_invoiced_aggregated_order`');
    }

    public function deleteCreditmemos()
    {
        $this->db->query('DELETE FROM `{{{prefix}}}sales_flat_creditmemo` WHERE entity_id NOT IN(' . implode(', ', $this->skip['creditmemos']) . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_flat_creditmemo_comment` WHERE parent_id NOT IN(' . implode(', ', $this->skip['creditmemos']) . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_flat_creditmemo_grid` WHERE entity_id NOT IN(' . implode(', ', $this->skip['creditmemos']) . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_flat_creditmemo_item` WHERE parent_id NOT IN(' . implode(', ', $this->skip['creditmemos']) . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_refunded_aggregated` WHERE id NOT IN(' . implode(', ', $this->skip['creditmemos']) . ')');
        $this->db->query('DELETE FROM `{{{prefix}}}sales_refunded_aggregated_order` WHERE id NOT IN(' . implode(', ', $this->skip['creditmemos']) . ')');
    }

    public function truncateCreditmemos()
    {
        $this->db->query('TRUNCATE `{{{prefix}}}sales_flat_creditmemo`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_flat_creditmemo_comment`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_flat_creditmemo_grid`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_flat_creditmemo_item`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_refunded_aggregated`');
        $this->db->query('TRUNCATE `{{{prefix}}}sales_refunded_aggregated_order`');
    }

    public function truncateBestsellers()
    {
        $tables = array();
        $tables = $this->db->query("SHOW TABLES LIKE '{{{prefix}}}sales\_bestsellers\_%';")->fetchColumn(0);
        foreach ($tables as $table) {
            $this->db->query("TRUNCATE TABLE `{$table}`;");
        }
    }

    public function truncateQuotes()
    {
        $tables = array();
        $tables = $this->db->query("SHOW TABLES LIKE '{{{prefix}}}sales\_flat\_quote%';")->fetchColumn(0);
        foreach ($tables as $table) {
            $this->db->query("TRUNCATE TABLE `{$table}`;");
        }
    }
}