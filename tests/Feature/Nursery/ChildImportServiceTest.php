<?php

use App\Models\Child;
use App\Models\Classroom;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ChildImportService;
use App\Services\Exceptions\ImportFormatException;
use App\Support\TenantContext;

beforeEach(function () {
    [$this->tenant, $this->owner] = createNurseryWithOwner();
    app(TenantContext::class)->set($this->tenant);
});

function importRows(array $rows): array
{
    return app(ChildImportService::class)->import($rows);
}

it('imports valid rows and auto-creates guardians, resolving classroom by name', function () {
    Classroom::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Sunflowers']);

    $rows = [
        ['الاسم الأول', 'اسم العائلة', 'تاريخ الميلاد', 'النوع', 'الفصل', 'اسم ولي الأمر', 'هاتف ولي الأمر', 'صلة القرابة'],
        ['يوسف', 'حسن', '2022-03-01', 'ذكر', 'Sunflowers', 'منى', '01099887766', 'الأم'],
        ['ليلى', 'حسن', '2021-06-15', 'أنثى', '', 'منى', '01099887766', 'الأم'],
    ];

    $result = importRows($rows);

    expect($result['imported'])->toBe(2);
    expect($result['failed'])->toBe(0);
    expect(Child::count())->toBe(2);
    expect(User::where('phone', '01099887766')->count())->toBe(1); // same guardian reused
    expect(Child::where('first_name', 'يوسف')->first()->classroom_id)->not->toBeNull();
});

it('converts an excel date serial number to a real date', function () {
    $rows = [
        ['الاسم الأول', 'تاريخ الميلاد', 'النوع', 'اسم ولي الأمر', 'هاتف ولي الأمر'],
        ['آدم', '44621', 'ذكر', 'سارة', '01088776655'], // 44621 = 2022-03-01
    ];

    $result = importRows($rows);

    expect($result['imported'])->toBe(1);
    expect(Child::first()->birth_date->toDateString())->toBe('2022-03-01');
});

it('reports invalid rows and still imports the valid ones', function () {
    $rows = [
        ['الاسم الأول', 'تاريخ الميلاد', 'النوع', 'اسم ولي الأمر', 'هاتف ولي الأمر'],
        ['', '2022-01-01', 'ذكر', 'أم', '0100'],                 // missing child name
        ['سالم', 'ليس تاريخا', 'ذكر', 'أم', '0101'],             // bad date
        ['نور', '2022-02-02', 'أنثى', 'أم', '0102'],             // valid
    ];

    $result = importRows($rows);

    expect($result['imported'])->toBe(1);
    expect($result['failed'])->toBe(2);
    expect(Child::count())->toBe(1);
});

it('throws when required columns are missing', function () {
    $rows = [
        ['الاسم الأول', 'النوع'], // no birth date / guardian columns
        ['يوسف', 'ذكر'],
    ];

    importRows($rows);
})->throws(ImportFormatException::class);

it('stops importing when the plan limit is reached', function () {
    $plan = SubscriptionPlan::factory()->create(['slug' => 'tiny', 'max_children' => 1]);
    Subscription::factory()->create(['tenant_id' => $this->tenant->id, 'subscription_plan_id' => $plan->id, 'status' => 'active']);

    $rows = [
        ['الاسم الأول', 'تاريخ الميلاد', 'النوع', 'اسم ولي الأمر', 'هاتف ولي الأمر'],
        ['طفل1', '2022-01-01', 'ذكر', 'أم', '0111'],
        ['طفل2', '2022-01-02', 'ذكر', 'أم', '0112'],
    ];

    $result = importRows($rows);

    expect($result['imported'])->toBe(1);
    expect($result['limit_reached'])->toBeTrue();
    expect(Child::count())->toBe(1);
});
