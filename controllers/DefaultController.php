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

                if ($page->is_main) {
                    // Get first image and set it as main
                    $model = Image::find();
                    if ($model) {
                        $model->is_main = 1;
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
 public function actionEditCrop($id) {
        $entry = Image::find()
                ->where(['id' => \Yii::$app->request->get('id')])
                ->one();
     return $this->render('edit-crop',['image'=>$entry]);
 }
    public function actionCrop() {
        $request = \Yii::$app->request;
        $post = $request->post();
        $form = $request->post('CropperForm');


        try {
            // Create a new SimpleImage object
            $image = new \claviska\SimpleImage('uploads/l1kUWA3UOT_20482603_810200375834266_8994343786263871488_n.jpg');

            $image->autoOrient();

           /* $image->overlay('uploads/watermark.png', 'top right');
            $image->text('CORNER CMS 1.1', ['fontFile' => 'uploads/BankGothic RUSS Medium.ttf', 'size' => 30]);
            $image->text('CORNER CMS 1.2', [
                'fontFile' => 'uploads/BankGothic RUSS Medium.ttf',
                'size' => 30,
                'color' => '#ff0000',
                'anchor' => 'top left',
                'shadow' => ['x' => 3, 'y' => 3, 'color' => '#000000']
            ]);
            $image->overlay('uploads/watermark.png', 'bottom right', 0.8, -10, -10);*/
         //   if($request->method == 'SETDRAGMODE'){
            if (isset($form['coord_x']) && isset($form['coord_y'])) {
                $image->crop($form['coord_x'], $form['coord_y'], $form['coord_x'] + $form['width'], $form['coord_y'] + $form['height']);
            }
          //  }
            if (isset($form['width']) && isset($form['height'])) {
                  $image->resize($form['width'], $form['height']);
            }

            //->resize(320)                          // resize to 320x200 pixels
            //->flip('both') 
            //->flip('x')
            if ($request->method == 'ROTATE' && isset($post['CropperForm']['rotate'])) {
                // $image->rotate($post['CropperForm']['rotate']);
            }
            // flip horizontally
            //->colorize('#3e8230')                      // tint dark blue
            //->border('black', 10)                       // add a 10 pixel black border

            /* ->polygon([
              ['x' => 300, 'y' => 600],
              ['x' => 600, 'y' => 1000],
              ['x' => 50, 'y' => 50]
              ], '#ff0000', 'filled') */
            //->blur('gaussian',5) //very slow load 
            //->thumbnail(200,200,'top')
            // ->resize(520)
            //->darken(40)
            //->desaturate()//grayscale
            //$image->contrast(30);
            $image->toFile('uploads/new-image.jpg', 'image/png');      // convert to PNG and save a copy to new-image.png
            $image->toString();                               // output to the screen
            // And much more! 💪
        } catch (Exception $err) {
            // Handle errors
            echo $err->getMessage();
        }
        die;
    }

}
