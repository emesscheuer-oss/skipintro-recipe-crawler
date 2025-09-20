<?php
error_reporting(E_ALL); ini_set('display_errors','1');
require __DIR__ . '/../../includes/parser.php';

function run_one($path){
  $html = file_get_contents($path);
  if (!$html) { fwrite(STDERR, "Cannot read $path\n"); return; }
  $res = parseRecipe($html, 'file://'.basename($path));
  $ings = $res['recipe']['recipeIngredient'] ?? [];
  echo "-- " . basename($path) . "\n";
  foreach ($ings as $row) {
    if (is_array($row)) {
      $q = isset($row['qty']) ? (is_array($row['qty'])? json_encode($row['qty']) : $row['qty']) : '';
      printf("qty=%s | unit=%s | item=%s | note=%s | raw=%s\n",
        (string)$q, (string)($row['unit']??''), (string)($row['item']??''), (string)($row['note']??''), (string)($row['raw']??''));
    } else {
      echo "RAW: ".$row."\n";
    }
  }
}

run_one(__DIR__ . '/fixtures/butter_chicken.jsonld');
run_one(__DIR__ . '/fixtures/ente_chop_suey.html');
run_one(__DIR__ . '/fixtures/biryani.html');
run_one(__DIR__ . '/fixtures/haehnchen_biryani.html');

