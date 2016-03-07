<?php

namespace PhpIntegrator\Application\Command;

use PhpIntegrator\IndexedTest;
use PhpIntegrator\IndexDatabase;

class SemanticLintTest extends IndexedTest
{
    protected function lintFile($file)
    {
        $path = __DIR__ . '/SemanticLintTest/' . $file;

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new SemanticLint();
        $command->setIndexDatabase($indexDatabase);

        return $command->semanticLint($path, false);
    }

    public function testReportsUnknownClassesWithNoNamespace()
    {
        $output = $this->lintFile('UnknownClassesNoNamespace.php');

        $this->assertEquals($output['errors']['unknownClasses'], [
            [
                'name'      => 'A\B',
                'namespace' => null,
                'start'     => 16,
                'end'       => 19
            ]
        ]);
    }

    public function testReportsUnknownClassesWithSingleNamespace()
    {
        $output = $this->lintFile('UnknownClassesSingleNamespace.php');

        $this->assertEquals($output['errors']['unknownClasses'], [
            [
                'name'      => 'DateTime',
                'namespace' => 'A',
                'start'     => 64,
                'end'       => 72
            ]
        ]);
    }

    public function testReportsUnknownClassesWithMultipleNamespaces()
    {
        $output = $this->lintFile('UnknownClassesMultipleNamespaces.php');

        $this->assertEquals($output['errors']['unknownClasses'], [
            [
                'name'      => 'DateTime',
                'namespace' => 'A',
                'start'     => 56,
                'end'       => 64
            ],

            [
                'name'      => 'SplFileInfo',
                'namespace' => 'B',
                'start'     => 117,
                'end'       => 128
            ]
        ]);
    }

    public function testReportsUnusedUseStatementsWithSingleNamespace()
    {
        $output = $this->lintFile('UnusedUseStatementsSingleNamespace.php');

        $this->assertEquals($output['warnings']['unusedUseStatements'], [
            [
                'name'  => 'Traversable',
                'alias' => 'Traversable',
                'start' => 39,
                'end'   => 50
            ]
        ]);
    }

    public function testReportsUnusedUseStatementsWithMultipleNamespaces()
    {
        $output = $this->lintFile('UnusedUseStatementsMultipleNamespaces.php');

        $this->assertEquals($output['warnings']['unusedUseStatements'], [
            [
                'name'  => 'SplFileInfo',
                'alias' => 'SplFileInfo',
                'start' => 47,
                'end'   => 58
            ],

            [
                'name'  => 'DateTime',
                'alias' => 'DateTime',
                'start' => 111,
                'end'   => 119
            ]
        ]);
    }

    // public function testSeesUseStatementsAsUsedIfTheyAppearInComments()
    // {
    //     $output = $this->lintFile('UnusedUseStatementsMultipleNamespaces.php');
    //
    //     $this->assertEquals($output['warnings']['unusedUseStatements'], [
    //         [
    //             'name'  => 'SplMinHeap',
    //             'alias' => 'SplMinHeap',
    //             'start' => 47,
    //             'end'   => 58
    //         ]
    //     ]);
    // }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testThrowsExceptionOnUnknownFile()
    {
        $command = new SemanticLint();
        $command->setIndexDatabase(new IndexDatabase(':memory:', 1));

        $output = $this->lintFile('MissingFile.php');
    }
}
