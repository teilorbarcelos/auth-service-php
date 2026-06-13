<?php

declare(strict_types=1);

$cloverFile = __DIR__ . '/../coverage/clover.xml';

if (!file_exists($cloverFile)) {
    echo "Erro: Arquivo coverage/clover.xml não encontrado. Execute os testes com cobertura primeiro.\n";
    exit(1);
}

$xml = new SimpleXMLElement(file_get_contents($cloverFile));
$metrics = $xml->xpath('//project/metrics');

if (!$metrics) {
    echo "Erro: Métricas não encontradas no arquivo clover.xml.\n";
    exit(1);
}

$totalElements = (int) $metrics[0]['elements'];
$coveredElements = (int) $metrics[0]['coveredelements'];

echo "\n--- Detalhes de Cobertura ---\n";

$uncoveredFiles = 0;
foreach ($xml->xpath('//file') as $file) {
    $fileMetrics = $file->metrics;
    $statements = (int) $fileMetrics['statements'];
    $coveredStatements = (int) $fileMetrics['coveredstatements'];
    
    if ($coveredStatements < $statements) {
        $uncoveredFiles++;
        $fileName = (string) $file['name'];
        $shortName = str_replace(realpath(__DIR__ . '/../'), '', $fileName);
        
        $missingLines = [];
        foreach ($file->line as $line) {
            if ((string)$line['type'] === 'stmt' && (int)$line['count'] === 0) {
                $missingLines[] = (int)$line['num'];
            }
        }
        
        echo "File: $shortName\n";
        echo "  - Statements: $coveredStatements/$statements\n";
        if (!empty($missingLines)) {
            echo "  - Uncovered lines: " . implode(', ', $missingLines) . "\n";
        }
        echo "\n";
    }
}

if ($totalElements === 0) {
    echo "Aviso: Nenhum elemento encontrado para cobertura.\n";
    exit(0);
}

$percentage = ($coveredElements / $totalElements) * 100;
echo "Cobertura total: " . number_format($percentage, 2) . "%\n";

if ($percentage < 100) {
    echo "❌ ERRO: A cobertura de testes está abaixo de 100%!\n";
    exit(1);
}

echo "✅ Sucesso: Cobertura total alcançada (100%)!\n";
exit(0);
