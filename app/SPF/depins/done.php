<?php

# incspf db

namespace SPF\depins;

function done($file, $dev)
{
    $db = \SPF\db();
    $db->query(
        'insert into devtool_depins_done (`file`, `date`, `dev`) values (
		    ' . $db->quote($file) . ',
		    ' . $db->quote(date('Y-m-d H:i:s')) . ',
		    ' . $db->quote($dev) . ')'
    );
}
