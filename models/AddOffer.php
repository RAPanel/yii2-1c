<?php
/**
 * Created by PhpStorm.
 * User: semyonchick
 * Date: 13.01.2016
 * Time: 13:02
 */

namespace app\models\import;


use ra\admin\models\PagePrice;
use Yii;
use yii\db\Transaction;

class AddOffer extends AddBase
{
    public function insert($row, $parent_id, $timestamp)
    {
        $transaction = \Yii::$app->db->beginTransaction(Transaction::READ_COMMITTED);
        $id = $this->getId($row['Ид'], false);
        if (!$id) {
            return;
            \Yii::$app->controller->result(0, "Can not find {$row['Ид']}");
        }
        if (isset($row['Цены']['Цена'])) {
            $list = $row['Цены']['Цена'];
            $list = is_numeric(key($list)) ? $list : [$list];
            foreach ($list as $data) {
                $pk = ['page_id' => $id, 'type_id' => $this->getId($data['ИдТипаЦены'], false)];
                $model = (new PagePrice());
                if ($find = $model::findOne($pk)) $model = $find;
                $model->setAttributes($pk + [
                        'value' => $data['ЦенаЗаЕдиницу'],
                        'count' => isset($row['Количество']) ? $row['Количество'] : 0,
                        'unit' => isset($row['Единица']) ? $row['Единица'] : 'шт',
                    ]);
                if (strtotime($model->updated_at) < $timestamp)
                    if (!$model->save()) {
                        Yii::$app->controller->result(0, $model->firstErrors);
                    }
            }
        }
        if (isset($row['Остатки']['Остаток']['Количество'])) {
            $list = $row['Остатки']['Остаток']['Количество'];
            $list = is_array($list) && is_numeric(key($list)) ? $list : [$list];
            foreach ($list as $data) {
                (new PagePrice())->updateAll(['count' => $data ?: 0], ['page_id' => $id]);
            }
        }
        $transaction->commit();
    }
}