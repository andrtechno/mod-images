<?php

namespace panix\mod\images\migrations;

use panix\engine\db\Migration;
use panix\mod\images\models\Image;

class m170622_111540_create_image_table extends Migration
{

    public function up()
    {
        $this->createTable(Image::tableName(), [
            'id' => $this->primaryKey(),
            'filePath' => $this->string(255)->notNull(),
            'path' => $this->string(255)->notNull(),
            'handler_hash' => $this->string(8)->notNull(),
            'handler_class' => $this->string(255)->notNull(),
            'object_id' => $this->integer(),
            'is_main' => $this->boolean()->defaultValue(0),
            'name' => $this->string(80),
            'alt_title' => $this->string(80),
            'modelName' => $this->string(150)->notNull(),
            'urlAlias' => $this->string(400)->notNull(),
            'ordern' => $this->integer()->unsigned(),
        ]);
        $this->createIndex('ordern', Image::tableName(), 'ordern', 0);
        $this->createIndex('object_id', Image::tableName(), 'object_id', 0);
        $this->createIndex('is_main', Image::tableName(), 'is_main', 0);
        $this->createIndex('handler_hash', Image::tableName(), 'is_main', 0);
    }

    public function down()
    {
        $this->dropTable(Image::tableName());
    }

}
