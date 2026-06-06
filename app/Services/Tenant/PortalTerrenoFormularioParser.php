<?php

namespace App\Services\Tenant;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Extrai os campos do formulário (hiperdados) de um terreno — resposta JSON da
 * rota terrenos_terrenos_formulario — cujo strHtml contém pares label→valor em
 * divs .col-md-3.
 *
 * @return array<string, string>  chave = label normalizado, valor = texto bruto
 */
class PortalTerrenoFormularioParser
{
    /**
     * @param  string  $strHtml  Conteúdo de data.strHtml da resposta JSON
     * @return array<string, string>
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
        $campos = [];

        foreach ($xp->query('//div[contains(@class,"col-md-")]') as $div) {
            if (! $div instanceof DOMElement) {
                continue;
            }

            $label = $xp->query('.//label', $div)->item(0);

            if ($label === null) {
                continue;
            }

            $chave = $this->normalizar($label->textContent);

            // O valor é o texto do div excluindo o texto do label.
            $textoDiv = $div->textContent;
            $textoLabel = $label->textContent;
            $valor = trim(str_replace($textoLabel, '', $textoDiv));

            if ($chave !== '') {
                $campos[$chave] = $valor;
            }
        }

        return $campos;
    }

    private function normalizar(string $texto): string
    {
        return trim(preg_replace('/\s+/', ' ', $texto) ?? $texto);
    }
}
