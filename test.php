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
//$res = $db->example->where('_id', '5ce78731f53abf2a48000512')->push('customers', 'introduction', ['overwork' => 12, 'height' => 170, 'balance' => 7300]);
// 更新数组中所有的值, 只要数组中有一个匹配到,那么其他没匹配上的值也会被更新
//$res = $db->example->where('_id', '5ce78731f53abf2a48000512')->where('introduction.overwork', 13)->update('customers', ['introduction.$[].overwork' => 9362]);
//根据下标更新, 下面的语句是更新下标为1的项的值
//$res = $db->example->where('_id', '5ce78731f53abf2a48000512')->where('introduction.1.overwork', 9362)->update('customers', ['introduction.1.overwork' => 2580]);
//更新数组中匹配的值
//$res = $db->example->where('_id', '5ce78731f53abf2a48000512')->where('introduction.overwork', 2580)->update('customers', ['introduction.$.overwork' => 0]);
//删除嵌套集introduction中salary = 5000 的所在项
//$res = $db->example->where('_id', '5ce78731f53abf2a48000512')->pull('customers', 'introduction', ['overwork' => 12]);
//对满足条件的嵌套里的某个字段进行自增效果
//$res = $db->example->where('_id', '5ce78731f53abf2a48000512')->where('introduction.height', 178)->increment('customers', 'introduction.$.overwork', 15);
//按id进行查询
//$cursor = $db->example->select('name,address,salary')->find('customers', ['5ce78731f53abf2a48000512', '5ce641acf53abf0ed00014f4']);
//模糊搜索
//$cursor = $db->example->likeWhere('name', '/*yang/*')->orderBy('salary', 'desc')->get('customers');
//$cursor = $db->example->where('salary', '>', 7000)->count('customers');
//$cursor = $db->example->where('salary', '>', 7000)->aggregate('customers');
//$cursor = $db->example->count('customers');
$cursor = $db->example->groupBy('name')->aggregate('customers', ['salary', 'address', 'age']);


p($cursor);
function p($data)
{
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}

//print_r($res);
//
//$columns = ['name', 'address', 'salary', 'age', 'interest', 'introduction'];
//$a = array_combine($columns, array_fill(0, count($columns), 1));
//print_r($a);