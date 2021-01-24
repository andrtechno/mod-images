<?php


namespace panix\mod\images\models;

use panix\engine\CMS;
use panix\mod\shop\components\ExternalFinder;
use Yii;
use yii\base\Exception;
use yii\helpers\FileHelper;
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
 * @property string $path
 * @property string $alt_title
 * @property string $handler_class
 * @property string $handler_hash
 */
class Image extends ActiveRecord
{
    const MODULE_ID = 'images';
    private $helper = false;
    private $existImage = true;

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
        if ($size) {
            $path = Yii::getAlias('@app/web/assets/product') . DIRECTORY_SEPARATOR . $this->object_id . DIRECTORY_SEPARATOR . $size;

            $url = (file_exists($path . DIRECTORY_SEPARATOR . $this->filePath)) ? "/assets/product/{$this->object_id}/{$size}/" . $this->filePath : $url;
        } else {
            $path = Yii::getAlias('@app/web/assets/product') . DIRECTORY_SEPARATOR . $this->object_id;

            $url = (file_exists($path . DIRECTORY_SEPARATOR . $this->filePath)) ? "/assets/product/{$this->object_id}/" . $this->filePath : $url;
        }

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
        $filePath = Yii::getAlias($this->path) . DIRECTORY_SEPARATOR . $this->object_id . DIRECTORY_SEPARATOR . $this->filePath;


        if (!file_exists($filePath)) {
            $this->existImage = false;
            $origin = Yii::$app->getModule('images')->getNoImagePath();
        } else {
            $origin = $this->getPathToOrigin();
        }

        $filePath = $this->createVersion($origin, $size);

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
        if (!file_exists($filePath)) {
            $this->existImage = false;
            $filePath = Yii::$app->getModule('images')->getNoImagePath();
        }
        return $filePath;
    }

    public function getUrlToOrigin()
    {
        $base = '/uploads/store/product/' . $this->object_id . '/' . $this->filePath;
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

    // public $assetPath;
    public function createVersion($imagePath, $size = false)
    {
        $sizes = explode('x', $size);

        $isSaveFile = false;
        if (isset($sizes[0]) && isset($sizes[1])) {
            $imageAssetPath = Yii::getAlias('@app/web/assets/product') . DIRECTORY_SEPARATOR . $this->object_id . DIRECTORY_SEPARATOR . $size;
            $assetPath = "/assets/product/{$this->object_id}/{$size}";
        } else {
            $imageAssetPath = Yii::getAlias('@app/web/assets/product') . DIRECTORY_SEPARATOR . $this->object_id;
            $assetPath = '/assets/product/' . $this->object_id;
        }
        if (!file_exists($imagePath)) {
            return false;
        }
        /** @var $img \panix\engine\components\ImageHandler */
        $img = Yii::$app->img;
        $img->load($imagePath);
        //echo basename($img->getFileName());


        if (!file_exists($imageAssetPath . DIRECTORY_SEPARATOR . basename($img->getFileName()))) {
            $isSaveFile = true;
            FileHelper::createDirectory($imageAssetPath, 0777);
        } else {
            return $assetPath . '/' . basename($img->getFileName());
        }

        $configApp = Yii::$app->settings->get('app');

        if ($sizes) {
            $img->resize((!empty($sizes[0])) ? $sizes[0] : 0, (!empty($sizes[1])) ? $sizes[1] : 0);
        }
        if (!in_array(mb_strtolower($this->getExtension()), ['jpg', 'jpeg', 'webp']) || !$this->existImage) {
            $configApp->watermark_enable = false;
            //  $img->grayscale();
            //$img->text(Yii::t('app/default', 'FILE_NOT_FOUND'), Yii::getAlias('@vendor/panix/engine/assets/assets/fonts') . '/Exo2-Light.ttf', $img->getWidth() / 100 * 5, [114, 114, 114], $img::POS_CENTER_BOTTOM, 0, $img->getHeight() / 100 * 5, 0, 0);
        }
        if ($configApp->watermark_enable) {
            $offsetX = isset($configApp->attachment_wm_offsetx) ? $configApp->attachment_wm_offsetx : 10;
            $offsetY = isset($configApp->attachment_wm_offsety) ? $configApp->attachment_wm_offsety : 10;
            $corner = isset($configApp->attachment_wm_corner) ? $configApp->attachment_wm_corner : 4;
            $path = Yii::getAlias('@uploads') . DIRECTORY_SEPARATOR . $configApp->attachment_wm_path;

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

                if (!($img->getWidth() <= $wm_width) || !($img->getHeight() <= $wm_height) || ($corner != 10)) {
                    $img->watermark($path, $offsetX, $offsetY, $corner, $wm_zoom);
                }

            }
        }


        if ($isSaveFile) {
            if (isset($sizes[0]) && isset($sizes[1])) {
                $img->thumb($sizes[0], $sizes[1]);
            }
            $img->save($imageAssetPath . DIRECTORY_SEPARATOR . basename($img->getFileName()));

        }
        return $img;

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
        if (Yii::$app->hasModule('csv')) {
            $external = new ExternalFinder('{{%csv}}');
            $external->deleteObject(ExternalFinder::OBJECT_IMAGE, $this->id);
        }
        parent::afterDelete();
    }

}
