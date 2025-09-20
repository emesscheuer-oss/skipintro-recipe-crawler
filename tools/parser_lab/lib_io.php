<?php

// Standalone helper library for parser_lab

function pl_read_file_utf8(string $path): string {
    if (!is_file($path)) {
        fwrite(STDERR, "File not found: $path\n");
        exit(2);
    }
    $data = file_get_contents($path);
    if ($data === false) {
        fwrite(STDERR, "Failed to read file: $path\n");
        exit(2);
    }
    // Normalize to UTF-8
    if (!mb_check_encoding($data, 'UTF-8')) {
        $enc = mb_detect_encoding($data, 'UTF-8, ISO-8859-1, ISO-8859-15, Windows-1252', true) ?: 'UTF-8';
        $data = mb_convert_encoding($data, 'UTF-8', $enc);
    }
    return $data;
}

function pl_ext(string $path): string {
    $e = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return $e;
}

