<?php

declare(strict_types=1);

namespace OCA\CareForms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000228Date20261028033000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('careforms_categories')) {
            $table = $schema->createTable('careforms_categories');
            $table->addColumn('id', 'bigint', ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $table->addColumn('category_key', 'string', ['notnull' => true, 'length' => 64]);
            $table->addColumn('label', 'string', ['notnull' => true, 'length' => 128]);
            $table->addColumn('sort_order', 'integer', ['notnull' => true, 'default' => 0]);
            $table->addColumn('created_by', 'string', ['notnull' => true, 'length' => 64]);
            $table->addColumn('created_at', 'bigint', ['notnull' => true]);
            $table->addColumn('updated_at', 'bigint', ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['category_key'], 'cf_category_key');
            $table->addIndex(['sort_order'], 'cf_category_order');
        }
        if (!$schema->hasTable('careforms_form_cat')) {
            $table = $schema->createTable('careforms_form_cat');
            $table->addColumn('id', 'bigint', ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $table->addColumn('form_id', 'string', ['notnull' => true, 'length' => 128]);
            $table->addColumn('category_key', 'string', ['notnull' => true, 'length' => 64]);
            $table->addColumn('updated_at', 'bigint', ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['form_id'], 'cf_form_category_form');
            $table->addIndex(['category_key'], 'cf_form_category_key');
        }
        return $schema;
    }
}
