<?php

namespace App\Http\Controllers\Nursery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Nursery\ImportChildrenRequest;
use App\Services\ChildImportService;
use App\Services\Exceptions\ImportFormatException;
use App\Support\SpreadsheetReader;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChildImportController extends Controller
{
    public function __construct(
        private SpreadsheetReader $reader,
        private ChildImportService $import,
    ) {}

    public function form(): View
    {
        return view('nursery.children.import');
    }

    public function store(ImportChildrenRequest $request): RedirectResponse
    {
        $file = $request->file('file');

        try {
            $rows = $this->reader->rows($file->getRealPath(), $file->getClientOriginalExtension());
            $result = $this->import->import($rows);
        } catch (ImportFormatException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->with('error', 'تعذّر قراءة الملف. تأكد أنه ملف Excel أو CSV صالح.');
        }

        $message = "تم استيراد {$result['imported']} طفل.";
        if ($result['failed'] > 0) {
            $message .= " تم تخطّي {$result['failed']} صف بسبب أخطاء.";
        }

        return redirect()->route('nursery.children.index')
            ->with('status', $message)
            ->with('import_errors', $result['errors']);
    }

    public function template(): StreamedResponse
    {
        $headers = ['الاسم الأول', 'اسم العائلة', 'تاريخ الميلاد', 'النوع', 'الفصل', 'اسم ولي الأمر', 'هاتف ولي الأمر', 'صلة القرابة'];
        $example = ['يوسف', 'حسن', '2022-03-01', 'ذكر', '', 'منى', '01099887766', 'الأم'];

        return response()->streamDownload(function () use ($headers, $example) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel renders Arabic correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            fputcsv($out, $example);
            fclose($out);
        }, 'children-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
