<?php
require_once './db.php';

$db = new db();
//$res = $db->example->insert('customers', ['name' => 'haiyang', 'address' => '上海', 'age' => 25, 'salary' => 5800]);
$res = $db->example
    ->where(['address' => '苏州', 'age' => 30])
    ->where('salary', '>', 100)
    ->update('customers', ['salary' => 800]);
print_r($res);