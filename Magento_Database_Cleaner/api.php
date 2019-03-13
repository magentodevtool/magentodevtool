<?php

class Magento_Service_Api
{

    protected $_actions = array(
        'change_admin' => '_doChangeAdmin',
        'patch_customers' => '_doPatchCustomers',
        'url_change' => '_doUrlChange',
        'truncate_logs' => '_doTruncateLogs',
        'truncate_catalog' => '_doTruncateCatalog',
        'truncate_products' => '_doTruncateProducts',
        'truncate_relations' => '_doTruncateRelations',
        'truncate_search' => '_doTruncateSearch',
        'truncate_urlrewrites' => '_doTruncateUrlRewrites',
        'truncate_subscribers' => '_doTruncateSubscribers',
        'truncate_sessions' => '_doTruncateSessions',
        'truncate_heavy_tables' => '_doTruncateHeavyTables',
    );

    const USER_ID = 1;

    const ROLE_ID = 1;

    const ROOT_CAT_PID = 0;

    const DEFAULT_CAT_PID = 1;

    protected static $_db;

    public function __construct(Database_Adapter $adapter)
    {
        self::$_db = $adapter;
    }

    public function getConnection()
    {
        return self::$_db;
    }

    public function getMessages()
    {
        return array();
    }

    public function run($action, $data = array())
    {
        if (!isset($this->_actions[$action]))
            throw new Exception("Unknown action '$action'");
        return call_user_func(array($this, $this->_actions[$action]), $data);
    }

    protected function _mergeDefaults($data, $defaults)
    {
        foreach ($defaults as $key => $value) {
            if (!empty($data[$key]))
                $defaults[$key] = $data[$key];
        }
        return $defaults;
    }

    protected function _skipEmails($field, $skip, $type = 'NOT LIKE', $glue = 'AND')
    {
        $where = (trim(strtoupper($glue)) == "AND") ? "1" : "0";
        foreach (explode(",", $skip) as $email) {
            $where .= " " . $glue . " `{$field}` " . $type . " '" . trim($email) . "'";
        }
        return $where;
    }

    // ACTIONS

    protected function _doChangeAdmin($data = array())
    {
        $connection = $this->getConnection();
        $data = $this->_mergeDefaults($data, array(
            'name' => 'John Doe',
            'email' => 'j.doe@fake.com',
            'login' => 'ISM',
            'password' => 'abcABC123',
            'is_fix_emails' => false,
            'is_fix_perm' => false,
            'is_delete_admins' => false
        ));
        $user_id = self::USER_ID;
        $role_id = self::ROLE_ID;
        $is_enterprise = count($connection->query("SHOW TABLES LIKE '{{{prefix}}}enterprise%'")->fetchColumn(0));
        $is_user_exist = count($connection->query("SELECT * FROM `{{{prefix}}}admin_user` WHERE `user_id` = {$user_id};")->fetchColumn(0));
        list($name, $surname) = explode(" ", (strpos($data['name'], ' ') === false) ? ($data['name'] . " Doe") : $data['name'], 2);

        if (!$is_user_exist) {
            $connection->query("INSERT INTO `{{{prefix}}}admin_user` (`user_id`, `firstname`, `lastname`, `email`, `username`, `password`, `extra`) VALUES ({$user_id}, '{$name}', '{$name}', '{$data['email']}', '{$data['login']}', '" . md5("aa" . $data['password']) . ":aa', 'N;');");
            $connection->query("INSERT INTO `{{{prefix}}}admin_role` (`role_type`, `user_id`, `role_name`, `tree_level`) VALUES ('U', {$user_id}, '{$name}', 2);");
        } else {
            $connection->query("UPDATE `{{{prefix}}}admin_user` SET `firstname` = '{$name}', `lastname` = '{$surname}', `username` = '{$data['login']}', `email` = '{$data['email']}', `password` = '" . md5("aa" . $data['password']) . ":aa', `extra` = 'N;', `created` = NOW(), `modified` = NOW(), `logdate` = NOW(), `is_active` = 1, `lock_expires` = NULL WHERE `user_id` = {$user_id};");
        }

        if ($data['is_fix_emails']) {
            $connection->query("UPDATE `{{{prefix}}}core_config_data` SET `value` = '{$data['email']}' WHERE `value` LIKE  '%@%';");
        }

        if ($data['is_fix_perm']) {
            $connection->query("UPDATE `{{{prefix}}}admin_role` SET `parent_id` =  {$role_id} WHERE `user_id` = {$user_id};");
        }

        if ($data['is_delete_admins']) {
            $connection->query("DELETE FROM `{{{prefix}}}admin_user` WHERE user_id != {$user_id};");
            $connection->query("DELETE FROM `{{{prefix}}}admin_role` WHERE user_id != {$user_id} AND role_id != {$role_id};");
        }

        if ($is_enterprise) {
            $connection->query("TRUNCATE TABLE `{{{prefix}}}enterprise_admin_passwords`;");
        }
    }

    protected function _doPatchCustomers($data = array())
    {
        $customer = new Magento_Resource_Customer($data);

        if ($customer->config['is_fake_emails']) $customer->fakeEmails();
        if ($customer->config['is_fake_names']) $customer->fakeNames();
        if ($customer->config['delete_customers']) {
            $customer->collectSkipData();
            $this->getConnection()->setForeignKeyChecks(0);
            empty($customer->skip['customers']) ? $customer->truncateCustomers() : $customer->deleteCustomers();
            empty($customer->skip['addresses']) ? $customer->truncateAddresses() : $customer->deleteAddresses();
            $customer->deleteOrders();
            empty($customer->skip['shipments']) ? $customer->truncateShipments() : $customer->deleteShipments();
            empty($customer->skip['invoices']) ? $customer->truncateInvoices() : $customer->deleteInvoices();
            empty($customer->skip['creditmemos']) ? $customer->truncateCreditmemos() : $customer->deleteCreditmemos();
            $customer->truncateBestsellers();
            $customer->truncateQuotes();
            $this->getConnection()->setForeignKeyChecks(1);
        }
    }

    protected function _doUrlChange($data = array())
    {
        $connection = $this->getConnection();
        $data = $this->_mergeDefaults($data, array(
            'unsecure_base' => '{{base_url}}',
            'secure_base' => '{{unsecure_base_url}}',
        ));
        $connection->query("UPDATE `{{{prefix}}}core_config_data` SET `value` = \"" . $data['unsecure_base'] . "\" WHERE `path` = 'web/unsecure/base_url';");
        $connection->query("UPDATE `{{{prefix}}}core_config_data` SET `value` = \"" . $data['secure_base'] . "\" WHERE `path` = 'web/secure/base_url';");
    }

    protected function _doTruncateLogs()
    {
        $connection = $this->getConnection();

        $tables = array();
        $tables = $connection->query("SHOW TABLES LIKE '{{{prefix}}}log\_%';")->fetchColumn(0);
        $connection->setForeignKeyChecks(0);
        foreach ($tables as $table) {
            $connection->query("TRUNCATE TABLE `{$table}`;");
        }
        $connection->setForeignKeyChecks(1);

        $tables = array();
        $tables = $connection->query("SHOW TABLES LIKE '{{{prefix}}}enterprise\_logging\_%';")->fetchColumn(0);
        $connection->setForeignKeyChecks(0);
        foreach ($tables as $table) {
            $connection->query("TRUNCATE TABLE `{$table}`;");
        }
        $connection->setForeignKeyChecks(1);
    }

    protected function _doTruncateCatalog()
    {
        $connection = $this->getConnection();

        // truncate catalog_category
        $tables = $connection->query("SHOW TABLES LIKE '{{{prefix}}}catalog\_category\_%';")->fetchColumn(0);
        $root_cat_pid = self::ROOT_CAT_PID;
        $default_cat_pid = self::DEFAULT_CAT_PID;
        $connection->setForeignKeyChecks(0);
        foreach ($tables as $table) {
            if ($table == 'catalog_category_entity') {
                $connection->query("DELETE FROM `{$table}` WHERE `parent_id` NOT IN({$root_cat_pid}, {$default_cat_pid});");
            } else {
                $connection->query("TRUNCATE TABLE `{$table}`;");
            }
        }
        $connection->setForeignKeyChecks(1);
        $this->_resetMviewMetadata($tables);
    }

    protected function _doTruncateProducts($data = array())
    {
        $data = $this->_mergeDefaults($data, array(
            'truncate_relations' => false,
        ));

        $connection = $this->getConnection();

        $tables = $connection->query("SHOW TABLES LIKE '{{{prefix}}}catalog\_product\_%';")->fetchColumn(0);

        // exclude base product relations (up-sells, cross-sels)
        $tables = array_diff($tables, array('catalog_product_link_attribute', 'catalog_product_link_type'));

        $connection->setForeignKeyChecks(0);
        foreach ($tables as $table) {
            $connection->query("TRUNCATE TABLE `{$table}`;");
        }
        $connection->setForeignKeyChecks(1);
        $this->_resetMviewMetadata($tables);

        // truncate products stock info
        $tables = array();
        $tables = $connection->query("SHOW TABLES LIKE '{{{prefix}}}cataloginventory\_stock\_%';")->fetchColumn(0);
        $connection->setForeignKeyChecks(0);
        foreach ($tables as $table) {
            $connection->query("TRUNCATE TABLE `{$table}`;");
        }
        $connection->setForeignKeyChecks(1);
        $this->_resetMviewMetadata($tables);

        // truncate reports
        $connection->setForeignKeyChecks(0);
        $connection->query("TRUNCATE TABLE `{{{prefix}}}report_compared_product_index`;");
        $connection->query("TRUNCATE TABLE `{{{prefix}}}report_viewed_product_index`;");
        $connection->query("TRUNCATE TABLE `{{{prefix}}}report_event`;");
        $connection->setForeignKeyChecks(1);

        // truncate index events
        $connection->setForeignKeyChecks(0);
        $connection->query("TRUNCATE TABLE `{{{prefix}}}index_event`;");
        $connection->setForeignKeyChecks(1);
    }

    protected function _doTruncateRelations($data = array())
    {
        // truncate catalog_category_product relations
        $tables = array();
        $connection = $this->getConnection();
        $tables = $connection->query("SHOW TABLES LIKE '{{{prefix}}}catalog\_category\_product%';")->fetchColumn(0);
        $connection->setForeignKeyChecks(0);
        foreach ($tables as $table) {
            $connection->query("TRUNCATE TABLE `{$table}`;");
        }
        $connection->setForeignKeyChecks(1);
        $this->_resetMviewMetadata($tables);
    }

    protected function _doTruncateSearch($data = array())
    {
        $tables = array();
        $connection = $this->getConnection();
        $tables = $connection->query("SHOW TABLES LIKE '{{{prefix}}}catalogsearch\_%';")->fetchColumn(0);
        $connection->setForeignKeyChecks(0);
        foreach ($tables as $table) {
            $connection->query("TRUNCATE TABLE `{$table}`;");
        }
        $connection->setForeignKeyChecks(1);
        $this->_resetMviewMetadata($tables);
    }

    protected function _doTruncateUrlRewrites($data = array())
    {
        $connection = $this->getConnection();
        // truncate Magento URL rewrites
        $tables = array_merge(
            $connection->query("SHOW TABLES LIKE '{{{prefix}}}%\_url\_rewrite%';")->fetchColumn(0),
            $connection->query("SHOW TABLES LIKE '{{{prefix}}}enterprise\_catalog\_%\_rewrite';")->fetchColumn(0),
            $connection->query("SHOW TABLES LIKE '{{{prefix}}}ecomdev\_urlrewrite\_%';")->fetchColumn(0)
        );
        $connection->setForeignKeyChecks(0);
        foreach ($tables as $table) {
            $connection->query("TRUNCATE TABLE `{$table}`;");
        }
        $connection->setForeignKeyChecks(1);

        $this->_resetMviewMetadata($tables);
    }

    protected function _doTruncateSubscribers()
    {
        $connection = $this->getConnection();
        $connection->setForeignKeyChecks(0);
        $connection->query("TRUNCATE TABLE `{{{prefix}}}newsletter_subscriber`;");
        $connection->setForeignKeyChecks(1);
    }

    protected function _doTruncateSessions()
    {
        $connection = $this->getConnection();
        $connection->setForeignKeyChecks(0);
        $connection->query("TRUNCATE TABLE `{{{prefix}}}core_session`;");
        $connection->setForeignKeyChecks(1);
    }

    protected function _doTruncateHeavyTables()
    {
        $connection = $this->getConnection();
        $rows = array();
        $rows = $connection->query("SELECT table_name, data_length FROM information_schema.tables where table_schema='" . $connection->getDBName() . "' and data_length > 1000000 order by data_length desc;")->fetchAll();

        $connection->query("# Check the list below to run only safe queries");
        foreach ($rows as $row) {
            $size = round($row['data_length'] / 1024 / 1024, 1);
            $connection->setForeignKeyChecks(0);
            $connection->query("#/*==== {$size} Mb ====*/TRUNCATE TABLE `{$row['table_name']}`;");
            $connection->setForeignKeyChecks(1);
        }
    }

    /**
     * Reset version_id in enterprise_mview_metadata table
     * Available from 1.13
     */
    protected function _resetMviewMetadata($tables)
    {
        $connection = $this->getConnection();
        foreach ($tables as $table) {
            if (preg_match('/.+_cl$/', $table)) {
                $connection->setForeignKeyChecks(0);
                $connection->query("UPDATE enterprise_mview_metadata SET version_id = 0 WHERE changelog_name = '{$table}';");
                $connection->setForeignKeyChecks(1);
            }
        }

    }
}