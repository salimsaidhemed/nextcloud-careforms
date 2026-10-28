<?php

declare(strict_types=1);

namespace OCA\CareForms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000226Date20261028013000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if ($schema->hasTable('careforms_addenda')) return $schema;
        $table = $schema->createTable('careforms_addenda');
        $table->addColumn('id', 'bigint', ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
        $table->addColumn('submission_id', 'bigint', ['notnull' => true, 'unsigned' => true]);
        $table->addColumn('sequence_number', 'integer', ['notnull' => true, 'unsigned' => true]);
        $table->addColumn('author_id', 'string', ['notnull' => true, 'length' => 64]);
        $table->addColumn('reason', 'text', ['notnull' => true]);
        $table->addColumn('data', 'text', ['notnull' => true]);
        $table->addColumn('status', 'string', ['notnull' => true, 'length' => 32, 'default' => 'draft']);
        $table->addColumn('created_at', 'bigint', ['notnull' => true]);
        $table->addColumn('updated_at', 'bigint', ['notnull' => true]);
        $table->addColumn('submitted_at', 'bigint', ['notnull' => false]);
        $table->addColumn('reviewed_by', 'string', ['notnull' => false, 'length' => 64]);
        $table->addColumn('reviewed_at', 'bigint', ['notnull' => false]);
        $table->addColumn('review_note', 'text', ['notnull' => false]);
        $table->addColumn('integrity_hash', 'string', ['notnull' => false, 'length' => 64]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['submission_id', 'sequence_number'], 'cf_addendum_sequence');
        $table->addIndex(['submission_id', 'status'], 'cf_addendum_submission');
        return $schema;
    }
}
