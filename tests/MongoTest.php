<?php
require_once 'PHPUnit/Framework.php';

/**
 * Test class for Mongo.
 * Generated by PHPUnit on 2009-04-09 at 18:09:02.
 */
class MongoTest extends PHPUnit_Framework_TestCase
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
        $this->object = new Mongo("localhost", false);
    }


    public function testConnect() {
        $this->object = new Mongo("localhost", false);
        $this->assertFalse($this->object->connected);

        $this->object->connect();
        $this->assertTrue($this->object->connected);

        $this->object->close();
        $this->assertFalse($this->object->connected);

        $this->object->connect();
        $this->object->connect();
        $this->assertTrue($this->object->connected);
    }

    /**
     * @expectedException MongoConnectionException
     */
    public function testDumbIPs1() {
	$m = new Mongo(",");
    }

    /**
     * @expectedException MongoConnectionException 
     */
    public function testDumbIPs2() {
	$m = new Mongo(":,:");
    }

    /**
     * @expectedException MongoConnectionException
     */
    public function testDumbIPs3() {
	$m = new Mongo("x:x");
    }

    // these should actually work, though
    public function testDumbIPs4() {
	$m = new Mongo("localhost:");
	$m = new Mongo("localhost,localhost");
	$m = new Mongo("localhost,localhost:27");
	$m = new Mongo("localhost:27017,localhost:27018,");
    }

    /**
     * @todo Implement testPairConnect().
     */
    public function testPairConnect() {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    public function testPersistConnect() {
      $m1 = new Mongo("localhost:27017", false);
      $m1->persistConnect("", "");

      $m2 = new Mongo("localhost:27017", false);
      $m2->persistConnect("", "");

      // TODO: check numLinks == numPersistentLinks

      // make sure this doesn't disconnect $m2      
      unset($m1);
      
      $c = $m2->selectCollection("foo","bar");
      $c->findOne();
    }

    /**
     * @todo Implement testPairPersistConnect().
     */
    public function testPairPersistConnect() {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    public function test__toString() {
        $this->assertEquals((string)$this->object, "localhost");

        $m = new Mongo();
        $this->assertEquals((string)$m, "localhost:27017");
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSelectDBException1()
    {
        $db = $this->object->selectDB("");
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSelectDBException2()
    {
        $db = $this->object->selectDB("my database");
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSelectDBException3()
    {
        $db = $this->object->selectDB("x.y.z");
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSelectDBException4()
    {
        $db = $this->object->selectDB(".");
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSelectDBException5()
    {
        $db = $this->object->selectDB(null);
    }

    public function testSelectDB() {
        $db = $this->object->selectDB("foo");
        $this->assertEquals((string)$db, "foo");
        $db = $this->object->selectDB("line\nline");
        $this->assertEquals((string)$db, "line\nline");
        $db = $this->object->selectDB("[x,y]");
        $this->assertEquals((string)$db, "[x,y]");
        $db = $this->object->selectDB(4);
        $this->assertEquals((string)$db, "4");
        $db = $this->object->selectDB("/");
        $this->assertEquals((string)$db, "/");
        $db = $this->object->selectDB("\"");
        $this->assertEquals((string)$db, "\"");
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSelectCollectionException1()
    {
        $db = $this->object->selectCollection("", "xyz");
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSelectCollectionException2()
    {
        $db = $this->object->selectCollection("xyz", 'a$');
    }

    public function testSelectCollection() {
        $c = $this->object->selectCollection("foo", "bar.baz");
        $this->assertEquals((string)$c, "foo.bar.baz");
        $c = $this->object->selectCollection(1, 6);
        $this->assertEquals((string)$c, "1.6"); 
        $c = $this->object->selectCollection("foo", '$cmd');
        $this->assertEquals((string)$c, 'foo.$cmd');
    }

    public function testDropDB() {
        $this->object->connect();
        $c = $this->object->selectCollection("temp", "foo");
        $c->insert(array('x' => 1));

        $this->object->dropDB("temp");
        $this->assertEquals($c->findOne(), NULL);

        $db = $this->object->selectDB("temp");
        $c->insert(array('x' => 1));

        $this->object->dropDB($db);
        $this->assertEquals($c->findOne(), NULL);
    }

    public function testRepairDB() {
        $this->object->connect();
        $db = $this->object->selectDB('temp');
        $this->object->repairDB($db);
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testLastError() {
        $this->sharedFixture->resetError();
        $err = $this->sharedFixture->lastError();
        $this->assertEquals(null, $err['err'], json_encode($err));
        $this->assertEquals(0, $err['n'], json_encode($err));
        $this->assertEquals(1, $err['ok'], json_encode($err));

        $this->sharedFixture->forceError();
        $err = $this->sharedFixture->lastError();
        $this->assertNotNull($err['err']);
        $this->assertEquals($err['n'], 0);
        $this->assertEquals($err['ok'], 1);
    }

    /**
     * @expectedException PHPUnit_Framework_Error 
     */
    public function testPrevError() {
        $this->sharedFixture->resetError();
        $err = $this->sharedFixture->prevError();
        $this->assertEquals($err['err'], null);
        $this->assertEquals($err['n'], 0);
        $this->assertEquals($err['nPrev'], -1);
        $this->assertEquals($err['ok'], 1);
        
        $this->sharedFixture->forceError();
        $err = $this->sharedFixture->prevError();
        $this->assertNotNull($err['err']);
        $this->assertEquals($err['n'], 0);
        $this->assertEquals($err['nPrev'], 1);
        $this->assertEquals($err['ok'], 1);
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testResetError() {
        $this->sharedFixture->resetError();
        $err = $this->sharedFixture->lastError();
        $this->assertEquals($err['err'], null);
        $this->assertEquals($err['n'], 0);
        $this->assertEquals($err['ok'], 1);
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testForceError() {
        $this->sharedFixture->forceError();
        $err = $this->sharedFixture->lastError();
        $this->assertNotNull($err['err']);
        $this->assertEquals($err['n'], 0);
        $this->assertEquals($err['ok'], 1);
    }

    public function testClose() {
        $this->object = new Mongo();
        $this->assertTrue($this->object->connected);

        $this->object->close();
        $this->assertFalse($this->object->connected);

        $this->object->close();
        $this->assertFalse($this->object->connected);
    }
}
?>
