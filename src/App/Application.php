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

    /**
     * Application constructor.
     * @param ClassLoader $loader
     */
    public function __construct(ClassLoader $loader)
    {
        parent::__construct('yaml-utils', '1.0.0');
        $this->loader = $loader;
    }

    /**
     * @return ClassLoader
     */
    public function getLoader(): ClassLoader
    {
        return $this->loader;
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultCommands() :array
    {
        $commands = parent::getDefaultCommands();
        $commands[] = new Command\YamlFixCommand();
        $commands[] = new Command\YamlSortCommand();
        return $commands;
    }
}