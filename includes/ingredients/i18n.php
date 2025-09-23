<?php

if (!function_exists('sitc_slugify_lite')) {
    function sitc_slugify_lite(string $value): string {
        $value = mb_strtolower($value, 'UTF-8');
        $value = strtr($value, [
            "\x{00E4}" => 'ae',
            "\x{00F6}" => 'oe',
            "\x{00FC}" => 'ue',
            "\x{00DF}" => 'ss',
            "\x{00E1}" => 'a',
            "\x{00E9}" => 'e',
            "\x{00ED}" => 'i',
            "\x{00F3}" => 'o',
            "\x{00FA}" => 'u',
            "\x{00E0}" => 'a',
            "\x{00E8}" => 'e',
            "\x{00F2}" => 'o',
            "\x{00F9}" => 'u',
        ]);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;
        $value = trim($value);
        return $value === '' ? '' : (preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}

if (!function_exists('sitc_ing_load_locale')) {
    function sitc_ing_load_locale(string $locale): array {
        static $cache = [];
        $normalizedLocale = strtolower(str_replace('_', '-', (string)$locale));
        if (preg_match('/^[a-z]{2}/', $normalizedLocale, $m)) {
            $localeKey = $m[0];
        } else {
            $localeKey = 'en';
        }
        if (isset($cache[$localeKey])) {
            return $cache[$localeKey];
        }

        $baseDir = __DIR__ . '/../i18n/ingredients';
        $defaults = [
            'countable_nouns'   => [],
            'uncountable_nouns' => [],
            'adjectives'        => [],
            'purpose_phrases'   => [],
            'fraction_words'    => [],
            'unit_aliases'      => [],
        ];

        $localesToTry = [$localeKey];
        if ($localeKey !== 'en') {
            $localesToTry[] = 'en';
        }

        $data = $defaults;
        foreach ($localesToTry as $candidate) {
            $file = $baseDir . '/' . $candidate . '.php';
            if (is_file($file)) {
                $loaded = include $file;
                if (is_array($loaded)) {
                    $data = sitc_ing_merge_lexicon($data, $loaded);
                }
            }
        }

        $uploadsLex = null;
        if (defined('WP_CONTENT_DIR')) {
            $uploadsPath = rtrim(WP_CONTENT_DIR, '/\\') . '/uploads/skipintro-recipe-crawler/i18n/' . $localeKey . '.php';
            if (is_file($uploadsPath)) {
                $loaded = include $uploadsPath;
                if (is_array($loaded)) {
                    $data = sitc_ing_merge_lexicon($data, $loaded);
                }
            }
        }

        return $cache[$localeKey] = $data;
    }
}

if (!function_exists('sitc_ing_merge_lexicon')) {
    function sitc_ing_merge_lexicon(array $base, array $overlay): array {
        foreach ($overlay as $key => $value) {
            if (!array_key_exists($key, $base)) {
                $base[$key] = $value;
                continue;
            }
            if (!is_array($value)) {
                $base[$key] = $value;
                continue;
            }
            if (!is_array($base[$key])) {
                $base[$key] = $value;
                continue;
            }
            $merged = $base[$key];
            foreach ($value as $item) {
                if (is_string($item)) {
                    if (!in_array($item, $merged, true)) {
                        $merged[] = $item;
                    }
                } elseif (is_array($item)) {
                    $merged[] = $item;
                }
            }
            $base[$key] = $merged;
        }
        return $base;
    }
}
if (!function_exists('sitc_ing_prepare_pattern')) {
    function sitc_ing_prepare_pattern(string $pattern): ?string {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return null;
        }
        if ($pattern[0] === '/' && substr($pattern, -1) === '/') {
            return $pattern;
        }
        $pattern = str_replace(' ', '\\s+', $pattern);
        return '/\\b(?:' . $pattern . ')\\b/iu';
    }
}

if (!function_exists('sitc_ing_match_patterns')) {
    function sitc_ing_match_patterns(string $haystack, array $patterns): bool {
        foreach ($patterns as $pattern) {
            if (!is_string($pattern)) {
                continue;
            }
            $regex = sitc_ing_prepare_pattern($pattern);
            if ($regex && preg_match($regex, $haystack)) {
                return true;
            }
        }
        return false;
    }
}
