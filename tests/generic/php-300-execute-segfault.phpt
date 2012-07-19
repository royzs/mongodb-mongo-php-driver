--TEST--
Test for bug PHP-300: execute crashes with array() as argument
--SKIPIF--
<?php require dirname(__FILE__) ."/skipif.inc"; ?>
--FILE--
<?php
require dirname(__FILE__) . "/../utils.inc";
$m = mongo();
$db = $m->phpunit;
$db->execute(array());
?>
--EXPECTF--
Fatal error: MongoDB::execute(): The argument is neither an object of MongoCode or a string in %s on line %d
