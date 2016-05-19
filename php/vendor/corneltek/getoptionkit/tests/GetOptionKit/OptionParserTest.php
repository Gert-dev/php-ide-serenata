<?php
/*
 * This file is part of the GetOptionKit package.
 *
 * (c) Yo-An Lin <cornelius.howl@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */
use GetOptionKit\OptionCollection;
use GetOptionKit\OptionParser;

class OptionParserTest extends PHPUnit_Framework_TestCase 
{
    public $parser;
    public $specs;

    public function setUp()
    {
        $this->specs = new OptionCollection;
        $this->parser = new OptionParser($this->specs);
    }

    public function testOptionWithNegativeValue() {
        $this->specs->add( 'n|nice:' , 'I take negative value' );
        $result = $this->parser->parse(array('a', '-n', '-1'));
        $this->assertEquals(-1, $result->nice);
    }

    public function testOptionWithShortNameAndLongName() {
        $this->specs->add( 'f|foo' , 'flag' );
        $result = $this->parser->parse(array('a', '-f'));
        $this->assertTrue($result->foo);

        $result = $this->parser->parse(array('a', '--foo'));
        $this->assertTrue($result->foo);
    }

    public function testSpec()
    {
        $this->specs->add( 'f|foo:' , 'option require value' );
        $this->specs->add( 'b|bar+' , 'option with multiple value' );
        $this->specs->add( 'z|zoo?' , 'option with optional value' );
        $this->specs->add( 'v|verbose' , 'verbose message' );
        $this->specs->add( 'd|debug'   , 'debug message' );

        $spec = $this->specs->get('foo');
        $this->assertTrue($spec->isRequired());

        $spec = $this->specs->get('bar');
        $this->assertTrue( $spec->isMultiple() );

        $spec = $this->specs->get('zoo');
        $this->assertTrue( $spec->isOptional() );

        $spec = $this->specs->get( 'debug' );
        $this->assertNotNull( $spec );
        is_class( 'GetOptionKit\\Option', $spec );
        is( 'debug', $spec->long );
        is( 'd', $spec->short );
        $this->assertTrue( $spec->isFlag() );
    }

    public function testRequire()
    {
        $this->specs->add( 'f|foo:' , 'option require value' );
        $this->specs->add( 'b|bar+' , 'option with multiple value' );
        $this->specs->add( 'z|zoo?' , 'option with optional value' );
        $this->specs->add( 'v|verbose' , 'verbose message' );
        $this->specs->add( 'd|debug'   , 'debug message' );

        $firstExceptionRaised = false;
        $secondExceptionRaised = false;

        // option required a value should throw an exception
        try {
            $result = $this->parser->parse( array('a', '-f' , '-v' , '-d' ) );
        }
        catch (Exception $e) {
            $firstExceptionRaised = true;
        }

        // even if only one option presented in args array
        try {
            $result = $this->parser->parse(array('a','-f'));
        } catch (Exception $e) {
            $secondExceptionRaised = true;
        }
        if ($firstExceptionRaised && $secondExceptionRaised) {
            return;
        }
        $this->fail('An expected exception has not been raised.');
    }

    public function testMultiple()
    {
        $opt = new OptionCollection;
        $opt->add( 'b|bar+' , 'option with multiple value' );
        $parser = new OptionParser($opt);
        $result = $parser->parse(explode(' ','app -b 1 -b 2 --bar 3'));
        $this->assertNotNull($result->bar);
        $this->assertCount(3,$result->bar);
    }


    public function testMultipleNumber()
    {
        $opt = new OptionCollection;
        $opt->add('b|bar+=number' , 'option with multiple value');
        $parser = new OptionParser($opt);
        $result = $parser->parse(explode(' ','app --bar 1 --bar 2 --bar 3'));
        $this->assertNotNull($result->bar);
        $this->assertCount(3,$result->bar);
        $this->assertSame([1,2,3],$result->bar);
    }

    public function testMultipleString()
    {
        $opts = new OptionCollection;
        $opts->add('b|bar+=string' , 'option with multiple value');
        $bar = $opts->get('bar');
        $this->assertNotNull($bar);
        $this->assertTrue($bar->isMultiple());
        $this->assertTrue($bar->isType('string'));
        $this->assertFalse($bar->isType('number'));


        $parser = new OptionParser($opts);
        $result = $parser->parse(explode(' ','app --bar lisa --bar mary --bar john a b c'));
        $this->assertNotNull($result->bar);
        $this->assertCount(3,$result->bar);
        $this->assertSame(['lisa', 'mary', 'john'],$result->bar);
        $this->assertSame(['a','b','c'], $result->getArguments());
    }


    /**
     * @expectedException Exception
     */
    public function testIntegerTypeNonNumeric()
    {
        $opt = new OptionCollection;
        $opt->add( 'b|bar:=number' , 'option with integer type' );

        $parser = new OptionParser($opt);
        $spec = $opt->get('bar');
        $this->assertTrue($spec->isTypeNumber());

        // test non numeric
        $result = $parser->parse(explode(' ','app -b test'));
        $this->assertNotNull($result->bar);
    }


    public function testIntegerTypeNumericWithoutEqualSign()
    {
        $opt = new OptionCollection;
        $opt->add('b|bar:=number', 'option with integer type');

        $spec = $opt->get('bar');
        $this->assertTrue($spec->isTypeNumber());

        $parser = new OptionParser($opt);
        $result = $parser->parse(explode(' ','app -b 123123'));
        $this->assertNotNull($result);
        $this->assertEquals(123123, $result->bar);
    }

    public function testIntegerTypeNumericWithEqualSign()
    {
        $opt = new OptionCollection;
        $opt->add('b|bar:=number' , 'option with integer type');

        $spec = $opt->get('bar');
        $this->assertTrue($spec->isTypeNumber());

        $parser = new OptionParser($opt);
        $result = $parser->parse(explode(' ','app -b=123123'));
        $this->assertNotNull($result);
        $this->assertNotNull($result->bar);
        $this->assertEquals(123123, $result->bar);
    }

    public function testStringType()
    {
        $this->specs->add( 'b|bar:=string' , 'option with type' );

        $spec = $this->specs->get('bar');

        $result = $this->parser->parse(explode(' ','app -b text arg1 arg2 arg3'));
        $this->assertNotNull($result->bar);

        $result = $this->parser->parse(explode(' ','app -b=text arg1 arg2 arg3'));
        $this->assertNotNull($result->bar);

        $args = $result->getArguments();
        $this->assertNotEmpty($args);
        $this->assertCount(3,$args);
        $this->assertEquals('arg1', $args[0]);
        $this->assertEquals('arg2', $args[1]);
        $this->assertEquals('arg3', $args[2]);
    }


    public function testSpec2()
    {
        $this->specs->add( 'long'   , 'long option name only.' );
        $this->specs->add( 'a'   , 'short option name only.' );
        $this->specs->add( 'b'   , 'short option name only.' );

        ok($this->specs->all());
        ok($this->specs);
        ok($result = $this->parser->parse(explode(' ','app -a -b --long')) );
        ok($result->a);
        ok($result->b);
    }

    public function testSpecCollection()
    {
        $this->specs->add( 'f|foo:' , 'option requires a value.' );
        $this->specs->add( 'b|bar+' , 'option with multiple value.' );
        $this->specs->add( 'z|zoo?' , 'option with optional value.' );
        $this->specs->add( 'v|verbose' , 'verbose message.' );
        $this->specs->add( 'd|debug'   , 'debug message.' );
        $this->specs->add( 'long'   , 'long option name only.' );
        $this->specs->add( 's'   , 'short option name only.' );

        ok( $this->specs->all() );
        ok( $this->specs );

        $this->assertCount( 7 , $array = $this->specs->toArray() );
        $this->assertNotEmpty( isset($array[0]['long'] ));
        $this->assertNotEmpty( isset($array[0]['short'] ));
        $this->assertNotEmpty( isset($array[0]['desc'] ));
    }

    public function optionTestProvider()
    {
        return [
            [ 'foo', 'simple boolean option', 'foo', true,
                [['a','--foo','a', 'b', 'c']]
            ],
            [ 'f|foo', 'simple boolean option', 'foo', true,
                [['a','--foo'], ['a','-f']] 
            ],
            [ 'f|foo:=string', 'string option', 'foo', 'xxx',
                [['a','--foo','xxx'], ['a','-f', 'xxx']] 
            ],
            [ 'f|foo:=string', 'string option', 'foo', 'xxx',
                [['a','b', 'c', '--foo','xxx'], ['a', 'a', 'b', 'c', '-f', 'xxx']] 
            ],
        ];
    }

    /**
     * @dataProvider optionTestProvider
     */
    public function test($specString, $desc, $key, $expectedValue, array $argvList)
    {
        $opts = new OptionCollection();
        $opts->add($specString, $desc);

        $parser = new OptionParser($opts);
        foreach ($argvList as $argv) {
            $res = $parser->parse($argv);
            $this->assertSame($expectedValue, $res->get($key));
        }
    }


    public function testMore()
    {
        $this->specs->add('f|foo:' , 'option require value' );
        $this->specs->add('b|bar+' , 'option with multiple value' );
        $this->specs->add('z|zoo?' , 'option with optional value' );
        $this->specs->add('v|verbose' , 'verbose message' );
        $this->specs->add('d|debug'   , 'debug message' );

        $result = $this->parser->parse( array('a', '-f' , 'foo value' , '-v' , '-d' ) );
        ok($result->foo);
        ok($result->verbose);
        ok($result->debug);
        is( 'foo value', $result->foo );
        ok( $result->verbose );
        ok( $result->debug );

        $result = $this->parser->parse( array('a', '-f=foo value' , '-v' , '-d' ) );
        ok( $result );
        ok( $result->foo );
        ok( $result->verbose );
        ok( $result->debug );

        is( 'foo value', $result->foo );
        ok( $result->verbose );
        ok( $result->debug );

        $result = $this->parser->parse( array('a', '-vd' ) );
        ok( $result->verbose );
        ok( $result->debug );
    }


}
