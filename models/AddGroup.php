<?php
namespace app\models\import;
/**
 * Created by PhpStorm.
 * User: semyonchick
 * Date: 12.01.2016
 * Time: 18:40
 */
class AddGroup extends AddBase
{
    public $aliases = [
        'Наименование' => 'name',
    ];

    public function afterAdd($row, $id, $timestamp)
    {
        if (isset($row['Группы']['Группа'])) {
            $list = $row['Группы']['Группа'];
            $list = is_numeric(key($list)) ? $list : [$list];
            foreach ($list as $data)
                $this->insert($data, $id, $timestamp);
        }
    }

    public function model()
    {
        $model = new \ra\models\Page([
            'module_id' => 2,
            'is_category' => 1,
            'status' => 1,
        ]);
        return $model;
    }
}