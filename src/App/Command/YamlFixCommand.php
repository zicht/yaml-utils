<?php
/**
 * @author    Philip Bergman <philip@zicht.nl>
 * @copyright Zicht Online <http://www.zicht.nl>
 */
namespace App\Command;

use App\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class YamlFixCommand  extends Command
{
    protected static $defaultName = 'fix';

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setDescription('fix or check all yaml files in the given scr dir')
            ->addOption('src', null, InputOption::VALUE_REQUIRED, 'the default source location', getcwd())
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'do an dry-run and print wrong files')
            ->addOption('indent', null, InputOption::VALUE_REQUIRED, 'The level where you switch to inline YAML', 8)
            ->addOption('exclude', null, InputOption::VALUE_IS_ARRAY|InputOption::VALUE_REQUIRED, 'exclude pattern for directories')
            ->addOption('exclude-file', null, InputOption::VALUE_IS_ARRAY|InputOption::VALUE_REQUIRED, 'exclude pattern for files');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var Application $app */
        $app = $this->getApplication();
        $app->getLoader()->addPsr4('Symfony\Component\Yaml\\', dirname(__FILE__) . '/../../../lib/yaml-4.2/');

        $files = [];
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

        /** @var \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($finder as $file) {
            try {
                Yaml::parse($file->getContents());
                if ($output->isVerbose()) {
                    $output->writeln(sprintf('file %s <info>ok</info>', (string)$file));
                }
            } catch (ParseException $e) {
                $output->writeln(sprintf('<fg=red>error parsing file %s</>', (string)$file));
                $files[] = $file;
            }
        }

        if (!$input->getOption('dry-run') && (bool)count($files)) {

            $process = new Process(sprintf('%s dump-file %s', $app->getBinName(), implode(' ', $files)));

            if ($process->run() !== 0) {
                throw new \RuntimeException($process->getErrorOutput());
            } else {
                if (false === $data = unserialize($process->getOutput())) {
                    throw new \RuntimeException('Failed unserialize dump output');
                }
                if (isset($data[1])) {
                    foreach ($data[1] as $file => $error) {
                        $output->writeln(sprintf('<fg=red>failed to dump file: %s, %s</>', $file, $error));
                    }
                }
                if (isset($data[0])) {
                    foreach ($data[0] as $file => $data) {
                        $output->write(sprintf('updating file %s', $file));
                        if (false === file_put_contents($file, Yaml::dump($data, $input->getOption('indent')))) {
                            $output->writeln('<fg=red> failed</>');
                        } else {
                            $output->writeln('<info> ok</>');
                        }
                    }
                }
            }
        }
    }
}