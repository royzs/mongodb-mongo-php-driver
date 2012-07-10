--TEST--
Connection strings: Test single host name with/without port
--SKIPIF--
<?php require __DIR__ ."/skipif.inc"; ?>
<?php
require __DIR__ . "/../utils.inc";

$port = port();
if ($port != "27017") {
    die("skip this tests attempts to connect to the standard port");
}
?>
--FILE--
<?php
require __DIR__ . "/../utils.inc";

$hostname = hostname();
$port     = port();

$a = new Mongo($hostname);
var_dump($a->connected);
$b = new Mongo("$hostname:$port");
var_dump($b->connected);
?>
--EXPECT--
bool(true)
bool(true)
