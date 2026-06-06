<?php

declare(strict_types=1);

// TEMPORARIO: extrai campos (label -> name -> value) da ficha HTML

$file = $argv[1] ?? __DIR__.'/_ficha_Visualizar_33975.html';
$html = file_get_contents($file);

$dom = new DOMDocument;
libxml_use_internal_errors(true);
$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
libxml_clear_errors();
$xp = new DOMXPath($dom);

fwrite(STDOUT, "===== INPUTS =====\n");
foreach ($xp->query('//input') as $el) {
    $name = $el->getAttribute('name');
    if ($name === '') {
        continue;
    }
    $type = $el->getAttribute('type');
    $val = $el->getAttribute('value');
    fwrite(STDOUT, sprintf("  [%s] %s = %s\n", $type ?: 'text', $name, mb_substr($val, 0, 80)));
}

fwrite(STDOUT, "\n===== SELECTS (opcao selecionada) =====\n");
foreach ($xp->query('//select') as $el) {
    $name = $el->getAttribute('name');
    if ($name === '') {
        continue;
    }
    $sel = '';
    foreach ($xp->query('.//option[@selected]', $el) as $opt) {
        $sel = trim($opt->textContent).' (val='.$opt->getAttribute('value').')';
    }
    fwrite(STDOUT, sprintf("  %s = %s\n", $name, $sel));
}

fwrite(STDOUT, "\n===== TEXTAREAS =====\n");
foreach ($xp->query('//textarea') as $el) {
    $name = $el->getAttribute('name');
    if ($name === '') {
        continue;
    }
    fwrite(STDOUT, sprintf("  %s = %s\n", $name, mb_substr(trim($el->textContent), 0, 80)));
}

// Tabelas (produtos, proprietarios, documentos costumam vir em <table>)
fwrite(STDOUT, "\n===== TABELAS (cabecalhos) =====\n");
foreach ($xp->query('//table') as $i => $table) {
    $ths = [];
    foreach ($xp->query('.//th', $table) as $th) {
        $t = trim(preg_replace('/\s+/', ' ', $th->textContent));
        if ($t !== '') {
            $ths[] = $t;
        }
    }
    $id = $table->getAttribute('id');
    if ($ths !== []) {
        fwrite(STDOUT, sprintf("  table#%s [%d]: %s\n", $id ?: '?', $i, implode(' | ', array_slice($ths, 0, 12))));
    }
}
