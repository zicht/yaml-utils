<?php
/**
 * @author    Philip Bergman <philip@zicht.nl>
 * @copyright Zicht Online <http://www.zicht.nl>
 */
namespace App\Command;

use App\Transport\Context;
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
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'do an dry-run')
            ->addOption('dump', null, InputOption::VALUE_NONE, 'dump the new yaml content')
            ->addOption('indent', null, InputOption::VALUE_REQUIRED, 'The level where you switch to inline YAML', 8)
            ->addOption('exclude', null, InputOption::VALUE_IS_ARRAY|InputOption::VALUE_REQUIRED, 'exclude pattern for directories')
            ->addOption('exclude-file', null, InputOption::VALUE_IS_ARRAY|InputOption::VALUE_REQUIRED, 'exclude pattern for files');
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
        $handler = function($signo, $signinfo) use ($sockets, $output) {
            switch ($signo) {
                case SIGUSR1:
                    $ctx = Context::newFromResource($sockets[0]);
                    try {
                        $ctx->addContext('parsed', Yaml::parse(file_get_contents($ctx->getContext('file'))));
                    } catch (ParseException $e) {
                        $ctx->setState(Context::STATE_ERROR)->addContext('error', $e->getMessage());
                    }
                    $ctx->write($sockets[0]);
                    break;
                case SIGINT:
                    $output->writeln(sprintf('received exit signal, stopping child process (#%s)', posix_getpid()));
                    fclose($sockets[0]);
                    exit(0);
                    break;
            }
        };
        pcntl_signal(SIGUSR1, $handler);
        pcntl_signal(SIGINT, $handler);
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
        foreach ($this->getFinder($input) as $file) {
            try {
                Yaml::parse($file->getContents());
                if ($output->isVerbose()) {
                    $output->writeln(sprintf('file %s <info>ok</info>', (string)$file));
                }
            } catch (ParseException $e) {
                $output->writeln(sprintf('<fg=yellow>error parsing file %s</>', (string)$file));
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
                    if (($isDump || $isDryRun) && $hasXdiff) {
                        $output->writeln(\xdiff_string_diff(file_get_contents($file), Yaml::dump($ctx->getContext('parsed'), $input->getOption('indent'))));
                    }
                    if ($isDump && !$hasXdiff) {
                        $output->writeln(Yaml::dump($ctx->getContext('parsed'), $input->getOption('indent')));
                    }
                }
            }
        }
        posix_kill($pid, SIGINT);
        $output->writeln('waiting for child to exit');
        $cid = pcntl_wait($status);
        $output->writeln(sprintf('child #%s finished', $cid));
    }

    /**
     * @param InputInterface $input
     * @return Finder
     */
    private function getFinder(InputInterface $input) :Finder
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
}