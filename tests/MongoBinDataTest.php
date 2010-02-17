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
        $this->assertEquals($bin->bin, 'abcdefg');
        $this->assertEquals($bin->type, 2);
        
        $this->object->insert( array("bin"=>$bin) );
      
        $obj = $this->object->findOne();
        $this->assertEquals($obj['bin']->bin, 'abcdefg');
        $this->assertEquals($obj['bin']->type, 2);
    }

    public function testWeird() {
        $x = chr(0);
        $b = new MongoBinData("$x$x$x$x");
        $this->assertEquals(ord($b->bin), 0);
        $this->assertEquals(ord($b->bin[1]), 0);
        $this->assertEquals(ord($b->bin[2]), 0);
        $this->assertEquals(ord($b->bin[3]), 0);

        $a = chr(255);
        $b = chr(7);
        $c = chr(199);
        $b = new MongoBinData("$a$b$c");
        $this->assertEquals(ord($b->bin[0]), 255);
        $this->assertEquals(ord($b->bin[1]), 7);
        $this->assertEquals(ord($b->bin[2]), 199);
    }

    public function testType2Mode() {
        $x = chr(0).chr(1).chr(2).chr(3);
        $b = new MongoBinData($x);
        $this->object->save(array("bin" => $b));
        $result = $this->object->findOne();

        $this->assertEquals(0, ord($b->bin[0]));
        $this->assertEquals(1, ord($b->bin[1]));
        $this->assertEquals(2, ord($b->bin[2]));
        $this->assertEquals(3, ord($b->bin[3]));
    }

    public function testTypes() {
        $x = chr(0).chr(1).chr(2).chr(3);
        $b = new MongoBinData($x, 1);
        $this->object->save(array("bin" => $b));
        $result = $this->object->findOne();

        $this->assertEquals(0, ord($b->bin[0]));
        $this->assertEquals(1, ord($b->bin[1]));
        $this->assertEquals(2, ord($b->bin[2]));
        $this->assertEquals(3, ord($b->bin[3]));
    }

    public function testConsts() {
        $this->assertEquals(1, MongoBinData::FUNC);
        $this->assertEquals(2, MongoBinData::BYTE_ARRAY);
        $this->assertEquals(3, MongoBinData::UUID);
        $this->assertEquals(5, MongoBinData::MD5);
        $this->assertEquals(128, MongoBinData::CUSTOM);

	$b = new MongoBinData("");
	$this->assertEquals(MongoBinData::BYTE_ARRAY, $b->type);
    }

    /**
     * @expectedException MongoException
     */
    public function testNonUTFString()
    {
      $this->object->insert(array("str" =>"\xFE"));
    }
}
