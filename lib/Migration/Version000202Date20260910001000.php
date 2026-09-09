<?php

declare(strict_types=1);

namespace OCA\CareForms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000202Date20260910001000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('careforms_audit')) {
            $table = $schema->createTable('careforms_audit');
            $table->addColumn('id', 'bigint', ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $table->addColumn('user_id', 'string', ['notnull' => true, 'length' => 64]);
            $table->addColumn('action', 'string', ['notnull' => true, 'length' => 64]);
            $table->addColumn('resource_type', 'string', ['notnull' => true, 'length' => 64]);
            $table->addColumn('resource_id', 'bigint', ['notnull' => false, 'unsigned' => true]);
            $table->addColumn('form_id', 'string', ['notnull' => false, 'length' => 128]);
            $table->addColumn('outcome', 'string', ['notnull' => true, 'length' => 16, 'default' => 'success']);
            $table->addColumn('metadata', 'text', ['notnull' => true, 'default' => '{}']);
            $table->addColumn('created_at', 'bigint', ['notnull' => true]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id', 'created_at'], 'careforms_audit_user_time_idx');
            $table->addIndex(['action', 'created_at'], 'careforms_audit_action_time_idx');
            $table->addIndex(['resource_type', 'resource_id'], 'careforms_audit_resource_idx');
        }

        return $schema;
    }
}
