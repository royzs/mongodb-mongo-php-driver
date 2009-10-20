<?php
require_once 'PHPUnit/Framework.php';

/**
 * Test class for Mongo.
 * Generated by PHPUnit on 2009-04-09 at 18:09:02.
 */
class MongoTest extends PHPUnit_Framework_TestCase
{
    public function testVersion() {
        $this->assertEquals("1.0.0+", Mongo::VERSION);
    }

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

    public function test__toString() {
        if (preg_match($this->sharedFixture->version_51, phpversion())) {
            $this->markTestSkipped("No implicit __toString in 5.1");
            return;
        }

        $this->assertEquals((string)$this->object, "localhost");

        $m = new Mongo();
        $this->assertEquals((string)$m, "localhost:27017");
    }

    public function testSelectDBException1()
    {
        if (preg_match("/5\.1\../", phpversion())) {
            $threw = false;
            try {
                $db = $this->object->selectDB("");
            }
            catch(Exception $e) {
                $threw = true;
            }
            $this->assertTrue($threw);
            return;
        }
        else {
            $threw = false;
            try {
                $db = $this->object->selectDB("");
            }
            catch(InvalidArgumentException $e) {
                $threw = true;
            }
            $this->assertTrue($threw);
            return;
        }
    }

    public function testSelectDBException2()
    {
        if (preg_match("/5\.1\../", phpversion())) {
            $threw = false;
            try {
                $db = $this->object->selectDB("my database");
            }
            catch(Exception $e) {
                $threw = true;
            }
            $this->assertTrue($threw);
            return;
        }
        else {
            $threw = false;
            try {
                $db = $this->object->selectDB("my database");
            }
            catch(InvalidArgumentException $e) {
                $threw = true;
            }
            $this->assertTrue($threw);
            return;
        }
    }

    public function testSelectDBException3()
    {
        if (preg_match("/5\.1\../", phpversion())) {
            $threw = false;
            try {
                $db = $this->object->selectDB("x.y.z");
            }
            catch(Exception $e) {
                $threw = true;
            }
            $this->assertTrue($threw);
            return;
        }
        else {
            $threw = false;
            try {
                $db = $this->object->selectDB("x.y.z");
            }
            catch(InvalidArgumentException $e) {
                $threw = true;
            }
            $this->assertTrue($threw);
            return;
        }
    }

    public function testSelectDBException4()
    {
        if (preg_match("/5\.1\../", phpversion())) {
            $threw = false;
            try {
                $db = $this->object->selectDB(".");
            }
            catch(Exception $e) {
                $threw = true;
            }
            $this->assertTrue($threw);
            return;
        }
        else {
            $threw = false;
            try {
                $db = $this->object->selectDB(".");
            }
            catch(InvalidArgumentException $e) {
                $threw = true;
            }
            $this->assertTrue($threw);
            return;
        }
    }

    public function testSelectDBException5()
    {
        if (preg_match("/5\.1\../", phpversion())) {
            $threw = false;
            try {
                $db = $this->object->selectDB(null);
            }
            catch(Exception $e) {
                $threw = true;
            }
            $this->assertTrue($threw);
            return;
        }
        else {
            $threw = false;
            try {
                $db = $this->object->selectDB(null);
            }
            catch(InvalidArgumentException $e) {
                $threw = true;
            }
            $this->assertTrue($threw);
            return;
        }
    }

    public function testSelectDB() {
        if (preg_match($this->sharedFixture->version_51, phpversion())) {
            $this->markTestSkipped("No implicit __toString in 5.1");
            return;
        }

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

    public function testSelectCollectionException1()
    {
        if (preg_match("/5\.1\../", phpversion())) {
            $threw = false;
            try {
                $db = $this->object->selectCollection("", "xyz");
            }
            catch(Exception $e) {
                $threw = true;
            }
            $this->assertTrue($threw);
            return;
        }
        else {
            $threw = false;
            try {
                $db = $this->object->selectCollection("", "xyz");
            }
            catch(InvalidArgumentException $e) {
                $threw = true;
            }
            $this->assertTrue($threw);
            return;
        }
    }

    public function testSelectCollection() {
        if (preg_match($this->sharedFixture->version_51, phpversion())) {
            $this->markTestSkipped("No implicit __toString in 5.1");
            return;
        }

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
