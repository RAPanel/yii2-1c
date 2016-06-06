<?php
namespace app\controllers;

use app\models\import\AddBase;
use app\models\Order;
use ra\admin\models\Cart;
use ra\admin\models\Exchange;
use ra\admin\models\forms\LoginForm;
use SimpleXMLElement;
use Yii;
use yii\base\Exception;
use yii\helpers\FileHelper;
use yii\helpers\Html;
use yii\web\Response;
use ZipArchive;

/**
 * @author ReRe Design studio
 * @email webmaster@rere-design.ru
 */
class BitrixController extends \yii\web\Controller
{
    /** @var bool */
    public $clearDir = false;

    /** @var int */
    public $maxDirTime = 86400;

    /** @var bool */
    public $zip = false;

    /** @var bool */
    public $adminNotify = true;

    /** @var string */
    public $dir = 'exchange1c';

    /** @var int */
    public $fileLimit = 2000000;

    /** @var string */
    public $moduleUrl = 'product';

    /** @var string */
    public $email = 'error@rere-hosting.ru';

    public $enableCsrfValidation = false;

    /** @var array */
    public $importSearch = array(
        'Классификатор.Группы.Группа' => 'group',
        'Классификатор.Свойства.Свойство' => 'prop',
        'Классификатор.ТипыЦен.ТипЦены' => 'priceType',
        'Классификатор.Склады.Склад' => 'restType',
        'Каталог.Товары.Товар' => 'item',
        'ПакетПредложений.ТипыЦен.ТипЦены' => 'priceType',
        'ПакетПредложений.Склады.Склад' => 'restType',
        'ПакетПредложений.Предложения.Предложение' => 'offer',
    );

    protected $accessForAll = false;

    /** @var int */
    private $_timestamp = 0;
    /** @var array */
    private $_result = array();
    /** @var array */
    private $_status = array();

    public function actionCheckauth()
    {
        if (!Yii::$app->user->isGuest || $this->accessForAll)
            $this->result(1, ['PHPSESSID', Yii::$app->session->id]);
        else {
            $this->result(0, 'You can not auth');
        }
    }

    public function result($status, $data = [])
    {
        $results = ['failure', 'success', 'progress'];
        if (!is_array($data)) $data = (array)$data;
        if (isset($results[$status])) array_unshift($data, $results[$status]);
        $this->_result = array_merge_recursive($this->_result, $data);
        if (!$status) {
            if ($this->adminNotify) {
                Yii::$app->mailer->compose()
                    ->setTo($this->email)
                    ->setSubject('EXCHANGE ERROR ' . Yii::$app->session->id)
                    ->setTextBody(print_r($this->_result, 1))
                    ->send();
            }
            throw new \yii\console\Exception(iconv('utf8', 'cp1251', implode("\n", array_values($this->_result))));
        }
        return true;
    }

    public function actionInit()
    {
        $this->getDir();

        // Чистим несуществующие связи
//        foreach (Yii::app()->db->createCommand('SELECT DISTINCT(`type`) FROM `exchange_1c`')->queryColumn() as $table)
//            Yii::app()->db->createCommand('DELETE e FROM `exchange_1c` e LEFT OUTER JOIN `' . $table . '` t ON t.id=e.id WHERE e.`type`="' . $table . '" AND t.id IS NULL')->execute();

        $this->result(9, ['zip=' . ($this->zip ? 'yes' : 'no'), 'file_limit=' . $this->fileLimit]);
    }

    public function getDir()
    {
        $dir = Yii::$app->request->get('dir') ?: Yii::$app->session->get('dir');

        if (!$dir) {
            $baseDir = Yii::getAlias('@app/runtime/' . $this->dir . '/');
            $dir = $baseDir . Yii::$app->request->get('dir', date('Y-m-d_') . Yii::$app->user->id) . DIRECTORY_SEPARATOR;
            Yii::$app->session->set('dir', $dir);
        }

        return $dir;
    }

    public function actionFile()
    {
        $fileData = file_get_contents("php://input");
        $file = $this->getFile(0);

        if (empty($fileData))
            $this->result(0, 'data is empty');

        try {
            $fp = fopen($file, "ab");
            fwrite($fp, $fileData);
            fclose($fp);
        } catch (Exception $e) {
            $this->result(0, $e);
        }
    }

    public function getFile($exist = true)
    {
        $dir = $this->getDir();
        $filename = Yii::$app->request->get('filename');
        Yii::$app->params['parseDir'] = dirname($dir . $filename) . '/';

        if (!$filename) $this->result(0, 'filename is empty');
        if (!FileHelper::createDirectory(dirname($dir . $filename))) $this->result(0, 'can`t create dir in path ' . $dir . $filename);
        if ($exist && !file_exists($dir . $filename)) $this->result(0, 'can`t find file ' . $dir . $filename);

        return $dir . $filename;
    }

    public function actionImport()
    {
        $file = $this->getFile();
        if ($this->zip && end(explode('.', $file)) == 'zip') {
            $this->unzip($file);
            return;
        }

        $this->_timestamp = filemtime($file);
        $this->_status = Yii::$app->session->get($file);
        $xml = simplexml_load_file($file);

        foreach ($this->importSearch as $key => $type)
            if (empty($this->_status[$type]) || $this->_status['count'][$type] > $this->_status[$type]) {
                $list = explode('.', $key);
                $data = $xml;
                $tagExist = true;
                foreach ($list as $row) if ($tagExist && isset($data->{$row})) {
                    $data = $data->{$row};
                    $tagExist = true;
                } else $tagExist = false;
                if ($tagExist) return $this->addData($data, $type, $file);
            }

        if (isset($xml->Каталог))
            if ($xml->Каталог['СодержитТолькоИзменения'] == 'false')
                $this->clearData($this->_timestamp, 'item');

        if (isset($xml->ПакетПредложений))
            if ($xml->ПакетПредложений['СодержитТолькоИзменения'] == 'false')
                $this->clearData($this->_timestamp, 'offer');

        Yii::$app->session->set($file, null);
    }

    public function unzip($file)
    {
        $zip = new ZipArchive;
        if ($zip->open($file) === true) {
            $zip->extractTo(dirname($file));
            $zip->close();
            return true;
        }
        return false;
    }

    public function addData($data, $type, $file)
    {
        $i = 0;
        if (isset($this->_status[$type])) $count = $this->_status[$type];
        if (empty($count)) $this->_status[$type] = $count = count($data);
        if (empty($this->_status[$type])) $this->_status[$type] = 0;
        if ($this->_status[$type] == $count) return $this->result(1);
        
        $this->result(2);
        foreach ($data as $row) {
            if ($this->_status[$type] - 1 >= $i++) continue;

            $this->addElement($this->trimAll($row), $type);

            $this->_status[$type] = $i;
            Yii::$app->session->set($file, $this->_status);

            if ($this->life) break;
        }
        return $this->result(9, 'now ' . $type . " {$this->_status[$type]}/{$count} " . round(100 * $this->_status[$type] / $count) . '%');
    }

    public function addElement($row, $type)
    {
        $class = "app\\models\\import\\Add" . ucfirst($type);
        /** @var AddBase $model */
        $model = (new $class);
        return $model->insert($row, null, $this->_timestamp);
    }

    public function trimAll($row)
    {
        if (is_object($row) || is_array($row)) {
            $row = array_map([$this, 'trimAll'], (array)$row);
            return empty($row) ? null : $row;
        } else return trim($row) ?: null;
    }

    public function clearData($timestamp, $type)
    {
//        var_dump($timestamp, $type);

    }

    public function actionSuccess()
    {
        $baseDir = dirname($this->getDir());
        if ($this->clearDir)
            FileHelper::removeDirectory($baseDir);
        elseif ($this->maxDirTime > 0) foreach (scandir($baseDir) as $dir) if (!in_array($dir, ['.', '..'])) {
            if (is_dir($dir = $baseDir . $dir . '/') && filemtime($dir . '.') < time() - $this->maxDirTime)
                FileHelper::removeDirectory($dir);
        }

        $this->clearData(null, 'photo');
    }

    public function actionIndex($mode)
    {
        file_put_contents(Yii::getAlias('@runtime/exchange.log'), '[' . date('c') . ']' . Yii::$app->request->url . PHP_EOL, FILE_APPEND);
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->add('Content-Type', 'text/plain');

        if (Yii::$app->user->isGuest && !$this->login())
            $this->result(0, 'You need auth');

        $action = 'action' . ucfirst($mode);
        $result = $this->{$action}();
        if (is_array($result)) return $result;
        if (empty($this->_result)) $this->result(1);
        return implode("\n", $this->_result);
    }

    public function login()
    {
        if ($this->accessForAll) return true;
        $remote_user = !empty($_SERVER["REMOTE_USER"])
            ? $_SERVER["REMOTE_USER"] : isset($_SERVER["REDIRECT_REMOTE_USER"]) ? $_SERVER["REDIRECT_REMOTE_USER"] : false;
        if ($remote_user && ($strTmp = base64_decode(substr($remote_user, 6))))
            list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', $strTmp);

        if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']))
            throw new Exception('Can not find access data');
        $model = new LoginForm();
        $model->setAttributes([
            'username' => $_SERVER['PHP_AUTH_USER'],
            'password' => $_SERVER['PHP_AUTH_PW'],
        ]);
        if (!$model->login(60 * 60))
            throw new Exception('Incorrect login or password');

        return true;
    }

    public function actionQuery()
    {
        /** @var $to SimpleXMLElement */
        function data($to, $key, $val, $name = 'ЗначениеРеквизита', $keyName = 'Наименование', $valName = 'Значение')
        {
            $param = $to->addChild($name);
            $param->addChild($keyName, $key);
            $param->addChild($valName, $val);
        }

        function id($value)
        {
            return Exchange::find()->select('id')->where(compact('value'))->scalar();
        }

        /*function bool($bool)
        {
            return $bool ? 'true' : 'false';
        }*/

        $query = Order::find()->where(['status_id' => 0]);

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>' . '<КоммерческаяИнформация />');
        $xml->addAttribute('ВерсияСхемы', '2.03');
        $xml->addAttribute('ДатаФормирования', date('Y-m-d'));
        $list = [];
        /** @var Order $row */
        foreach ($query->each() as $row):
            $document = $xml->addChild('Документ');
            $document->addChild('Ид', Yii::$app->id . ' ' . $row->id);
            $document->addChild('Номер', $row->id);
            $document->addChild('Дата', date('Y-m-d', strtotime($row->created_at)));
            $document->addChild('ХозОперация', 'Заказ товара');
            $document->addChild('Роль', 'Продавец');
            $document->addChild('Валюта', 'руб');
            $document->addChild('Курс', '1');
            $document->addChild('Сумма', $row->getTotal());

            $user = $document->addChild('Контрагенты')->addChild('Контрагент');
//            $user->addChild('Ид', 'User' . $row->user_id);
            $user->addChild('Наименование', $row->name);
            $user->addChild('ПолноеНаименование', $row->name);
            $user->addChild('Роль', 'Покупатель');

            $address = $user->addChild('АдресРегистрации');
            $address->addChild('Представление', '');
            data($address, 'Город', $row->city, 'АдресноеПоле', 'Тип');
            data($address, 'Адрес', $row->address, 'АдресноеПоле', 'Тип');

            $contact = $user->addChild('Контакты');
            data($contact, 'Почта', $row->email, 'Контакт', 'Тип');
            data($contact, 'Телефон', $row->phone, 'Контакт', 'Тип');

            $user->addChild('Роль', 'Покупатель');

            $items = $document->addChild('Товары');
            /** @var Cart $value */
            foreach ($row->items as $value):
                $item = $items->addChild('Товар');
                $item->addChild('Ид', id($value->data->id));
                $item->addChild('ИдКаталога', id($value->data->parent_id));
                $item->addChild('Наименование', Html::encode($value->data->name));

                $params = $item->addChild('ЗначенияРеквизитов');
                data($params, 'ВидНоменклатуры', 'Товар');
                data($params, 'ТипНоменклатуры', 'Товар');

                $item->addChild('ЦенаЗаЕдиницу', $value->price);
                $item->addChild('Количество', $value->quantity);
                $item->addChild('Сумма', $value->total);
                $item->addChild('Коэффициент', 1);
            endforeach;

            $document->addChild('Время', date('H:i:s', strtotime($row->created_at)));

            $document->addChild('Комментарий', $row->comment);

            $params = $document->addChild('ЗначенияРеквизитов');
            data($params, 'Метод оплаты', $row->getPay());
            data($params, 'Способ доставки', $row->getDelivery());
            data($params, 'Заказ оплачен', $row->is_payed);
            data($params, 'Отменен', $row->status_id == 99);
            data($params, 'Финальный статус', $row->status_id == 9);
            data($params, 'Статус заказа', '[N] Заказ ' . $row->getStatus());
            data($params, 'Дата изменения статуса', strtotime($row->updated_at));
            data($params, 'Сайт', Yii::$app->name);
        endforeach;

        Yii::$app->response->format = \yii\web\Response::FORMAT_XML;
        Yii::$app->response->content = $xml->asXML();
        Yii::$app->response->send();
    }

    /*public function actionClearAll()
    {
        if (Yii::app()->user->checkAccess('webmaster')) {
            $sql = 'DELETE FROM `' . (new Price())->tableName() . '`';
            Yii::app()->db->createCommand($sql)->execute();
            $sql = 'DELETE FROM `page` WHERE module_id=:module AND (`level`>1 OR `level`=0)';
            Yii::app()->db->createCommand($sql)->execute(array('module' => Module::get($this->moduleUrl)));
            $sql = 'DELETE FROM `exchange_1c` WHERE `type`=:type)';
            Yii::app()->db->createCommand($sql)->execute(array('type' => 'page'));
        }
    }*/

    public function getLife()
    {
        return ((time() - $_SERVER['REQUEST_TIME']) > (ini_get('max_execution_time') - 10)) || memory_get_usage() > ((ini_get('memory_limit') - 5) * 1024 * 1024);
    }
}