<?php

use App\Http\Controllers\Api\Fleet\BackupController;
use App\Http\Controllers\Api\Fleet\HealthController;
use Tests\TestCase;

uses(TestCase::class);

it('FM contract version constant matches the spec docs Contract Version line', function () {
    $specPath = base_path('docs/fleet-manager-agent-contract.md');

    expect(file_exists($specPath))->toBeTrue("spec doc not found at {$specPath}");

    $contents = file_get_contents($specPath);

    expect(preg_match('/^\*\*Contract Version:\*\*\s+`([^`]+)`/m', $contents, $match))
        ->toBe(1, 'spec doc missing or malformed `Contract Version:` line on its own line at the head of the doc');

    expect($match[1])->toBe(
        HealthController::CONTRACT_VERSION,
        sprintf(
            'CONTRACT_VERSION drift — trait says "%s", spec doc says "%s". Bump both together at every boundary-touching session.',
            HealthController::CONTRACT_VERSION,
            $match[1],
        ),
    );

    expect(BackupController::CONTRACT_VERSION)->toBe(
        HealthController::CONTRACT_VERSION,
        'HealthController and BackupController must read the same CONTRACT_VERSION via the HasContractVersion trait',
    );
});
