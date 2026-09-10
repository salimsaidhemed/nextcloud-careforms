<?php

declare(strict_types=1);

namespace OCA\CareForms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000211Date20260910180000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('careforms_submissions')) return $schema;

        $table = $schema->getTable('careforms_submissions');
        if (!$table->hasColumn('reviewed_by')) $table->addColumn('reviewed_by', 'string', ['notnull' => false, 'length' => 64]);
        if (!$table->hasColumn('reviewed_at')) $table->addColumn('reviewed_at', 'bigint', ['notnull' => false]);
        if (!$table->hasColumn('review_note')) $table->addColumn('review_note', 'text', ['notnull' => false]);
        if (!$table->hasIndex('cf_sub_status_time')) $table->addIndex(['status', 'submitted_at'], 'cf_sub_status_time');
        return $schema;
    }
}
