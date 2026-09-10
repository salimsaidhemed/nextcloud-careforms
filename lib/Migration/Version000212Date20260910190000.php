<?php

declare(strict_types=1);

namespace OCA\CareForms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000212Date20260910190000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('careforms_submissions')) {
            return $schema;
        }

        $table = $schema->getTable('careforms_submissions');
        if (!$table->hasColumn('signed_by')) {
            $table->addColumn('signed_by', 'string', ['notnull' => false, 'length' => 64]);
        }
        if (!$table->hasColumn('signer_name')) {
            $table->addColumn('signer_name', 'string', ['notnull' => false, 'length' => 255]);
        }
        if (!$table->hasColumn('signed_at')) {
            $table->addColumn('signed_at', 'bigint', ['notnull' => false]);
        }
        if (!$table->hasColumn('signature_data')) {
            $table->addColumn('signature_data', 'text', ['notnull' => false]);
        }
        if (!$table->hasColumn('integrity_hash')) {
            $table->addColumn('integrity_hash', 'string', ['notnull' => false, 'length' => 64]);
        }

        return $schema;
    }
}
