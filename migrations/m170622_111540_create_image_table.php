<?php

class m170622_111540_create_image_table extends \yii\db\Migration {

    public function up() {
        $this->createTable('{{%image}}', [
            'id' => $this->primaryKey(),
            'filePath' => $this->string(400)->notNull(),
            'object_id' => $this->integer(),
            'is_main' => $this->boolean()->defaultValue(0),
            'modelName' => $this->string(150)->notNull(),
            'urlAlias' => $this->string(400)->notNull(),
            'ordern' => $this->integer(),
        ]);
        $this->createIndex('ordern', '{{%image}}', 'ordern', 0);
        $this->createIndex('object_id', '{{%image}}', 'object_id', 0);
        $this->createIndex('is_main', '{{%image}}', 'is_main', 0);
    }

    public function down() {
        $this->dropTable('{{%image}}');
    }

}
