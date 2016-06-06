<?php
namespace app\models\import;

use ra\admin\models\Reference;

/**
 * Created by PhpStorm.
 * User: semyonchick
 * Date: 12.01.2016
 * Time: 18:40
 */
class AddReference extends AddBase
{
    public $id = 'ИдЗначения';
    public $parent = 'character_id';
    public $aliases = [
        'Значение' => 'value',
    ];

    public function model()
    {
        $model = new Reference();
        return $model;
    }
}