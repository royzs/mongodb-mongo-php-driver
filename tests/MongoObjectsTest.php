<?php
require_once 'PHPUnit/Framework.php';

/**
 * Test class for Mongo.
 * Generated by PHPUnit on 2009-04-09 at 18:09:02.
 */
class MongoObjectsTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var    Mongo
     * @access protected
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    public function setUp() {
      ini_set('mongo.objects', 1);
    }

    public function tearDown() {
      ini_set('mongo.objects', 0);
    }

    public function testObjects() {
      $c = $this->sharedFixture->selectCollection('phpunit', 'objs1');
      $c->drop();

      $obj = (object)array('x' => 1);
      $x = array('obj' => $obj);

      $c->insert($x);
      $x = $c->findOne();

      $this->assertTrue(is_object($x->obj));
      $this->assertEquals(1, $x->obj->x);
    }

    public function testNested() {
      $c = $this->sharedFixture->selectCollection('phpunit', 'objs2');
      $c->drop();

      $obj2 = (object)array('x' => (object)array('foo' => (object)array()));
      $c->insert(array('obj' => $obj2));

      $x = $c->findOne();

      $this->assertTrue(is_object($x->obj));
      $this->assertTrue(is_object($x->obj->x));
      $this->assertTrue(is_object($x->obj->x->foo));
    }

    public function testClass() {
      $c = $this->sharedFixture->selectCollection('phpunit', 'objs3');
      $c->drop();

      $f = new Foo();
      $a = array('foo' => $f);
      $c->insert($a);

      $foo = $c->findOne();
      $this->assertTrue(is_object($foo->foo));
      $this->assertEquals(1, $foo->foo->x);
      $this->assertEquals(2, $foo->foo->y);
      $this->assertEquals("hello", $foo->foo->z);

      //      $this->assertTrue($c instanceof MongoCollection);
      //      $this->assertEquals(1, $c->count());
    }

    public function testMethods() {
      $c = $this->sharedFixture->selectCollection('phpunit', 'objs4');
      $c->drop();

      $f = new Foo();

      $c->insert($f);
      $f->x = 3;
      $c->save($f);
      $f->y = 7;
      $c->update(array('_id' => $f->_id), $f);
      $c->remove($f);
    }
}

class Foo {
  public function __construct() {
    $this->x = 1;
    $this->y = 2;
    $this->z = "hello";
  }
}

?>
