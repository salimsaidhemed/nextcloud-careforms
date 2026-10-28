<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Service/RecordHistoryPresenter.php';

use OCA\CareForms\Service\RecordHistoryPresenter;

$presenter = new RecordHistoryPresenter();
$comparison = $presenter->compare(
    ['name' => 'Old name', 'score' => 4, 'empty' => ''],
    ['name' => 'New name', 'score' => 4, 'empty' => 'Added value'],
    ['name' => 'Patient name', 'score' => 'Score', 'empty' => 'Additional detail'],
);

if (($comparison[0]['changeType'] ?? null) !== 'replaced') throw new RuntimeException('Replacement was not identified.');
if (($comparison[0]['before'] ?? null) !== 'Old name' || ($comparison[0]['after'] ?? null) !== 'New name') throw new RuntimeException('Before/after replacement values were not preserved.');
if (($comparison[1]['changeType'] ?? null) !== 'unchanged') throw new RuntimeException('Unchanged value was not identified.');
if (($comparison[2]['changeType'] ?? null) !== 'added') throw new RuntimeException('Added value was not identified.');
if (($comparison[2]['label'] ?? null) !== 'Additional detail') throw new RuntimeException('Configured field label was not used.');

fwrite(STDOUT, "RecordHistoryPresenter tests passed.\n");
