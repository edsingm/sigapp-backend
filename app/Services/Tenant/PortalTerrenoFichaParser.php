<?php

namespace App\Services\Tenant;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Extrai os dados da ficha de um terreno (rota Terrenos/Visualizar/{id}) do
 * portal comproterreno.com.br. Retorna os valores como aparecem no HTML
 * (strings cruas); a normalização para o schema é responsabilidade do seeder.
 */
class PortalTerrenoFichaParser
{
    /**
     * @return array{
     *     endereco: string, complemento: string, bairro: string, cidade: string,
     *     uf: string, distrito: string, zoneamento: string, operacao_urbana: string,
     *     area_total: string, data_compra: string, status_portal: string,
     *     zona_regional: string, gestor: string,
     *     produtos: array<int, array<string, string>>
     * }
     */
    public function parse(string $html): array
    {
        $xp = $this->buildXPath($html);

        return [
            'endereco' => $this->inputValue($xp, 'TER_Endereco'),
            'complemento' => $this->inputValue($xp, 'TER_Complemento'),
            'bairro' => $this->inputValue($xp, 'TER_Bairro'),
            'cidade' => $this->inputValue($xp, 'TER_Cidade'),
            'uf' => $this->ufSigla($this->selectedText($xp, 'UF_ID')),
            'distrito' => $this->inputValue($xp, 'TER_Distrito'),
            'zoneamento' => $this->inputValue($xp, 'TER_Zoneamento'),
            'operacao_urbana' => $this->inputValue($xp, 'TER_OperacaoUrbana'),
            'area_total' => $this->inputValue($xp, 'TER_AreaTotal'),
            'data_compra' => $this->inputValue($xp, 'TER_DataCompra'),
            'status_portal' => $this->selectedText($xp, 'CAX_Status_ID'),
            'zona_regional' => $this->selectedText($xp, 'CAX_Zona_ID'),
            'gestor' => $this->selectedText($xp, 'CAX_Gestor_ID'),
            'produtos' => $this->parseProdutos($xp),
        ];
    }

    private function buildXPath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    private function inputValue(DOMXPath $xp, string $name): string
    {
        $node = $xp->query(sprintf('//input[@name=%s]', $this->quote($name)))->item(0);

        return $node instanceof DOMElement ? trim($node->getAttribute('value')) : '';
    }

    private function selectedText(DOMXPath $xp, string $name): string
    {
        $option = $xp->query(
            sprintf('//select[@name=%s]//option[@selected]', $this->quote($name))
        )->item(0);

        return $option instanceof DOMElement ? trim($option->textContent) : '';
    }

    /**
     * Converte "SP - São Paulo" -> "SP".
     */
    private function ufSigla(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $sigla = trim(explode('-', $text, 2)[0]);

        return mb_strlen($sigla) === 2 ? mb_strtoupper($sigla) : $sigla;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parseProdutos(DOMXPath $xp): array
    {
        $table = $this->findTableByHeader($xp, 'Tipo Unidade');

        if ($table === null) {
            return [];
        }

        $produtos = [];

        foreach ($xp->query('.//tbody/tr', $table) as $row) {
            $cells = [];

            foreach ($xp->query('.//td', $row) as $cell) {
                $cells[] = trim(preg_replace('/\s+/', ' ', $cell->textContent) ?? '');
            }

            // Cabeçalho: Tipo | Lançamento | Unidades | Permutas | Área Priv. | Preço M² | Preço | VGV | %
            if (count($cells) < 8 || $cells[0] === '') {
                continue;
            }

            $produtos[] = [
                'tipo_unidade' => $cells[0],
                'lancamento' => $cells[1],
                'unidades' => $cells[2],
                'permutas' => $cells[3],
                'area_privativa' => $cells[4],
                'preco_m2' => $cells[5],
                'preco' => $cells[6],
                'vgv_total' => $cells[7],
            ];
        }

        return $produtos;
    }

    private function findTableByHeader(DOMXPath $xp, string $headerText): ?DOMElement
    {
        foreach ($xp->query('//table') as $table) {
            foreach ($xp->query('.//th', $table) as $th) {
                if (str_contains(trim($th->textContent), $headerText)) {
                    return $table instanceof DOMElement ? $table : null;
                }
            }
        }

        return null;
    }

    private function quote(string $value): string
    {
        if (! str_contains($value, "'")) {
            return "'".$value."'";
        }

        return 'concat(\''.str_replace("'", "', \"'\", '", $value).'\')';
    }
}
