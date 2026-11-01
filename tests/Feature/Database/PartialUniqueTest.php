<?php

use App\Support\Database\PartialUnique;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('pu_enrollments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('child_id');
        $table->string('status');
    });
    PartialUnique::create('pu_enrollments', 'pu_enrollments_one_active', ['child_id'], 'status', 'active');
});

afterEach(fn () => Schema::dropIfExists('pu_enrollments'));

function puEnroll(int $child, string $status): void
{
    DB::table('pu_enrollments')->insert(['child_id' => $child, 'status' => $status]);
}

it('allows one active row per key and any number of others', function () {
    puEnroll(1, 'active');
    puEnroll(1, 'ended');
    puEnroll(1, 'ended');
    puEnroll(2, 'active');

    // PostgreSQL aborts the test's transaction on error, so try inside a savepoint
    // there; MySQL/MariaDB already committed it with the DDL above.
    $attempt = DB::getDriverName() === 'pgsql'
        ? fn () => DB::transaction(fn () => puEnroll(1, 'active'))
        : fn () => puEnroll(1, 'active');
    expect($attempt)->toThrow(UniqueConstraintViolationException::class);
});

it('frees the key once the active row changes status', function () {
    puEnroll(1, 'active');
    DB::table('pu_enrollments')->where('child_id', 1)->update(['status' => 'ended']);

    puEnroll(1, 'active');

    expect(DB::table('pu_enrollments')->where('status', 'active')->count())->toBe(1);
});

it('drops cleanly', function () {
    PartialUnique::drop('pu_enrollments', 'pu_enrollments_one_active', ['child_id']);
    puEnroll(1, 'active');
    puEnroll(1, 'active');

    expect(Schema::getColumnListing('pu_enrollments'))->toEqualCanonicalizing(['id', 'child_id', 'status']);
});
