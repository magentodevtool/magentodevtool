<?php

namespace Project\Installation\WebServer\Nginx;

class Config
{
    /** @var string */
    public $serverName;

    /** @var array|null */
    public $domains;

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

        if (!preg_match_all('~^\s*server_name[^\s]*\s+([^;]*)\s*;\n~ism', $configContent, $ms)) {
            return false;
        }

        $this->domains = $ms[1];

        $domains = array();

        array_walk($this->domains, function (&$domain) use (&$domains) {
            $domain = explode(' ', $domain);
            $domains = array_merge($domains, $domain);
            $domain = null;
        });

        $this->domains = array_unique(array_filter($domains));

    }

    public function getFiles($docRoot)
    {
        $files = array();
        foreach (glob('/etc/nginx/sites-enabled/*') as $file) {
            $content = file_get_contents($file);
            if (preg_match('~^\s*root\s+"?' . preg_quote(rtrim($docRoot, '/')) . '\/?[/" \t]*;\n~ism', $content)) {
                $files[] = array(
                    'name' => $file,
                    'content' => $content
                );
            }
        }
        return $files;
    }

}