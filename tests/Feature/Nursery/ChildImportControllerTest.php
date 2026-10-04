<?php

use App\Models\Child;
use Illuminate\Http\UploadedFile;

function uploadCsv(string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'imp').'.csv';
    file_put_contents($path, $content);

    return new UploadedFile($path, 'children.csv', 'text/csv', null, true);
}

it('downloads the CSV template', function () {
    [$tenant, $owner] = createNurseryWithOwner();

    $this->actingAs($owner)
        ->get(route('nursery.children.import.template'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('imports children from an uploaded CSV', function () {
    [$tenant, $owner] = createNurseryWithOwner();

    $csv = "الاسم الأول,اسم العائلة,تاريخ الميلاد,النوع,الفصل,اسم ولي الأمر,هاتف ولي الأمر,صلة القرابة\n"
        ."يوسف,حسن,2022-03-01,ذكر,,منى,01099887766,الأم\n";

    $this->actingAs($owner)
        ->post(route('nursery.children.import.store'), ['file' => uploadCsv($csv)])
        ->assertRedirect(route('nursery.children.index'))
        ->assertSessionHas('status');

    expect(Child::where('first_name', 'يوسف')->exists())->toBeTrue();
});

it('rejects a file that is missing required columns', function () {
    [$tenant, $owner] = createNurseryWithOwner();

    $csv = "الاسم الأول,النوع\nيوسف,ذكر\n";

    $this->actingAs($owner)
        ->post(route('nursery.children.import.store'), ['file' => uploadCsv($csv)])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Child::count())->toBe(0);
});
