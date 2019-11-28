<?php


namespace panix\mod\images\models;

use Yii;
use yii\helpers\Url;
use yii\helpers\BaseFileHelper;
use panix\engine\db\ActiveRecord;

/**
 * This is the model class for table "image".
 *
 * @property integer $id
 * @property string $filePath
 * @property integer $object_id
 * @property integer $is_main
 * @property string $modelName
 * @property string $urlAlias
 * @property string $alt_title
 */
class Image extends ActiveRecord
{
    const MODULE_ID = 'images';
    private $helper = false;

    public function clearCache()
    {
        $subDir = $this->getSubDur();

        $dirToRemove = Yii::$app->getModule('images')->getCachePath() . DIRECTORY_SEPARATOR . $subDir;

        if (preg_match('/' . preg_quote($this->modelName, '/') . '/', $dirToRemove)) {
            BaseFileHelper::removeDirectory($dirToRemove);
        }

        return true;
    }

    public function getExtension()
    {
        $ext = pathinfo($this->getPathToOrigin(), PATHINFO_EXTENSION);
        return $ext;
    }

    public function getUrl($size = false)
    {
        $urlSize = ($size) ? '_' . $size : '';
        $url = Url::toRoute([
            '/images/default/get-file',
            'dirtyAlias' => $this->urlAlias . $urlSize . '.' . $this->getExtension()
        ]);

        return $url;
    }

    public static function getSort()
    {
        return new \yii\data\Sort([
            'attributes' => [
                'alt_title',
            ],
        ]);
    }

    public function getPath($size = false)
    {
        $urlSize = ($size) ? '_' . $size : '';
        $base = Yii::$app->getModule('images')->getCachePath();
        $sub = $this->getSubDur();

        $origin = $this->getPathToOrigin();

        $filePath = $base . DIRECTORY_SEPARATOR .
            $sub . DIRECTORY_SEPARATOR . $this->urlAlias . $urlSize . '.' . pathinfo($origin, PATHINFO_EXTENSION);


        // if (!file_exists($filePath)) {
        $this->createVersion($origin, $size);
        if (!file_exists($filePath)) {
            throw new \Exception('Problem with image creating.');
        }
        // }

        return $filePath;
    }

    public function getContent($size = false)
    {
        //return file_get_contents($this->getPath($size));
        //print_r($this->getPath($size));die;
        // $origin = $this->getPathToOrigin();
        //echo $origin;die;
        //$this->createVersion($origin, $size);
        return $this->getPath($size);
    }

    public function getPathToOrigin()
    {
        $base = Yii::$app->getModule('images')->getStorePath();
        $filePath = $base . DIRECTORY_SEPARATOR . $this->filePath;
        return $filePath;
    }

    public function getUrlToOrigin()
    {
        $filePath = Yii::getAlias('@uploads/store') . DIRECTORY_SEPARATOR . $this->filePath;
        return $filePath;
    }

    public function getUrlToOrigin2()
    {
        $base = '/uploads/store/' . $this->filePath;
        $filePath = $base;
        return $filePath;
    }

    public function getSizes()
    {
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

    public function getSizesWhen($sizeString)
    {

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

    public function createVersion($imagePath, $sizeString = false)
    {


        $sizes = explode('x', $sizeString);

        /** @var $img \panix\engine\components\ImageHandler */
        $img = Yii::$app->img;
        $img->load($imagePath);


        $configApp = Yii::$app->settings->get('app');

        $offsetX = isset($configApp->attachment_wm_offsetx) ? $configApp->attachment_wm_offsetx : 10;
        $offsetY = isset($configApp->attachment_wm_offsety) ? $configApp->attachment_wm_offsety : 10;
        $corner = isset($configApp->attachment_wm_corner) ? $configApp->attachment_wm_corner : 4;
        $path = !empty($configApp->attachment_wm_path) ? $configApp->attachment_wm_path : Yii::getAlias('@uploads') . '/watermark.png';
        $wm_width = 0;
        if ($imageInfo = @getimagesize($path)) {
            $wm_width = (float)$imageInfo[0];
            // $wm_height = (float)$imageInfo[1];
        }

        $toWidth = min($img->getWidth(), $wm_width);
        $wm_zoom = round($toWidth / $wm_width / 2, 1);
        $img->watermark($path, $offsetX, $offsetY, $corner, $wm_zoom);

        if ($sizes) {
            $img->resize((!empty($sizes[0])) ? $sizes[0] : 0, (!empty($sizes[1])) ? $sizes[1] : 0);
        }


        $img->show();
        die;

    }

    public function setMain($is_main = true)
    {
        if ($is_main) {
            $this->is_main = 1;
        } else {
            $this->is_main = 0;
        }
    }

    public function getMimeType($size = false)
    {
        return image_type_to_mime_type(exif_imagetype($this->getPath($size)));
    }

    protected function getSubDur()
    {
        return \yii\helpers\Inflector::pluralize($this->modelName) . '/' . $this->object_id;
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%image}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['filePath', 'object_id', 'modelName', 'urlAlias'], 'required'],
            [['object_id', 'is_main'], 'integer'],
            [['alt_title'], 'string', 'max' => 80],
            [['filePath', 'urlAlias'], 'string', 'max' => 400],
            [['modelName'], 'string', 'max' => 150]
        ];
    }

    public function afterDelete()
    {
        $this->clearCache();
        $storePath = Yii::$app->getModule('images')->getStorePath();

        $fileToRemove = $storePath . DIRECTORY_SEPARATOR . $this->filePath;
        if (preg_match('@\.@', $fileToRemove) and is_file($fileToRemove)) {
            unlink($fileToRemove);
        }

        parent::afterDelete();
    }

}
