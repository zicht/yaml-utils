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

class YamlFixCommand extends AbstractYamlFileCommand
{
    protected static $defaultName = 'fix';

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setDescription('fix or check all yaml files in the given scr dir')
            ->addOption('sort', null, InputOption::VALUE_NONE, 'sort all values')
            ->addOption('dump', null, InputOption::VALUE_NONE, 'dump the new yaml content');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        switch ($pid = pcntl_fork()) {
            case -1:
                throw new \RuntimeException('failed to fork process');
                break;
            case 0:
                $this->child($input, $output, $sockets);
                break;
            default:
                $this->parent($input, $output, $sockets, $pid);
                break;
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param array $sockets
     */
    private function child(InputInterface $input, OutputInterface $output, $sockets)
    {
        $this->getApplication()->getLoader()->addPsr4('Symfony\Component\Yaml\\', dirname(__FILE__) . '/../../../lib/yaml-2.3/');
        fclose($sockets[1]);
        $handler = function($signo, $signinfo) use ($sockets, $input, $output) {
            switch ($signo) {
                case SIGUSR1:
                    $ctx = Context::newFromResource($sockets[0]);
                    try {
                        $data = Yaml::parse(file_get_contents($ctx->getContext('file')));

                        if ($input->getOption('sort')) {
                            $this->sort($data);
                        }

                        $ctx->addContext('parsed', $data);
                    } catch (ParseException $e) {
                        $ctx->setState(Context::STATE_ERROR)->addContext('error', $e->getMessage());
                    }
                    $ctx->write($sockets[0]);
                    break;
                case SIGUSR2:
                    $output->writeln(sprintf('received exit signal, stopping child process (#%s)', posix_getpid()));
                    fclose($sockets[0]);
                    exit(0);
                    break;
            }
        };
        pcntl_signal(SIGUSR1, $handler);
        pcntl_signal(SIGUSR2, $handler);
        while (!feof($sockets[0])) {
            pcntl_signal_dispatch();
            // set to 10 seconds because when
            // signaled sleep will be interrupted
            sleep(10);
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param array $sockets
     * @param int $pid
     */
    private function parent(InputInterface $input, OutputInterface $output, $sockets, $pid)
    {
        $this->getApplication()->getLoader()->addPsr4('Symfony\Component\Yaml\\', dirname(__FILE__) . '/../../../lib/yaml-4.2/');
        fclose($sockets[0]);
        $output->writeln(sprintf('successfully forked process (child pid %d)', $pid));
        $isDryRun = $input->getOption('dry-run');
        $isDump = $input->getOption('dump');
        $hasXdiff = function_exists('xdiff_string_diff');
        foreach ($this->getFiles($input) as $file) {
            try {
                Yaml::parse($file->getContents());
                if ($output->isVerbose()) {
                    $output->writeln(sprintf('file %s <info>ok</info>', (string)$file));
                }
            } catch (ParseException $e) {
                $output->writeln(sprintf('<fg=yellow>failed parsing file %s</>', (string)$file));
                $ctx = new Context(['file' => (string)$file]);
                $ctx->write($sockets[1]);
                posix_kill($pid, SIGUSR1);
                $ctx->read($sockets[1]);
                if (Context::STATE_ERROR === $ctx->getState()) {
                    $output->writeln(sprintf('<fg=red>failed to dump file: %s, %s</>', $file, $ctx->getContext('error')));
                } else {
                    if (!$isDryRun) {
                        $output->write(sprintf('<fg=cyan>updating file %s</>', $file));
                        if (false === $s = file_put_contents($file, Yaml::dump($ctx->getContext('parsed'), $input->getOption('indent')))) {
                            $output->writeln('<fg=red> failed</>');
                        } else {
                            $output->writeln(sprintf('<info> ok (written %d bytes)</>', $s));
                        }
                    }
                    if ($isDump) {
                        if ($hasXdiff) {
                            $output->writeln(\xdiff_string_diff(file_get_contents($file), Yaml::dump($ctx->getContext('parsed'), $input->getOption('indent'))));
                        } else {
                            $output->writeln(Yaml::dump($ctx->getContext('parsed'), $input->getOption('indent')));
                        }
                    }
                }
            }
        }
        posix_kill($pid, SIGUSR2);
        $output->writeln('waiting for child to exit');
        $cid = pcntl_wait($status);
        $output->writeln(sprintf('child #%s finished', $cid));
    }
}