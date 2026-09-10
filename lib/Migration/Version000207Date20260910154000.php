<?php

declare(strict_types=1);

namespace OCA\CareForms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000207Date20260910154000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('careforms_patients')) {
            $table = $schema->createTable('careforms_patients');
            $table->addColumn('id', 'bigint', ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $table->addColumn('medical_record_number', 'string', ['notnull' => true, 'length' => 64]);
            $table->addColumn('first_name', 'string', ['notnull' => true, 'length' => 128]);
            $table->addColumn('last_name', 'string', ['notnull' => true, 'length' => 128]);
            $table->addColumn('date_of_birth', 'string', ['notnull' => false, 'length' => 10]);
            $table->addColumn('status', 'string', ['notnull' => true, 'length' => 16, 'default' => 'active']);
            $table->addColumn('created_at', 'bigint', ['notnull' => true]);
            $table->addColumn('updated_at', 'bigint', ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['medical_record_number'], 'cf_patient_mrn');
            $table->addIndex(['last_name', 'first_name'], 'cf_patient_name');
        }

        if ($schema->hasTable('careforms_submissions')) {
            $submissions = $schema->getTable('careforms_submissions');
            if (!$submissions->hasColumn('patient_id')) {
                $submissions->addColumn('patient_id', 'bigint', ['notnull' => false, 'unsigned' => true]);
                $submissions->addIndex(['patient_id'], 'cf_submission_patient');
            }
        }

        return $schema;
    }
}
