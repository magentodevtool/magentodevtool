<?php

namespace Project\Installation;

class Spf
{

    public $function;
    public $args;

    /**
     * @var \Project\Installation $inst
     */
    protected $inst;

    public function __construct($inst)
    {
        $this->inst = $inst;
    }

    public function run()
    {
        \spf::$function = $this->function;
        \spf::$args = $this->args;
        $runMethod = $this->inst->type === 'local' ? 'runLocally' : 'runRemotely';
        return $this->$runMethod();
    }

    protected function runLocally()
    {

        $cwd = $this->inst->_appRoot;

        $cwdSave = getcwd();
        if (!is_dir($cwd)) {
            error("SPF: invalid cwd");
        }
        chdir($cwd);

        try {
            global $instInfo;
            $instInfo = $this->inst->getInfo();
            $result = \spf::run();
        } catch (\Exception $e) {
        }

        chdir($cwdSave);

        if (isset($e)) {
            throw $e;
        }

        return $result;

    }

    protected function runRemotely()
    {

        $functionName = \spf::getName();
        $functionText = \spf::getText();
        $argsValue = 'unserialize(' . var_export(serialize(\spf::$args), true) . ")";
        $includes = \spf::getIncludesText();
        $instInfoValue = 'unserialize(' . var_export(serialize($this->inst->getInfo()), true) . ")";
        $ldapUserValue = var_export(LDAP_USER, true);

        $code = <<<SPFCODE
?>$functionText
?>$includes<?php
\$result = null;
\$exception_message = false;
\$instInfo = $instInfoValue;
define('LDAP_USER', $ldapUserValue);
try {
    \$result = call_user_func_array('$functionName', $argsValue);
} catch (\\Exception \$e) {
    \$exception_message = \$e->getMessage();
}
\$srz = serialize(compact('result', 'exception_message'));
die(json_encode(\$srz));
SPFCODE;

        if (!$this->inst->execOld('php -r %s', $code)) {
            error("SPF: php -r failed: " . $this->inst->execOutput);
        }

        $outputArr = explode("\n", $this->inst->execOutput);
        // use last line (skip notices and warnings before json)
        $returnSrz = json_decode(array_pop($outputArr));
        $return = @unserialize(trim($returnSrz));

        array_map(function ($v) {
            trigger_error($v, E_USER_WARNING);
        }, array_unique($outputArr));

        if ($return === false) {
            error('SPF: failed to unserialize output: "' . $this->inst->execOutput . '"');
        }

        if ($return['exception_message']) {
            error($return['exception_message']);
        }

        return $return['result'];

    }

}
