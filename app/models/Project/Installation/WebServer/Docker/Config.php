<?php

namespace Project\Installation\WebServer\Docker;

use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

class Config
{
    const DOCKER_COMPOSE_FILE_MAIN = 'docker-compose.yml';
    const DOCKER_COMPOSE_FILE_OVERRIDE = 'docker-compose.override.yml';
    const DOCKER_COMPOSE_FILE_LOCAL = 'app/etc/docker-compose.local.yml';

    const DOCKER_COMPOSE_ENV_FILE = '.env';

    protected $yamlFiles = array(
        self::DOCKER_COMPOSE_FILE_MAIN,
        self::DOCKER_COMPOSE_FILE_OVERRIDE
    );

    public $SSL = true;

    /** @var  null|array */
    protected $config;
    // end

    /** @var string */
    public $serverName;

    /** @var array|null */
    public $domains;

    /** @var string */
    public $envFileName;

    /**
     * @var \Project\Installation $inst
     */
    protected $inst;

    public function __construct($inst)
    {
        $this->inst = $inst;

        $domains = array();

        $config = $this->getDockerComposeConfig();

        foreach (['nginx', 'nginx-frontend'] as $service) {
            if (isset($config['services'][$service]['networks'])) {
                foreach ($config['services'][$service]['networks'] as $network) {
                    if (isset($network['aliases'])) {
                        $domains = array_merge($domains, $network['aliases']);
                    }
                }
            }
        }

        $this->domains = array_unique(array_filter($domains));

        $this->envFileName = $this->inst->getDockerComposeRoot() . self::DOCKER_COMPOSE_ENV_FILE;
    }

    /**
     * @return array
     */
    public function getDockerComposeConfig()
    {
        if ($this->config === null) {
            $this->config = [];

            $parser = new Yaml();

            // load main files
            foreach ($this->yamlFiles as $file) {
                $file = $this->inst->getDockerComposeRoot() . $file;
                if (file_exists($file)) {
                    $yaml = $parser->parse(file_get_contents($file));
                    $this->config = array_merge_recursive($this->config, $yaml);
                }
            }

            // load app/etc file
            $file = $this->inst->getDockerComposeRoot() . self::DOCKER_COMPOSE_FILE_LOCAL;
            if (file_exists($file)) {
                $yaml = $parser->parse(file_get_contents($file));
                $mainServices = array_keys($this->config['services']);
                // remove missing services
                foreach (array_keys($yaml['services']) as $service) {
                    if (!in_array($service, $mainServices)) {
                        unset($yaml['services'][$service]);
                    }
                }
                $this->config = array_merge_recursive($this->config, $yaml);
            }
        }

        return $this->config;
    }

    public function save(array $config)
    {
        $file = $this->inst->getDockerComposeRoot() . self::DOCKER_COMPOSE_FILE_LOCAL;

        $yaml = new Yaml();

        $localConfig = $yaml->parse(file_get_contents($file));

        $localConfig = array_merge_recursive($localConfig, $config);

        $config = $yaml->dump($localConfig, 50, 2, true, true);

        if (!file_put_contents($file, $config)) {
            error('Cannot save Docker Compose configuration to ' . $file);
        }
    }

    public function updateEnvFile()
    {
        $projectName = $this->inst->getDockerComposeProjectName();

        $file = $this->envFileName;

        if (file_exists($file)) {
            $content = file_get_contents($file);
            $content = preg_replace(
                '/^[^#]*\s*COMPOSE_PROJECT_NAME=[0-9A-z]+\s*$/m',
                'COMPOSE_PROJECT_NAME=' . $this->inst->getDockerComposeProjectName(),
                $content
            );
        } else {
            $content = <<<TXT
# DevTool generated .env file for Docker Compose
COMPOSE_PROJECT_NAME=$projectName
TXT;
        }

        file_put_contents($file, $content);
    }

    /**
     * @param array $aliases
     * @return bool|string
     */
    public function updateNetworkAliases(array $aliases)
    {
        $file = $this->inst->getDockerComposeRoot() . self::DOCKER_COMPOSE_FILE_LOCAL;

        $content = file_get_contents($file);

        if (!$content) {
            return false;
        }

        $aliases = array_diff($aliases, $this->domains);

        if (preg_match_all('/\s+aliases:((\s+-\s)(([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}))*/m', $content, $matches)) {
            list($fullString, , $tabs) = $matches;

            if (!$tabs || count($tabs = array_unique($tabs)) != 1) {
                // we have different levels of tabbing
                return false;
            }

            $tabs = array_pop($tabs);
            $aliases = $tabs . implode($tabs, $aliases);

            $fullString = array_unique($fullString);

            foreach ($fullString as $string) {
                $content = str_replace($string, $string . $aliases, $content);
            }
        }

        if (!file_put_contents($file, $content)) {
            return false;
        }

        return $content;
    }

    public function getServices()
    {
        $config = $this->getDockerComposeConfig();

        return $config['services'];
    }

}