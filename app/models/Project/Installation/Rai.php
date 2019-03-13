<?php

namespace Project\Installation;

/**
 * Class Rai
 * @package Project\Installation
 *
 * @method \Project\Installation inst()
 */
trait Rai
{

    function uploadRai($scheduleRaiClean = true)
    {
        if (isset($this->_rai)) {
            return $this->_rai;
        }
        return $this->_rai = $this->_uploadRai($scheduleRaiClean);
    }

    function _uploadRai($scheduleRaiClean = true)
    {

        $containerFolder = 'zdevtool';
        $absContainerFolder = $this->inst()->_docRoot . $containerFolder . '/';
        $folder = 'RAI-' . rand(100000, 999999);
        $absFolder = $absContainerFolder . $folder . '/';
        $pwd = rand(1000, 99999999);
        $pwdExpr = 'unserialize(' . json_encode(serialize($pwd)) . ')';
        $instInfoExpr = 'json_decode(' . json_encode($this->inst()->getDataJson()) . ')';
        $ldapUserExpr = 'unserialize(' . json_encode(serialize(LDAP_USER)) . ')';
        $config = <<<config
<?php
\$PWD = $pwdExpr;
\$instInfo = $instInfoExpr;
\$ldapUser = $ldapUserExpr;
config;


        if ($this->inst()->type === 'remote') {

            $remoteInst = $this->inst();

            // prepare RAI
            exec(cmd(
                array(
                    'cp -r %s %s',
                    'echo %s > %s',
                    'cd %s',
                    'tar -cf %s %s',
                    'rm -rf %s',
                ),
                APP_DIR . 'RAI',
                TMP_DIR . $folder,
                $config,
                TMP_DIR . $folder . '/config.php',
                TMP_DIR,
                $folder . '.tar',
                $folder,
                $folder
            ));

            // upload RAI to remote
            if (!$remoteInst->execOld('mkdir -p %s', $absContainerFolder)) {
                return false;
            }

            $remoteInst->uploadFile(TMP_DIR . $folder . '.tar', $absContainerFolder . $folder . '.tar');
            $remoteInst->execOld(
                array(
                    'cd %s',
                    'tar -xf %s',
                    'rm -rf %s',
                    // if local time and server time have big diff then rai functions don't work due to "expire" security check
                    'find %s -exec touch -m {} \;'
                ),
                $absContainerFolder,
                $folder . '.tar',
                $folder . '.tar',
                $absFolder
            );

            // execOld because can fail if dir existed and you are not owner
            $remoteInst->execOld('chmod -R g+w %s', $absContainerFolder);

            // clean RAI tag on local
            exec(cmd('rm -rf %s', TMP_DIR . $folder . '.tar'));

        } else {
            exec(cmd('mkdir %s', $absContainerFolder));
            exec(cmd('cp -r %s %s', APP_DIR . 'RAI', $absFolder), $o, $r);
            if ($r !== 0) {
                return false;
            }
            file_put_contents($absFolder . 'config.php', $config);
        }

        $rai = (object)array(
            'dir' => $absFolder,
            'url' => isset($this->_url) ? $this->_url . $containerFolder . '/' . $folder . '/' : false,
            'PWD' => $pwd,
            'inst' => $this,
        );

        if ($scheduleRaiClean) {
            $this->scheduleRaiClean($rai);
        }

        return $rai;

    }

    function scheduleRaiClean($rai)
    {

        static $isRemoveAllRaiScheduled;
        global $raiToClean;

        if (!$isRemoveAllRaiScheduled) {
            register_shutdown_function(array($this, 'removeAllRai'));
            $isRemoveAllRaiScheduled = true;
        }

        if (!isset($raiToClean)) {
            $raiToClean = array();
        }
        $raiToClean[] = $rai;

    }

    function removeAllRai()
    {
        global $raiToClean;
        foreach ($raiToClean as $rai) {
            $this->removeRai($rai);
        }
    }

    function removeRai($rai)
    {
        // rmdir should work only if dir is empty
        $rai->inst->execOld('rm -rf %s; rmdir %s', $rai->dir, preg_replace('~/[^/]+/$~', '', $rai->dir));
    }

    function execRaiScriptByUrl($script, $params = array())
    {
        if (!$rai = $this->uploadRai()) {
            return false;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $rai->url . $script . (count($params) ? '?' . http_build_query($params) : ''));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if (!empty($this->httpAuth)) {
            $user = $this->httpAuth->user;
            $password = $this->httpAuth->password;
            curl_setopt($ch, CURLOPT_USERPWD, "$user:$password");
        }
        // debug RAI, uncomment it only temporary but not commit, otherwise it can hangs on Alpha if xdebug is enabled there
        //  curl_setopt($ch, CURLOPT_HTTPHEADER, array("Cookie: XDEBUG_SESSION=PHPSTORM"));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array('PWD' => $rai->PWD));
        $output = curl_exec($ch);
        if ($output === false) {
            trigger_error(curl_error($ch), E_USER_WARNING);
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $this->execOutput = $output;
        if ($httpCode !== 200) {
            return false;
        }

        // encode last line in case of some notices e.g. deprecated php.ini configuration
        $result = explode("\n", $output);
        return json_decode(end($result));
    }

    /**
     * @deprecated use SPF instead
     */
    function execRaiScript($script, $params = array())
    {
        if (!$rai = $this->uploadRai()) {
            return false;
        }
        $command = cmd('php %s', $rai->dir . $script);
        if (count($params)) {
            $command .= ' ' . call_user_func_array('shellescapef', $params);
        }
        if (!$this->inst()->execOld($command)) {
            return false;
        }
        // encode last line in case of some notices e.g. deprecated php.ini configuration
        $o = explode("\n", trim($this->inst()->execOutput));
        return json_decode(end($o));
    }

}
