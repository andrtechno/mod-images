<?php

namespace panix\mod\images\behaviors;


use panix\engine\CMS;
use panix\engine\components\ImageHandler;
use panix\mod\images\models;
use panix\mod\images\models\Image;
use Yii;
use yii\base\Behavior;
use yii\base\Exception;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\BaseFileHelper;
use yii\helpers\FileHelper;
use yii\web\ForbiddenHttpException;
use yii\web\UploadedFile;
use yii\httpclient\Client;

/**
 * Class ImageBehavior
 *
 * @property ActiveQuery $imageQuery
 * @package panix\mod\images\behaviors
 */
class ImageBehavior extends Behavior
{
    public $attribute;
    public $createAliasMethod = false;
    public $path = '@uploads';
    protected $_file;
    private $imageQuery;

    public function attach($owner)
    {

        parent::attach($owner);


    }

    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
            //ActiveRecord::EVENT_AFTER_FIND=>'test'
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',

        ];
    }

    public function beforeSave()
    {
        /** @var ActiveRecord $owner */
        $owner = $this->owner;
        $owner->file = \yii\web\UploadedFile::getInstances($owner, 'file');
        //if (count($owner->file) > Yii::$app->params['plan'][Yii::$app->params['plan_id']]['product_upload_files']) {
        //    throw new ForbiddenHttpException();
        //}

    }

    public function afterSave()
    {
        if (!Yii::$app instanceof \yii\console\Application) {
            $this->updateMainImage();
            $this->updateImageTitles();
        }
    }

    public function downloadFile($url, $saveTo = '@runtime')
    {
        $filename = basename($url);
        $savePath = Yii::getAlias($saveTo);
        if (!file_exists($savePath)) {
            FileHelper::createDirectory($savePath, $mode = 0775, $recursive = true);
        }
        $saveTo = $savePath . DIRECTORY_SEPARATOR . $filename;

        //return file of exsts path
        if (file_exists($saveTo)) {
            return $saveTo;
        }

        $fh = fopen($saveTo, 'w');
        $client = new Client([
            'transport' => 'yii\httpclient\CurlTransport'
        ]);
        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl($url)
            ->setOutputFile($fh)
            ->send();

        if ($response->isOk) {
            return $saveTo;
        } else {
            return false;
        }
    }

    /**
     *
     * Method copies image file to module store and creates db record.
     *
     * @param $file |string UploadedFile Or absolute url
     * @param bool $is_main
     * @param string $alt
     * @return bool|Image
     * @throws \Exception
     */
    public function attachImage($file, $is_main = false, $alt = '')
    {
        $isDownloaded = preg_match('/http(s?)\:\/\//i', $file);
        if ($isDownloaded) {
            $download = $this->downloadFile($file);
            if ($download) {
                $file = $download;
            }
        }
        $uniqueName = \panix\engine\CMS::gen(10);


        if (!$this->owner->primaryKey) {
            throw new \Exception('Owner must have primaryKey when you attach image!');
        }


        if (!is_object($file)) {
            $pictureFileName = $uniqueName . '.' . pathinfo($file, PATHINFO_EXTENSION);
        } else {
            $pictureFileName = $uniqueName . '.' . $file->extension;
        }
        $path = Yii::getAlias($this->path) . DIRECTORY_SEPARATOR . $this->owner->primaryKey;
        $newAbsolutePath = $path . DIRECTORY_SEPARATOR . $pictureFileName;

        BaseFileHelper::createDirectory($path, 0775, true);


        $image = new Image;
        $image->object_id = $this->owner->primaryKey;
        $image->filePath = $pictureFileName;
        $image->handler_class = '\\' . get_class($this->owner);
        $image->handler_hash = $this->owner->getHash();
        $image->path = $this->path;
        $image->alt_title = $alt;
        $image->urlAlias = $this->getAlias($image);

        if (!$image->save()) {

            return false;
        }

        if (count($image->getErrors()) > 0) {

            $ar = array_shift($image->getErrors());

            unlink($newAbsolutePath);
            throw new \Exception(array_shift($ar));
        }
        $img = $this->owner->getImage();

        //If main image not exists
        if ($img == null || $is_main) {
            $this->setMainImage($image);
        }

        /** @var ImageHandler $img */
        if (is_object($file)) {
            $file->saveAs($newAbsolutePath);
        } else {
            if (!@copy($file, $newAbsolutePath)) {
                $image->delete();
            }
        }
        $img = Yii::$app->img->load($newAbsolutePath);
        if ($img->getHeight() > Yii::$app->params['maxUploadImageSize']['height'] || $img->getWidth() > Yii::$app->params['maxUploadImageSize']['width']) {
            $img->resize(Yii::$app->params['maxUploadImageSize']['width'], Yii::$app->params['maxUploadImageSize']['height']);
        }
        if ($img->save($newAbsolutePath)) {
            //   unlink($runtimePath);
        }
        //remove download file
        if ($isDownloaded) {
            if (file_exists($download)) {
                unlink($download);
            }
        }
        return $image;
    }

    /**
     * Sets main image of model
     * @param $img
     * @throws \Exception
     */
    public function setMainImage($img)
    {

        if ($this->owner->primaryKey != $img->object_id) {
            throw new \Exception('Image must belong to this model');
        }
        $counter = 1;
        /* @var $img Image */
        $img->setMain(true);
        $img->urlAlias = $this->getAliasString() . '-' . $counter;
        $img->save();


        $images = $this->owner->getImages();
        foreach ($images as $allImg) {

            if ($allImg->id == $img->id) {
                continue;
            } else {
                $counter++;
            }

            $allImg->setMain(false);
            $allImg->urlAlias = $this->getAliasString() . '-' . $counter;
            $allImg->save();
        }

        $this->owner->clearImagesCache();
    }

    /**
     * Clear all images cache (and resized copies)
     * @return bool
     */
    public function clearImagesCache()
    {
        $cachePath = Yii::$app->getModule('images')->getCachePath();
        $subdir = Yii::$app->getModule('images')->getModelSubDir($this->owner);

        $dirToRemove = $cachePath . '/' . $subdir;

        if (preg_match('/' . preg_quote($cachePath, '/') . '/', $dirToRemove)) {
            BaseFileHelper::removeDirectory($dirToRemove);
            //exec('rm -rf ' . $dirToRemove);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns model images
     * First image alwats must be main image
     * @return array|yii\db\ActiveRecord[]
     */
    public function getImages($additionWhere = false)
    {

        $finder = $this->getImagesFinder($additionWhere);

        if (Yii::$app->getModule('images')->className === null) {
            $imageQuery = Image::find();
        } else {
            $class = Yii::$app->getModule('images')->className;
            $imageQuery = $class::find();
        }
        $imageQuery->where($finder);
        //$imageQuery->orderBy(['is_main' => SORT_DESC, 'id' => SORT_ASC]);
        $imageQuery->orderBy(['ordern' => SORT_DESC]);

        $imageRecords = $imageQuery->all();
        //if (!$imageRecords && Yii::$app->getModule('images')->placeHolderPath) {
        //    return [Yii::$app->getModule('images')->getPlaceHolder()];
        //}
        return $imageRecords;
    }

    /**
     * returns main model image
     * @param $main
     * @return array|null|ActiveRecord
     */
    public function getImage($main = 1)
    {
        $wheres['object_id'] = $this->owner->primaryKey;
        $wheres['handler_hash'] = $this->owner->getHash();
        if ($main)
            $wheres['is_main'] = 1;
        $query = Image::find()->where($wheres);

        //echo $query->createCommand()->rawSql;die;
        $img = $query->one();

        if (!$img) {
            return NULL;
        }


        return $img;
    }

    public function getImageData($size)
    {
        $noImagePath = Yii::getAlias('@uploads') . DIRECTORY_SEPARATOR . 'no-image.jpg';
        $wheres['object_id'] = $this->owner->primaryKey;
        $wheres['handler_hash'] = $this->owner->getHash();

        $wheres['is_main'] = 1;
        $query = Image::find()->where($wheres);

        //echo $query->createCommand()->rawSql;die;
        /** @var Image $img */
        $img = $query->one();

        if (!$img) {
            return (object)[
                'url' => $this->createVersion2($noImagePath, $size),
                // 'model'=>$img
            ];
        }
        $path = Yii::getAlias($img->path) . DIRECTORY_SEPARATOR . $img->object_id . DIRECTORY_SEPARATOR . $img->filePath;

        if (file_exists($path)) {
            $assetPath = Yii::getAlias("@web/assets/product/{$img->object_id}/{$size}") . DIRECTORY_SEPARATOR . $img->filePath;


            // $s = $img->createVersion($path, $size);
            return (object)[
                'url' => $img->createVersion($path, $size),
                'model' => $img
            ];
            //  CMS::dump($s);
            //  die;
        } else {
            return (object)[
                'url' => $img->createVersion($noImagePath, $size),
                'model' => $img
            ];

        }


        //   return $img;
    }

    public function getPathToOrigin($filePath)
    {
        //$base = Yii::$app->getModule('images')->getStorePath();

        if (!file_exists($filePath)) {
            // $this->existImage = false;
            $filePath = Yii::$app->getModule('images')->getNoImagePath();
        }
        return $filePath;
    }

    public function getExtension($path)
    {
        $ext = pathinfo($this->getPathToOrigin($path), PATHINFO_EXTENSION);
        return $ext;
    }

    public function createVersion2($imagePath, $size = false)
    {
        $owner = $this->owner;
        $sizes = explode('x', $size);

        $isSaveFile = false;
        if (isset($sizes[0]) && isset($sizes[1])) {
            $imageAssetPath = Yii::getAlias('@app/web/assets/product') . DIRECTORY_SEPARATOR . $owner->id . DIRECTORY_SEPARATOR . $size;
            $assetPath = "/assets/product/{$owner->id}/{$size}";
        } else {
            $imageAssetPath = Yii::getAlias('@app/web/assets/product') . DIRECTORY_SEPARATOR . $owner->id;
            $assetPath = '/assets/product/' . $owner->id;
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
        if (!in_array(mb_strtolower($this->getExtension($imagePath)), ['jpg', 'jpeg', 'webp'])) {
            $configApp->watermark_enable = false;
            //$img->grayscale();
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
        return $assetPath . '/' . basename($img->getFileName());
        // return $img;

    }

    /**
     * returns model image by name
     * @return array|null|ActiveRecord
     */
    public function getImageByName($name)
    {
        $query = Image::find()->where([
            'object_id' => $this->owner->primaryKey,
            'handler_hash' => $this->owner->getHash()
        ]);
        $query->andWhere(['name' => $name]);
        //    $imageQuery = Image::find();

        //$finder = $this->getImagesFinder(['name' => $name]);
        //$imageQuery->where($finder);
        //$imageQuery->orderBy(['is_main' => SORT_DESC, 'id' => SORT_ASC]);
        //$imageQuery->orderBy(['ordern' => SORT_DESC]);

        $img = $query->one();
        if (!$img) {
            // return Yii::$app->getModule('images')->getPlaceHolder();
            return NULL;
        }

        return $img;
    }

    /**
     * Remove all model images
     */
    public function afterDelete()
    {
        $images = $this->owner->getImages();
        if (count($images) < 1) {
            return true;
        } else {
            foreach ($images as $image) {
                $this->owner->removeImage($image);
            }

            $path = Yii::getAlias($this->path) . DIRECTORY_SEPARATOR . $this->owner->primaryKey;
            BaseFileHelper::removeDirectory($path);
        }
    }


    /**
     * removes concrete model's image
     * @param Image $img
     * @return bool
     * @throws \Exception
     */
    public function removeImage(Image $img)
    {

        $storePath = Yii::$app->getModule('images')->getStorePath();

        $fileToRemove = $storePath . DIRECTORY_SEPARATOR . $img->filePath;
        if (preg_match('@\.@', $fileToRemove) and is_file($fileToRemove)) {
            unlink($fileToRemove);
        }
        $img->delete();
        return true;
    }

    private function getImagesFinder($additionWhere = false)
    {
        $base = [
            'object_id' => $this->owner->primaryKey,
            'handler_hash' => $this->owner->getHash()
        ];

        if ($additionWhere) {
            $base = \yii\helpers\BaseArrayHelper::merge($base, $additionWhere);
        }

        return $base;
    }

    /** Make string part of image's url
     * @return string
     * @throws \Exception
     */
    private function getAliasString()
    {
        if ($this->createAliasMethod) {
            $string = $this->owner->{$this->createAliasMethod}();
            if (!is_string($string)) {
                throw new \Exception("Image's url must be string!");
            } else {
                return $string;
            }
        } else {
            return substr(md5(microtime()), 0, 10);
        }
    }

    /**
     *
     * Обновить алиасы для картинок
     * Зачистить кэш
     */
    public function getAlias()
    {
        $aliasWords = $this->getAliasString();
        $imagesCount = count($this->owner->getImages());

        return $aliasWords . '-' . intval($imagesCount + 1);
    }

    protected function updateMainImage()
    {
        $post = Yii::$app->request->post('AttachmentsMainId');
        if ($post) {

            Image::updateAll(['is_main' => 0], 'object_id=:pid AND handler_hash=:hash', ['hash' => $this->owner->getHash(), 'pid' => $this->owner->primaryKey]);

            $customer = Image::findOne($post);
            if ($customer) {
                $customer->is_main = 1;
                $customer->update();
            }
        }
    }

    protected function updateImageTitles()
    {
        if (sizeof(Yii::$app->request->post('attachment_image_titles', []))) {
            foreach (Yii::$app->request->post('attachment_image_titles', []) as $id => $title) {
                if (!empty($title)) {
                    $customer = Image::findOne($id);
                    if ($customer) {
                        $customer->alt_title = $title;
                        $customer->update();
                    }
                }
            }
        }
    }


}
