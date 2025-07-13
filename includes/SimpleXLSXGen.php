<?php
/**
 * Class SimpleXLSXGen
 * Export data to MS Excel 2007 XLSX file
 * Author: sergey.shuchkin@gmail.com
 */

class SimpleXLSXGen {

    public $curSheet;
    protected $defaultFont;
    protected $defaultFontSize;
    protected $sheets;
    protected $template;
    protected $F, $F_KEYS; // fonts
    protected $XF, $XF_KEYS; // cellXfs
    protected $SI, $SI_KEYS; // shared strings
    const N_NORMAL = 0; // General
    const N_INT = 1; // 0
    const N_DEC = 2; // 0.00
    const N_PERCENT_INT = 9; // 0%
    const N_PRECENT_DEC = 10; // 0.00%
    const N_DATE = 14; // mm-dd-yy
    const N_TIME = 20; // h:mm
    const N_DATETIME = 22; // m/d/yy h:mm
    protected $template_path;

    public function __construct() {
        $this->curSheet = -1;
        $this->defaultFont = 'Calibri';
        $this->sheets = [ ['name' => 'Sheet1', 'rows' => [] ] ];
        $this->SI = [];        // sharedStrings index
        $this->SI_KEYS = []; //  & keys
        $this->F = [ ['name' => 'Calibri', 'sz' => '11'] ]; // fonts
        $this->F_KEYS = [['name' => 'Calibri', 'sz' => '11']]; // & keys
        $this->XF  = [ ['numFmtId' => 0, 'fontId' => 0, 'fillId' => 0, 'borderId' => 0, 'xfId' => 0] ]; // styles
        $this->XF_KEYS = ['N0'];
    }

    public static function fromArray( array $rows, $sheetName = null ) {
        $xlsx = new static();
        return $xlsx->addSheet( $rows, $sheetName );
    }

    public function addSheet( array $rows, $name = null ) {
        $this->curSheet++;
        if ($name) {
            $this->sheets[$this->curSheet]['name'] = $name;
        }
        $this->sheets[$this->curSheet]['rows'] = $rows;
        return $this;
    }

    public function downloadAs( $filename ) {
        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment;filename="' . $filename . '"' );
        header( 'Cache-Control: max-age=0' );
        $this->output();
        return $this;
    }

    public function output() {
        $fh = fopen( 'php://output', 'wb' );
        $this->write( $fh );
        fclose( $fh );
        return $this;
    }

    protected function write( $fh ) {
        // Create ZIP stream
        $zip = new \ZipArchive();
        $tmp = tempnam( sys_get_temp_dir(), 'xlsx' );
        $zip->open( $tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );

        $zip->addEmptyDir( '_rels/' );
        $zip->addEmptyDir( 'xl/_rels/' );
        $zip->addEmptyDir( 'xl/worksheets/' );

        // Add XML files
        $zip->addFromString( '_rels/.rels', $this->_rels() );
        $zip->addFromString( 'xl/_rels/workbook.xml.rels', $this->xl_rels() );
        $zip->addFromString( 'xl/workbook.xml', $this->workbook() );
        $zip->addFromString( '[Content_Types].xml', $this->contentTypes() );

        foreach ( $this->sheets as $k => $v ) {
            $zip->addFromString( 'xl/worksheets/sheet' . ($k + 1) . '.xml', $this->worksheet( $k ) );
        }

        $zip->close();
        readfile( $tmp );
        unlink( $tmp );
    }

    protected function _rels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    }

    protected function xl_rels() {
        $r = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ( $this->sheets as $k => $v ) {
            $r .= '<Relationship Id="rId'.($k+1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($k+1).'.xml"/>';
        }
        return $r . '</Relationships>';
    }

    protected function workbook() {
        $r = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets>';
        foreach ( $this->sheets as $k => $v ) {
            $r .= '<sheet name="' . htmlspecialchars( $v['name'] ) . '" sheetId="' . ( $k + 1) . '" r:id="rId' . ( $k + 1) . '"/>';
        }
        return $r . '</sheets></workbook>';
    }

    protected function contentTypes() {
        $r = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        foreach ( $this->sheets as $k => $v ) {
            $r .= '<Override PartName="/xl/worksheets/sheet'.($k+1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return $r . '</Types>';
    }

    protected function worksheet( $idx ) {
        $r = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ( $this->sheets[$idx]['rows'] as $i => $row ) {
            $r .= '<row r="'.($i+1).'">';
            foreach ( $row as $j => $cell ) {
                $r .= '<c r="' . $this->num2name($j) . ($i+1) . '">';
                if ( is_string($cell) ) {
                    $r .= '<v>' . htmlspecialchars($cell) . '</v>';
                } elseif ( is_int($cell) || is_float($cell) ) {
                    $r .= '<v>' . $cell . '</v>';
                } elseif ( is_bool($cell) ) {
                    $r .= '<v>' . (int) $cell . '</v>';
                } elseif ( $cell === null ) {
                    $r .= '<v></v>';
                }
                $r .= '</c>';
            }
            $r .= '</row>';
        }
        return $r . '</sheetData></worksheet>';
    }

    protected function num2name($num) {
        $numeric = ($num) % 26;
        $letter  = chr(65 + $numeric);
        $num2    = intval($num / 26);
        if ($num2 > 0) {
            return $this->num2name($num2 - 1) . $letter;
        }
        return $letter;
    }
}