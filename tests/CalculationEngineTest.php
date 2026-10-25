<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Service/CalculationEngine.php';

use OCA\CareForms\Service\CalculationEngine;

$engine = new CalculationEngine();

$cases = [
    ['2 + 3 * 4', 14.0],
    ['(2 + 3) * 4', 20.0],
    ['-2 + +5', 3.0],
];
foreach ($cases as [$expression, $expected]) {
    $result = $engine->evaluate($expression, []);
    if ($result['status'] !== 'ok' || abs((float)$result['value'] - $expected) > 1.0e-9) {
        throw new RuntimeException('Calculation precedence or unary handling failed for ' . $expression);
    }
}

if ($engine->references('weight / (height * height)') !== ['weight', 'height']) {
    throw new RuntimeException('Calculation references were not extracted correctly.');
}
$result = $engine->evaluate('weight / (height * height)', ['weight' => 70, 'height' => 1.75], 2);
if ($result !== ['status' => 'ok', 'value' => 22.86]) {
    throw new RuntimeException('Calculation precision was not applied correctly.');
}
if ($engine->evaluate('weight / height', ['weight' => 70, 'height' => '']) !== ['status' => 'empty']) {
    throw new RuntimeException('Empty calculation dependencies were not handled correctly.');
}
if ($engine->evaluate('weight / height', ['weight' => 70, 'height' => 0]) !== ['status' => 'error', 'error' => 'Division by zero.']) {
    throw new RuntimeException('Division by zero was not rejected safely.');
}

try {
    $engine->parse('phpinfo()');
    throw new RuntimeException('Unsupported calculation syntax was accepted.');
} catch (InvalidArgumentException) {
    // Expected.
}

fwrite(STDOUT, "CalculationEngine tests passed.\n");
