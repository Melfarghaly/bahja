<?php

/*
|--------------------------------------------------------------------------
| Postman collection generator
|--------------------------------------------------------------------------
| Runs every documented API scenario (success and errors) against a seeded
| database with real Sanctum tokens, asserts the expected status, and saves
| the real responses as Postman examples:
|
|   php artisan test --group=postman
|
| Output: docs/api/Bahga-API.postman_collection.json (+ environment file).
| Excluded from the normal suite (phpunit.xml) because it writes files.
*/

use App\Models\Child;
use App\Models\ChildFeePlan;
use App\Models\Classroom;
use App\Models\FeeDiscount;
use App\Models\FeePlan;
use App\Models\PaymentIntent;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantEntitlementOverride;
use App\Models\TuitionInvoice;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\EntitlementService;
use App\Services\GuardianService;
use App\Services\Messaging\SmsGateway;
use App\Services\Tuition\TuitionBillingService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Support\Facades\Http;
use Laravel\Pennant\Feature;
use Tests\Postman\PostmanCollection;
use Tests\Support\FakeSmsGateway;

it('generates the Postman collection from real API responses', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-04 09:30:00'));
    configureGateways();
    Http::fake([
        'accept.paymob.test/v1/intention/' => Http::response(['id' => 'pi_test_5f2a', 'client_secret' => 'egy_csk_test_8c1d'], 201),
        'fawry.test/*' => Http::response(['type' => 'ChargeResponse', 'statusCode' => 200, 'referenceNumber' => '966512345']),
    ]);

    /* ------------------------------------------------------------ data */
    (new SubscriptionPlanSeeder)->run();
    $tenant = Tenant::factory()->create(['name' => 'حضانة البراعم', 'slug' => 'baraem', 'phone' => '0223456789']);
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'subscription_plan_id' => SubscriptionPlan::where('slug', 'pro')->value('id'), 'status' => 'active']);
    app(TenantContext::class)->set($tenant);

    $owner = User::factory()->create(['name' => 'سامية المديرة', 'email' => 'owner@bahga.test', 'phone' => '01000000001']);
    $tenant->members()->attach($owner->id, ['member_type' => 'owner', 'status' => 'active']);
    $teacher = attachTeacher($tenant, User::factory()->create(['name' => 'مس هالة', 'email' => 'teacher@bahga.test', 'phone' => '01000000004']));
    $mother = User::factory()->create(['name' => 'منى عبد الله', 'email' => 'mother@bahga.test', 'phone' => '01000000002']);
    $driver = User::factory()->create(['name' => 'عم سيد السائق', 'phone' => '01000000003']);
    $father = User::factory()->create(['name' => 'أحمد محمود', 'phone' => '01000000005']);

    $classroom = Classroom::factory()->create(['tenant_id' => $tenant->id, 'name' => 'عباد الشمس', 'capacity' => 20]);
    $yousef = Child::factory()->create(['tenant_id' => $tenant->id, 'first_name' => 'يوسف', 'last_name' => 'محمود', 'gender' => 'male', 'birth_date' => '2022-03-14', 'classroom_id' => $classroom->id, 'medical_notes' => ['allergies' => ['فول سوداني']]]);
    $layla = Child::factory()->create(['tenant_id' => $tenant->id, 'first_name' => 'ليلى', 'last_name' => 'محمود', 'gender' => 'female', 'birth_date' => '2023-06-02', 'classroom_id' => $classroom->id]);
    $saleem = Child::factory()->create(['tenant_id' => $tenant->id, 'first_name' => 'سليم', 'last_name' => 'حسن', 'gender' => 'male', 'birth_date' => '2022-11-20']);

    $guardians = app(GuardianService::class);
    foreach ([$yousef, $layla] as $child) {
        $guardians->attach($child, $mother, ['relationship' => 'mother', 'role' => 'primary', 'can_pickup' => true, 'is_payer' => true]);
        $guardians->attach($child, $driver, ['relationship' => 'driver', 'role' => 'pickup_authorized', 'can_view_wall' => false, 'can_pickup' => true]);
    }
    $guardians->attach($yousef, $father, ['relationship' => 'father', 'role' => 'viewer', 'custody_flag' => 'blocked']);

    Feature::for($tenant)->activate('bahga-pay');
    FeeDiscount::create(['name' => 'خصم الإخوة', 'type' => 'sibling', 'value_type' => 'percent', 'value' => 1_000, 'is_active' => true]);
    $monthly = FeePlan::create(['name' => 'المصروفات الشهرية', 'amount_piasters' => 185_000, 'frequency' => 'monthly', 'is_active' => true]);
    foreach ([$yousef, $layla] as $child) {
        ChildFeePlan::create(['child_id' => $child->id, 'fee_plan_id' => $monthly->id, 'starts_on' => '2026-10-01']);
    }
    app(TuitionBillingService::class)->generate($tenant, CarbonImmutable::parse('2026-10-01'), $owner);
    $invoice = TuitionInvoice::where('payer_id', $mother->id)->sole();
    app(AttendanceService::class)->checkIn($layla, $teacher);

    $foreign = Tenant::factory()->create(['name' => 'حضانة أخرى']);
    $foreignChild = Child::factory()->create(['tenant_id' => $foreign->id]);
    $stranger = User::factory()->create(['name' => 'شخص غير مخوَّل', 'phone' => '01099999999']);
    app(TenantContext::class)->forget();

    $tokens = [
        'owner' => $owner->createToken('postman')->plainTextToken,
        'teacher' => $teacher->createToken('postman')->plainTextToken,
        'guardian' => $mother->createToken('postman')->plainTextToken,
        'blocked_guardian' => $father->createToken('postman')->plainTextToken,
    ];
    $vars = [
        'tenant_id' => $tenant->id, 'child_id' => $yousef->id, 'classroom_id' => $classroom->id,
        'guardian_id' => $driver->id, 'collector_id' => $mother->id, 'invoice_id' => $invoice->id,
        'plan_id' => SubscriptionPlan::where('slug', 'advanced')->value('id'),
    ];

    /* ------------------------------------------------------ collection */
    $c = new PostmanCollection('Bahga API v1 — بهجة', file_get_contents(__DIR__.'/collection-description.md'), [
        ['key' => 'base_url', 'value' => 'http://localhost:8000/api', 'description' => 'API root (no trailing slash)'],
        ['key' => 'lang', 'value' => 'ar', 'description' => 'Response language: ar | en'],
        ['key' => 'owner_login', 'value' => 'owner@bahga.test'],
        ['key' => 'teacher_login', 'value' => 'teacher@bahga.test'],
        ['key' => 'guardian_login', 'value' => '01000000002', 'description' => 'Guardians usually sign in with their phone'],
        ['key' => 'password', 'value' => 'password', 'description' => 'Demo seeder password'],
        ['key' => 'owner_token', 'value' => '', 'description' => 'Set by "Login — owner"'],
        ['key' => 'teacher_token', 'value' => '', 'description' => 'Set by "Login — teacher"'],
        ['key' => 'guardian_token', 'value' => '', 'description' => 'Set by "Login — guardian"'],
        ['key' => 'tenant_id', 'value' => '1', 'description' => 'Set by "GET /me" (first nursery)'],
        ['key' => 'child_id', 'value' => '1', 'description' => 'Set by "List children" / "My children"'],
        ['key' => 'classroom_id', 'value' => '1'],
        ['key' => 'guardian_id', 'value' => '', 'description' => 'Set by "Get child" (a non-primary guardian)'],
        ['key' => 'collector_id', 'value' => '', 'description' => 'Set by "Get child" (an authorized guardian)'],
        ['key' => 'invoice_id', 'value' => '1', 'description' => 'Set by "My invoices"'],
        ['key' => 'plan_id', 'value' => '4', 'description' => 'A plan id from "Plans"'],
    ]);

    $run = function (string $key, string $name, int $status, array $o = []) use ($c, &$specs, $tokens, &$vars) {
        $spec = $specs[$key];
        $as = array_key_exists('as', $o) ? $o['as'] : $spec['auth'];
        $tenantHeader = $o['tenant'] ?? ($spec['tenant'] ?? true);
        $query = $o['query'] ?? ($spec['query'] ?? []);
        $bodyTemplate = array_key_exists('body', $o) ? $o['body'] : ($spec['body'] ?? null);
        $v = array_merge($vars, $o['vars'] ?? []);

        $fill = function ($value) use (&$fill, $v) {
            if (is_array($value)) {
                return array_map($fill, $value);
            }
            if (is_string($value) && preg_match('/^\{\{(\w+)\}\}$/', $value, $m)) {
                return $v[$m[1]];
            }

            return is_string($value) ? preg_replace_callback('/\{\{(\w+)\}\}/', fn ($m) => $v[$m[1]], $value) : $value;
        };

        $headers = ['Accept' => 'application/json', 'Accept-Language' => $o['lang'] ?? 'ar'];
        if ($tenantHeader) {
            $headers['X-Tenant-Id'] = (string) ($o['tenant_id'] ?? $v['tenant_id']);
        }
        if ($as !== null) {
            $headers['Authorization'] = 'Bearer '.($o['token'] ?? $tokens[$as]);
        }

        app('auth')->forgetGuards();
        $this->flushHeaders();
        $uri = '/api/'.$fill($spec['path']).($query ? '?'.http_build_query($fill($query)) : '');
        $response = $this->withHeaders($headers)->json($spec['method'], $uri, $bodyTemplate === null ? [] : $fill($bodyTemplate));

        expect($response->status())->toBe($status, "[{$key}] {$name}: ".$response->getContent());

        $content = $response->getContent();
        $pretty = $content === '' ? '' : json_encode(json_decode($content, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $responseHeaders = array_filter([
            'Content-Type' => $content === '' ? null : 'application/json',
            'Content-Language' => $response->headers->get('Content-Language'),
            'Retry-After' => $response->headers->get('Retry-After'),
        ]);

        $c->example($key, [
            'name' => "{$status} — {$name}",
            'auth' => $as,
            'tenant' => $tenantHeader,
            'query' => $query,
            'body' => $bodyTemplate,
            'status' => $status,
            'headers' => $responseHeaders,
            'response' => $pretty,
        ]);

        return $response;
    };

    $specs = require __DIR__.'/endpoints.php';
    foreach (require __DIR__.'/folders.php' as $folder => $description) {
        $c->folder($folder, $description);
    }
    foreach ($specs as $key => $spec) {
        $c->request($key, $spec);
    }

    /* ------------------------------------------------- 1. Authentication */
    $run('login_owner', 'تسجيل دخول بالبريد', 201, ['body' => ['login' => 'owner@bahga.test', 'password' => 'password', 'device_name' => 'owner-iphone']]);
    $run('login_owner', 'بيانات دخول خاطئة', 422, ['body' => ['login' => 'owner@bahga.test', 'password' => 'wrong', 'device_name' => 'owner-iphone']]);
    $run('login_owner', 'حقول ناقصة', 422, ['body' => ['login' => 'owner@bahga.test']]);
    $run('login_teacher', 'تسجيل دخول المعلمة', 201, ['body' => ['login' => 'teacher@bahga.test', 'password' => 'password', 'device_name' => 'teacher-tablet']]);
    $run('login_guardian', 'تسجيل دخول برقم الهاتف', 201, ['body' => ['login' => '01000000002', 'password' => 'password', 'device_name' => 'mona-android']]);
    $run('login_guardian', 'محاولة سادسة خلال دقيقة (مسموح)', 422, ['body' => ['login' => '01000000002', 'password' => 'x', 'device_name' => 'mona-android']]);
    $run('login_guardian', 'محاولات كثيرة', 429, ['body' => ['login' => '01000000002', 'password' => 'x', 'device_name' => 'mona-android']]);

    $sms = new FakeSmsGateway;
    $this->app->instance(SmsGateway::class, $sms);
    $run('otp_request', 'إرسال رمز الدخول', 202, ['body' => ['phone' => '+201000000002']]);
    $run('otp_request', 'طلب رمز جديد قبل 60 ثانية', 422, ['body' => ['phone' => '01000000002']]);
    $run('otp_request', 'رقم غير صحيح', 422, ['body' => ['phone' => '12345']]);
    preg_match('/\d{6}/', $sms->sent[0]['message'], $m);
    $run('otp_verify', 'رمز خاطئ', 422, ['body' => ['phone' => '01000000002', 'code' => '000000', 'device_name' => 'mona-android']]);
    $run('otp_verify', 'رمز صحيح → توكن', 201, ['body' => ['phone' => '01000000002', 'code' => $m[0], 'device_name' => 'mona-android']]);
    $run('otp_verify', 'نفس الرمز مرة ثانية', 422, ['body' => ['phone' => '01000000002', 'code' => $m[0], 'device_name' => 'mona-android']]);

    $run('me', 'المالكة', 200, ['as' => 'owner']);
    $run('me', 'المعلمة', 200, ['as' => 'teacher']);
    $run('me', 'وليّ الأمر', 200, ['as' => 'guardian']);
    $run('me', 'بدون توكن', 401, ['as' => null]);

    $run('me_update', 'تعديل الاسم والبريد', 200);
    $run('me_update', 'بريد مستخدم من قبل', 422, ['body' => ['email' => 'owner@bahga.test']]);

    $throwaway = $owner->createToken('to-revoke')->plainTextToken;
    $run('logout', 'تسجيل الخروج من هذا الجهاز', 204, ['token' => $throwaway]);
    $run('logout', 'توكن غير صالح', 401, ['token' => $throwaway]);

    /* ----------------------------------------------- 2. Staff: classrooms */
    $run('classrooms', 'الفصول', 200);
    $run('classrooms', 'وليّ أمر يحاول الوصول', 403, ['as' => 'guardian']);

    /* -------------------------------------------------- 3. Staff: children */
    $run('children_index', 'أطفال فصل', 200);
    $run('children_index', 'بحث بالاسم', 200, ['query' => ['q' => 'يوسف']]);
    $run('children_index', 'فصل من حضانة أخرى', 422, ['query' => ['classroom_id' => '999999']]);
    $run('children_index', 'وليّ أمر يحاول الوصول', 403, ['as' => 'guardian', 'query' => []]);
    $run('children_show', 'بيانات الطفل للموظفين', 200);
    $run('children_show', 'طفل غير موجود أو من حضانة أخرى', 404, ['vars' => ['child_id' => $foreignChild->id]]);
    $run('children_show', 'وليّ أمر يحاول الوصول', 403, ['as' => 'guardian']);

    $run('children_store', 'تسجيل طفل مع وليّ أمر جديد بالهاتف', 201);
    $run('children_store', 'بيانات ناقصة', 422, ['body' => ['first_name' => 'آدم', 'gender' => 'unknown']]);
    $run('children_store', 'المعلمة لا تسجّل أطفالاً', 403, ['as' => 'teacher']);
    $limit = TenantEntitlementOverride::factory()->create(['tenant_id' => $tenant->id, 'key' => 'children', 'value' => Child::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count()]);
    app(EntitlementService::class)->forget($tenant);
    $run('children_store', 'تجاوز حد الأطفال في الخطة', 402);
    $limit->delete();
    app(EntitlementService::class)->forget($tenant);

    $run('children_update', 'تعديل بيانات طفل', 200);
    $run('children_update', 'قيمة غير صحيحة', 422, ['body' => ['birth_date' => '2030-01-01']]);
    $run('children_update', 'المعلمة لا تعدّل', 403, ['as' => 'teacher']);

    $run('guardians_store', 'ربط وليّ أمر بصلاحيات', 200, ['vars' => ['guardian_id' => $stranger->id]]);
    $run('guardians_store', 'صلة قرابة غير صحيحة', 422, ['body' => ['user_id' => '{{guardian_id}}', 'relationship' => 'uncle', 'role' => 'primary']]);
    $run('guardians_store', 'المعلمة لا تغيّر صلاحيات الاستلام', 403, ['as' => 'teacher']);
    $run('guardians_destroy', 'فصل وليّ أمر', 200, ['vars' => ['guardian_id' => $stranger->id]]);
    $run('guardians_destroy', 'طفل من حضانة أخرى', 404, ['vars' => ['child_id' => $foreignChild->id]]);

    /* ------------------------------------------------ 4. Staff: attendance */
    $run('attendance_index', 'كشف اليوم', 200);
    $run('attendance_index', 'تاريخ في المستقبل', 422, ['query' => ['date' => '2026-12-31']]);
    $run('attendance_index', 'وليّ أمر يحاول الوصول', 403, ['as' => 'guardian', 'query' => []]);
    $run('check_in', 'تسجيل حضور بالـ QR', 200);
    $run('check_in', 'طفل من حضانة أخرى', 422, ['body' => ['child_id' => $foreignChild->id, 'method' => 'qr']]);
    $run('check_in', 'وليّ أمر لا يسجّل حضوراً', 403, ['as' => 'guardian']);
    $run('check_out', 'انصراف مع مستلم مخوَّل', 200);
    $run('check_out', 'مستلم غير مخوَّل', 403, ['body' => ['child_id' => '{{child_id}}', 'collector_id' => $stranger->id]]);
    $run('check_out', 'وصيّ محظور بحكم حضانة', 403, ['body' => ['child_id' => '{{child_id}}', 'collector_id' => $father->id]]);
    $run('check_out', 'بيانات ناقصة', 422, ['body' => ['child_id' => '{{child_id}}']]);

    /* ------------------------------------------------- 5. Guardian: wards */
    $run('wards_index', 'أطفالي', 200);
    $run('wards_index', 'وصيّ محظور لا يرى الطفل', 200, ['as' => 'blocked_guardian']);
    $run('wards_show', 'تفاصيل طفلي', 200);
    $run('wards_show', 'ليس من أطفالي', 404, ['vars' => ['child_id' => $saleem->id]]);
    $run('wards_attendance', 'سجل الحضور', 200);
    $run('wards_attendance', 'نطاق تاريخ غير صحيح', 422, ['query' => ['from' => '2026-10-01', 'to' => '2026-09-01']]);
    $run('wards_attendance', 'وصيّ محظور', 404, ['as' => 'blocked_guardian', 'query' => []]);
    $run('wards_notifications', 'إيقاف رسائل SMS', 200);
    $run('wards_notifications', 'قيمة ناقصة', 422, ['body' => []]);

    /* ------------------------------------------------ 6. Guardian: invoices */
    $run('payment_methods', 'طرق الدفع المتاحة', 200);
    $run('invoices_index', 'فواتيري', 200);
    Feature::for($tenant)->deactivate('bahga-pay');
    $run('invoices_index', 'بهجة باي غير مفعّلة للحضانة', 404);
    Feature::for($tenant)->activate('bahga-pay');
    $run('invoices_show', 'تفاصيل الفاتورة', 200);
    $run('invoices_show', 'فاتورة لست دافعها', 404, ['as' => 'blocked_guardian']);

    $run('checkout', 'فوري: كود دفع', 201, ['body' => ['gateway' => 'fawry']]);
    $run('checkout', 'فوري: نفس الكود عند التكرار', 200, ['body' => ['gateway' => 'fawry']]);
    $run('checkout', 'بطاقة/محفظة: رابط Paymob', 201);
    $run('checkout', 'طريقة دفع غير معروفة', 422, ['body' => ['gateway' => 'visa']]);
    $override = TenantEntitlementOverride::factory()->create(['tenant_id' => $tenant->id, 'key' => 'auto_collection', 'value' => false]);
    app(EntitlementService::class)->forget($tenant);
    $run('checkout', 'الخطة لا تشمل الدفع الإلكتروني', 402);
    $override->delete();
    app(EntitlementService::class)->forget($tenant);

    /* ------------------------------------------------------- 7. Webhooks */
    app(TenantContext::class)->set($tenant);
    $paymob = PaymentIntent::where('gateway', 'paymob')->sole();
    $fawry = PaymentIntent::where('gateway', 'fawry')->sole();
    app(TenantContext::class)->forget();
    [$callback, $hmac] = paymobCallback($paymob->merchant_reference, $paymob->amount_piasters, id: 192837465);
    $run('webhook_paymob', 'دفعة ناجحة موقّعة', 200, ['body' => $callback, 'query' => ['hmac' => $hmac]]);
    $run('webhook_paymob', 'نفس الحدث مرة أخرى', 200, ['body' => $callback, 'query' => ['hmac' => $hmac]]);
    $run('webhook_paymob', 'توقيع غير صحيح', 401, ['body' => $callback, 'query' => ['hmac' => str_repeat('0', 128)]]);
    $run('webhook_fawry', 'إشعار دفع موقّع', 200, ['body' => fawryNotification($fawry->merchant_reference, $fawry->amount()->toPounds())]);
    $run('webhook_fawry', 'توقيع غير صحيح', 401, ['body' => ['merchantRefNumber' => $fawry->merchant_reference, 'orderStatus' => 'PAID', 'paymentAmount' => '1.00', 'orderAmount' => '1.00', 'messageSignature' => 'forged']]);
    $run('checkout', 'لا يوجد مبلغ مستحق', 422);

    /* ---------------------------------------------------- 8. Subscription */
    $run('subscription', 'الاشتراك والاستهلاك', 200);
    $run('subscription', 'المعلمة لا ترى الاشتراك', 403, ['as' => 'teacher']);
    $run('plans', 'الخطط المتاحة', 200);
    $run('change_plan', 'الترقية إلى Advanced', 200);
    $run('change_plan', 'التخفيض لخطة أقل من الاستهلاك', 402, ['body' => ['subscription_plan_id' => SubscriptionPlan::factory()->create(['slug' => 'tiny', 'name' => 'Tiny', 'max_children' => 1])->id]]);
    $run('change_plan', 'خطة غير موجودة', 422, ['body' => ['subscription_plan_id' => 999]]);

    /* -------------------------------------------- 9. Errors (reference) */
    $run('error_route', 'مسار غير موجود', 404, ['as' => null, 'tenant' => false]);
    $run('error_language', 'نفس الخطأ بالإنجليزية', 422, ['lang' => 'en']);

    /* ---------------------------------------------------------- write */
    $dir = base_path('docs/api');
    is_dir($dir) || mkdir($dir, 0755, true);
    $c->write($dir.'/Bahga-API.postman_collection.json');
    file_put_contents($dir.'/Bahga-API-local.postman_environment.json', json_encode([
        'id' => 'b4a6c1d2-bahga-local-env',
        'name' => 'Bahga — local (demo seeder)',
        'values' => [
            ['key' => 'base_url', 'value' => 'http://localhost:8000/api', 'type' => 'default', 'enabled' => true],
            ['key' => 'lang', 'value' => 'ar', 'type' => 'default', 'enabled' => true],
            ['key' => 'owner_login', 'value' => 'owner@bahga.test', 'type' => 'default', 'enabled' => true],
            ['key' => 'teacher_login', 'value' => 'teacher@bahga.test', 'type' => 'default', 'enabled' => true],
            ['key' => 'guardian_login', 'value' => '01000000002', 'type' => 'default', 'enabled' => true],
            ['key' => 'password', 'value' => 'password', 'type' => 'secret', 'enabled' => true],
        ],
        '_postman_variable_scope' => 'environment',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

    expect($c->stats()['requests'])->toBe(count($specs));
})->group('postman');
