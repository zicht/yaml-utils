<?php
/**
 * @author    Philip Bergman <philip@zicht.nl>
 * @copyright Zicht Online <http://www.zicht.nl>
 */
namespace App\Command;

use App\Transport\Context;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class YamlSortCommand extends AbstractYamlFileCommand
{
    protected static $defaultName = 'sort';

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setDescription('Sort the yaml data')
            ->addOption('yaml-version', null, InputOption::VALUE_REQUIRED, 'The yaml version used for parsing files (2.3 or 4.2)', '4.2')
            ->addOption('dump', null, InputOption::VALUE_NONE, 'dump the new yaml content');
    }

    /**
     * @inheritdoc
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        if (!in_array($input->getOption('yaml-version'), ['2.3', '4.2'])) {
            throw new \RuntimeException('only support 2.3 and 4.2');
        }

        $this->getApplication()->getLoader()->addPsr4('Symfony\Component\Yaml\\', dirname(__FILE__) . '/../../../lib/yaml-' . $input->getOption('yaml-version') . '/');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isDryRun = $input->getOption('dry-run');
        $isDump = $input->getOption('dump');
        $hasXdiff = function_exists('xdiff_string_diff');

        foreach ($this->getFiles($input) as $file) {
            $output->writeln(sprintf('sorting file %s', (string)$file));
            $data = Yaml::parse($file->getContents());
            $this->sort($data);

            if (!$isDryRun) {
                $output->write(sprintf('<fg=cyan>updating file %s</>', $file));
                if (false === $s = file_put_contents($file, Yaml::dump($data, $input->getOption('indent')))) {
                    $output->writeln('<fg=red> failed</>');
                } else {
                    $output->writeln(sprintf('<info> ok (written %d bytes)</>', $s));
                }
            }
            if ($isDump) {
                if ($hasXdiff) {
                    $output->writeln(\xdiff_string_diff($file->getContents(), Yaml::dump($data, $input->getOption('indent'))));
                } else {
                    $output->writeln(Yaml::dump($data, $input->getOption('indent')));
                }
            }
        }
    }
}