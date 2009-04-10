<?php
require_once 'MongoTest.php';
require_once 'MongoDBTest.php';
 
class MongoSuite extends PHPUnit_Framework_TestSuite
{
    public static function suite()
    {
        $suite = new MongoSuite('Mongo Tests');

        $suite->addTestSuite('MongoTest');
        $suite->addTestSuite('MongoDBTest');

        return $suite;
    }
 
    protected function setUp()
    {
        $this->sharedFixture = new Mongo();
    }
 
    protected function tearDown()
    {
        $this->sharedFixture->close();
    }
}
?>
