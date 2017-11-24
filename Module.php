<?php

namespace panix\mod\images;

use panix\mod\images\models\PlaceHolder;
use Yii;
use panix\mod\images\models\Image;

class Module extends \panix\engine\WebModule { //\yii\base\Module

    public $routes = [
'images/crop' => 'images/default/crop',
        'images/delete/<id>' => 'images/default/delete',

    ];
    public $imagesStorePath = '@app/web/uploads/store';
    public $imagesCachePath = '@app/web/uploads/cache';
    public $graphicsLibrary = 'GD';
    //public $controllerNamespace = 'panix\mod\images\controllers';
    public $placeHolderPath;
    public $waterMark = false;
    public $className;
    public $imageCompressionQuality = 85;
    //public $routes = [
    //    'getImage/<item>/<dirtyAlias>' => 'images/default/imageByItemAndAlias',
    //];
    public function getImage($item, $dirtyAlias) {
        //Get params
        $params = $data = $this->parseImageAlias($dirtyAlias);

        $alias = $params['alias'];
        $size = $params['size'];

        $object_id = preg_replace('/[^0-9]+/', '', $item);
        $modelName = preg_replace('/[0-9]+/', '', $item);


        //Lets get image
        if (empty($this->className)) {
            $imageQuery = Image::find();
        } else {
            $class = $this->className;
            $imageQuery = $class::find();
        }
        $image = $imageQuery
                ->where([
                    'modelName' => $modelName,
                    'object_id' => $object_id,
                    'urlAlias' => $alias
                ])
                /*     ->where('modelName = :modelName AND object_id = :object_id AND urlAlias = :alias',
                  [
                  ':modelName' => $modelName,
                  ':object_id' => $object_id,
                  ':alias' => $alias
                  ]) */
                ->one();
        //if (!$image) {
        //    return $this->getPlaceHolder();
        //}

        return $image;
    }

    public function getStorePath() {
        return Yii::getAlias($this->imagesStorePath);
    }

    public function getCachePath() {
        return Yii::getAlias($this->imagesCachePath);
    }

    public function getModelSubDir($model) {

        $modelName = $this->getShortClass($model);
        $modelDir = \yii\helpers\Inflector::pluralize($modelName) . '/' . $model->id;
        return $modelDir;
    }

    public function getShortClass($obj) {
        $className = get_class($obj);

        if (preg_match('@\\\\([\w]+)$@', $className, $matches)) {
            $className = $matches[1];
        }

        return $className;
    }

    /**
     *
     * Parses size string
     * For instance: 400x400, 400x, x400
     *
     * @param $notParsedSize
     * @return array|null
     */
    public function parseSize($notParsedSize) {
        $sizeParts = explode('x', $notParsedSize);
        $part1 = (isset($sizeParts[0]) and $sizeParts[0] != '');
        $part2 = (isset($sizeParts[1]) and $sizeParts[1] != '');
        if ($part1 && $part2) {
            if (intval($sizeParts[0]) > 0 &&
                    intval($sizeParts[1]) > 0
            ) {
                $size = [
                    'width' => intval($sizeParts[0]),
                    'height' => intval($sizeParts[1])
                ];
            } else {
                $size = null;
            }
        } elseif ($part1 && !$part2) {
            $size = [
                'width' => intval($sizeParts[0]),
                'height' => null
            ];
        } elseif (!$part1 && $part2) {
            $size = [
                'width' => null,
                'height' => intval($sizeParts[1])
            ];
        } else {
            throw new \Exception('Something bad with size, sorry!');
        }

        return $size;
    }

    public function parseImageAlias($parameterized) {
        $params = explode('_', $parameterized);

        if (count($params) == 1) {
            $alias = $params[0];
            $size = null;
        } elseif (count($params) == 2) {
            $alias = $params[0];
            $size = $this->parseSize($params[1]);
            if (!$size) {
                $alias = null;
            }
        } else {
            $alias = null;
            $size = null;
        }


        return ['alias' => $alias, 'size' => $size];
    }

    public function init() {
        parent::init();
        if (!$this->imagesStorePath
                or ! $this->imagesCachePath
                or
                $this->imagesStorePath == '@app'
                or
                $this->imagesCachePath == '@app'
        )
            throw new \Exception('Setup imagesStorePath and imagesCachePath images module properties!!!');
        // custom initialization code goes here
    }

    public function getPlaceHolder() {

        if ($this->placeHolderPath) {
            return new PlaceHolder();
        } else {
            return null;
        }
    }

}
