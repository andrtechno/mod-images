<?php
namespace panix\mod\images\controllers;

use yii\web\Controller;
use Yii;
use panix\mod\images\models\Image;
use panix\mod\images\ModuleTrait;

class ImagesController extends Controller
{
    use ModuleTrait;
    public function actionIndex()
    {
        echo "Hello, man. It's ok, dont worry.";
    }

    public function actionTestTest()
    {
        echo "Hello, man. It's ok, dont worry.";
    }


    /**
     *
     * All we need is love. No.
     * We need item (by id or another property) and alias (or images number)
     * @param $item
     * @param $alias
     *
     */
    public function actionImageByItemAndAlias($item='', $dirtyAlias)
    {
        $dotParts = explode('.', $dirtyAlias);
        if(!isset($dotParts[1])){
            throw new \yii\web\HttpException(404, 'Image must have extension');
        }
        $dirtyAlias = $dotParts[0];

        $size = isset(explode('_', $dirtyAlias)[1]) ? explode('_', $dirtyAlias)[1] : false;
        $alias = isset(explode('_', $dirtyAlias)[0]) ? explode('_', $dirtyAlias)[0] : false;
        $image = $this->getModule()->getImage($item, $alias);

        if($image->getExtension() != $dotParts[1]){
            throw new \yii\web\HttpException(404, 'Image not found (extension)');
        }

        if($image){
            header('Content-Type: ' . $image->getMimeType($size) );
            echo $image->getContent($size);
        }else{
            throw new \yii\web\HttpException(404, 'There is no images');
        }

    }
}