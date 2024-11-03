<?php
namespace ForgePDF;
use Exception;
use ForgePDF\ForgePDFTab;
/*******************************************************************************
 * ForgePDF                                                                     *
 *                                                                              *
 * Version: 1.00                                                                *
 * Date:    2024-10-29                                                          *
 * Author:  Luan Costa                                                          *
 *******************************************************************************/

class ForgePDF extends ForgePDFTab
{
    const VERSION = "1.00";

    protected $f;

    /**
     * Opens or creates a PDF file for writing.
     *
     * This function initializes a writable file stream to output a PDF document.
     * It attempts to create the specified file (defaulting to "doc.pdf") and triggers
     * an error if the file cannot be created. After opening the file, it writes the
     * necessary PDF header information.
     *
     * @param string $file Name of the PDF file to be created or opened (default is "doc.pdf").
     * @throws Exception if the file cannot be created or opened.
     */
    public function Open($file = "doc.pdf")
    {
        $this->f = fopen($file, "wb");
        if (!$this->f) {
            $this->Error("Unable to create output file: " . $file);
        }
        $this->_putheader();
    }

    /**
     * Adds an image to the PDF at the specified position and dimensions.
     *
     * This function checks if the image file has already been loaded; if not,
     * it retrieves the image's dimensions and type, storing this data for future use.
     * If the image file is missing or invalid, an error is triggered.
     * After verification, the image is inserted into the PDF at the provided coordinates
     * and dimensions, or defaults if not specified.
     *
     * @param string $file Path to the image file.
     * @param float|null $x The x-coordinate for the image's position in the document (default is the current x position).
     * @param float|null $y The y-coordinate for the image's position in the document (default is the current y position).
     * @param float $w Width of the image in the document (default is auto-scaled based on the image's original aspect ratio).
     * @param float $h Height of the image in the document (default is auto-scaled based on the image's original aspect ratio).
     * @param string $type Image type (e.g., 'JPG', 'PNG'); if empty, it will be auto-detected based on the file extension.
     * @param string $link URL or internal link associated with the image (optional).
     *
     * @throws Exception if the image file is missing or not a valid image format.
     */
    public function Image(
        $file,
        $x = null,
        $y = null,
        $w = 0,
        $h = 0,
        $type = "",
        $link = ""
    ) {
        if (!isset($this->images[$file])) {
            $a = getimagesize($file);
            if ($a === false) {
                $this->Error("Missing or incorrect image file: " . $file);
            }
            $this->images[$file] = [
                "w" => $a[0],
                "h" => $a[1],
                "type" => $a[2],
                "i" => count($this->images) + 1,
            ];
        }
        parent::Image($file, $x, $y, $w, $h, $type, $link);
    }

    /**
     * Outputs the PDF document to the specified destination.
     *
     * This function finalizes the PDF document and sends it to the chosen output
     * destination (e.g., browser, file, or string). If the document has not been fully
     * generated (state less than 3), it closes the document to ensure all content is
     * properly written before outputting.
     *
     * @param string $dest Destination for the output: 'I' for inline in the browser,
     *                     'D' for download, 'F' for saving to a file, and 'S' for returning as a string.
     *                     Default is empty, which uses the class's standard output destination.
     * @param string $name Name of the output file. Relevant when $dest is 'D' or 'F'.
     * @param bool $isUTF8 Indicates if the file name is UTF-8 encoded. Default is false.
     */
    public function Outpute($dest = "", $name = "", $isUTF8 = false)
    {
        if ($this->state < 3) {
            $this->Close();
        }
    }

    protected function _endpage()
    {
        parent::_endpage();
        // Write page to file
        $this->_putstreamobject($this->pages[$this->page]);
        unset($this->pages[$this->page]);
    }

    protected function _getoffset()
    {
        return ftell($this->f);
    }

    protected function _put($s)
    {
        fwrite($this->f, $s . "\n", strlen($s) + 1);
    }

    protected function _putimages()
    {
        foreach (array_keys($this->images) as $file) {
            $type = $this->images[$file]["type"];
            if ($type == 1) {
                $info = $this->_parsegif($file);
            } elseif ($type == 2) {
                $info = $this->_parsejpg($file);
            } elseif ($type == 3) {
                $info = $this->_parsepng($file);
            } else {
                $this->Error("Unsupported image type: " . $file);
            }
            $this->_putimage($info);
            $this->images[$file]["n"] = $info["n"];
            unset($info);
        }
    }

    protected function _putpage($n)
    {
        $this->_newobj();
        $this->_put("<</Type /Page");
        $this->_put("/Parent 1 0 R");
        if (isset($this->PageInfo[$n]["size"])) {
            $this->_put(
                sprintf(
                    "/MediaBox [0 0 %.2F %.2F]",
                    $this->PageInfo[$n]["size"][0],
                    $this->PageInfo[$n]["size"][1]
                )
            );
        }
        if (isset($this->PageInfo[$n]["rotation"])) {
            $this->_put("/Rotate " . $this->PageInfo[$n]["rotation"]);
        }
        $this->_put("/Resources 2 0 R");
        if (!empty($this->PageLinks[$n])) {
            $s = "/Annots [";
            foreach ($this->PageLinks[$n] as $pl) {
                $s .= $pl[5] . " 0 R ";
            }
            $s .= "]";
            $this->_put($s);
        }
        if ($this->WithAlpha) {
            $this->_put(
                "/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>"
            );
        }
        $this->_put("/Contents " . (2 + $n) . " 0 R>>");
        $this->_put("endobj");
        $this->_putlinks($n);
    }

    protected function _putpages()
    {
        $nb = $this->page;
        $n = $this->n;
        for ($i = 1; $i <= $nb; $i++) {
            $this->PageInfo[$i]["n"] = ++$n;
            foreach ($this->PageLinks[$i] as &$pl) {
                $pl[5] = ++$n;
            }
            unset($pl);
        }
        for ($i = 1; $i <= $nb; $i++) {
            $this->_putpage($i);
        }
        // Pages root
        $this->_newobj(1);
        $this->_put("<</Type /Pages");
        $kids = "/Kids [";
        for ($i = 1; $i <= $nb; $i++) {
            $kids .= $this->PageInfo[$i]["n"] . " 0 R ";
        }
        $kids .= "]";
        $this->_put($kids);
        $this->_put("/Count " . $nb);
        if ($this->DefOrientation == "P") {
            $w = $this->DefPageSize[0];
            $h = $this->DefPageSize[1];
        } else {
            $w = $this->DefPageSize[1];
            $h = $this->DefPageSize[0];
        }
        $this->_put(
            sprintf("/MediaBox [0 0 %.2F %.2F]", $w * $this->k, $h * $this->k)
        );
        $this->_put(">>");
        $this->_put("endobj");
    }

    protected function _putheader()
    {
        if ($this->_getoffset() == 0) {
            parent::_putheader();
        }
    }

    protected function _enddoc()
    {
        parent::_enddoc();
        fclose($this->f);
    }

    public function convertText($text)
    {
        if (!$text) {
            return null;
        }
        return iconv("UTF-8", "windows-1252", $text);
    }
}
?>
