<?php
require_once 'PHPUnit/Framework.php';

/**
 * Test class for MongoBinData
 * Generated by PHPUnit on 2009-04-10 at 13:30:28.
 */
class MongoBinDataTest extends PHPUnit_Framework_TestCase
{
    protected $object;

    protected function setUp()
    {
        $this->object = $this->sharedFixture->selectCollection("phpunit", "bindata");
        $this->object->drop();
    }

    public function testBasic() {
        $bin = new MongoBinData("abcdefg");
        $this->assertEquals($bin->length, 7);
        $this->assertEquals($bin->bin, 'abcdefg');
        $this->assertEquals($bin->type, 2);
        
        $this->object->insert( array("bin"=>$bin) );
      
        $obj = $this->object->findOne();
        $this->assertEquals($obj['bin']->length, 7);
        $this->assertEquals($obj['bin']->bin, 'abcdefg');
        $this->assertEquals($obj['bin']->type, 2);
    }

    public function testWeird() {
        $x = chr(0);
        $b = new MongoBinData("$x$x$x$x");
        $this->assertEquals($b->length, 4);
        $this->assertEquals(ord($b->bin), 0);
        $this->assertEquals(ord($b->bin[1]), 0);
        $this->assertEquals(ord($b->bin[2]), 0);
        $this->assertEquals(ord($b->bin[3]), 0);

        $a = chr(255);
        $b = chr(7);
        $c = chr(199);
        $b = new MongoBinData("$a$b$c");
        $this->assertEquals($b->length, 3);
        $this->assertEquals(ord($b->bin[0]), 255);
        $this->assertEquals(ord($b->bin[1]), 7);
        $this->assertEquals(ord($b->bin[2]), 199);
    }
}
