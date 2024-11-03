<?php
namespace ForgePDF;
use Exception;
use Fpdf\Fpdf;

/*******************************************************************************
 * ForgePDF                                                                     *
 *                                                                              *
 * Version: 1.00                                                                *
 * Date:    2024-10-29                                                          *
 * Author:  Luan Costa                                                          *
 *******************************************************************************/

class ForgePDFTab extends Fpdf
{
    // Atributos protegidos que controlam larguras e alinhamentos das colunas.
    protected $widths;
    protected $aligns;

    /**
     * Define a largura de cada coluna para a tabela.
     *
     * @param array $w Array com as larguras das colunas.
     */
    public function SetWidths($w)
    {
        $this->widths = $w;
    }

    /**
     * Define o alinhamento de cada coluna para a tabela.
     *
     * @param array $a Array com alinhamentos (L - Esquerda, C - Centro, R - Direita).
     */
    public function SetAligns($a)
    {
        $this->aligns = $a;
    }

    /**
     * Cria uma linha com células ajustadas automaticamente ao conteúdo.
     *
     * @param array $data Conteúdo de cada célula da linha.
     */
    public function Row($data)
    {
        $nb = 0;
        // Calcula o número máximo de linhas necessárias para cada célula.
        for ($i = 0; $i < count($data); $i++) {
            $nb = max($nb, $this->NbLines($this->widths[$i], $data[$i]));
        }
        $h = 5 * $nb;
        $this->CheckPageBreak($h);
        for ($i = 0; $i < count($data); $i++) {
            $w = $this->widths[$i];
            $a = isset($this->aligns[$i]) ? $this->aligns[$i] : "L";
            $x = $this->GetX();
            $y = $this->GetY();
            $this->Rect($x, $y, $w, $h); // Desenha o contorno da célula.
            $this->MultiCell($w, 5, $data[$i], 0, $a); // Insere o texto com alinhamento.
            $this->SetXY($x + $w, $y);
        }
        $this->Ln($h);
    }

    /**
     * Verifica se há necessidade de quebra de página, caso a altura da linha ultrapasse o limite da página.
     *
     * @param int $h Altura total da linha.
     */
    private function CheckPageBreak($h)
    {
        if ($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation); // Adiciona nova página, se necessário.
        }
    }

    /**
     * Calcula o número de linhas necessárias para uma célula com base na largura da célula e no texto.
     *
     * @param int $w Largura da célula.
     * @param string $txt Texto que será inserido na célula.
     * @return int Número de linhas necessárias.
     */
    private function NbLines($w, $txt)
    {
        if (!isset($this->CurrentFont)) {
            $this->Error("No font has been set"); // Gera erro se nenhuma fonte estiver definida.
        }
        $cw = $this->CurrentFont["cw"];
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $wmax = (($w - 2 * $this->cMargin) * 1000) / $this->FontSize;
        $s = str_replace("\r", "", (string) $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") {
            $nb--;
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c == " ") {
                $sep = $i;
            }
            $l += $cw[$c];
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) {
                        $i++;
                    }
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }
}
