<?php

include 'functions.php';

$leftStringInt = $argv[1] ?? 0;
$rightStringInt = $argv[2] ?? 0;

try {
    $result = longUnsignedIntSum($leftStringInt, $rightStringInt);
} catch (\Exception $e) {
    $result = $e->getMessage();
}

echo $result . PHP_EOL;