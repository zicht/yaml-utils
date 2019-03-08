<?php
/**
 * @author    Philip Bergman <philip@zicht.nl>
 * @copyright Zicht Online <http://www.zicht.nl>
 */
declare(strict_types=1);
namespace App;

use Composer\Autoload\ClassLoader;
use Symfony\Component\Console\Application as BaseApplication;
use App\Command;

class Application extends BaseApplication
{
    /** @var ClassLoader */
    private $loader;
    /** @var string */
    private $binName;

    public function __construct(ClassLoader $loader, string $binName)
    {
        parent::__construct('yaml-utils', '1.0.0');
        $this->loader = $loader;
        $this->binName = $binName;
    }

    /**
     * @return ClassLoader
     */
    public function getLoader(): ClassLoader
    {
        return $this->loader;
    }
    /**
     * @return string
     */
    public function getBinName(): string
    {
        return $this->binName;
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultCommands() :array
    {
        $commands = parent::getDefaultCommands();
        $commands[] = new Command\DumpFileCommand();
        $commands[] = new Command\YamlFixCommand();
        return $commands;
    }


}