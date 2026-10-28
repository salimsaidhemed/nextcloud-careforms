<?php

declare(strict_types=1);

namespace OCA\CareForms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000227Date20261028023000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if ($schema->hasTable('careforms_addenda')) {
            $addenda = $schema->getTable('careforms_addenda');
            if (!$addenda->hasColumn('change_request_id')) {
                $addenda->addColumn('change_request_id', 'bigint', ['notnull' => false, 'unsigned' => true]);
                $addenda->addIndex(['change_request_id'], 'cf_addendum_request');
            }
        }
        if ($schema->hasTable('careforms_change_req')) return $schema;
        $table = $schema->createTable('careforms_change_req');
        $table->addColumn('id', 'bigint', ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
        $table->addColumn('submission_id', 'bigint', ['notnull' => true, 'unsigned' => true]);
        $table->addColumn('sequence_number', 'integer', ['notnull' => true, 'unsigned' => true]);
        $table->addColumn('requested_by', 'string', ['notnull' => true, 'length' => 64]);
        $table->addColumn('instructions', 'text', ['notnull' => true]);
        $table->addColumn('status', 'string', ['notnull' => true, 'length' => 24, 'default' => 'open']);
        $table->addColumn('created_at', 'bigint', ['notnull' => true]);
        $table->addColumn('responded_at', 'bigint', ['notnull' => false]);
        $table->addColumn('resolved_at', 'bigint', ['notnull' => false]);
        $table->addColumn('response_addendum_id', 'bigint', ['notnull' => false, 'unsigned' => true]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['submission_id', 'sequence_number'], 'cf_change_req_sequence');
        $table->addIndex(['submission_id', 'status'], 'cf_change_req_status');
        return $schema;
    }
}
