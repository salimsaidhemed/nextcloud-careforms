<?php

declare(strict_types=1);

namespace OCA\CareForms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000225Date20261026003000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('careforms_submissions')) return $schema;
        $table = $schema->getTable('careforms_submissions');
        if (!$table->hasColumn('integrity_version')) {
            $table->addColumn('integrity_version', 'integer', ['notnull' => false, 'unsigned' => true]);
        }
        if (!$table->hasColumn('definition_hash')) {
            $table->addColumn('definition_hash', 'string', ['notnull' => false, 'length' => 64]);
        }
        return $schema;
    }
}
