<?php

declare(strict_types=1);

namespace OCA\CareForms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000200Date20260909220000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('careforms_submissions')) {
            $table = $schema->createTable('careforms_submissions');
            $table->addColumn('id', 'bigint', [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('user_id', 'string', [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('form_id', 'string', [
                'notnull' => true,
                'length' => 128,
            ]);
            $table->addColumn('form_version', 'string', [
                'notnull' => true,
                'length' => 32,
            ]);
            $table->addColumn('status', 'string', [
                'notnull' => true,
                'length' => 16,
                'default' => 'draft',
            ]);
            $table->addColumn('data', 'text', [
                'notnull' => true,
                'default' => '{}',
            ]);
            $table->addColumn('created_at', 'bigint', [
                'notnull' => true,
            ]);
            $table->addColumn('updated_at', 'bigint', [
                'notnull' => true,
            ]);
            $table->addColumn('submitted_at', 'bigint', [
                'notnull' => false,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id', 'status'], 'careforms_user_status_idx');
            $table->addIndex(['form_id'], 'careforms_form_idx');
            $table->addIndex(['updated_at'], 'careforms_updated_idx');
        }

        return $schema;
    }
}
