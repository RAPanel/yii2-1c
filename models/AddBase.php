<?php

namespace app\models\import;

use Yii;
use yii\base\Object;
use yii\db\ActiveRecord;
use yii\db\Query;

/**
 * Created by PhpStorm.
 * User: semyonchick
 * Date: 12.01.2016
 * Time: 18:40
 */
class AddBase extends Object
{
    public $id = 'Ид';
    public $parent = 'parent_id';
    public $aliases = [
        'Наименование' => 'name',
    ];

    public function insert($row, $parent_id, $timestamp)
    {
        if (empty($row[$this->id])) return false;

        $id = $this->getId($row[$this->id]);

        $model = $this->model();
        if (!$model) return false;

        if (isset($row['ПометкаУдаления']) && $row['ПометкаУдаления'] == 'true') {
            if ($id) {
                $model->deleteAll(['id' => $id]);
                $this->deleteId($row[$this->id]);
            }
            return false;
        }

        if (!$this->beforeAdd($row, $id, $timestamp)) return false;

        if ($id) {
            if ($find = $model::findOne($id)) $model = $find;
        } elseif (!$model->id && !empty($row[key($this->aliases)]) && ($find = $model::findOne([current($this->aliases) => $row[key($this->aliases)]]))) $model = $find;

        $data = $this->change_keys($row);
        $model->setAttributes($data);
        if ($parent_id) $model->{$this->parent} = $parent_id;
        $timestamp = time();
        if (!$id || !$model->hasAttribute('updated_at') || strtotime($model->updated_at) < $timestamp) {
            if ($model->save()) {
                if (!$id) {
                    $this->addId($row[$this->id], $model->id);
                }
            } else {
                Yii::$app->controller->result(0, $model->firstErrors);
                return false;
            }
        }

        $this->afterAdd($row, $model->id, $timestamp);

        return $model->id;
    }

    public function getId($id, $type = null)
    {
        if (is_null($type)) $type = $this->getType();
        return (new Query())->from('ra_exchange')->select('value')->where(compact('id', 'type'))->scalar();
    }

    public function getType()
    {
        return strtolower(preg_replace('#^.+\\\Add#', '', get_class($this)));
    }

    /**
     * @return ActiveRecord
     */
    public function model()
    {
        return null;
    }

    public function deleteId($id, $type = null)
    {
        if (is_null($type)) $type = $this->getType();
        Yii::$app->db->createCommand()->delete('ra_exchange', compact('id', 'type'))->execute();
    }

    public function beforeAdd(&$row, $id, $timestamp)
    {
        return true;
    }

    public function change_keys($list)
    {
        $data = [];
        if (is_array($list))
            foreach ($list as $key => $value)
                if (array_key_exists($key, $this->aliases)) {
                    if (is_array($this->aliases[$key])) foreach ($this->aliases[$key] as $i => $val) {
                        if (isset($list[$key][$i][0])) $data[$val] = $list[$key][$i];
                        else {
                            if ($list[$key][$i]) $data[$val] = array($list[$key][$i]);
                            else $data[$val] = $list[$key];
                        }
                    }
                    else $data[$this->aliases[$key]] = $list[$key];
                }
        return $data;
    }

    public function addId($id, $value)
    {
        $type = $this->getType();
        Yii::$app->db->createCommand()->insert('ra_exchange', compact('id', 'type', 'value'))->execute();
    }

    public function afterAdd($row, $id, $timestamp)
    {

    }
}