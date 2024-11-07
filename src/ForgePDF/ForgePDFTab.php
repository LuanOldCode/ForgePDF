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
    // Protected attributes controlling column widths and alignments.
    protected $widths;
    protected $aligns;

    /**
     * Sets the width of each column for the table.
     *
     * @param array $w Array with the column widths.
     */
    public function SetWidths($w)
    {
        $this->widths = $w;
    }

    /**
     * Sets the alignment of each column for the table.
     *
     * @param array $a Array with alignments (L - Left, C - Center, R - Right).
     */
    public function SetAligns($a)
    {
        $this->aligns = $a;
    }

    /**
     * Creates a row with cells adjusted automatically to the content.
     *
     * @param array $data Content of each cell in the row.
     */
    public function Row($data)
    {
        $nb = 0;
        // Calculates the maximum number of lines needed for each cell.
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
            $this->Rect($x, $y, $w, $h); // Draws the cell border.
            $this->MultiCell($w, 5, $data[$i], 0, $a); // Inserts text with alignment.
            $this->SetXY($x + $w, $y);
        }
        $this->Ln($h);
    }

    /**
     * Checks if a page break is needed, if the row height exceeds the page limit.
     *
     * @param int $h Total height of the row.
     */
    private function CheckPageBreak($h)
    {
        if ($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation); // Adds a new page if needed.
        }
    }

    /**
     * Calculates the number of lines needed for a cell based on cell width and text.
     *
     * @param int $w Cell width.
     * @param string $txt Text to be inserted in the cell.
     * @return int Number of lines needed.
     */
    private function NbLines($w, $txt)
    {
        if (!isset($this->CurrentFont)) {
            $this->Error("No font has been set"); // Triggers error if no font is set.
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
