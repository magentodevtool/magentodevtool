<?php

namespace Project\Installation\Deployment;

class customNotes
{
    /**
     * @var \Project\Installation $inst
     */
    protected $inst;
    protected $notesFile;
    protected $notes;
    protected $loadException;

    function __construct($inst)
    {
        $this->inst = $inst;
        // appPath is needed for "git show origin:master" because related path using "./" isn't supported for git version 1.7.1
        $appPath = str_replace($this->inst->folder, '', $this->inst->_appRoot);
        $this->notesFile = $appPath . 'app/devtool/deployment/notes.xml';
    }

    function getHash()
    {
        $hashText = '';
        foreach ($this->get() as $note) {
            $hashText .= preg_replace('/\s+/', '', $note);
        }
        return md5($hashText);
    }

    function get()
    {
        if ($this->loadException) {
            throw $this->loadException;
        }
        if (!is_null($this->notes)) {
            return $this->notes;
        }

        try {
            $this->loadException = null;
            $this->load();
            return $this->notes;
        } catch (\Exception $e) {
            $this->loadException = $e;
            throw $e;
        }

    }

    protected function load()
    {
        $this->notes = array();

        try {
            $xml = $this->inst->exec('git show origin/master:' . $this->notesFile);
        } catch (\Exception $e) {
            // likely, file doesn't exists, it's ok
            return;
        }

        $xml = xml_load_string($xml);
        foreach ($xml->note as $note) {
            $regexp = '/' . str_replace('/', '\\/', $note->instRegexp) . '/i';
            if (@preg_match($regexp, null) === false) {
                error("instRegexp value \"{$note->instRegexp}\" is invalid");
            }
            if (preg_match($regexp, $this->inst->name)) {
                $this->notes[] = trim($note->text);
            }
        }
    }

    public function markAsRead($hash)
    {
        $this->inst->vars->set('deployment/customNotes/readHash', $hash);
    }

    public function areRead()
    {
        $hash = $this->inst->vars->get('deployment/customNotes/readHash');
        return $hash && $hash == $this->getHash();
    }


}
