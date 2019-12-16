<?php


namespace panix\mod\images\models;

use Yii;
use yii\base\Exception;
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
 * @property string $urlAlias
 * @property string $alt_title
 * @property string $handler_class
 * @property string $handler_hash
 */
class Image extends ActiveRecord
{
    const MODULE_ID = 'images';
    private $helper = false;


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
        //$base = Yii::$app->getModule('images')->getCachePath();
        //$sub = $this->getSubDur();

        $origin = $this->getPathToOrigin();

        //$filePath = $base . DIRECTORY_SEPARATOR .
        //    $sub . DIRECTORY_SEPARATOR . $this->urlAlias . $urlSize . '.' . pathinfo($origin, PATHINFO_EXTENSION);

        //echo Yii::getAlias($this->path).DIRECTORY_SEPARATOR.$this->object_id.DIRECTORY_SEPARATOR.$this->filePath;
        //echo '<br>';
        //echo $filePath;

        $filePath = Yii::getAlias($this->path) . DIRECTORY_SEPARATOR . $this->object_id . DIRECTORY_SEPARATOR . $this->filePath;
        //die;

        // if (!file_exists($filePath)) {

        if (!file_exists($filePath)) {
            throw new Exception('Problem with image creating.');
        } else {
            $this->createVersion($origin, $size);
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
        //$base = Yii::$app->getModule('images')->getStorePath();
        $filePath = Yii::getAlias($this->path) . DIRECTORY_SEPARATOR . $this->object_id . DIRECTORY_SEPARATOR . $this->filePath;

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
        $path = Yii::getAlias('@uploads') . DIRECTORY_SEPARATOR . $configApp->attachment_wm_path;

        if ($sizes) {
            $img->resize((!empty($sizes[0])) ? $sizes[0] : 0, (!empty($sizes[1])) ? $sizes[1] : 0);
        }
        $wm_width = 0;
        $wm_height = 0;
        if (file_exists($path)) {
            if ($imageInfo = @getimagesize($path)) {
                $wm_width = (float)$imageInfo[0] + $offsetX;
                $wm_height = (float)$imageInfo[1] + $offsetY;
            }

            $toWidth = min($img->getWidth(), $wm_width);

            if ($wm_width > $img->getWidth() || $wm_height > $img->getHeight()) {
                $wm_zoom = round($toWidth / $wm_width / 3, 1);
            } else {
                $wm_zoom = false;
            }

            if (!($img->getWidth() <= $wm_width) || !($img->getHeight() <= $wm_height)) {
                $img->watermark($path, $offsetX, $offsetY, $corner, $wm_zoom);
            }


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
            [['filePath', 'object_id', 'urlAlias', 'handler_hash', 'handler_class'], 'required'],
            [['object_id', 'is_main'], 'integer'],
            [['alt_title'], 'string', 'max' => 80],
            [['filePath', 'urlAlias'], 'string', 'max' => 400],
            [['handler_class', 'handler_hash'], 'string', 'max' => 150]
        ];
    }

    public function afterDelete()
    {

        $fileToRemove = $this->getPathToOrigin();

        if (preg_match('@\.@', $fileToRemove) and is_file($fileToRemove)) {
            unlink($fileToRemove);
        }

        parent::afterDelete();
    }

}
