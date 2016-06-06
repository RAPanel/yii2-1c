<?php
namespace app\models\import;

use ra\admin\helpers\Text;
use ra\admin\models\Character;
use yii\db\Transaction;

/**
 * Created by PhpStorm.
 * User: semyonchick
 * Date: 12.01.2016
 * Time: 18:40
 */
class AddProp extends AddBase
{
    public $id = 'Ид';
    public $aliases = [
        'url' => 'url',
        'Наименование' => 'name',
        'ТипЗначений' => 'type',
    ];

    public function beforeAdd(&$row, $id, $timestamp)
    {
        $list = [
            'Справочник' => 'dropdown',
        ];
        $row['ТипЗначений'] = isset($list[$row['ТипЗначений']]) ? $list[$row['ТипЗначений']] : 'text';

        if(!$id) $row['url'] = Text::translate($row['Наименование']);

        return true;
    }

    public function afterAdd($row, $id, $timestamp)
    {
        $transaction = \Yii::$app->db->beginTransaction(Transaction::READ_COMMITTED);
        if ($id && isset($row['ВариантыЗначений']['Справочник'])) {
            $list = $row['ВариантыЗначений']['Справочник'];
            $list = is_numeric(key($list)) ? $list : [$list];
            foreach ($list as $data)
                (new AddReference)->insert($data, $id, $timestamp);
        }
        $transaction->commit();
    }

    /**
     * @return \yii\db\ActiveRecord
     */
    public function model()
    {
        $model = new Character(['multi'=>0]);
        return $model;
    }
}