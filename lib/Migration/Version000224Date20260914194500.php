<?php

declare(strict_types=1);

namespace OCA\CareForms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000224Date20260914194500 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('careforms_definitions')) {
            $table = $schema->createTable('careforms_definitions');
            $table->addColumn('id', 'bigint', [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('form_id', 'string', [
                'notnull' => true,
                'length' => 128,
            ]);
            $table->addColumn('form_version', 'integer', [
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('schema_version', 'integer', [
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('definition_json', 'text', [
                'notnull' => true,
            ]);
            $table->addColumn('created_by', 'string', [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('created_at', 'bigint', [
                'notnull' => true,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['form_id', 'form_version'], 'cf_def_form_ver');
            $table->addIndex(['form_id'], 'cf_def_form');
        }

        return $schema;
    }
}
