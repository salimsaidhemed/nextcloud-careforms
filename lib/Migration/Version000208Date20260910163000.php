<?php

declare(strict_types=1);

namespace OCA\CareForms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000208Date20260910163000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('careforms_form_versions')) {
            $table = $schema->createTable('careforms_form_versions');
            $table->addColumn('id', 'bigint', ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $table->addColumn('form_id', 'string', ['notnull' => true, 'length' => 128]);
            $table->addColumn('version_number', 'integer', ['notnull' => true, 'unsigned' => true]);
            $table->addColumn('status', 'string', ['notnull' => true, 'length' => 16, 'default' => 'draft']);
            $table->addColumn('created_by', 'string', ['notnull' => true, 'length' => 64]);
            $table->addColumn('created_at', 'bigint', ['notnull' => true]);
            $table->addColumn('updated_at', 'bigint', ['notnull' => true]);
            $table->addColumn('published_at', 'bigint', ['notnull' => false]);
            $table->addColumn('archived_at', 'bigint', ['notnull' => false]);
            $table->setPrimaryKey(['id'], 'cf_form_versions_pk');
            $table->addUniqueIndex(['form_id', 'version_number'], 'cf_form_version');
            $table->addIndex(['form_id', 'status'], 'cf_form_status');
        }
        return $schema;
    }
}
