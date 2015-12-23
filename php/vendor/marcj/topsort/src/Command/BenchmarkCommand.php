<?php
namespace MJS\TopSort\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BenchmarkCommand extends Command
{
    /**
     * @var Table
     */
    protected $table;

    /**
     * @var ProgressBar
     */
    protected $process;

    protected function configure()
    {
        $this
            ->setName('benchmark')
            ->addArgument('count', InputArgument::OPTIONAL, 'Count', 1000)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->testSimpleCount($input->getArgument('count'), $output);
        $this->testGroupCount($input->getArgument('count'), $output);
    }

    protected function testGroupCount($count, $output)
    {
        $this->test($count, ['GroupedFixedArraySort', 'GroupedArraySort', 'GroupedStringSort'], $output);
    }

    protected function testSimpleCount($count, $output)
    {
        $this->test($count, ['FixedArraySort', 'ArraySort', 'StringSort'], $output);
    }

    protected function test($count, $classes, OutputInterface $output)
    {
        $this->table = new Table($output);
        $this->table->setHeaders(array('Implementation', 'Memory', 'Duration'));
        $output->writeln(sprintf('<info>%d elements</info>', $count));

        $this->process = new ProgressBar($output, count($classes));
        $this->process->start();
        foreach ($classes as $class) {
            $shortClass = $class;
//            if (in_array($class, $blacklist)) {
//                $this->table->addRow([$class, '--', '--']);
//                continue;
//            };

            $path = __DIR__ . '/../../bin/test.php';

            $result = `php $path $class $count`;
            $data = json_decode($result, true);
            if (!$data) {
                echo $result;
            }

            $this->table->addRow(
                [
                    $shortClass,
                    sprintf('%11sb', number_format($data['memory'])),
                    sprintf('%6.4fs', $data['time'])
                ]
            );

            $this->process->advance();
        }

        $this->process->finish();
        $output->writeln('');
        $this->table->render($output);
    }
}