<?php

use App\Support\SpreadsheetReader;

function makeXlsx(string $path): void
{
    $sst = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="3" uniqueCount="3">'
        .'<si><t>الاسم الأول</t></si><si><t>النوع</t></si><si><t>يوسف</t></si></sst>';

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheetData>'
        .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row>'
        .'<row r="2"><c r="A2" t="s"><v>2</v></c><c r="B2"><v>44621</v></c></row>'
        .'</sheetData></worksheet>';

    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('xl/sharedStrings.xml', $sst);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();
}

it('reads a csv file into rows', function () {
    $path = tempnam(sys_get_temp_dir(), 'imp').'.csv';
    file_put_contents($path, "\xEF\xBB\xBFالاسم الأول,النوع\nيوسف,ذكر\n");

    $rows = (new SpreadsheetReader())->rows($path, 'csv');

    expect($rows[0][0])->toBe('الاسم الأول');
    expect($rows[1])->toBe(['يوسف', 'ذكر']);

    @unlink($path);
});

it('reads a namespaced xlsx with shared strings', function () {
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    makeXlsx($path);

    $rows = (new SpreadsheetReader())->rows($path, 'xlsx');

    expect($rows[0])->toBe(['الاسم الأول', 'النوع']);
    expect($rows[1][0])->toBe('يوسف');
    expect($rows[1][1])->toBe('44621'); // raw Excel serial; converted later by the import service

    @unlink($path);
});
