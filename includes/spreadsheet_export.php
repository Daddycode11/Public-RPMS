<?php
/** Minimal OOXML workbook, with inline strings so user text never becomes a formula. */
function exportWorkbook(array $headers, array $rows, string $filename): void {
    $temp=tempnam(ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),'rpms_export_');
    if($temp===false) throw new RuntimeException('Export storage is unavailable.');
    try {
        $zip=new ZipArchive();
        if($zip->open($temp,ZipArchive::OVERWRITE)!==true) throw new RuntimeException('Could not create workbook.');
        $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="RPMS Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $xml='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" state="frozen"/></sheetView></sheetViews><sheetData>';
        foreach(array_merge([$headers],$rows) as $index=>$row) {
            $xml.='<row r="'.($index+1).'">';
            foreach(array_values($row) as $value) {
                if(is_int($value) || is_float($value)) $xml.='<c t="n"><v>'.$value.'</v></c>';
                else {
                    $text=preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u','',(string)$value);
                    $xml.='<c t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars($text,ENT_XML1|ENT_QUOTES,'UTF-8').'</t></is></c>';
                }
            }
            $xml.='</row>';
        }
        $zip->addFromString('xl/worksheets/sheet1.xml',$xml.'</sheetData></worksheet>');
        $zip->close();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.preg_replace('/[^a-zA-Z0-9_-]/','',$filename).'.xlsx"');
        header('Content-Length: '.filesize($temp));
        readfile($temp);
    } finally { if(is_file($temp)) unlink($temp); }
    exit;
}
