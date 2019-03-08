<?php
/**
 * @author    Philip Bergman <philip@zicht.nl>
 * @copyright Zicht Online <http://www.zicht.nl>
 */
namespace App\Command;

use App\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class DumpFileCommand  extends Command
{
    protected static $defaultName = 'dump-file';

    /**
     * @inheritdoc
     */
    protected function configure()
    {

        $this
            ->setDescription('internal command for dumping yaml files')
            ->addArgument('file', InputArgument::IS_ARRAY|InputArgument::REQUIRED, 'the yml file(s) to dump');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var Application $app */
        $app = $this->getApplication();
        $app->getLoader()->addPsr4('Symfony\Component\Yaml\\', dirname(__FILE__) . '/../../../lib/yaml-2.3/');

        $data = [];

        foreach ($input->getArgument('file') as $file) {

            if (!file_exists($file)) {
                throw new \RuntimeException('could not find file ' . $file);
            }

            try {
                $data[0][$file] = Yaml::parse(file_get_contents($file));
            } catch (ParseException $e) {
                $data[1][$file] = $e->getMessage();
                if ($output->isVerbose()) {
                    $output->writeln(sprintf("<error>%s</error>", $e->getMessage()));
                }
            }
        }

        $output->writeln(serialize($data));
    }
}