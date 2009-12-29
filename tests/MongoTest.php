<?php
require_once 'PHPUnit/Framework.php';

/**
 * Test class for Mongo.
 * Generated by PHPUnit on 2009-04-09 at 18:09:02.
 */
class MongoTest extends PHPUnit_Framework_TestCase
{
    public function testVersion() {
        $this->assertEquals("1.0.2", Mongo::VERSION);
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
        $this->object = new Mongo("localhost", array("connect" => false));
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

    public function testConnect2() {
        $this->object = new Mongo("localhost", array("connect" => false));
        $this->assertFalse($this->object->connected);
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

    /**
     * @expectedException MongoConnectionException
     */
    public function testDumbIPs4() {
	$m = new Mongo("localhost:");
    }

    // these should actually work, though
    public function testDumbIPs5() {
	$m = new Mongo("localhost,localhost");
	$m = new Mongo("localhost,localhost:27");
	$m = new Mongo("localhost:27017,localhost:27018,");
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testPersistConnect() {
        $m1 = new Mongo("localhost:27017", false);
        $m1->persistConnect("", "");
        
        $m2 = new Mongo("localhost:27017", false);
        $m2->persistConnect("", "");
        
        // make sure this doesn't disconnect $m2      
        unset($m1);
        
        $c = $m2->selectCollection("foo","bar");
        $c->findOne();
    }

    public function testPersistConnect2() {
        $m1 = new Mongo("localhost:27017", array("persist" => true));
        $m2 = new Mongo("localhost:27017", array("persist" => true));
        
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

        $this->assertEquals("localhost", (string)$this->object);

        $m = new Mongo();
        $this->assertEquals("localhost:27017", (string)$m);
    }


    /**
     * @expectedException Exception
     */
    public function testSelectDBException1()
    {
        $db = $this->object->selectDB("");
    }

    /**
     * @expectedException Exception
     */
    public function testSelectDBException2()
    {
        $db = $this->object->selectDB("my database");
    }

    /**
     * @expectedException Exception
     */
    public function testSelectDBException3()
    {
        $db = $this->object->selectDB("x.y.z");
    }

    /**
     * @expectedException Exception
     */
    public function testSelectDBException4()
    {
        $db = $this->object->selectDB(".");
    }

    /**
     * @expectedException Exception
     */
    public function testSelectDBException5()
    {
        $db = $this->object->selectDB(null);
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

    /**
     * @expectedException Exception
     */
    public function testSelectCollectionException1()
    {
        $db = $this->object->selectCollection("", "xyz");
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

    public function testMongoFormat() {
      $m = new Mongo("mongodb://localhost");
      $m = new Mongo("mongodb://localhost:27017");
      $m = new Mongo("mongodb://localhost:27017,localhost:27018");
      $m = new Mongo("mongodb://localhost:27017,localhost:27018,localhost:27019");
      $m = new Mongo("mongodb://localhost:27018,localhost,localhost:27019");
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testPersistConn() {
      $m1 = new Mongo("localhost", true, true);

      // uses the same connection as $m1
      $m2 = new Mongo("localhost", false);
      $m2->persistConnect();

      // creates a new connection
      $m3 = new Mongo("127.0.0.1", false);
      $m3->persistConnect();

      // creates a new connection
      $m4 = new Mongo("127.0.0.1:27017", false);
      $m4->persistConnect();
      
      // creates a new connection
      $m5 = new Mongo("localhost", false);
      $m5->persistConnect("foo");

      // uses the $m5 connection
      $m6 = new Mongo("localhost", false);
      $m6->persistConnect("foo");
      
      // uses $md5
      $m7 = new Mongo("localhost", false);
      $m7->persistConnect("foo", "bar");

      $m8 = new Mongo();
    }

    public function testPersistConn2() {
      $m1 = new Mongo("localhost", array("persist" => true));

      // uses the same connection as $m1
      $m2 = new Mongo("localhost", array("persist" => true));
        
      // creates a new connection
      $m3 = new Mongo("127.0.0.1", array("persist" => true));

      // creates a new connection
      $m4 = new Mongo("127.0.0.1:27017", array("persist" => true));
      
      // creates a new connection
      $m5 = new Mongo("localhost", array("persist" => "foo"));

      // uses the $m5 connection
      $m6 = new Mongo("localhost", array("persist" => "foo"));
      
      $m8 = new Mongo();
    }

    public function testAuthenticate1() {
      exec("mongo tests/addUser.js", $output, $exit_code);
      if ($exit_code == 0) {
        $m = new Mongo("mongodb://testUser:testPass@localhost");
      }
    }

    public function testAuthenticate2() {
      exec("mongo tests/addUser.js", $output, $exit_code);
      if ($exit_code != 0) {
        $this->markTestSkipped("can't add user");
        return;
      }
      $ok = true;

      try {
        $m = new Mongo("mongodb://testUser:testPa@localhost");
      }
      catch(MongoConnectionException $e) {
        $ok = false;
      }

      $this->assertFalse($ok);
    }

    public function testGetters() {
        if (preg_match($this->sharedFixture->version_51, phpversion())) {
            $this->markTestSkipped("No implicit __toString in 5.1");
            return;
        }

        $db = $this->sharedFixture->foo;
        $this->assertTrue($db instanceof MongoDB);
        $this->assertEquals("$db", "foo");
        
        $c = $db->bar;
        $this->assertTrue($c instanceof MongoCollection);
        $this->assertEquals("$c", "foo.bar");
        
        $c2 = $c->baz;
        $this->assertTrue($c2 instanceof MongoCollection);
        $this->assertEquals("$c2", "foo.bar.baz");

        $x = $this->sharedFixture->foo->bar->baz;
        $this->assertTrue($x instanceof MongoCollection);
        $this->assertEquals("$x", "foo.bar.baz");
    }


    public function testStatic() {
        $start = memory_get_usage(true);

        for ($i=0; $i<100; $i++) {
          StaticFunctionTest::connect();
        }
        $this->assertEquals($start, memory_get_usage(true));
    }
}

class StaticFunctionTest {
  private static $conn = null;

  public static function connect()
  {
    self::$conn = new Mongo;
  }
}

?>
