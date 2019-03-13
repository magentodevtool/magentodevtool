<?php

/** @var \Project\Installation */

if ($inst->type === 'remote') {

    try {
        $ssh = $inst->getSsh()->connect();
        $ssh->exec('pwd', $o, $r);
        $output = implode("\n", $o);
        $sshConnection = $r === 0 ? true : $output;
    } catch (Exception $e) {
        $sshConnection = $e->getMessage();
    }

    $zDevtoolFolder = 'Fix SSH connection first';
    if ($sshConnection === true) {
        unset($e);
        try {
            $inst->exec(array(
                'cd %s',
                'mkdir -p zdevtool',
                'echo "<?php echo 123;" >zdevtool/test904386796.php',
                'echo "Order deny,allow" >zdevtool/.htaccess',
                'echo "Allow from all" >>zdevtool/.htaccess',
            ), $inst->_docRoot);
        } catch (Exception $e) {
            $zDevtoolFolder = 'failed to create file zdevtool/test904386796.php in docroot';
        }
        if (!isset($e)) {
            $url = $inst->_url . 'zdevtool/test904386796.php';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            if (!empty($inst->httpAuth)) {
                $user = $inst->httpAuth->user;
                $password = $inst->httpAuth->password;
                curl_setopt($ch, CURLOPT_USERPWD, "$user:$password");
            }
            $output = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode === 200 && $output === "123") {
                $zDevtoolFolder = true;
            } elseif ($httpCode !== 200) {
                $zDevtoolFolder = "HTTP code $httpCode received but must be 200";
            } elseif ($output !== "123") {
                $zDevtoolFolder = "invalid output received";
            }

            $inst->exec('cd %s && rm -rf zdevtool/test904386796.php zdevtool/.htaccess', $inst->_docRoot);
            try {
                $inst->exec('cd %s && rmdir zdevtool', $inst->_docRoot);
            } catch (Exception $e) {
            }
        }
    }

    $repository = true;
    if (!$inst->isCloud) {
        try {
            $inst->exec('git fetch');
        } catch (Exception $e) {
            $repository = 'Can\'t fetch';
        }
    }

} else {

    $repository = true;
    if (!file_exists($inst->folder . '.git/HEAD')) {
        $repository = 'clone is required';
    } else {
        try {
            $inst->exec('git fetch');
            try {
                $testBranchName = 'devtool-test-branch-' . rand(0, 9999);
                $inst->exec('git push origin master:%s', $testBranchName);
                try {
                    $inst->exec('git push origin :%s', $testBranchName);
                } catch (Exception $e) {
                    $repository = 'Can\'t remove branch';
                }
            } catch (Exception $e) {
                $repository = 'Can\'t push branch';
            }
        } catch (Exception $e) {
            $repository = 'Can\'t fetch';
        }
    }

}

return $inst->form('testAccess/result', compact('sshConnection', 'userGroup', 'zDevtoolFolder', 'repository'));
