<?php

# incspf error
# incspf db
# incspf depins/getName
# incspf git/getFileCreateDate
# incspf exec

namespace SPF\depins;

function getList()
{

    prepareTable();

    $depins = array();
    foreach (array('*', '*/*') as $patt) {
        foreach (glob("app/depins/$patt") as $file) {
            if (getName($file)) {
                $depins[$file] = preg_replace('~^' . preg_quote(getcwd()) . '~', '', $file);
            }
        }
    }

    foreach (\SPF\db()->query('select * from devtool_depins_done')->fetchAll() as $row) {
        $depin = $row['file'];
        if (strpos($depin, '/') === false) {
            $depin = 'dev/depins/' . $depin;
        }
        if (isset($depins[$depin])) {
            unset($depins[$depin]);
        }
    }

    foreach ($depins as $file => &$depin) {
        $depin = (object)array(
            'file' => $file,
            'content' => file_get_contents($file),
            'date' => is_dir('.git') ? \SPF\git\getFileCreateDate($file) : '-',
        );
    }

    usort($depins, 'SPF\depins\compareDepins');

    return $depins;

}

function compareDepins($a, $b)
{
    $v1 = preg_replace('~^.+depins/~', '', $a->file);
    $v2 = preg_replace('~^.+depins/~', '', $b->file);
    return version_compare($v1, $v2);
}

function prepareTable()
{
    if (!\SPF\db()->query('show tables like "devtool_depins_done"')->rowCount()) {
        if (\SPF\db()->query('show tables like "ism_depins_done"')->rowCount()) {
            if (!\SPF\db()->query('rename table ism_depins_done to devtool_depins_done')) {
                \SPF\error('Can\'t rename table ism_depins_done');
            }
        } else {
            $query = '
                CREATE TABLE `devtool_depins_done` (
			      `file` varchar(150) NOT NULL,
			      `date` datetime NOT NULL,
			      `dev` varchar(50) NOT NULL,
			      PRIMARY KEY (`file`),
			      KEY `date` (`date`),
			      KEY `dev` (`dev`)
		        ) ENGINE=MyISAM
            ';
            if (!\SPF\db()->query($query)) {
                \SPF\error('Can\'t create table devtool_depins_done');
            }
        }
    }
}