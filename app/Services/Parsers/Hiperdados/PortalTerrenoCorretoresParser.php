<?php

namespace App\Services\Tenant;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Extrai a lista de corretores da resposta da rota terrenos_corretores_consultar.
 *
 * Colunas esperadas na tabela:
 *   (avatar) | Corretor | Telefone | Celular | Valor M2 | Área | Valor Total | R$ | Permuta Fin. | Permuta Fís. | Data Apresentação
 */
class PortalTerrenoCorretoresParser
{
    /**
     * @param  string  $strHtml  Conteúdo de data.strHtml da resposta JSON
     * @return array<int, array{nome: string, telefone: string, celular: string, valor_m2: string, area: string, valor_total: string, data_apresentacao: string}>
     */
    public function parse(string $strHtml): array
    {
        if ($strHtml === '') {
            return [];
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><div>'.$strHtml.'</div>');
        libxml_clear_errors();

        $xp = new DOMXPath($dom);

        // Localiza a tabela que tem o header "Corretor"
        $table = null;

        $tablesResult = $xp->query('//table');
        if ($tablesResult !== false) {
            foreach ($tablesResult as $t) {
                if (! $t instanceof DOMNode) {
                    continue;
                }
                $thsResult = $xp->query('.//th', $t);
                if ($thsResult === false) {
                    continue;
                }
                foreach ($thsResult as $th) {
                    if (! $th instanceof DOMNode) {
                        continue;
                    }
                    if (trim($th->textContent) === 'Corretor') {
                        $table = $t instanceof DOMElement ? $t : null;
                        break 2;
                    }
                }
            }
        }

        if ($table === null) {
            return [];
        }

        $corretores = [];

        $rowsResult = $xp->query('.//tbody/tr', $table);
        if ($rowsResult === false) {
            return [];
        }

        foreach ($rowsResult as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $cells = [];

            $tdsResult = $xp->query('.//td', $row);
            if ($tdsResult === false) {
                continue;
            }
            foreach ($tdsResult as $td) {
                if (! $td instanceof DOMNode) {
                    continue;
                }
                $cells[] = trim(preg_replace('/\s+/', ' ', $td->textContent) ?? '');
            }

            // Linha de total ou vazia — ignorar
            $nome = $cells[1] ?? '';

            if ($nome === '' || $nome === 'Total') {
                continue;
            }

            $corretores[] = [
                'nome' => $nome,
                'telefone' => $cells[2] ?? '',
                'celular' => $cells[3] ?? '',
                'valor_m2' => $cells[4] ?? '',
                'area' => $cells[5] ?? '',
                'valor_total' => $cells[6] ?? '',
                'data_apresentacao' => $cells[10] ?? '',
            ];
        }

        return $corretores;
    }
}
