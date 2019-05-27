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
$res = $db->example->where('_id', '5ce78731f53abf2a48000512')->push('customers', 'introduction', ['overwork' => 99, 'height' => 178, 'balance' => 25000]);
// 更新数组中所有的值, 只要数组中有一个匹配到,那么其他没匹配上的值也会被更新
//$res = $db->example->where('_id', '5ce78731f53abf2a48000512')->where('introduction.overwork', 13)->update('customers', ['introduction.$[].overwork' => 9362]);
//根据下标更新, 下面的语句是更新下标为1的项的值
//$res = $db->example->where('_id', '5ce78731f53abf2a48000512')->where('introduction.1.overwork', 9362)->update('customers', ['introduction.1.overwork' => 2580]);
//更新数组中匹配的值
//$res = $db->example->where('_id', '5ce78731f53abf2a48000512')->where('introduction.overwork', 2580)->update('customers', ['introduction.$.overwork' => 0]);


//$res = $db->example->where('_id', '5ce78731f53abf2a48000512')->pull('customers', 'introduction', ['overwork' => 0]);

print_r($res);