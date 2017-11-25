<?php

namespace panix\mod\images\behaviors;

use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use panix\mod\images\models;
use panix\mod\images\models\Image;
use yii\helpers\BaseFileHelper;

class ImageBehavior extends Behavior {

    public $createAliasMethod = false;

    public function attach($owner) {
        parent::attach($owner);
    }

    public function events() {
        return [
            ActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',

        ];
    }


    public function afterSave() {
        $this->updateMainImage();
        $this->updateImageTitles();
    }

    /**
     * @var ActiveRecord|null Model class, which will be used for storing image data in db, if not set default class(models/Image) will be used
     */

    /**
     *
     * Method copies image file to module store and creates db record.
     *
     * @param $absolutePath
     * @param bool $is_main
     * @return bool|Image
     * @throws \Exception
     */
    public function attachImage($absolutePath, $is_main = false, $name = '') {
        /*if (!preg_match('#http#', $absolutePath)) {
            if (!file_exists($absolutePath)) {
                throw new \Exception('File not exist! :' . $absolutePath);
            }
        } else {
            //nothing
        }*/

        if (!$this->owner->primaryKey) {
            throw new \Exception('Owner must have primaryKey when you attach image!');
        }

        $pictureFileName = substr(md5(microtime(true) . $absolutePath), 4, 6)
                . '.' .
                pathinfo($absolutePath, PATHINFO_EXTENSION);
        $pictureSubDir = Yii::$app->getModule('images')->getModelSubDir($this->owner);
        $storePath = Yii::$app->getModule('images')->getStorePath($this->owner);

        $newAbsolutePath = $storePath .
                DIRECTORY_SEPARATOR . $pictureSubDir .
                DIRECTORY_SEPARATOR . $pictureFileName;

        BaseFileHelper::createDirectory($storePath . DIRECTORY_SEPARATOR . $pictureSubDir, 0775, true);

        copy($absolutePath, $newAbsolutePath);
        if(file_exists($absolutePath)){
            unlink($absolutePath);
        }
        if (!file_exists($newAbsolutePath)) {
            throw new \Exception('Cant copy file! ' . $absolutePath . ' to ' . $newAbsolutePath);
        }

        if (Yii::$app->getModule('images')->className === null) {
            $image = new Image;
        } else {
            $class = Yii::$app->getModule('images')->className;
            $image = new $class();
        }
        $image->object_id = $this->owner->primaryKey;
        $image->filePath = $pictureSubDir . '/' . $pictureFileName;
        $image->modelName = Yii::$app->getModule('images')->getShortClass($this->owner);
        $image->alt_title = $name;

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
        if (
                $img == null
                or
                $is_main
        ) {
            $this->setMainImage($image);
        }


        return $image;
    }

    /**
     * Sets main image of model
     * @param $img
     * @throws \Exception
     */
    public function setMainImage($img) {

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
    public function clearImagesCache() {
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
    public function getImages($additionWhere = false) {
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
     * @return array|null|ActiveRecord
     */
    public function getImage() {
        if (Yii::$app->getModule('images')->className === null) {
            $imageQuery = Image::find();
        } else {
            $class = Yii::$app->getModule('images')->className;
            $imageQuery = $class::find();
        }
        $finder = $this->getImagesFinder(['is_main' => 1]);
        $imageQuery->where($finder);
        //$imageQuery->orderBy(['is_main' => SORT_DESC, 'id' => SORT_ASC]);
        $imageQuery->orderBy(['ordern' => SORT_DESC]);

        $img = $imageQuery->one();
        if (!$img) {
            return NULL; //Yii::$app->getModule('images')->getPlaceHolder();
        }

        return $img;
    }

    /**
     * returns model image by name
     * @return array|null|ActiveRecord
     */
    public function getImageByName($name) {
        if (Yii::$app->getModule('images')->className === null) {
            $imageQuery = Image::find();
        } else {
            $class = Yii::$app->getModule('images')->className;
            $imageQuery = $class::find();
        }
        $finder = $this->getImagesFinder(['name' => $name]);
        $imageQuery->where($finder);
        //$imageQuery->orderBy(['is_main' => SORT_DESC, 'id' => SORT_ASC]);
        $imageQuery->orderBy(['ordern' => SORT_DESC]);

        $img = $imageQuery->one();
        if (!$img) {
            // return Yii::$app->getModule('images')->getPlaceHolder();
            return NULL;
        }

        return $img;
    }

    /**
     * Remove all model images
     */
    public function removeImages() {
        $images = $this->owner->getImages();
        if (count($images) < 1) {
            return true;
        } else {
            foreach ($images as $image) {
                $this->owner->removeImage($image);
            }
            $storePath = Yii::$app->getModule('images')->getStorePath($this->owner);
            $pictureSubDir = Yii::$app->getModule('images')->getModelSubDir($this->owner);
            $dirToRemove = $storePath . DIRECTORY_SEPARATOR . $pictureSubDir;
            BaseFileHelper::removeDirectory($dirToRemove);
        }
    }

    

    /**
     * removes concrete model's image
     * @param Image $img
     * @throws \Exception
     * @return bool
     */
    public function removeImage(Image $img) {
        if ($img instanceof models\PlaceHolder) {
            return false;
        }
        $img->clearCache();

        $storePath = Yii::$app->getModule('images')->getStorePath();

        $fileToRemove = $storePath . DIRECTORY_SEPARATOR . $img->filePath;
        if (preg_match('@\.@', $fileToRemove) and is_file($fileToRemove)) {
            unlink($fileToRemove);
        }
        //$img->delete();
      //  return true;
    }

    private function getImagesFinder($additionWhere = false) {
        $base = [
            'object_id' => $this->owner->primaryKey,
            'modelName' => Yii::$app->getModule('images')->getShortClass($this->owner)
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
    private function getAliasString() {
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
    private function getAlias() {
        $aliasWords = $this->getAliasString();
        $imagesCount = count($this->owner->getImages());

        return $aliasWords . '-' . intval($imagesCount + 1);
    }

    protected function updateMainImage() {
        $post = Yii::$app->request->post('AttachmentsMainId');
        if ($post) {
            $modelName = Yii::$app->getModule('images')->getShortClass($this->owner);

            Image::updateAll(['is_main' => 0], 'object_id=:pid AND modelName=:model', ['model' => $modelName, 'pid' => $this->owner->primaryKey]);

            $customer = Image::findOne($post);
         
            $customer->is_main = 1;
            $customer->update();
           
        }
    }

    protected function updateImageTitles() {
        if (sizeof(Yii::$app->request->post('attachment_image_titles', []))) {
            foreach (Yii::$app->request->post('attachment_image_titles', []) as $id => $title) {
                if(!empty($title)){
                $customer = Image::findOne($id);
                $customer->alt_title = $title;
                $customer->update();
                }
            }
        }
    }

}
