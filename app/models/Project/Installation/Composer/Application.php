<?php

namespace Project\Installation\Composer;

use Composer\Console\Application as ConsoleApplication;
use Composer\IO\BufferIO;
use Composer\Factory as ComposerFactory;
use Project\Installation;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Input\ArrayInput;


class Application
{

    const COMPOSER_WORKING_DIR = '--working-dir';

    /**
     * @var \Project\Installation $inst
     */
    protected $inst;

    /** @var  \Composer\Composer */
    protected $composer;

    /**
     * Path to composer.json file
     *
     * @var string
     */
    protected $composerJson;

    /**
     * Buffered output
     *
     * @var BufferedOutput
     */
    protected $consoleOutput;

    /**
     * @var ConsoleApplication
     */
    private $consoleApplication;

    /**
     * Constructs class
     *
     * @param \Project\Installation $inst
     * @param string $pathToComposerJson
     */
    public function __construct(
        $inst,
        $pathToComposerJson
    ) {
        $this->inst = $inst;

        $this->composerJson = $pathToComposerJson;

        $this->consoleApplication = new ConsoleApplication();
        $this->consoleOutput = new BufferedOutput();

        $this->consoleApplication->setAutoExit(false);
    }

    /**
     * @return \Composer\Composer
     */
    public function getComposer()
    {
        if (empty($this->composer)) {
            $io = new BufferIO();

            $factory = new ComposerFactory();

            $this->composer = $factory->createComposer(
                $io,
                $this->composerJson,
                false,
                dirname($this->composerJson),
                false
            );
        }

        return $this->composer;
    }

    public function resetComposer()
    {
        $this->composer = null;
        $this->consoleApplication->resetComposer();
    }

    /**
     * Runs composer command
     *
     * @param array $commandParams
     * @param string|null $workingDir
     * @return bool
     * @throws \RuntimeException
     */
    public function runComposerCommand(array $commandParams, $workingDir = null)
    {
        $this->consoleApplication->resetComposer();

        if ($workingDir) {
            $commandParams[self::COMPOSER_WORKING_DIR] = $workingDir;
        } else {
            $commandParams[self::COMPOSER_WORKING_DIR] = dirname($this->composerJson);
        }

        $input = new ArrayInput($commandParams);

        $exitCode = $this->consoleApplication->run($input, $this->consoleOutput);

        if ($exitCode) {
            throw new \RuntimeException(
                sprintf('Command "%s" failed: %s', $commandParams['command'], $this->consoleOutput->fetch())
            );
        }

        return $this->consoleOutput->fetch();
    }

}
