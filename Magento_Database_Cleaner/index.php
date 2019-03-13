<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include "database.php";
include "api.php";
include "functions.php";
include "resource/customer.php";

ob_start();

if (!empty($_POST['check_database'])) {
    try {
        $db = new Database_Adapter(post('db_host'), post('db_user'), post('db_pass'), post('db_name'), post('db_prefix'));
        echo "<div class='success-message'><b>Database connection:</b><i> OK</i></div>";
    } catch (Exception $e) {
        echo "<div class='error-message'><b>Database connection:</b><i> " . nl2br($e->getMessage()) . "</i><p>" . nl2br($e) . "</p></div>";
    }
}

if (!empty($_POST['exec_query'])) {
    $query_status = 1;
    try {
        $q = $_POST['exec_query'];
        $db = new Database_Adapter(post('db_host'), post('db_user'), post('db_pass'), post('db_name'), post('db_prefix'));
        $db->query($q);
        echo "<div class='success-message'><b>Query: </b><i> {$q}</i></div>";
    } catch (Exception $e) {
        $query_status = 0;
        echo "<div class='error-message'><b>Query FAIL:</b><i> " . nl2br($e->getMessage()) . "</i><p>" . nl2br($e) . "</p></div>";
    }
}

if (!empty($_POST['actions'])) {
    $queries_by_actions = array();
    try {
        $api = new Magento_Service_API($connection = new Database_Adapter(post('db_host'), post('db_user'), post('db_pass'), post('db_name'), post('db_prefix'), 1));
        // setup query collector
        $actions = $_POST['actions'];
        foreach ($actions as $action => $value) {
            if ($value) {
                try {
                    $queries = array();
                    $connection->onQuery = function ($sql) use (&$queries) {
                        if (strpos($sql, 'SHOW') !== false || strpos($sql, 'SELECT') !== false) return;
                        echo "<pre>" . $sql . "</pre>";
                        $queries[] = $sql;
                    };
                    $api->run($action, post('actions_args/' . $action));
                    $queries_by_actions[$action] = $queries;
                    echo "<div class='success-message'><b>" . $action . ":</b><i> OK</i></div>";
                } catch (Exception $e) {
                    echo "<div class='error-message'><b>" . $action . ":</b><i> " . nl2br($e->getMessage()) . "</i><p>" . nl2br($e) . "</p></div>";
                }
            }
        }
    } catch (Exception $e) {
        echo "<div class='error-message'><b>Something goes wrong:</b><i> " . nl2br($e->getMessage()) . "</i><p>" . nl2br($e) . "</p></div>";
    }
}

$output = ob_get_contents();
ob_end_clean();

if (isset($_GET['do']) && $_GET['do'] == 'ajax') {
    $response = array('output' => $output);
    if (isset($queries_by_actions))
        $response['queries'] = $queries_by_actions;
    if (isset($query_status))
        $response['query_ok'] = $query_status;
    echo json_encode($response);
    return;
}

?>
<html>
<head>
    <title>Magento Service script</title>
    <style>
        * {
            font-family: sans-serif;
        }

        body, table {
            font-size: 13px;
        }

        td {
            vertical-align: top;
            padding: 3px;
            margin: 0px;
        }

        td.label {
            padding-top: 6px;
        }

        .error-message {
            border: 1px solid red;
            padding: 10px;
            background: #ffefef;
            color: #800;
            margin-bottom: 20px;
        }

        .error-message p {
            font-size: 12px;
            font-family: monospace;
        }

        input[type=text] {
            border: 1px solid #909090;
            padding: 3px;
            border-radius: 2px;
            color: #222;
        }

        pre {
            font-size: 11px;
            background: #eee;
        }

        .success-message {
            border: 1px solid green;
            padding: 10px;
            background: #efffef;
            color: #080;
            margin-bottom: 20px;
        }

        p.description {
            font-size: 11px;
            padding: 0px;
            margin: 0px;
        }

        table.actions tr.action > td {
            background: #88d0dd;
            border-top: 2px solid white;
        }

        td.step {
            padding: 0 20px;
            border: 2px solid #ddd;
            border-top: none;
            border-bottom: none;
        }

        table td.step:last-child {
            border-right: none;
        }

        table td.step:first-child {
            border-left: none;
        }

        #queries li, #queries {
            padding: 0px;
            margin: 0;
            list-style: none;
        }

        #queries li.query {
            background: #ddd;
            font-size: 11px;
            padding: 4px;
            margin: 2px;
        }

        #queries li.success {
            background: #dfd;
        }

        #queries li.fail {
            background: #fdd;
        }

        #queries li.group {
            text-transform: uppercase;
            font-weight: bold;
            background: #aaa;
            padding: 6px;
        }

        #queries {
            margin-top: 10px;
        }

        #output {
            height: 600px;
            overflow-y: scroll;
            margin: 10px;
            padding: 10px;
        }
    </style>
    <script
        src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
    <script type="application/javascript">
        var script_url = 'http://<?php echo $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME']; ?>?do=ajax';

        function check_connection() {
            var args = "";
            $('input[name^=db_]').each(function () {
                args += $(this).attr('name') + "=" + $(this).val() + "&";
            });
            args += "check_database=yes";
            do_request(script_url, args);
        }

        function apply_actions() {
            var args = $('form').serialize();
            do_request(script_url, args);
        }

        function execute_queries() {
            //$('input[disabled]').attr('disable')
            $("#queries li.query").removeClass('success').removeClass('fail');
            $("#queries li.query").each(function () {
                var $query = $(this);
                var args = "";
                $('input[name^=db_]').each(function () {
                    args += $(this).attr('name') + "=" + $(this).val() + "&";
                });
                args += "exec_query=" + $query.text();
                do_request(script_url, args, function (data) {
                    if (data.query_ok) {
                        $query.addClass('success').removeClass('fail');
                    } else {
                        $query.removeClass('success').addClass('fail');
                    }
                });
            });
        }

        function do_request(url, args, callback) {
            console.log(url);
            console.log(args);
            console.log(callback);
            $.post(url, args, function (data) {
                console.log(data);
                if (typeof data.output != 'undefined')
                    $("#output").html(data.output + $("#output").html());
                if (typeof data.queries != 'undefined') {
                    $("#queries li").remove();
                    for (var action in data.queries) {
                        $("#queries").append('<li class="group">' + action + '</li>');
                        for (var i in data.queries[action]) {
                            $("#queries").append('<li class="query">' + data.queries[action][i] + '</li>');
                        }
                    }
                }
                if (typeof callback == 'function')
                    callback(data);
            }, 'json');
        }
    </script>
</head>
<body>
<div class="content">
<div class='main-form'>
<form method="post">
<table class="frame" width="100%">
<tr>
<td width="200px" class="step">
    <h2>1. Database credentials</h2> <input type="button"
                                            onclick='check_connection()' value="Check connection"><br><br>
    <table cellspacing="0">
        <tr>
            <td class='label'>Host</td>
            <td><input type='text'
                       value='<?= htmlspecialchars(post('db_host')) ?>'
                       name='db_host'>
            </td>
        </tr>
        <tr>
            <td class='label'>User</td>
            <td><input type='text'
                       value='<?= htmlspecialchars(post('db_user')) ?>' name='db_user'>
            </td>
        </tr>
        <tr>
            <td class='label'>Pass</td>
            <td><input type='text'
                       value='<?= htmlspecialchars(post('db_pass')) ?>' name='db_pass'>
            </td>
        </tr>
        <tr>
            <td>Name</td>
            <td><input type='text'
                       value='<?= htmlspecialchars(post('db_name')) ?>'
                       name='db_name'></td>
        </tr>
        <tr>
            <td class='label'>Prefix</td>
            <td><input type='text'
                       value='<?= htmlspecialchars(post('db_prefix')) ?>' name='db_prefix'>
            </td>
        </tr>
    </table>
</td>
<td width="500px" class="step">
<h2>2. Actions</h2>
<input type="button" value="Generate SQL"
       onclick="apply_actions()">
</br>
<table cellspacing="0" class="actions">
<tr>
    <td><input type="checkbox" onchange="$('[name^=actions][type=checkbox]').attr('checked', this.checked)"></td>
    <td class='label'>Mass checker</td>
</tr>
<tr class="action">
    <td><input name='actions[change_admin]'
            <?php echo checked('actions/change_admin'); ?> type="checkbox">
    </td>
    <td class='label'>Patch admin with UID#1</td>
</tr>
<tr>
    <td></td>
    <td>
        <table>
            <tr>
                <td class='label'>Name</td>
                <td><input type='text'
                           value='<?php echo post('actions_args/change_admin/name', 'John Doe'); ?>'
                           name='actions_args[change_admin][name]'>
                </td>
            </tr>
            <tr>
                <td class='label'>E-Mail</td>
                <td><input type='text'
                           value='<?php echo post('actions_args/change_admin_args/email', 'j.doe@fake.com'); ?>'
                           name='actions_args[change_admin][email]'>
                </td>
            </tr>
            <tr>
                <td></td>
                <td><input type='checkbox'
                        <?php if (post('actions_args/change_admin/is_fix_emails')) echo "checked"; ?>
                           name='actions_args[change_admin][is_fix_emails]'> replace all
                    emails in back office to this one
                </td>
            </tr>
            <tr>
                <td class='label'>Login</td>
                <td><input type='text'
                           value='<?php echo post('actions_args/change_admin/login', 'ISM'); ?>'
                           name='actions_args[change_admin][login]'>
                </td>
            </tr>
            <tr>
                <td class='label'>Pass</td>
                <td><input type='text'
                           value='<?php echo post('actions_args/change_admin/password', 'abcABC123'); ?>'
                           name='actions_args[change_admin][password]'>
                </td>
            </tr>
            <tr>
                <td><input type='checkbox'
                        <?php if (post('actions_args/change_admin/is_fix_perm')) echo "checked"; ?>
                           name='actions_args[change_admin][is_fix_perm]'></td>
                <td>Set UID#1 role to 1</td>
            </tr>
            <tr>
                <td><input type='checkbox'
                        <?php if (post('actions_args/change_admin/is_delete_admins')) echo "checked"; ?>
                           name='actions_args[change_admin][is_delete_admins]'></td>
                <td>Delete admins (except UID#1)</td>
            </tr>
        </table>
    </td>
</tr>
<tr class="action">
    <td><input name='actions[patch_customers]'
            <?php echo checked('actions/patch_customers'); ?> type="checkbox">
    </td>
    <td class='label'>Patch customers</td>
</tr>
<tr>
    <td></td>
    <td>
        <table>
            <tr>
                <td><input name='actions_args[patch_customers][is_fake_emails]'
                        <?php echo checked('actions_args/patch_customers/is_fake_emails'); ?>
                           type="checkbox"></td>
                <td class='label'>Fake customer emails</td>
            </tr>
            <tr>
                <td><input name='actions_args[patch_customers][is_fake_names]'
                        <?php echo checked('actions_args/patch_customers/is_fake_names'); ?>
                           type="checkbox"></td>
                <td class='label'>Fake customers names</td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <table>
                        <tr>
                            <td class='label'>Suffix</td>
                            <td>
                                <input type='text'
                                       value='<?php echo post('actions_args/patch_customers/suffix', '.fake'); ?>'
                                       name='actions_args[patch_customers][suffix]'>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td><input name='actions_args[patch_customers][delete_customers]'
                        <?php echo checked('actions_args/patch_customers/delete_customers'); ?>
                           type="checkbox"></td>
                <td class='label'>Delete customer accounts, orders, invoices, creditmemos and shippings
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <table>
                        <tr>
                            <td class='label'>Skip customers with emails by patterns (keep empty to truncate all)</td>
                            <td>
                                <input
                                    type='text'
                                    value='<?php echo post('actions_args/patch_customers/skip', '%your-company%, %your-company'); ?>'
                                    name='actions_args[patch_customers][skip]'>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </td>
</tr>
<tr class="action">
    <td><input name='actions[url_change]'
            <?php echo checked('actions/url_change'); ?> type="checkbox">
    </td>
    <td class='label'>Change base url</td>
</tr>
<tr>
    <td></td>
    <td>
        <table>
            <tr>
                <td class='label'>Unsecure Base URL</td>
                <td><input type='text'
                           value='<?php echo post('actions_args/url_change/unsecure_base', '{{base_url}}'); ?>'
                           name='actions_args[url_change][unsecure_base]'>
                </td>
            </tr>
            <tr>
                <td class='label'>Secure Base URL</td>
                <td><input type='text'
                           value='<?php echo post('actions_args/url_change/secure_base', '{{unsecure_base_url}}'); ?>'
                           name='actions_args[url_change][secure_base]'>
                </td>
            </tr>
        </table>
    </td>
</tr>
<tr class="action">
    <td><input name='actions[truncate_logs]'
            <?php echo checked('actions/truncate_logs'); ?>
               type="checkbox"></td>
    <td class='label'>Truncate logs</td>
</tr>
<tr class="action">
    <td><input name='actions[truncate_catalog]'
            <?php echo checked('actions/truncate_catalog'); ?>
               type="checkbox">
    </td>
    <td class='label'>Truncate categories</td>
</tr>
<tr class="action">
    <td><input name='actions[truncate_products]'
            <?php echo checked('actions/truncate_products'); ?>
               type="checkbox">
    </td>
    <td class='label'>Truncate products</td>
</tr>
<tr class="action">
    <td><input name='actions[truncate_relations]'
            <?php echo checked('actions/truncate_relations'); ?>
               type="checkbox">
    </td>
    <td class='label'>Truncate category/product relations</td>
</tr>
<tr class="action">
    <td><input name='actions[truncate_search]'
            <?php echo checked('actions/truncate_search'); ?>
               type="checkbox">
    </td>
    <td class='label'>Truncate catalog search</td>
</tr>
<tr class="action">
    <td><input name='actions[truncate_urlrewrites]'
            <?php echo checked('actions/truncate_urlrewrites'); ?>
               type="checkbox">
    </td>
    <td class='label'>Truncate URL rewrites</td>
</tr>
<tr class="action">
    <td><input name='actions[truncate_subscribers]'
            <?php echo checked('actions/truncate_subscribers'); ?>
               type="checkbox">
    </td>
    <td class='label'>Truncate subscribers</td>
</tr>
<tr class="action">
    <td><input name='actions[truncate_sessions]'
            <?php echo checked('actions/truncate_sessions'); ?>
               type="checkbox">
    </td>
    <td class='label'>Truncate DB session</td>
</tr>
<tr class="action">
    <td><input name='actions[truncate_heavy_tables]'
            <?php echo checked('actions/truncate_heavy_tables'); ?>
               type="checkbox">
    </td>
    <td class='label'>Truncate any >1Mb tables</td>
</tr>
</table>
<br></td>
<td class="step">
    <h2>3. SQL Queries</h2> <input type='button' value='Execute all'
                                   onclick='execute_queries()'> <input type='button' value='Clean'
                                                                       onclick='$("#queries").html("")'>
    <ul id='queries'>
    </ul>
</td>
</tr>
</table>
<hr>
<h2>
    Output <input type='button' onclick='$("#output").html("")'
                  value='Clean'>
</h2>

<div id='output'>
    <?php if (isset($output)) echo $output; ?>
</div>
</form>
</div>

</div>
</body>
</html>
