<?php
require_once 'PHPUnit/Framework.php';

/**
 * Test class for MongoId
 * Generated by PHPUnit on 2009-04-10 at 13:30:28.
 */
class MongoIdTest extends PHPUnit_Framework_TestCase
{
    public function testBasic() {
        $id1 = new MongoId();
        $this->assertEquals(strlen($id1->id), 12);

        $id2 = new MongoId('49c10bb63eba810c0c3fc158');
        $this->assertEquals((string)$id2, '49c10bb63eba810c0c3fc158');
      
        $c = $this->sharedFixture->selectCollection("phpunit", "id");
        $c->drop();
        $c->insert(array("_id" => 1));
        $obj = $c->findOne();
        $this->assertEquals($obj['_id'], 1);
    }
}

?>
