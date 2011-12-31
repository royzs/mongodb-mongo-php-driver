<?php
/**
 * Test class for Mongo.
 * Generated by PHPUnit on 2009-04-09 at 18:09:02.
 */
class MongoGridFSTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var    MongoCursor
     * @access protected
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp()
    {
        $m = new Mongo();
        $db = $m->selectDB('phpunit');
        $this->object = $db->getGridFS();
        $this->object->drop();
        $this->object->start = memory_get_usage(true);
    }

    protected function tearDown() {
        $start = $this->object->start;
        unset($this->object);
        //$this->assertEquals($start, memory_get_usage(true));
    }

    public function test__construct() {
        $m = new Mongo();
        $db = $m->selectDB('phpunit');
        $grid = $db->getGridFS('x', 'y');
        $this->assertEquals((string)$grid, 'phpunit.x.files');
        $this->assertEquals((string)$grid->chunks, 'phpunit.x.chunks');

        $grid = $db->getGridFS('x');
        $this->assertEquals((string)$grid, 'phpunit.x.files');
        $this->assertEquals((string)$grid->chunks, 'phpunit.x.chunks');

        $grid = $db->getGridFS();
        $this->assertEquals((string)$grid, 'phpunit.fs.files');
        $this->assertEquals((string)$grid->chunks, 'phpunit.fs.chunks');
    }

    /**
     * @expectedException Exception
     */
    public function test__construct2() {
        $m = new mongo();
        $m->foo->getGridFS(null);
    }

    public function testDrop() {
        $this->object->storeFile('tests/somefile');
        $c = $this->object->chunks->count();
        $this->assertGreaterThan(0, $c);
        $this->assertEquals($this->count(), 1);

        $this->object->drop();

        $this->assertEquals($this->object->chunks->count(), 0);
        $this->assertEquals($this->object->count(), 0);
    }

    public function testFind() {
        $this->object->storeFile('tests/somefile');

        $cursor = $this->object->find();
        $this->assertTrue($cursor instanceof MongoGridFSCursor);
        $file = $cursor->getNext();
        $this->assertNotNull($file);
        $this->assertTrue($file instanceof MongoGridFSFile);
    }

    public function testStoreFile() {
        $this->assertEquals($this->object->findOne(), null);
        $id = $this->object->storeFile('tests/somefile');
        $this->assertTrue($id instanceof MongoId);
        $this->assertEquals(24, strlen("$id"));
        $this->assertNotNull($this->object->findOne());
        $this->assertNotNull($this->object->chunks->findOne());
    }

    public function testStoreFileSafe() {
        $throws = false;
        $result = $this->object->storeFile('tests/somefile', array("_id" => 1), array("safe" => true));
        try {
            $result = $this->object->storeFile('tests/somefile', array("_id" => 1), array("safe" => true));
        }
        catch (MongoCursorException $e) {
            $throws = true;
        }
        $this->assertTrue($throws);
        $this->assertEquals(1, $result);
    }

    public function testFindOne() {
        $this->assertEquals($this->object->findOne(), null);
        $this->object->storeFile('tests/somefile');
        $obj = $this->object->findOne();

        $this->assertTrue($obj instanceof MongoGridFSFile);

        $obj = $this->object->findOne(array('filename' => 'zxvf'));
        $this->assertEquals($obj, null);

        $obj = $this->object->findOne('tests/somefile');
        $this->assertNotNull($obj);
    }

    public function testFindOneFields() {
        $this->object->storeFile('tests/somefile', array("x" => 1, "y" => "foo", "z" => "bar"));
        $obj = $this->object->findOne(array(), array("x" => 1, "y" => 1));

        $this->assertEquals(1, $obj->file['x']);
        $this->assertEquals("foo", $obj->file['y']);
        $this->assertArrayNotHasKey("z", $obj->file);
        $this->assertArrayNotHasKey("length", $obj->file);
    }

    /**
     * @expectedException MongoGridFSException
     */
    public function testFindOneFields2() {
        $this->object->storeFile('tests/somefile', array("x" => 1, "y" => "foo", "z" => "bar"));
        $obj = $this->object->findOne(array(), array("x" => 1, "y" => 1));
        $obj->getBytes();
    }

    public function testRemove()
    {
        $this->object->storeFile('tests/somefile');

        $this->object->remove();
        $this->assertEquals($this->object->findOne(), null);
        $this->assertEquals($this->object->chunks->findOne(), null);
    }

    /*
    //temporarily out-of-order until I can post a file
    public function testStoreUpload() {
        $_FILES['x']['name'] = 'myfile';
        $_FILES['x']['tmp_name'] = 'tests/somefile';

        $this->object->storeUpload('x');

        $file = $this->object->findOne();
        $this->assertTrue($file instanceof MongoGridFSFile);
        $this->assertEquals($file->getFilename(), 'myfile');

        $this->object->drop();

        $this->object->storeUpload('x', 'y');

        $file = $this->object->findOne();
        $this->assertTrue($file instanceof MongoGridFSFile);
        $this->assertEquals($file->getFilename(), 'y');
    }
    */

    public function testGetBytes() {
        $contents = file_get_contents('tests/somefile');

        $this->object->storeFile('tests/somefile');
        $file = $this->object->findOne();

        $this->assertEquals($file->getBytes(), $contents);
    }

    public function testSpecFields() {
        $this->object->storeFile('tests/somefile');
        $file = $this->object->findOne();
        $file = $file->file;
        $this->assertTrue($file['_id'] instanceof MongoId, json_encode($file));
        $this->assertEquals('tests/somefile', $file['filename'], json_encode($file));
        $this->assertEquals(129, $file['length'], json_encode($file));
        $this->assertTrue($file['uploadDate'] instanceof MongoDate, json_encode($file));
        $this->assertEquals("638975db5fddb781d747d75e77b370b4", $file['md5'], json_encode($file));

        $this->object->storeFile('tests/somefile', array('_id' => 4, 'filename' => 'foo', 'length' => 0, 'chunkSize'=>23, 'uploadDate' => 'yesterday', 'md5'=>16, 'md4'=>32));
        $file = $this->object->findOne(array('_id' => 4));
        $file = $file->file;
        $this->assertEquals('foo', $file['filename'], json_encode($file));
        $this->assertEquals(0, $file['length'], json_encode($file));
        $this->assertEquals(23, $file['chunkSize'], json_encode($file));
        $this->assertEquals('yesterday', $file['uploadDate'], json_encode($file));
        $this->assertEquals(16, $file['md5'], json_encode($file));
        $this->assertEquals(32, $file['md4'], json_encode($file));
    }

    public function testFileData() {
        $this->object->storeFile('tests/somefile');
        $files = $this->object->db->selectCollection("fs.files");

        $file = $files->findOne();
        $id = $file['_id'];

        $chunks = $this->object->db->selectCollection("fs.chunks");
        $pieces = $chunks->find(array('files_id' => $id));

        $piece = $pieces->getNext();
        $this->assertTrue($piece['data'] instanceof MongoBinData);

        $bytes1 = $this->object->findOne()->getBytes();
        $bytes2 = $piece['data']->bin;

        $this->assertEquals($bytes1, $bytes2);
    }

    public function testStoreBytes() {
        $x = chr(0);
        $y = chr(255);
        $z = chr(127);
        $w = chr(128);
        $bytes = "${x}4g7$y$z$w$x";

        $this->object->storeBytes($bytes, array('myopt' => new MongoBinData($bytes)));

        $obj = $this->object->findOne();
        $this->assertNotNull($obj, "this can be caused by an old db version or if objcheck is on");

        $b = $obj->getBytes();

        $this->assertEquals($b, $obj->file['myopt']->bin);
        $this->assertEquals(8, strlen($b));

        $this->assertEquals(0, ord(substr($b, 0)));
        $this->assertEquals(52, ord(substr($b, 1)));
        $this->assertEquals(103, ord(substr($b, 2)));
        $this->assertEquals(55, ord(substr($b, 3)));
        $this->assertEquals(255, ord(substr($b, 4)));
        $this->assertEquals(127, ord(substr($b, 5)));
        $this->assertEquals(128, ord(substr($b, 6)));
        $this->assertEquals(0, ord(substr($b, 7)));

    }

    public function testGetBigBytes() {
      $str1 = file_get_contents("tests/Formelsamling.pdf");
      $id = $this->object->storeFile("tests/Formelsamling.pdf");
      $str2 = $this->object->findOne(array('_id' => $id))->getBytes();
      $this->assertEquals($str1, $str2);
    }

    public function testMD5() {
      $mid = $this->object->storeFile("mongo.c");
      $gid = $this->object->storeFile("gridfs.c");

      $mongo = $this->object->findOne(array("_id" => $mid));
      $gridfs = $this->object->findOne(array("_id" => $gid));

      $this->assertNotEquals($mongo->file['md5'], $gridfs->file['md5']);

      $this->assertEquals($mongo->file['md5'], md5(file_get_contents("mongo.c")));
      $this->assertEquals($gridfs->file['md5'], md5(file_get_contents("gridfs.c")));
    }

    public function testFH() {
      $fh = fopen('tests/somefile', 'r');
      $this->object->storeFile($fh, array('_id' => 1));
      $file = $this->object->findOne();
      $this->assertEquals(129, $file->file['length'], json_encode($file->file));
      $this->assertEquals(1, $file->file['_id'], json_encode($file->file));
    }

    public function testOverwrite() {
      $fh1 = fopen('tests/somefile', 'r');
      $fh2 = fopen('mongo.c', 'r');
      $this->object->storeFile($fh1, array('_id' => 1));
      $this->object->storeFile($fh2, array('_id' => 1));

      $file = $this->object->findOne();
      $this->assertEquals(129, $file->file['length'], json_encode($file->file));
    }

    public function testSafeRemove() {
      $grid = $this->object;
      $id = $grid->storeFile('tests/Formelsamling.pdf');

      $x = $grid->remove(array("_id" => $id), array("safe" => true));
      $this->assertEquals(true, (bool)$x['ok']);
      $this->assertEquals(1, $x['n']);
      $this->assertNull($x['err']);
    }

    public function testPut() {
      $filename = 'tests/Formelsamling.pdf';
      $id = $this->object->put($filename);

      $db = $this->object->db;
      $files = $db->fs->files;

      $file = $files->findOne(array("_id" => $id));
      $this->assertEquals($id, $file['_id']);
      $this->assertEquals($filename, $file['filename']);
    }

    public function testGet() {
      $filename = 'tests/Formelsamling.pdf';
      $id = $this->object->put($filename);

      $file = $this->object->get($id);
      $this->assertEquals($id, $file->file['_id']);
      $this->assertEquals($filename, $file->file['filename']);
    }

    public function testDelete() {
      $filename = 'tests/Formelsamling.pdf';
      $id = $this->object->put($filename);

      $this->object->delete($id);
      $file = $this->object->get($id);
      $this->assertNull($file);
    }

    public function testEmpty() {
        $this->object->storeFile('tests/empty');
        $file = $this->object->findOne();
        $this->assertEquals(0, $file->file['length']);
    }
}

?>
