@php
    $statePath = $getStatePath();
    $resources = $getResources();
    $stateExpr = "\$wire.entangle('{$statePath}')";
@endphp

<x-admin.permission-matrix :state-expr="$stateExpr" :resources="$resources" />
