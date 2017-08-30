<?php

namespace panix\mod\images\controllers;


use panix\engine\controllers\WebController;
use panix\mod\images\models\Image;

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

    public function actionDelete() {


        $json = array();



            $entry = Image::find()
                    ->where(['id' => \Yii::$app->request->post('id')])
                    ->all();
            if (!empty($entry)) {
                foreach ($entry as $page) {
                    //if (!in_array($page->primaryKey, $model->hidden_delete)) {

                    $page->delete(); //$page->deleteByPk($_REQUEST['id']);

                    if ($page->isMain) {
                        // Get first image and set it as main
                        $model = Image::find();
                        if ($model) {
                            $model->isMain = 1;
                            $model->save();
                        }
                    }

                    $json = array(
                        'status' => 'success',
                        'message' => Yii::t('app', 'SUCCESS_RECORD_DELETE')
                    );
                    //} else {
                    //    $json = array(
                    //       'status' => 'error',
                    //         'message' => Yii::t('app', 'ERROR_RECORD_DELETE')
                    //    );
                    //}
                }
            }


        echo \yii\helpers\Json::encode($json);
        die;
    }

}
