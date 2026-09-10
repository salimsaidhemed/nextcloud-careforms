<?php

declare(strict_types=1);

namespace OCA\CareForms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000210Date20260910171500 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('careforms_form_versions')) {
            return $schema;
        }

        $table = $schema->getTable('careforms_form_versions');

        // Development compatibility cleanup: the original v0.2.8 migration
        // created `version`. The application now uses `version_number` to avoid
        // colliding with OCP\AppFramework\Db\Entity internals.
        if ($table->hasIndex('cf_form_version')) {
            $table->dropIndex('cf_form_version');
        }

        if ($table->hasColumn('version')) {
            $table->dropColumn('version');
        }

        if ($table->hasColumn('version_number') && !$table->hasIndex('cf_form_version')) {
            $table->addUniqueIndex(['form_id', 'version_number'], 'cf_form_version');
        }

        return $schema;
    }
}
