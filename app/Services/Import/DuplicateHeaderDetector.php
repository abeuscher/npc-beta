<?php

namespace App\Services\Import;

/**
 * Header-only duplicate/similarity detection for the import wizard's
 * Review Data step. Given the parsed CSV headers, returns a list of
 * finding groups — each group names a rule, the headers that matched,
 * and their positional indices in the original header array.
 *
 * Rules (in priority order):
 *   1. exact_match_normalized  — lowercase + strip non-alphanumeric
 *   2. trailing_digit_suffix   — "Address" / "Address 2", "Name_1" / "Name_2"
 *   3. word_subset             — "Email" / "Email address"
 *
 * A header is only ever reported in the highest-priority finding it
 * qualifies for. Header text is the only signal; cell values are out
 * of scope (that is NoiseDetector's territory).
 */
class DuplicateHeaderDetector
{
    /**
     * @return array<int, array{rule: string, headers: array<int, string>, indices: array<int, int>, summary: string}>
     */
    public static function detect(array $headers): array
    {
        if (empty($headers)) {
            return [];
        }

        $findings = [];
        $claimed  = [];

        foreach (static::detectExactMatches($headers) as $group) {
            if (static::isKnownLegitimateGroup($group['headers'])) {
                continue;
            }

            $findings[] = $group;
            foreach ($group['indices'] as $i) {
                $claimed[$i] = true;
            }
        }

        foreach (static::detectTrailingDigitSuffixes($headers) as $group) {
            if ($filtered = static::subtractClaimed($group, $claimed)) {
                if (static::isKnownLegitimateGroup($filtered['headers'])) {
                    continue;
                }

                $findings[] = $filtered;
                foreach ($filtered['indices'] as $i) {
                    $claimed[$i] = true;
                }
            }
        }

        foreach (static::detectWordSubsets($headers) as $group) {
            if ($filtered = static::subtractClaimed($group, $claimed)) {
                if (static::isKnownLegitimateGroup($filtered['headers'])) {
                    continue;
                }

                $findings[] = $filtered;
                foreach ($filtered['indices'] as $i) {
                    $claimed[$i] = true;
                }
            }
        }

        return $findings;
    }

    /**
     * Carve-outs for header families that look duplicate-ish but represent
     * distinct fields. Currently: address-line columns (Address / Address 1
     * / Address 2 / Address Line 1, and the street/street-address variants).
     */
    private static function isKnownLegitimateGroup(array $headers): bool
    {
        foreach ($headers as $header) {
            $normalized = strtolower(trim($header));

            $isAddressLine = preg_match('/^address(\s+line)?(\s*\d+)?$/', $normalized)
                || preg_match('/^street(\s+address)?(\s+line)?(\s*\d+)?$/', $normalized);

            if (! $isAddressLine) {
                return false;
            }
        }

        return true;
    }

    private static function subtractClaimed(array $group, array $claimed): ?array
    {
        $keptHeaders = [];
        $keptIndices = [];

        foreach ($group['indices'] as $pos => $idx) {
            if (! isset($claimed[$idx])) {
                $keptIndices[] = $idx;
                $keptHeaders[] = $group['headers'][$pos];
            }
        }

        if (count($keptIndices) < 2) {
            return null;
        }

        return [
            'rule'    => $group['rule'],
            'headers' => $keptHeaders,
            'indices' => $keptIndices,
            'summary' => $group['summary'],
        ];
    }

    private static function detectExactMatches(array $headers): array
    {
        $byKey = [];

        foreach ($headers as $idx => $header) {
            $key = static::normalizeExact($header);

            if ($key === '') {
                continue;
            }

            $byKey[$key][] = ['idx' => $idx, 'header' => $header];
        }

        $groups = [];

        foreach ($byKey as $entries) {
            if (count($entries) < 2) {
                continue;
            }

            $groups[] = [
                'rule'    => 'exact_match_normalized',
                'headers' => array_column($entries, 'header'),
                'indices' => array_column($entries, 'idx'),
                'summary' => count($entries) . ' columns normalize to the same name.',
            ];
        }

        return $groups;
    }

    private static function detectTrailingDigitSuffixes(array $headers): array
    {
        $byKey = [];

        foreach ($headers as $idx => $header) {
            $stripped = static::stripTrailingSuffix($header);

            if ($stripped === '') {
                continue;
            }

            $byKey[$stripped][] = ['idx' => $idx, 'header' => $header];
        }

        $groups = [];

        foreach ($byKey as $key => $entries) {
            if (count($entries) < 2) {
                continue;
            }

            $hasActualSuffix = false;
            foreach ($entries as $entry) {
                if (strtolower(trim($entry['header'])) !== $key) {
                    $hasActualSuffix = true;
                    break;
                }
            }

            if (! $hasActualSuffix) {
                continue;
            }

            $groups[] = [
                'rule'    => 'trailing_digit_suffix',
                'headers' => array_column($entries, 'header'),
                'indices' => array_column($entries, 'idx'),
                'summary' => 'Columns differ only by a trailing number or suffix.',
            ];
        }

        return $groups;
    }

    private static function detectWordSubsets(array $headers): array
    {
        $tokenized = [];

        foreach ($headers as $idx => $header) {
            $tokens = static::tokenize($header);

            if (empty($tokens)) {
                continue;
            }

            $tokenized[$idx] = ['header' => $header, 'tokens' => $tokens];
        }

        $indices = array_keys($tokenized);
        $parent  = array_combine($indices, $indices);

        $find = function (int $x) use (&$parent, &$find): int {
            while ($parent[$x] !== $x) {
                $parent[$x] = $parent[$parent[$x]];
                $x          = $parent[$x];
            }

            return $x;
        };

        $union = function (int $a, int $b) use (&$parent, $find): void {
            $ra = $find($a);
            $rb = $find($b);

            if ($ra !== $rb) {
                $parent[$ra] = $rb;
            }
        };

        $count = count($indices);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $indices[$i];
                $b = $indices[$j];

                $tokensA = $tokenized[$a]['tokens'];
                $tokensB = $tokenized[$b]['tokens'];

                if ($tokensA === $tokensB) {
                    continue;
                }

                if (static::isPrefixSubset($tokensA, $tokensB)
                    || static::isPrefixSubset($tokensB, $tokensA)) {
                    $union($a, $b);
                }
            }
        }

        $components = [];
        foreach ($indices as $idx) {
            $root               = $find($idx);
            $components[$root][] = $idx;
        }

        $groups = [];

        foreach ($components as $members) {
            if (count($members) < 2) {
                continue;
            }

            sort($members);

            $groups[] = [
                'rule'    => 'word_subset',
                'headers' => array_map(fn ($i) => $tokenized[$i]['header'], $members),
                'indices' => $members,
                'summary' => 'Columns share overlapping word patterns.',
            ];
        }

        return $groups;
    }

    private static function normalizeExact(string $header): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(trim($header))) ?? '';
    }

    private static function stripTrailingSuffix(string $header): string
    {
        $value = strtolower(trim($header));

        $patterns = [
            '/\s*[\(\[][^\)\]]*[\)\]]\s*$/',
            '/\s*#\s*\d+\s*$/',
            '/[\s_\-]*\d+\s*$/',
            '/[\s_\-]+(alt|alternate|secondary|other|primary)$/',
        ];

        foreach ($patterns as $pattern) {
            $value = preg_replace($pattern, '', $value);
            $value = trim($value);
        }

        return $value;
    }

    private static function tokenize(string $header): array
    {
        $withSpaces = preg_replace('/([a-z])([A-Z])/', '$1 $2', $header) ?? $header;
        $lower      = strtolower($withSpaces);
        $parts      = preg_split('/[\s_\-,.;:|\/\\\\()\[\]]+/', $lower) ?: [];

        return array_values(array_filter($parts, fn ($t) => $t !== ''));
    }

    /**
     * True when $a's tokens are a proper prefix of $b's tokens (order-sensitive).
     * This is intentionally stricter than set-subset: it lets us group
     * "Email" with "Email address" without bridging "Address" into the
     * same group via the shared "address" token.
     */
    private static function isPrefixSubset(array $a, array $b): bool
    {
        $countA = count($a);
        $countB = count($b);

        if ($countA === 0 || $countA >= $countB) {
            return false;
        }

        for ($i = 0; $i < $countA; $i++) {
            if ($a[$i] !== $b[$i]) {
                return false;
            }
        }

        return true;
    }
}
