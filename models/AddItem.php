<?php
namespace app\models\import;

use app\models\Page;
use ra\admin\helpers\RA;
use ra\admin\models\Character;
use ra\admin\models\Photo;

/**
 * Created by PhpStorm.
 * User: semyonchick
 * Date: 12.01.2016
 * Time: 18:40
 */
class AddItem extends AddBase
{
    public $aliases = [
        'Наименование' => 'name',
        'data' => 'pageData',
        'pageCharacters' => 'pageCharacters',
        'parent_id' => 'parent_id',
    ];

    public function beforeAdd(&$row, $id, $timestamp)
    {
        $row['about'] = mb_substr($row['Описание'], 0, 300, 'utf8');
        $last = strripos($row['about'], '.');
        if ($last > 150) $row['about'] = mb_substr($row['Описание'], 0, $last + 1, 'utf8');
        else {
            $last = strripos($row['about'], ' ');
            if ($last > 150) $row['about'] = mb_substr($row['Описание'], 0, $last, 'utf8');
        }
        $row['data']['content'] = nl2br($row['Описание']);
        $row['parent_id'] = $this->getId($row['Группы']['Ид'], false);

        if (isset($row['ЗначенияСвойств']['ЗначенияСвойства'])) {
            $list = $row['ЗначенияСвойств']['ЗначенияСвойства'];
            $list = is_numeric(key($list)) ? $list : [$list];
            foreach ($list as $value)
                $row['pageCharacters'][] = [
                    'character_id' => $this->getId($value['Ид'], false),
                    'value' => $value['Значение'] ? ($this->getId($value['Значение'], false) ?: $value['Значение']) : '',
                ];
        }

        foreach ([
                     'number' => 'Артикул',
                     'brand' => 'Изготовитель',
                 ] as $key => $character) if (isset($row[$character])) {
            if (!$id = RA::character($key)) {
                $model = (new  Character(['url' => $key, 'name' => $character, 'type' => is_array($row[$character]) ? 'dropdown' : 'text', 'multi' => 0]));
                $model->save();
                $id = $model->id;
            }
            if (is_array($row[$character])) {
                $value = $this->getId($row[$character]['Ид'], 'reference');
                if (!$value) $value = (new AddReference(['id' => 'Ид', 'aliases' => ['Наименование' => 'value']]))->insert($row[$character], $id, $timestamp);
            } else $value = $row[$character];
            $row['pageCharacters'][] = [
                'character_id' => $id,
                'value' => $value ?: '',
            ];
        }

        return true;
    }

    public function afterAdd($row, $id, $timestamp)
    {
        if (!empty($row['Картинка']))
            foreach ((array)$row['Картинка'] as $file)
                if (file_exists(\Yii::$app->params['parseDir'] . $file))
                    Photo::add(\Yii::$app->params['parseDir'] . $file, $row['Наименование'], $id, Page::tableName());
    }

    public function model()
    {
        $model = new \app\models\Page([
            'module_id' => 2,
            'is_category' => 0,
            'status' => 1,
        ]);
        return $model;
    }
}