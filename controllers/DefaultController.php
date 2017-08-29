<?php

namespace panix\mod\images\controllers;

use panix\engine\controllers\WebController;

class DefaultController extends WebController {


    public function actionGetImage($item = '', $dirtyAlias) {

        $dotParts = explode('.', $dirtyAlias);
        if (!isset($dotParts[1])) {
            throw new \yii\web\HttpException(404, 'Image must have extension');
        }
        $dirtyAlias = $dotParts[0];

        $size = isset(explode('_', $dirtyAlias)[1]) ? explode('_', $dirtyAlias)[1] : false;
        $alias = isset(explode('_', $dirtyAlias)[0]) ? explode('_', $dirtyAlias)[0] : false;
        $image = \Yii::$app->getModule('images')->getImage($item, $alias);

        if ($image->getExtension() != $dotParts[1]) {
            throw new \yii\web\HttpException(404, 'Image not found (extension)');
        }

        if ($image) {
            header('Content-Type: ' . $image->getMimeType($size));
            echo $image->getContent($size);
        } else {
            throw new \yii\web\HttpException(404, 'There is no images');
        }
    }

}
