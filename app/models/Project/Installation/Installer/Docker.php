<?php

namespace Project\Installation\Installer;

use Project\Installation;

/**
 * Class Docker
 * @package Project\Installation
 *
 * @method Installation inst()
 *
 */
trait Docker
{

    protected $dockerComposeGitIgnoreFiles = array(
        '/.env',
        '/vendor',
        '/.docker-images',
        '/.docker',
        '/docker-compose.yml',
        '/docker-compose-xhgui.yml',
        '/app/etc/docker-compose.local.yml',
    );

    function getDockerCheckers()
    {
        return array(
            'checkCustomMainDomain' => 'setup custom main domain',
            'checkDockerServerType' => 'change server type',
            'checkDockerInstalled' => 'install Docker',
            'checkDockerComposeInstalled' => 'install Docker Compose',
            'checkDockerIsRunning' => 'start Docker daemon',
            'checkDockerNetwork' => 'create DevTool Docker Network',
            'checkDockerGitIgnoreFiles' => 'add Docker Compose files to .gitignore',
            'checkComposerFiles' => 'init Composer',
            'checkDockerComposePackageInstalled' => 'install Docker package',
            // TODO: this is too slow for M2 now due to dep. updates, this should be optimized, then enabled
//            'checkDockerComposePackageUpdate' => 'update Composer package',
            'checkDockerComposeFiles' => 'create Docker Compose files',
            'checkDockerComposeEnvFile' => 'create Docker Compose .env file',
            'checkDockerComposeProjectName' => 'set Docker Compose Project Name in .env file',
            'checkDockerDomains' => 'fix Docker service domains',
            'checkDockerContainersUp' => 'run Docker Containers (this can take some time)',
            'checkDomainAvailable' => 'fix domain',
        );
    }

    public function checkDockerServerType()
    {
        return !($this->isDevtoolInDocker() && $this->inst()->webServer->type != 'docker');
    }

    public function fixDockerServerType()
    {
        if ($this->isDevtoolInDocker()) {
            $this->inst()->setWebServerType('docker');
            $this->inst()->setLastWebServerType('docker');
        }

        return $this->checkDockerServerType();
    }

    public function checkDockerInstalled()
    {
        return trim(`which docker`) != '';
    }

    public function checkDockerComposeInstalled()
    {
        return trim(`which docker-compose`) != '';
    }

    public function checkDockerIsRunning()
    {
        return file_exists('/var/run/docker.sock');
    }

    public function checkComposerFiles()
    {
        return file_exists($this->getDockerComposeRoot() . 'composer.json');
    }

    public function checkDockerComposePackageUpdate()
    {
        $dockerPackage = $this->getDockerPackageName();

        $output = $this->inst()->composer->run([
            'command' => 'update',
            'packages' => [$dockerPackage],
            '--dry-run' => true,
            '--ignore-platform-reqs' => true
        ]);

        return strpos($output, "Updating $dockerPackage") === false;
    }

    public function fixDockerComposePackageUpdate()
    {
        $dockerPackage = $this->getDockerPackageName();

        $this->inst()->composer->run([
            'command' => 'update',
            'packages' => [$dockerPackage],
            '--ignore-platform-reqs' => true
        ]);

        return $this->checkDockerComposePackageUpdate();
    }

    public function checkDockerComposePackageInstalled()
    {
        $composer = $this->inst()->composer->getApplication()->getComposer();

        if (!$composer) {
            return false;
        }

        $repository = $composer->getRepositoryManager()->getLocalRepository();

        $package = $repository->findPackages($this->getDockerPackageName(), $this->getDockerPackageVersion());

        return !!$package;
    }

    public function fixDockerComposePackageInstalled()
    {
        $dockerPackage = $this->getDockerPackageName() . ':' . $this->getDockerPackageVersion();

        $this->inst()->composer->run([
            'command' => 'require',
            'packages' => [$dockerPackage],
            '--no-update' => true,
            '--dev' => true,
            '--ignore-platform-reqs' => true
        ]);

        $this->inst()->composer->run([
            'command' => 'update',
            'packages' => [$dockerPackage],
            '--ignore-platform-reqs' => true
        ]);

        $this->inst()->composer->getApplication()->resetComposer();

        return $this->checkDockerComposePackageInstalled();
    }

    public function checkDockerGitIgnoreFiles()
    {
        $filename = $this->getDockerComposeRoot() . '.gitignore';
        if (!file_exists($filename)) {
            return false;
        }

        $ignore = file_get_contents($filename);

        foreach ($this->dockerComposeGitIgnoreFiles as $file) {
            if (!preg_match('/^' . preg_quote($file, '/') . '$/m', $ignore)) {
                return false;
            }
        }

        return true;
    }

    public function fixDockerGitIgnoreFiles()
    {
        $filename = $this->getDockerComposeRoot() . '.gitignore';
        if (file_exists($filename)) {
            $ignore = file_get_contents($filename);

            $missed = array();

            foreach ($this->dockerComposeGitIgnoreFiles as $file) {
                if (!preg_match('/^' . preg_quote($file, '/') . '$/m', $ignore)) {
                    $missed[] = $file;
                }
            }
        } else {
            $missed = $this->dockerComposeGitIgnoreFiles;
        }

        $ignore = PHP_EOL . implode(PHP_EOL, $missed) . PHP_EOL;

        file_put_contents($filename, $ignore, FILE_APPEND);

        return $this->checkDockerGitIgnoreFiles();
    }

    /**
     * Creates composer.jsom file
     *
     * @return bool
     *
     */
    public function fixComposerFiles()
    {
        $projectName = $this->getComposerProjectName();

        $dockerPackageName = $this->getDockerPackageName();
        $dockerPackageVersion = $this->getDockerPackageVersion();

        $json = <<<JSON
{
    "name": "yourcompany/{$projectName}",
    "description": "{$projectName}",
    "type": "project",
    "license": "proprietary",
    "authors": [
        {
            "name": "Magento Devtool",
            "email": "devtoolproject2019@gmail.com"
        }
    ],
    "repositories": [
        {
            "type": "composer",
            "url": "https://magento2-packages.company.com/"
        }
    ],
    "minimum-stability": "alpha",
    "require": {},
    "require-dev": {
        "{$dockerPackageName}":"{$dockerPackageVersion}"
    }
}
JSON;

        file_put_contents($this->getDockerComposeRoot() . 'composer.json', $json);

        return $this->checkComposerFiles();
    }

    public function checkDockerComposeFiles()
    {
        return file_exists($this->getDockerComposeRoot() . 'docker-compose.yml')
            && file_exists($this->getDockerComposeRoot() . 'app/etc/docker-compose.local.yml');
    }

    public function fixDockerComposeFiles()
    {
        $this->fixDockerComposePackageInstalled();

        return $this->checkDockerComposeFiles();
    }

    public function checkDockerComposeEnvFile()
    {
        return file_exists($this->getDockerComposeEnvFileName());
    }

    public function fixDockerComposeEnvFile()
    {
        $this->inst()->webServer->getConfig()->updateEnvFile();

        return $this->checkDockerComposeEnvFile();
    }

    public function checkDockerComposeProjectName()
    {
        $content = file_get_contents($this->getDockerComposeEnvFileName());
        return preg_match(
            '/^\s*COMPOSE_PROJECT_NAME=' . preg_quote($this->inst()->getDockerComposeProjectName()) . '\s*$/m',
            $content
        );
    }

    public function fixDockerComposeProjectName()
    {
        $this->inst()->webServer->getConfig()->updateEnvFile();

        return $this->checkDockerComposeProjectName();
    }

    public function checkDockerNetwork()
    {
        $docker = new \Docker\Docker();

        try {
            $network = $docker->getNetworkManager()->find('devtool');
        } catch (\Http\Client\Common\Exception\ClientErrorException $e) {
            return false;
        } catch (\Http\Client\Plugin\Exception\ClientErrorException $e) {
            return false;
        }

        return $network && $network->getName() == 'devtool';
    }

    public function fixDockerNetwork()
    {
        $docker = new \Docker\Docker();

        $config = new \Docker\API\Model\NetworkCreateConfig();
        $config->setName('devtool');

        $docker->getNetworkManager()->create($config);

        return $this->checkDockerNetwork();
    }

    public function checkDockerContainersUp()
    {
        $services = array_keys($this->inst()->webServer->getConfig()->getServices());

        // Grunt service is not supposed to be running
        $services = array_filter($services, function ($service) {
            return !in_array($service, array('grunt', 'gulp'));
        });

        $ids = array_map(function ($service) {
            return '/' . $this->getDockerComposeContainerName($service);
        }, $services);

        $docker = new \Docker\Docker();

        $containers = $docker->getContainerManager()->findAll();
        foreach ($containers as $container) {
            foreach ($ids as $key => $id) {
                if (in_array($id, $container->getNames())) {
                    unset($ids[$key]);
                    break;
                }
            }
        }

        return !$ids;
    }

    public function fixDockerContainersUp()
    {
        $this->inst()->spf('docker/startContainers');

        return $this->checkDockerContainersUp();
    }

    public function checkDockerDomains()
    {
        return in_array($this->inst()->domain, $this->inst()->webServer->getDomains());
    }

    /**
     * @return bool
     *
     */
    public function fixDockerDomains()
    {
        $dockerConfig = $this->inst()->webServer->getConfig();

        $dockerConfig->updateNetworkAliases(array($this->inst()->domain));

        return $this->checkDockerDomains();
    }

    /**
     * @return string|false
     */
    public function getDockerComposeServicesText()
    {
        return $this->inst()->spf('docker/getComposeContainers');
    }

    public function getDockerContainersText()
    {
        return $this->inst()->spf('docker/getDockerContainersText');
    }

    public function getDockerRunningContainers()
    {
        $result = [];

        $docker = new \Docker\Docker();

        $containers = $docker->getContainerManager()->findAll();

        if ($containers) {
            $result = array_map(function ($container) {
                /** @var \Docker\API\Model\ContainerConfig $container */
                $ports = array_filter($container->getPorts(), function ($port) {
                    /** @var \Docker\API\Model\Port $port */
                    return (bool)$port->getPublicPort();
                });

                return array(
                    'id' => $container->getId(),
                    'names' => $container->getNames(),
                    'image' => $container->getImage(),
                    'ports' => $ports
                );
            }, $containers);
        }

        return $result;
    }

    public function getDockerRunningContainersWithPorts()
    {
        return array_filter($this->inst()->getDockerRunningContainers(), function ($container) {
            return !empty($container['ports']);
        });
    }

    /**
     * @return string
     */
    public function getDockerComposeProjectName()
    {
        return $this->inst()->getKey();
    }

    /**
     * @return string
     */
    public function getDockerComposeEnvFileName()
    {
        return $this->inst()->webServer->getConfig()->envFileName;
    }

    /**
     * @return string
     */
    public function getDockerComposeRoot()
    {
        return $this->inst()->_appRoot;
    }

    /**
     * @return string
     */
    public function getComposerProjectName()
    {
        return strtolower(preg_replace('~[^0-9A-z]~', '', $this->inst()->project->name));
    }

    /**
     * @return array
     */
    public function getDockerComposeGitIgnoreFiles()
    {
        return $this->dockerComposeGitIgnoreFiles;
    }

    /**
     * @return string
     */
    public function getDockerPackageName()
    {
        return ($this->inst()->project->type === 'magento2')
            ? 'company/dockerized-magento2'
            : 'company/dockerized-magento';
    }

    /**
     * @return string
     */
    public function getDockerPackageVersion()
    {
        return ($this->inst()->project->type === 'magento2')
            ? '*'
            : '*';
    }

    public function isDevtoolInDocker()
    {
        return !empty($_ENV['DEVTOOL_DOCKER']);
    }

    public function getDockerComposeContainerName($name)
    {
        return $this->getDockerComposeProjectName() . '_' . $name . '_1';
    }
}
