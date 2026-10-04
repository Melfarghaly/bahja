<?php

namespace App\Services;

use App\Models\Classroom;
use App\Services\Exceptions\ImportFormatException;
use App\Services\Exceptions\PlanLimitException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Imports children (with a primary guardian) from a parsed spreadsheet.
 *
 * Header matching is tolerant of Arabic and English column names. Each row is
 * validated independently; valid rows are created, invalid rows are reported,
 * and the run stops gracefully if the subscription plan limit is reached.
 */
class ChildImportService
{
    /** Logical column => accepted header aliases (normalized). */
    private const ALIASES = [
        'first_name' => ['first_name', 'firstname', 'الاسم الاول', 'الاسم الأول', 'الاسم', 'اسم الطفل'],
        'last_name' => ['last_name', 'lastname', 'اسم العائلة', 'اللقب', 'العائلة'],
        'birth_date' => ['birth_date', 'birthdate', 'dob', 'تاريخ الميلاد', 'الميلاد', 'تاريخ الميلاد (yyyy-mm-dd)'],
        'gender' => ['gender', 'sex', 'النوع', 'الجنس'],
        'classroom' => ['classroom', 'class', 'الفصل', 'الفصل الدراسي'],
        'guardian_name' => ['guardian_name', 'guardian', 'اسم ولي الامر', 'اسم ولي الأمر', 'ولي الامر', 'ولي الأمر'],
        'guardian_phone' => ['guardian_phone', 'phone', 'mobile', 'هاتف ولي الامر', 'هاتف ولي الأمر', 'رقم ولي الامر', 'رقم ولي الأمر', 'الهاتف', 'رقم الهاتف'],
        'guardian_relationship' => ['relationship', 'guardian_relationship', 'صلة القرابة', 'القرابة', 'صلة ولي الامر', 'صلة ولي الأمر'],
    ];

    private const GENDERS = [
        'male' => 'male', 'm' => 'male', 'ذكر' => 'male', 'ولد' => 'male',
        'female' => 'female', 'f' => 'female', 'انثى' => 'female', 'أنثى' => 'female', 'بنت' => 'female',
    ];

    private const RELATIONSHIPS = [
        'mother' => 'mother', 'الام' => 'mother', 'الأم' => 'mother', 'ام' => 'mother', 'أم' => 'mother',
        'father' => 'father', 'الاب' => 'father', 'الأب' => 'father', 'اب' => 'father', 'أب' => 'father',
        'grandparent' => 'grandparent', 'جد' => 'grandparent', 'جدة' => 'grandparent', 'جد/جدة' => 'grandparent',
        'nanny' => 'nanny', 'مربية' => 'nanny',
        'driver' => 'driver', 'سائق' => 'driver',
        'other' => 'other', 'اخرى' => 'other', 'أخرى' => 'other',
    ];

    public function __construct(private ChildService $children) {}

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array{imported: int, failed: int, limit_reached: bool, errors: array<int, array{row: int, message: string}>}
     */
    public function import(array $rows): array
    {
        if (count($rows) < 2) {
            throw new ImportFormatException('الملف فارغ أو لا يحتوي على بيانات.');
        }

        $map = $this->mapHeaders($rows[0]);
        $this->assertRequiredColumns($map);

        $imported = 0;
        $errors = [];
        $limitReached = false;

        foreach (array_slice($rows, 1, null, true) as $index => $row) {
            $rowNumber = $index + 1; // 1-based, header is row 1

            $data = $this->extractRow($row, $map);

            if ($this->isEmptyRow($data)) {
                continue;
            }

            $validator = $this->validateRow($data);

            if ($validator->fails()) {
                $errors[] = ['row' => $rowNumber, 'message' => $validator->errors()->first()];

                continue;
            }

            try {
                $this->children->create($this->buildPayload($data));
                $imported++;
            } catch (PlanLimitException $e) {
                $limitReached = true;
                $errors[] = ['row' => $rowNumber, 'message' => 'تم بلوغ الحد الأقصى للأطفال في خطتك الحالية. تم إيقاف الاستيراد.'];
                break;
            }
        }

        return [
            'imported' => $imported,
            'failed' => count($errors),
            'limit_reached' => $limitReached,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<int, string>  $header
     * @return array<string, int>
     */
    private function mapHeaders(array $header): array
    {
        $map = [];

        foreach ($header as $index => $label) {
            $normalized = $this->normalize($label);

            foreach (self::ALIASES as $logical => $aliases) {
                if (in_array($normalized, $aliases, true) && ! isset($map[$logical])) {
                    $map[$logical] = $index;
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $map
     */
    private function assertRequiredColumns(array $map): void
    {
        $required = ['first_name', 'birth_date', 'gender', 'guardian_name', 'guardian_phone'];
        $missing = array_diff($required, array_keys($map));

        if ($missing !== []) {
            throw new ImportFormatException(
                'الملف ينقصه أعمدة مطلوبة: '.implode('، ', $missing).'. حمّل القالب واتبع تنسيقه.'
            );
        }
    }

    /**
     * @param  array<int, string>  $row
     * @param  array<string, int>  $map
     * @return array<string, string>
     */
    private function extractRow(array $row, array $map): array
    {
        $data = [];

        foreach ($map as $logical => $index) {
            $data[$logical] = isset($row[$index]) ? trim((string) $row[$index]) : '';
        }

        return $data;
    }

    /**
     * @param  array<string, string>  $data
     */
    private function isEmptyRow(array $data): bool
    {
        return trim(implode('', $data)) === '';
    }

    /**
     * @param  array<string, string>  $data
     */
    private function validateRow(array $data): \Illuminate\Validation\Validator
    {
        $normalized = [
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'birth_date' => $this->normalizeDate($data['birth_date'] ?? ''),
            'gender' => $this->normalizeGender($data['gender'] ?? ''),
            'guardian_name' => $data['guardian_name'] ?? '',
            'guardian_phone' => $data['guardian_phone'] ?? '',
        ];

        return Validator::make($normalized, [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'in:male,female'],
            'guardian_name' => ['required', 'string', 'max:120'],
            'guardian_phone' => ['required', 'string', 'max:20'],
        ], [
            'first_name.required' => 'اسم الطفل مطلوب.',
            'birth_date.required' => 'تاريخ الميلاد غير صالح.',
            'birth_date.date' => 'تاريخ الميلاد غير صالح.',
            'gender.in' => 'النوع يجب أن يكون ذكر أو أنثى.',
            'guardian_name.required' => 'اسم وليّ الأمر مطلوب.',
            'guardian_phone.required' => 'هاتف وليّ الأمر مطلوب.',
        ]);
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, mixed>
     */
    private function buildPayload(array $data): array
    {
        return [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] !== '' ? $data['last_name'] : '—',
            'birth_date' => $this->normalizeDate($data['birth_date']),
            'gender' => $this->normalizeGender($data['gender']),
            'classroom_id' => $this->resolveClassroomId($data['classroom'] ?? ''),
            'guardians' => [[
                'name' => $data['guardian_name'],
                'phone' => $data['guardian_phone'],
                'relationship' => $this->normalizeRelationship($data['guardian_relationship'] ?? ''),
                'role' => 'primary',
                'can_view_wall' => true,
                'can_pickup' => true,
                'is_payer' => true,
            ]],
        ];
    }

    private function resolveClassroomId(string $name): ?int
    {
        if (trim($name) === '') {
            return null;
        }

        return Classroom::where('name', trim($name))->value('id');
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->trim()->lower()->replace(['ـ'], '')->toString();
    }

    private function normalizeGender(string $value): string
    {
        return self::GENDERS[$this->normalize($value)] ?? '';
    }

    private function normalizeRelationship(string $value): string
    {
        return self::RELATIONSHIPS[$this->normalize($value)] ?? 'mother';
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        // Excel stores dates as a serial number (days since 1899-12-30).
        if (is_numeric($value)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return $value; // Leave invalid; the validator will flag it.
        }
    }
}
