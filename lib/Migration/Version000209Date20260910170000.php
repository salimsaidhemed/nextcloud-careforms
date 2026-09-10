<?php

declare(strict_types=1);

namespace OCA\CareForms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000209Date20260910170000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('careforms_form_versions')) {
            return $schema;
        }

        $table = $schema->getTable('careforms_form_versions');
        if (!$table->hasColumn('version_number')) {
            $table->addColumn('version_number', 'integer', [
                'notnull' => true,
                'unsigned' => true,
                'default' => 1,
            ]);
        }

        return $schema;
    }
}
