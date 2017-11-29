<?php

/**
 * This is the model class for table "image".
 *
 * @property integer $id
 * @property string $filePath
 * @property integer $object_id
 * @property integer $is_main
 * @property string $modelName
 * @property string $urlAlias
 */

namespace panix\mod\images\models;

use Yii;
use yii\base\Exception;
use yii\helpers\Url;
use yii\helpers\BaseFileHelper;

class Image extends \panix\engine\db\ActiveRecord {

    private $helper = false;

    public function clearCache() {
        $subDir = $this->getSubDur();

        $dirToRemove = Yii::$app->getModule('images')->getCachePath() . DIRECTORY_SEPARATOR . $subDir;

        if (preg_match('/' . preg_quote($this->modelName, '/') . '/', $dirToRemove)) {
            BaseFileHelper::removeDirectory($dirToRemove);
        }

        return true;
    }

    public function getExtension() {
        $ext = pathinfo($this->getPathToOrigin(), PATHINFO_EXTENSION);
        return $ext;
    }

    public function getUrl($size = false) {
        $urlSize = ($size) ? '_' . $size : '';
        $url = Url::toRoute([
                    '/' . Yii::$app->getModule('images')->id . '/get-image',
                    'item' => $this->object_id,
                    'm' => $this->modelName,
                    //'item' => $this->modelName . $this->object_id,
                    'dirtyAlias' => $this->urlAlias . $urlSize . '.' . $this->getExtension()
        ]);

        return $url;
    }

    public static function getSort() {
        return new \yii\data\Sort([
            'attributes' => [
                'alt_title',
            ],
        ]);
    }

    public function getPath($size = false) {
        $urlSize = ($size) ? '_' . $size : '';
        $base = Yii::$app->getModule('images')->getCachePath();
        $sub = $this->getSubDur();

        $origin = $this->getPathToOrigin();

        $filePath = $base . DIRECTORY_SEPARATOR .
                $sub . DIRECTORY_SEPARATOR . $this->urlAlias . $urlSize . '.' . pathinfo($origin, PATHINFO_EXTENSION);
        ;

        if (!file_exists($filePath)) {
            $this->createVersion($origin, $size);

            if (!file_exists($filePath)) {
                throw new \Exception('Problem with image creating.');
            }
        }

        return $filePath;
    }

    public function getContent($size = false) {
        return file_get_contents($this->getPath($size));
    }

    public function getPathToOrigin() {
        $base = Yii::$app->getModule('images')->getStorePath();
        $filePath = $base . DIRECTORY_SEPARATOR . $this->filePath;
        return $filePath;
    }

    public function getUrlToOrigin() {
        $filePath = Yii::getAlias('@web/uploads/store') . DIRECTORY_SEPARATOR . $this->filePath;
        return $filePath;
    }

    public function getSizes() {
        $sizes = false;
        if (Yii::$app->getModule('images')->graphicsLibrary == 'Imagick') {
            $image = new \Imagick($this->getPathToOrigin());
            $sizes = $image->getImageGeometry();
        } else {
            $image = new \claviska\SimpleImage($this->getPathToOrigin());
            $sizes['width'] = $image->getWidth();
            $sizes['height'] = $image->getHeight();
        }

        return $sizes;
    }

    public function getSizesWhen($sizeString) {

        $size = Yii::$app->getModule('images')->parseSize($sizeString);
        if (!$size) {
            throw new \Exception('Bad size..');
        }



        $sizes = $this->getSizes();

        $imageWidth = $sizes['width'];
        $imageHeight = $sizes['height'];
        $newSizes = [];
        if (!$size['width']) {
            $newWidth = $imageWidth * ($size['height'] / $imageHeight);
            $newSizes['width'] = intval($newWidth);
            $newSizes['height'] = $size['height'];
        } elseif (!$size['height']) {
            $newHeight = intval($imageHeight * ($size['width'] / $imageWidth));
            $newSizes['width'] = $size['width'];
            $newSizes['height'] = $newHeight;
        }

        return $newSizes;
    }

    public function createVersion($imagePath, $sizeString = false) {
        if (strlen($this->urlAlias) < 1) {
            throw new \Exception('Image without urlAlias!');
        }

        $cachePath = Yii::$app->getModule('images')->getCachePath();

        $subDirPath = $this->getSubDur();
        $fileExtension = pathinfo($this->filePath, PATHINFO_EXTENSION);

        if ($sizeString) {
            $sizePart = '_' . $sizeString;
        } else {
            $sizePart = '';
        }

        $pathToSave = $cachePath . '/' . $subDirPath . '/' . $this->urlAlias . $sizePart . '.' . $fileExtension;

        BaseFileHelper::createDirectory(dirname($pathToSave), 0777, true);


        if ($sizeString) {
            $size = Yii::$app->getModule('images')->parseSize($sizeString);
        } else {
            $size = false;
        }

        if (Yii::$app->getModule('images')->graphicsLibrary == 'Imagick') {
            $image = new \Imagick($imagePath);

            $image->setImageCompressionQuality(Yii::$app->getModule('images')->imageCompressionQuality);

            if ($size) {
                if ($size['height'] && $size['width']) {
                    $image->cropThumbnailImage($size['width'], $size['height']);
                } elseif ($size['height']) {
                    $image->thumbnailImage(0, $size['height']);
                } elseif ($size['width']) {
                    $image->thumbnailImage($size['width'], 0);
                } else {
                    throw new \Exception('Something wrong with this->module->parseSize($sizeString)');
                }
            }

            $image->writeImage($pathToSave);
        } else {

            $image = new \claviska\SimpleImage($imagePath);

            if ($size) {
                if ($size['height'] && $size['width']) {

                    $image->thumbnail($size['width'], $size['height']);
                } elseif ($size['height']) {
                    $image->fitToHeight($size['height']);
                } elseif ($size['width']) {
                    $image->fitToWidth($size['width']);
                } else {
                    throw new \Exception('Something wrong with this->module->parseSize($sizeString)');
                }
            }

            //WaterMark
            if (Yii::$app->getModule('images')->waterMark) {

                if (!file_exists(Yii::getAlias(Yii::$app->getModule('images')->waterMark))) {
                    throw new Exception('WaterMark not detected!');
                }

                $wmMaxWidth = intval($image->getWidth() * 0.4);
                $wmMaxHeight = intval($image->getHeight() * 0.4);

                $waterMarkPath = Yii::getAlias(Yii::$app->getModule('images')->waterMark);

                $waterMark = new \claviska\SimpleImage($waterMarkPath);


                if ($waterMark->getHeight() > $wmMaxHeight or $waterMark->getWidth() > $wmMaxWidth) {

                    $waterMarkPath = Yii::$app->getModule('images')->getCachePath() . DIRECTORY_SEPARATOR .
                            pathinfo(Yii::$app->getModule('images')->waterMark)['filename'] .
                            $wmMaxWidth . 'x' . $wmMaxHeight . '.' .
                            pathinfo(Yii::$app->getModule('images')->waterMark)['extension'];

                    //throw new Exception($waterMarkPath);
                    if (!file_exists($waterMarkPath)) {
                        $waterMark->fitToWidth($wmMaxWidth);
                        $waterMark->toFile($waterMarkPath, 'image/png', 100);
                        if (!file_exists($waterMarkPath)) {
                            throw new Exception('Cant save watermark to ' . $waterMarkPath . '!!!');
                        }
                    }
                }

                $image->overlay($waterMarkPath, 'bottom right', .5, -10, -10);
            }
            $image->toFile($pathToSave, $image->getMimeType(), Yii::$app->getModule('images')->imageCompressionQuality);
            //$image->save($pathToSave, Yii::$app->getModule('images')->imageCompressionQuality);
        }

        return $image;
    }

    public function setMain($is_main = true) {
        if ($is_main) {
            $this->is_main = 1;
        } else {
            $this->is_main = 0;
        }
    }

    public function getMimeType($size = false) {
        return image_type_to_mime_type(exif_imagetype($this->getPath($size)));
    }

    protected function getSubDur() {
        return \yii\helpers\Inflector::pluralize($this->modelName) . '/' . $this->object_id;
    }

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return '{{%image}}';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['filePath', 'object_id', 'modelName', 'urlAlias'], 'required'],
            [['object_id', 'is_main'], 'integer'],
            [['alt_title'], 'string', 'max' => 80],
            [['filePath', 'urlAlias'], 'string', 'max' => 400],
            [['modelName'], 'string', 'max' => 150]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'id' => 'ID',
            'filePath' => 'File Path',
            'object_id' => 'Item ID',
            'is_main' => 'Is Main',
            'modelName' => 'Model Name',
            'urlAlias' => 'Url Alias',
        ];
    }

    public function afterDelete() {
        $this->clearCache();
        $storePath = Yii::$app->getModule('images')->getStorePath();

        $fileToRemove = $storePath . DIRECTORY_SEPARATOR . $this->filePath;
        if (preg_match('@\.@', $fileToRemove) and is_file($fileToRemove)) {
            unlink($fileToRemove);
        }

        parent::afterDelete();
    }

}
