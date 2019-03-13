<?php

namespace Project\Installation\WebServer\Apache;

class Config
{

    /**
     * @var \Project\Installation $inst
     */
    protected $inst;

    public function __construct($inst)
    {

        $this->inst = $inst;
        $this->files = $this->getFiles($this->inst->_docRoot);
        if (!count($this->files)) {
            return false;
        }
        $configContent = stripSharpComments($this->files[0]['content']);

        $this->fileName = $this->files[0]['name'];
        $this->fileText = $configContent;

        if (!preg_match('~VirtualHost\s+([^>]+)~ism', $configContent, $ms)) {
            return false;
        }
        $this->NameVirtualHost = $ms[1];

        if (!preg_match_all('~\sServerName\s+([^\s]+)~ism', $configContent, $ms)) {
            return false;
        }
        $this->ServerName = $ms[1];

        $this->ServerAlias = array();
        if (preg_match_all('~\sServerAlias\s+([^\n]+)~ism', $configContent, $ms)) {
            foreach ($ms[1] as $aliases) {
                $aliases = explode(' ', $aliases);
                foreach ($aliases as $alias) {
                    $alias = trim($alias);
                    if ($alias !== '') {
                        $this->ServerAlias[] = $alias;
                    }
                }
            }
        }

        $this->domains = array_merge((array)$this->ServerName, $this->ServerAlias);

    }

    function getFiles($docRoot)
    {
        $files = array();
        $this->SSL = false;
        foreach (glob('/etc/apache2/sites-enabled/*') as $file) {
            $content = file_get_contents($file);
            if (preg_match('~\s+DocumentRoot\s+"?' . preg_quote(rtrim($docRoot, '/')) . '[/" \t]*\n~ism', $content)) {
                if (preg_match('~\sSSLEngine\s+on\s~ism', $content)) {
                    // skip SSL virtual hosts
                    $this->SSL = true;
                    continue;
                }
                $files[] = array(
                    'name' => $file,
                    'content' => $content
                );
            }
        }
        return $files;
    }

    function save($config)
    {
        sudo_file_put_contents($this->fileName, $config);
        reloadApache();
    }

}