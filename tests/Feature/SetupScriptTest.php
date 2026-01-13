<?php

use function Pest\Laravel\get;

describe('install script', function () {
    it('returns a valid bash script', function () {
        $response = get('/join');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');

        $script = $response->getContent();
        expect($script)->toStartWith('#!/bin/bash');
    });

    it('is compatible with Bash 3.x (macOS default)', function () {
        $response = get('/join');
        $script = $response->getContent();

        // Bash 4+ features that should NOT be present
        $bash4Features = [
            'declare -A' => 'associative arrays',
            'declare -a -A' => 'associative arrays',
            '${!array[@]}' => 'indirect array expansion',
            '|&' => 'pipe stderr shorthand',
            'coproc' => 'coprocesses',
            '&>>' => 'append redirect stdout+stderr',
        ];

        foreach ($bash4Features as $pattern => $feature) {
            expect($script)->not->toContain($pattern, "Script contains Bash 4+ feature: {$feature} ({$pattern})");
        }
    });

    it('has valid bash syntax', function () {
        $response = get('/join');
        $script = $response->getContent();

        // Write to temp file and check syntax with bash -n
        $tempFile = tempnam(sys_get_temp_dir(), 'burnboard_script_');
        file_put_contents($tempFile, $script);

        exec("bash -n {$tempFile} 2>&1", $output, $exitCode);

        unlink($tempFile);

        expect($exitCode)->toBe(0, 'Bash syntax check failed: '.implode("\n", $output));
    });
});

describe('uninstall script', function () {
    it('returns a valid bash script', function () {
        $response = get('/uninstall');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');

        $script = $response->getContent();
        expect($script)->toStartWith('#!/bin/bash');
    });

    it('is compatible with Bash 3.x (macOS default)', function () {
        $response = get('/uninstall');
        $script = $response->getContent();

        $bash4Features = [
            'declare -A' => 'associative arrays',
            'declare -a -A' => 'associative arrays',
            '${!array[@]}' => 'indirect array expansion',
            '|&' => 'pipe stderr shorthand',
            'coproc' => 'coprocesses',
            '&>>' => 'append redirect stdout+stderr',
        ];

        foreach ($bash4Features as $pattern => $feature) {
            expect($script)->not->toContain($pattern, "Script contains Bash 4+ feature: {$feature} ({$pattern})");
        }
    });

    it('has valid bash syntax', function () {
        $response = get('/uninstall');
        $script = $response->getContent();

        $tempFile = tempnam(sys_get_temp_dir(), 'burnboard_script_');
        file_put_contents($tempFile, $script);

        exec("bash -n {$tempFile} 2>&1", $output, $exitCode);

        unlink($tempFile);

        expect($exitCode)->toBe(0, 'Bash syntax check failed: '.implode("\n", $output));
    });
});
