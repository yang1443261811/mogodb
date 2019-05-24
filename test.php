<?php
require_once './db.php';

$db = new db();
/*$res = $db->example->insert('customers', [
    'name' => '任楠',
    'address' => '南充',
    'age' => 26,
    'salary' => 5800,
    'interest' => [
        'movie',
        'music',
        'game',
    ]
]);*/
//$res = $db->example
//    ->where(['address' => '苏州', 'age' => 30])
//    ->where('salary', '>', 100)
//    ->decrement('customers', 'salary', 52);

//$res = $db->example->where('_id', '5ce78731f53abf2a48000512')->push('customers', 'interest', 'basketball');
$res = $db->example->where('_id', '5ce78731f53abf2a48000512')->push('customers', 'interest', 'swimming');
//$res = $db->example->where('name', '任楠')->increment('customers', 'age');
print_r($res);