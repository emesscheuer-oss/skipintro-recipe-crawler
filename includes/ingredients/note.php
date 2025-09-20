<?php
declare(strict_types=1);
require_once __DIR__ . '/i18n.php';

/**
 * Item/Notes extraction: comma/parentheses/"-Stück"/TK; removes qty/unit prefix.
 */
if (!function_exists('sitc_ing_extract_item_note')) {
function sitc_ing_extract_item_note(array $tok, $qty, ?string $unit, string $locale = 'de'): array {
    $raw  = (string)($tok['raw'] ?? '');
    $norm = (string)($tok['norm'] ?? $raw);

    // 1) Remove leading qty (range or single)
    $rest = $norm;
    if (is_array($qty) && isset($qty['low'], $qty['high'])) {
        if (preg_match('/^(\s*)(\d+(?:\.\d+)?)\s*[\x{2013}-]\s*(\d+(?:\.\d+)?)/u', $rest, $m)) {
            $rest = (string)substr($rest, strlen($m[0]));
        }
    } elseif (is_numeric($qty)) {
        if (preg_match('/^(\s*)(\d+(?:\.\d+)?)/u', $rest, $m)) {
            $rest = (string)substr($rest, strlen($m[0]));
        }
    }

    // 2) Optional unit token directly after (strip only if unit detected)
    if ($unit !== null && preg_match('/^\s*([\p{L}\.]+)/u', $rest, $um)) {
        $tokUnit = rtrim($um[1], '.');
        $canon = function_exists('sitc_unit_alias_canonical') ? sitc_unit_alias_canonical($tokUnit) : null;
        if ($canon === $unit) {
            $rest = (string)substr($rest, strlen($um[0]));
        }
    }
    $rest = trim($rest);

    $lex = function_exists('sitc_ing_load_locale') ? sitc_ing_load_locale($locale) : [];
    $adjectives = $lex['adjectives'] ?? [];
    $purposePatterns = $lex['purpose_phrases'] ?? [];

    $noteSegments = [];
    $noteKeys = [];

    $normalizeWhitespace = static function (string $value): string {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    };

    $addNote = function (string $segment) use (&$noteSegments, &$noteKeys, $normalizeWhitespace) {
        $clean = $normalizeWhitespace($segment);
        if ($clean === '') {
            return;
        }
        if (function_exists('sitc_slugify_lite')) {
            $key = sitc_slugify_lite($clean);
            if ($key !== '' && isset($noteKeys[$key])) {
                return;
            }
            if ($key !== '') {
                $noteKeys[$key] = true;
            }
        }
        if (!in_array($clean, $noteSegments, true)) {
            $noteSegments[] = $clean;
        }
    };

    $matchesPatterns = static function (string $segment, array $patterns): bool {
        if (!$patterns) {
            return false;
        }
        if (!function_exists('sitc_slugify_lite') || !function_exists('sitc_ing_match_patterns')) {
            return false;
        }
        $slug = sitc_slugify_lite($segment);
        return $slug !== '' && sitc_ing_match_patterns($slug, $patterns);
    };

    $classifySegment = function (string $segment) use ($addNote, $matchesPatterns, $adjectives, $purposePatterns) {
        if ($segment === '') {
            return;
        }
        if ($matchesPatterns($segment, $purposePatterns) || $matchesPatterns($segment, $adjectives)) {
            $addNote($segment);
            return;
        }
        $addNote($segment);
    };

    // 3) Extract parentheses notes at end (allow multiple)
    while ($rest !== '' && preg_match('/^(.*)\(([^()]*)\)\s*$/u', $rest, $pm)) {
        $rest = trim($pm[1]);
        $payload = $pm[2];
        $parts = preg_split('/\s*[;,]\s*/u', $payload) ?: [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $classifySegment($part);
        }
    }

    // 4) Comma tail note (adjectives or purpose)
    while ($rest !== '') {
        $pos = function_exists('mb_strrpos') ? mb_strrpos($rest, ',') : strrpos($rest, ',');
        if ($pos === false) {
            break;
        }
        $tail = trim(function_exists('mb_substr') ? mb_substr($rest, $pos + 1) : substr($rest, $pos + 1));
        if ($tail === '') {
            break;
        }
        if (!$matchesPatterns($tail, $adjectives) && !$matchesPatterns($tail, $purposePatterns)) {
            break;
        }
        $classifySegment($tail);
        $rest = trim(function_exists('mb_substr') ? mb_substr($rest, 0, $pos) : substr($rest, 0, $pos));
    }

    // 5) TK at start
    if (preg_match('/^TK\b/u', $rest)) {
        $rest = trim(preg_replace('/^TK\b\s*/u', '', $rest) ?? $rest);
        $addNote('TK');
    }

    // 6) Hyphen suffix "-Stück" becomes note
    if (preg_match('/-\s*St(?:u|\\x{00FC}|ue)ck\b/u', $rest)) {
        $rest = trim(preg_replace('/-\s*St(?:u|\\x{00FC}|ue)ck\b/u', '', $rest) ?? $rest);
        $addNote('St' . "\u{00FC}" . 'ck');
    }

    // 7) If unit is clove, remove zehe/zehen tokens from item
    if ($unit === 'clove') {
        $rest = preg_replace('/\bzehe(n)?\b/iu', '', $rest) ?? $rest;
    }

    // 8) Purpose phrases within item (locale driven)
    if ($purposePatterns) {
        foreach ($purposePatterns as $pattern) {
            if (!is_string($pattern)) {
                continue;
            }
            $regex = sitc_ing_prepare_pattern($pattern);
            if (!$regex) {
                continue;
            }
            if (preg_match_all($regex, $rest, $matches)) {
                foreach ($matches[0] as $match) {
                    $classifySegment($match);
                }
                $rest = preg_replace($regex, ' ', $rest) ?? $rest;
            }
        }
    }

    $rest = $normalizeWhitespace($rest);

    // 9) Remove leading/trailing adjective tokens
    $tokens = $rest !== '' ? preg_split('/\s+/u', $rest, -1, PREG_SPLIT_NO_EMPTY) : [];
    if (!is_array($tokens)) {
        $tokens = [];
    }

    $matchesAdj = function (string $text) use ($matchesPatterns, $adjectives): bool {
        return $matchesPatterns($text, $adjectives);
    };

    $extractLeadingAdjectives = function (array &$tokens) use ($matchesAdj, $addNote) {
        while ($tokens) {
            $matched = false;
            $max = min(3, count($tokens));
            for ($window = $max; $window >= 1; $window--) {
                $candidateTokens = array_slice($tokens, 0, $window);
                $candidate = implode(' ', $candidateTokens);
                if ($matchesAdj($candidate)) {
                    $addNote($candidate);
                    $tokens = array_slice($tokens, $window);
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                break;
            }
        }
    };

    $extractTrailingAdjectives = function (array &$tokens) use ($matchesAdj, $addNote) {
        while ($tokens) {
            $matched = false;
            $max = min(3, count($tokens));
            for ($window = $max; $window >= 1; $window--) {
                $candidateTokens = array_slice($tokens, -$window);
                $candidate = implode(' ', $candidateTokens);
                if ($matchesAdj($candidate)) {
                    $addNote($candidate);
                    $tokens = array_slice($tokens, 0, count($tokens) - $window);
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                break;
            }
        }
    };

    $extractLeadingAdjectives($tokens);
    $extractTrailingAdjectives($tokens);

    $item = $tokens ? implode(' ', $tokens) : '';
    $item = $normalizeWhitespace($item);
    $item = trim($item, " \t.,;:-");

    $finalNote = null;
    if ($noteSegments) {
        $finalNote = implode(', ', $noteSegments);
    }

    return ['item' => $item, 'note' => $finalNote];
}
}

