<?php
namespace app\models\import;
use ra\admin\models\PriceType;

/**
 * Created by PhpStorm.
 * User: semyonchick
 * Date: 12.01.2016
 * Time: 18:40
 */
class AddPriceType extends AddBase
{
    public $aliases = [
        'Наименование' => 'name',
        'Валюта' => 'currency',
    ];

    public function model()
    {
        $model = new PriceType();
        return $model;
    }
}