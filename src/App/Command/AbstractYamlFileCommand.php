<?php
/**
 * @author    Philip Bergman <philip@zicht.nl>
 * @copyright Zicht Online <http://www.zicht.nl>
 */
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;

class AbstractYamlFileCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->addOption('src', null, InputOption::VALUE_REQUIRED, 'the default source location', getcwd())
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'do an dry-run')
            ->addOption('indent', null, InputOption::VALUE_REQUIRED, 'The level where you switch to inline YAML', 8)
            ->addOption('exclude', null, InputOption::VALUE_IS_ARRAY|InputOption::VALUE_REQUIRED, 'exclude pattern for directories')
            ->addOption('exclude-file', null, InputOption::VALUE_IS_ARRAY|InputOption::VALUE_REQUIRED, 'exclude pattern for files');
    }


    /**
     * @param InputInterface $input
     * @return Finder
     */
    protected function getFiles(InputInterface $input) :Finder
    {
        $finder = (new Finder())
            ->name('/\.y(a)?ml$/')
            ->in($input->getOption('src'))
            ->files();
        foreach ($input->getOption('exclude') as $excluded) {
            $finder->notPath($excluded);
        }
        foreach ($input->getOption('exclude-file') as $excluded) {
            $finder->notName($excluded);
        }
        return $finder;
    }

    /**
     * do an recursive sort
     *
     * @param array $data
     * @return bool
     */
    protected function sort(array &$data)
    {
        ksort($data);
        foreach ($data as &$value) {
            if (is_array($value)) {
                if (false === $this->sort($value)) {
                    return false;
                }
            }
        }
        return true;
    }
}