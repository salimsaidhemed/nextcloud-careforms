<?php

declare(strict_types=1);

namespace OCA\CareForms\Service;

use InvalidArgumentException;

final class CalculationEngine
{
    /** @return list<string> */
    public function references(string $expression): array
    {
        $references = [];
        $this->walk($this->parse($expression), static function (array $node) use (&$references): void {
            if (($node['type'] ?? null) === 'field' && !in_array($node['id'], $references, true)) {
                $references[] = $node['id'];
            }
        });
        return $references;
    }

    /** @param array<string, mixed> $values @return array{status:string,value?:float|int,error?:string} */
    public function evaluate(string $expression, array $values, ?int $precision = null): array
    {
        try {
            $result = $this->evaluateNode($this->parse($expression), $values);
        } catch (InvalidArgumentException $error) {
            return ['status' => 'error', 'error' => $error->getMessage()];
        }
        if ($result['status'] === 'ok' && $precision !== null) {
            $result['value'] = round((float)$result['value'], $precision);
        }
        return $result;
    }

    /** @return array<string, mixed> */
    public function parse(string $expression): array
    {
        if (trim($expression) === '') throw new InvalidArgumentException('Expression is required.');
        if (strlen($expression) > 1000) throw new InvalidArgumentException('Expression must not exceed 1000 characters.');
        $tokens = $this->tokenize($expression);
        $index = 0;
        $node = $this->parseAddition($tokens, $index);
        if (($tokens[$index]['type'] ?? null) !== 'eof') {
            throw new InvalidArgumentException(sprintf('Unexpected token at position %d.', $tokens[$index]['position'] + 1));
        }
        return $node;
    }

    /** @return list<array{type:string,value?:float|int|string,position:int}> */
    private function tokenize(string $expression): array
    {
        $tokens = [];
        for ($index = 0, $length = strlen($expression); $index < $length;) {
            if (ctype_space($expression[$index])) { $index++; continue; }
            $rest = substr($expression, $index);
            if (preg_match('/^(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?/', $rest, $match) === 1) {
                $number = (float)$match[0];
                if (!is_finite($number)) throw new InvalidArgumentException(sprintf('Numeric constant must be finite at position %d.', $index + 1));
                $tokens[] = ['type' => 'number', 'value' => $number, 'position' => $index];
                $index += strlen($match[0]); continue;
            }
            if (preg_match('/^[a-z][a-z0-9_]*/', $rest, $match) === 1) {
                $tokens[] = ['type' => 'field', 'value' => $match[0], 'position' => $index];
                $index += strlen($match[0]); continue;
            }
            if (str_contains('+-*/()', $expression[$index])) {
                $tokens[] = ['type' => $expression[$index], 'value' => $expression[$index], 'position' => $index++]; continue;
            }
            throw new InvalidArgumentException(sprintf('Unsupported token at position %d.', $index + 1));
        }
        $tokens[] = ['type' => 'eof', 'position' => strlen($expression)];
        return $tokens;
    }

    /** @param list<array<string, mixed>> $tokens @return array<string, mixed> */
    private function parseAddition(array $tokens, int &$index): array
    {
        $node = $this->parseMultiplication($tokens, $index);
        while (in_array($tokens[$index]['type'], ['+', '-'], true)) {
            $operator = $tokens[$index++]['type'];
            $node = ['type' => 'binary', 'operator' => $operator, 'left' => $node, 'right' => $this->parseMultiplication($tokens, $index)];
        }
        return $node;
    }

    /** @param list<array<string, mixed>> $tokens @return array<string, mixed> */
    private function parseMultiplication(array $tokens, int &$index): array
    {
        $node = $this->parseUnary($tokens, $index);
        while (in_array($tokens[$index]['type'], ['*', '/'], true)) {
            $operator = $tokens[$index++]['type'];
            $node = ['type' => 'binary', 'operator' => $operator, 'left' => $node, 'right' => $this->parseUnary($tokens, $index)];
        }
        return $node;
    }

    /** @param list<array<string, mixed>> $tokens @return array<string, mixed> */
    private function parseUnary(array $tokens, int &$index): array
    {
        if (in_array($tokens[$index]['type'], ['+', '-'], true)) {
            return ['type' => 'unary', 'operator' => $tokens[$index++]['type'], 'operand' => $this->parseUnary($tokens, $index)];
        }
        return $this->parsePrimary($tokens, $index);
    }

    /** @param list<array<string, mixed>> $tokens @return array<string, mixed> */
    private function parsePrimary(array $tokens, int &$index): array
    {
        $token = $tokens[$index];
        if ($token['type'] === 'number') { $index++; return ['type' => 'number', 'value' => $token['value']]; }
        if ($token['type'] === 'field') { $index++; return ['type' => 'field', 'id' => $token['value']]; }
        if ($token['type'] === '(') {
            $index++; $node = $this->parseAddition($tokens, $index);
            if (($tokens[$index]['type'] ?? null) !== ')') throw new InvalidArgumentException(sprintf('Expected ) at position %d.', $tokens[$index]['position'] + 1));
            $index++; return $node;
        }
        throw new InvalidArgumentException(sprintf('Expected a number, field ID, or parenthesis at position %d.', $token['position'] + 1));
    }

    /** @param array<string, mixed> $node @param callable(array<string, mixed>):void $visitor */
    private function walk(array $node, callable $visitor): void
    {
        $visitor($node);
        if (($node['type'] ?? null) === 'unary') $this->walk($node['operand'], $visitor);
        if (($node['type'] ?? null) === 'binary') { $this->walk($node['left'], $visitor); $this->walk($node['right'], $visitor); }
    }

    /** @param array<string, mixed> $node @param array<string, mixed> $values @return array{status:string,value?:float|int,error?:string} */
    private function evaluateNode(array $node, array $values): array
    {
        if ($node['type'] === 'number') return ['status' => 'ok', 'value' => $node['value']];
        if ($node['type'] === 'field') {
            $value = $values[$node['id']] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '')) return ['status' => 'empty'];
            if (!is_int($value) && !is_float($value)) return ['status' => 'error', 'error' => sprintf('Field %s must contain a number.', $node['id'])];
            return ['status' => 'ok', 'value' => $value];
        }
        if ($node['type'] === 'unary') {
            $value = $this->evaluateNode($node['operand'], $values);
            if ($value['status'] !== 'ok') return $value;
            return ['status' => 'ok', 'value' => $node['operator'] === '-' ? -$value['value'] : $value['value']];
        }
        $left = $this->evaluateNode($node['left'], $values); if ($left['status'] !== 'ok') return $left;
        $right = $this->evaluateNode($node['right'], $values); if ($right['status'] !== 'ok') return $right;
        if ($node['operator'] === '/' && (float)$right['value'] === 0.0) return ['status' => 'error', 'error' => 'Division by zero.'];
        $value = match ($node['operator']) {
            '+' => $left['value'] + $right['value'], '-' => $left['value'] - $right['value'],
            '*' => $left['value'] * $right['value'], '/' => $left['value'] / $right['value'],
        };
        return is_finite((float)$value) ? ['status' => 'ok', 'value' => $value] : ['status' => 'error', 'error' => 'Calculation did not produce a finite number.'];
    }
}
