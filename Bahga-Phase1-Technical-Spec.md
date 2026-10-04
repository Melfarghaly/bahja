# بهجة (Bahga) — التحليل التقني للمرحلة الأولى

**الوثيقة موجّهة للمبرمجين** · الحزمة: Laravel (PHP) + PostgreSQL · النطاق: قاعدة البيانات + منصة الويب لإدارة الحضانات والاشتراكات

> هذه الوثيقة تترجم التحليل التجاري إلى مخطط تنفيذ هندسي. القاعدة الذهبية المتفق عليها: **تُصمَّم العلاقتان الجوهريتان (طفل↔وصيّ، معلمة↔حضانة) كعلاقتَي Many-to-Many في قاعدة البيانات منذ اليوم الأول**، حتى لو ظهرت مبسّطة في الواجهة. تغيير ذلك لاحقاً مكلف جداً.

---

## 1. نطاق المرحلة الأولى (Scope)

### داخل النطاق (In Scope)
- نموذج بيانات كامل يدعم العلاقتين M:N (لكن واجهة مبسّطة: وصيّ أساسي + مشاهدة الأشقّاء، معلمة بحضانة واحدة).
- إدارة الحضانات (Tenants): التسجيل، الإعداد الأساسي، الفروع (اختياري لاحقاً).
- إدارة المستخدمين والأدوار والصلاحيات (RBAC على مستوى الزوج user×child و user×nursery).
- إدارة الأطفال والأوصياء (CRUD + ربط الأوصياء + علم can_pickup).
- إدارة المعلمات وربطها بالحضانة.
- الحضور/الانصراف (Check-in/out) عبر QR + التحقق الأساسي من المخوَّل بالاستلام.
- **الاشتراكات (Subscriptions)**: الخطط (مجاني/750/1900/3900)، الفوترة، حالة الاشتراك، الحدود (Quotas).
- لوحة تحكم ويب (Admin/Owner) + لوحة وصول لولي الأمر والمعلمة (Web أولاً).

### خارج النطاق (Phase 2+)
تعدد الأوصياء بالصلاحيات الكاملة وحالات الحضانة القانونية، معلمة متعددة الحضانات وتبديل السياق، نجوم الشكر ولوحة الترتيب، الحافلات، نظام الطفل التائه الكامل، الهوية المهنية المنقولة، تقارير AI، White-Label، تطبيق الموبايل.

> ملاحظة: ما نخرجه من واجهة المرحلة 1 لا نخرجه من **مخطط قاعدة البيانات**. الجداول الوسيطة وأعمدة الصلاحيات تُبنى الآن وتُستخدم لاحقاً.

---

## 2. المعمارية العامة (Architecture)

### الحزمة التقنية المقترحة

| الطبقة | التقنية | السبب |
|---|---|---|
| Framework | **Laravel 11** (PHP 8.3) | سرعة تطوير، شائع في السوق المصري، نظام بيئي ناضج |
| Database | **PostgreSQL 16** | يدعم Row-Level Security، JSONB، قيود قوية، أداء عالٍ للعلاقات |
| Auth/API | **Laravel Sanctum** | توكنات SPA/Mobile، بسيط وكافٍ للمرحلة 1 |
| الصلاحيات | **spatie/laravel-permission** + طبقة مخصصة للزوج | الأدوار العامة + تحقق على مستوى pivot |
| تعدد المستأجرين | **Global Scopes + tenant_id** (لا schema-per-tenant) | المعلمة تعبر المستأجرين مستقبلاً |
| الطوابير | **Laravel Queue (Redis)** | الإشعارات، الرسائل، الفوترة غير المتزامنة |
| الدفع | **Paymob / Fawry** (Adapter Pattern) | بوابات محلية مصرية |
| الواجهة | **Inertia.js + Vue 3** أو **Blade + Livewire** | لوحة تحكم سريعة بدون SPA منفصل |
| التخزين | **S3-compatible** (صور الأطفال) | حساس، يحتاج توقيع مؤقت للروابط |

### تقسيم الطبقات (Layered, مع التزام معايير الفريق)

```
HTTP Request
   │
   ▼
Route ──► Middleware (auth, tenant.scope, plan.quota)
   │
   ▼
Controller  ◄── يستقبل Form Request (Validation فقط) — رفيع جداً
   │
   ▼
Service Class  ◄── كل Business Logic هنا (Transactions, قواعد العمل)
   │
   ▼
Repository / Eloquent Models  ◄── العلاقات + Eager Loading
   │
   ▼
PostgreSQL (Global Scope على tenant_id)
```

**القواعد الإلزامية للفريق:**
1. **Validation دائماً في Form Requests** — لا تحقق يدوي داخل الـ Controller.
2. **كل Business Logic داخل Service Classes** — الـ Controllers تنسّق فقط (استقبل → نادِ Service → أرجِع Resource).
3. **Eloquent Relationships صحيحة + Eager Loading** لمنع N+1 (نفرض ذلك بـ `Model::preventLazyLoading()` في بيئة التطوير).
4. **كل استجابة عبر API Resources** (لا تُرجع Models خام).
5. **كل عملية متعددة الكتابة داخل DB Transaction**.

---

## 3. تعدد المستأجرين (Multi-Tenancy) — أخطر قرار معماري

كل حضانة = **Tenant**. النموذج المختار: **قاعدة بيانات واحدة + عمود `tenant_id` + Global Scope** (وليس schema-per-tenant ولا database-per-tenant)، لأن المعلمة ستعبر أكثر من مستأجر في المرحلة 2، وهذا يستحيل عملياً مع العزل الفيزيائي.

### آلية العزل

```php
// app/Models/Concerns/BelongsToTenant.php
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        // يُطبَّق تلقائياً على كل استعلام
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if (! $model->tenant_id && app()->bound('currentTenant')) {
                $model->tenant_id = app('currentTenant')->id;
            }
        });
    }
}
```

```php
// app/Scopes/TenantScope.php
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->bound('currentTenant')) {
            $builder->where(
                $model->getTable().'.tenant_id',
                app('currentTenant')->id
            );
        }
    }
}
```

```php
// app/Http/Middleware/IdentifyTenant.php — يحدد المستأجر من المستخدم/الـ subdomain
public function handle(Request $request, Closure $next)
{
    $tenant = $request->user()?->currentTenant(); // أو من subdomain
    abort_if(! $tenant, 403, 'No tenant context');
    app()->instance('currentTenant', $tenant);
    return $next($request);
}
```

### دفاع بطبقتين (Defense in Depth)
- **الطبقة 1 (التطبيق):** Global Scope يضيف `tenant_id` لكل استعلام تلقائياً.
- **الطبقة 2 (قاعدة البيانات):** تفعيل **PostgreSQL Row-Level Security (RLS)** على الجداول الحساسة (children, attendance, media) كشبكة أمان نهائية — حتى لو نسي مطوّر إضافة scope، تمنع القاعدة التسرب.

```sql
ALTER TABLE children ENABLE ROW LEVEL SECURITY;
CREATE POLICY tenant_isolation ON children
  USING (tenant_id = current_setting('app.current_tenant')::bigint);
```

> **أخطر مخاطرة في المشروع:** أي خطأ في العزل = تسريب بيانات أطفال = كارثة قانونية وسمعية. لذلك العزل مبني على طبقتين، ويُختبر صراحةً (انظر §11).

---

## 4. نموذج البيانات (Data Model / ERD)

### الكيانات الأساسية والعلاقات

```
                         ┌──────────────┐
                         │   tenants    │  (الحضانة = المستأجر)
                         └──────┬───────┘
                                │ 1
              ┌─────────────────┼──────────────────┬───────────────┐
              │ N               │ N                │ N             │ N
        ┌─────▼─────┐    ┌──────▼──────┐    ┌──────▼─────┐  ┌──────▼────────┐
        │   users   │    │  children   │    │ classrooms │  │ subscriptions │
        └─────┬─────┘    └──────┬──────┘    └────────────┘  └───────────────┘
              │                 │
   ┌──────────┴────────┐        │
   │ (M:N) child_guardian│◄──────┘   جدول وسيط: الطفل ↔ وليّ الأمر
   └─────────┬──────────┘
             │
   ┌─────────▼──────────┐
   │ (M:N) teacher_nursery│        جدول وسيط: المعلمة ↔ الحضانة
   └────────────────────┘
```

### 4.1 العلاقة الجوهرية الأولى: الطفل ↔ ولي الأمر (Many-to-Many)

الطفل له أكثر من وصيّ (أم/أب/جد/سائق)، والوصيّ له أكثر من طفل (أشقّاء). الربط عبر **`child_guardian`** الذي يحمل **الصلاحيات على مستوى الزوج**:

| العمود | النوع | الوصف |
|---|---|---|
| `child_id` | FK | الطفل |
| `guardian_id` | FK → users | الوصيّ |
| `relationship` | enum | mother, father, grandparent, nanny, driver, other |
| `role` | enum | `primary`, `viewer`, `pickup_authorized`, `emergency_contact` |
| `can_view_wall` | bool | يرى الحائط الزمني |
| `can_pickup` | bool | **مخوَّل بالاستلام** (جوهر ميزة الأمان) |
| `is_payer` | bool | الوصيّ المسؤول مالياً |
| `custody_flag` | enum nullable | `none`, `view_only`, `blocked` (لحالات الحضانة — Phase 2 لكن العمود موجود الآن) |
| `notify_preferences` | jsonb | تفضيلات الإشعارات لكل وصيّ |

> في المرحلة 1 نكشف فقط: وصيّ `primary` واحد + إمكانية إضافة `pickup_authorized`. لكن الجدول يدعم الكل.

### 4.2 العلاقة الجوهرية الثانية: المعلمة ↔ الحضانة (Many-to-Many)

المعلمة قد تعمل في أكثر من حضانة (Phase 2)، والحضانة بها معلمات كثيرات. الربط عبر **`teacher_nursery`**:

| العمود | النوع | الوصف |
|---|---|---|
| `teacher_id` | FK → users | المعلمة |
| `tenant_id` | FK → tenants | الحضانة |
| `role` | enum | head_teacher, teacher, assistant |
| `classroom_id` | FK nullable | الفصل المسند |
| `status` | enum | active, on_leave, inactive |
| `employment_type` | enum | full_time, part_time, substitute |
| `joined_at` / `left_at` | timestamp | للسجل التاريخي |

> ملاحظة هوية المعلمة المنقولة (Phase 3) تُبنى لاحقاً فوق هذا الجدول: الملف العام يتراكم عبر صفوف `teacher_nursery` المتعددة. لذا نفصل **هوية المستخدم العالمية** (جدول `users` بلا tenant_id) عن **عضويته في مستأجر** (جدول pivot).

### 4.3 ملخص الجداول

| الجدول | الغرض | tenant scoped؟ |
|---|---|---|
| `tenants` | الحضانات | — (الجذر) |
| `users` | **هوية عالمية** لكل الأشخاص (مالك/معلمة/وصيّ) | **لا** (عالمي) |
| `tenant_user` | عضوية المستخدم في مستأجر + دوره العام | pivot |
| `children` | الأطفال | نعم |
| `child_guardian` | M:N طفل↔وصيّ + صلاحيات | نعم |
| `teacher_nursery` | M:N معلمة↔حضانة | نعم |
| `classrooms` | الفصول | نعم |
| `attendances` | حضور/انصراف + من سلّم/استلم | نعم |
| `subscription_plans` | الخطط (كتالوج عام) | لا |
| `subscriptions` | اشتراك كل حضانة | نعم |
| `invoices` / `payments` | الفواتير والمدفوعات | نعم |
| `roles` / `permissions` | spatie | mixed |

---

## 5. الترحيلات (Migrations)

أمثلة الجداول الحرجة. الباقي يتبع نفس النمط.

### 5.1 tenants

```php
Schema::create('tenants', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();         // subdomain
    $table->string('phone')->nullable();
    $table->string('address')->nullable();
    $table->string('logo_path')->nullable();
    $table->enum('status', ['trial', 'active', 'suspended', 'cancelled'])
          ->default('trial');
    $table->jsonb('settings')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

### 5.2 users (هوية عالمية — بلا tenant_id)

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('phone')->unique();        // الهاتف هو المعرّف الأساسي في مصر
    $table->string('email')->nullable()->unique();
    $table->string('password');
    $table->string('avatar_path')->nullable();
    $table->timestamp('phone_verified_at')->nullable();
    $table->rememberToken();
    $table->timestamps();
    $table->softDeletes();
});

// عضوية المستخدم في الحضانات + دوره العام داخل كل حضانة
Schema::create('tenant_user', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->enum('member_type', ['owner', 'admin', 'teacher', 'guardian']);
    $table->enum('status', ['active', 'invited', 'inactive'])->default('active');
    $table->timestamps();
    $table->unique(['tenant_id', 'user_id', 'member_type']);
});
```

### 5.3 children + child_guardian (M:N)

```php
Schema::create('children', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
    $table->string('first_name');
    $table->string('last_name');
    $table->date('birth_date');
    $table->enum('gender', ['male', 'female']);
    $table->string('photo_path')->nullable();
    $table->jsonb('medical_notes')->nullable();   // حساسيات، أدوية
    $table->enum('status', ['active', 'graduated', 'withdrawn'])->default('active');
    $table->timestamps();
    $table->softDeletes();
    $table->index(['tenant_id', 'status']);
});

Schema::create('child_guardian', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('child_id')->constrained()->cascadeOnDelete();
    $table->foreignId('guardian_id')->constrained('users')->cascadeOnDelete();
    $table->enum('relationship', ['mother','father','grandparent','nanny','driver','other']);
    $table->enum('role', ['primary','viewer','pickup_authorized','emergency_contact'])
          ->default('viewer');
    $table->boolean('can_view_wall')->default(true);
    $table->boolean('can_pickup')->default(false);
    $table->boolean('is_payer')->default(false);
    $table->enum('custody_flag', ['none','view_only','blocked'])->default('none');
    $table->jsonb('notify_preferences')->nullable();
    $table->timestamps();
    $table->unique(['child_id', 'guardian_id']);
    $table->index(['tenant_id', 'child_id']);
});
```

### 5.4 teacher_nursery (M:N)

```php
Schema::create('teacher_nursery', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
    $table->enum('role', ['head_teacher','teacher','assistant'])->default('teacher');
    $table->enum('status', ['active','on_leave','inactive'])->default('active');
    $table->enum('employment_type', ['full_time','part_time','substitute'])->default('full_time');
    $table->timestamp('joined_at')->nullable();
    $table->timestamp('left_at')->nullable();
    $table->timestamps();
    $table->unique(['teacher_id', 'tenant_id']);
});
```

### 5.5 attendances (الحضور + التحقق من الاستلام)

```php
Schema::create('attendances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('child_id')->constrained()->cascadeOnDelete();
    $table->date('date');
    $table->timestamp('checked_in_at')->nullable();
    $table->foreignId('checked_in_by')->nullable()->constrained('users'); // الوصيّ المسلِّم
    $table->timestamp('checked_out_at')->nullable();
    $table->foreignId('picked_up_by')->nullable()->constrained('users');  // المستلِم
    $table->boolean('pickup_verified')->default(false); // هل كان مخوَّلاً؟
    $table->string('check_in_method')->default('qr');   // qr / manual
    $table->timestamps();
    $table->unique(['child_id', 'date']);
    $table->index(['tenant_id', 'date']);
});
```

### 5.6 الاشتراكات (Subscriptions)

```php
// كتالوج عام (غير tenant scoped)
Schema::create('subscription_plans', function (Blueprint $table) {
    $table->id();
    $table->string('name');                       // مجاني / أساسي / احترافي / متقدم
    $table->string('slug')->unique();
    $table->unsignedInteger('price_egp');         // 0 / 750 / 1900 / 3900
    $table->enum('billing_cycle', ['monthly','yearly'])->default('monthly');
    $table->unsignedInteger('max_children')->nullable();   // null = غير محدود
    $table->unsignedInteger('max_teachers')->nullable();
    $table->unsignedInteger('included_sms')->default(0);
    $table->jsonb('features');                    // مصفوفة المزايا المفعّلة
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

Schema::create('subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('subscription_plan_id')->constrained();
    $table->enum('status', ['trialing','active','past_due','cancelled','expired'])
          ->default('trialing');
    $table->timestamp('trial_ends_at')->nullable();
    $table->timestamp('current_period_start')->nullable();
    $table->timestamp('current_period_end')->nullable();
    $table->timestamp('cancelled_at')->nullable();
    $table->string('gateway')->nullable();        // paymob / fawry
    $table->string('gateway_subscription_id')->nullable();
    $table->timestamps();
    $table->index(['tenant_id', 'status']);
});

Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('subscription_id')->nullable()->constrained();
    $table->string('number')->unique();
    $table->unsignedInteger('amount_egp');
    $table->enum('status', ['draft','open','paid','void','failed'])->default('open');
    $table->timestamp('due_at')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();
});
```

---

## 6. النماذج والعلاقات (Eloquent + Eager Loading)

### 6.1 Child

```php
class Child extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $casts = ['medical_notes' => 'array', 'birth_date' => 'date'];

    // M:N مع الأوصياء — مع كل أعمدة الـ pivot
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'child_guardian', 'child_id', 'guardian_id')
            ->withPivot(['relationship','role','can_view_wall','can_pickup','is_payer','custody_flag'])
            ->withTimestamps();
    }

    // المخوَّلون بالاستلام فقط — أساس ميزة الأمان
    public function authorizedPickups(): BelongsToMany
    {
        return $this->guardians()->wherePivot('can_pickup', true);
    }

    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function attendances(): HasMany { return $this->hasMany(Attendance::class); }
}
```

### 6.2 User (هوية عالمية)

```php
class User extends Authenticatable
{
    use HasApiTokens, HasRoles, SoftDeletes;

    // الأطفال الذين هو وصيّ عليهم (لوحة الأشقّاء الموحّدة)
    public function wards(): BelongsToMany
    {
        return $this->belongsToMany(Child::class, 'child_guardian', 'guardian_id', 'child_id')
            ->withPivot(['relationship','role','can_view_wall','can_pickup','is_payer']);
    }

    // الحضانات التي يعمل بها كمعلمة
    public function nurseriesAsTeacher(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'teacher_nursery', 'teacher_id', 'tenant_id')
            ->withPivot(['role','status','employment_type','classroom_id']);
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->withPivot(['member_type','status']);
    }
}
```

### 6.3 منع N+1 إلزامياً

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    // في غير الإنتاج: ارمِ استثناءً عند أي Lazy Loading
    Model::preventLazyLoading(! app()->isProduction());
}
```

```php
// مثال صحيح داخل Service: Eager Loading دائماً
$children = Child::query()
    ->with([
        'classroom:id,name',
        'guardians' => fn ($q) => $q->select('users.id','users.name','users.phone'),
        'attendances' => fn ($q) => $q->where('date', today()),
    ])
    ->where('status', 'active')
    ->paginate(20);
```

---

## 7. الصلاحيات (RBAC) — على مستوى الزوج

نموذج الصلاحيات هجين:
- **أدوار عامة** عبر `spatie/laravel-permission`: `owner`, `admin`, `teacher`, `guardian`.
- **صلاحيات دقيقة على مستوى الزوج** (لا على مستوى الحساب): `user × child` و `user × nursery`. مثال: وصيّ يرى الطفل أ ولا يستلمه، ويستلم الطفل ب.

تُطبَّق عبر **Laravel Policies** تقرأ من جداول الـ pivot:

```php
class ChildPolicy
{
    public function pickup(User $user, Child $child): bool
    {
        // التحقق على مستوى الزوج user×child من جدول child_guardian
        return $child->guardians()
            ->where('guardian_id', $user->id)
            ->wherePivot('can_pickup', true)
            ->wherePivot('custody_flag', '!=', 'blocked')
            ->exists();
    }

    public function viewWall(User $user, Child $child): bool
    {
        return $user->can('manage', $child->tenant) // المعلمة/الإدارة
            || $child->guardians()
                ->where('guardian_id', $user->id)
                ->wherePivot('can_view_wall', true)->exists();
    }
}
```

> القاعدة: **التحقق على مستوى الزوج، لا على مستوى الحساب.** هذا ما يجعل حالات الحضانة وتعدد الأوصياء ممكنة لاحقاً دون إعادة كتابة.

---

## 8. طبقة الخدمات و Form Requests

### 8.1 Form Request (Validation فقط)

```php
class StoreChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Child::class);
    }

    public function rules(): array
    {
        return [
            'first_name'          => ['required','string','max:100'],
            'last_name'           => ['required','string','max:100'],
            'birth_date'          => ['required','date','before:today'],
            'gender'              => ['required', Rule::in(['male','female'])],
            'classroom_id'        => ['nullable','exists:classrooms,id'],
            'guardians'           => ['required','array','min:1'],
            'guardians.*.user_id' => ['required','exists:users,id'],
            'guardians.*.role'    => ['required', Rule::in(['primary','viewer','pickup_authorized'])],
            'guardians.*.can_pickup' => ['boolean'],
        ];
    }
}
```

### 8.2 Service Class (كل Business Logic + Transaction)

```php
class ChildService
{
    public function create(array $data): Child
    {
        return DB::transaction(function () use ($data) {
            // 1) تحقق من حد الخطة (Quota) قبل الإنشاء
            $this->subscriptionService->assertCanAddChild(app('currentTenant'));

            // 2) أنشئ الطفل (tenant_id يُضاف تلقائياً عبر BelongsToTenant)
            $child = Child::create(Arr::except($data, ['guardians']));

            // 3) اربط الأوصياء بالصلاحيات (M:N)
            foreach ($data['guardians'] as $g) {
                $child->guardians()->attach($g['user_id'], [
                    'tenant_id'    => $child->tenant_id,
                    'relationship' => $g['relationship'],
                    'role'         => $g['role'],
                    'can_pickup'   => $g['can_pickup'] ?? false,
                    'is_payer'     => $g['is_payer'] ?? false,
                ]);
            }

            // 4) فعّل أحداث (إشعار، حساب وصيّ ...)
            event(new ChildEnrolled($child));

            return $child->load('guardians', 'classroom');
        });
    }
}
```

### 8.3 Controller (رفيع — تنسيق فقط)

```php
class ChildController extends Controller
{
    public function __construct(private ChildService $children) {}

    public function store(StoreChildRequest $request): ChildResource
    {
        $child = $this->children->create($request->validated());
        return new ChildResource($child);
    }
}
```

### 8.4 الخدمات المطلوبة في المرحلة 1
`ChildService` · `GuardianService` · `TeacherService` · `AttendanceService` (مع `verifyPickup`) · `SubscriptionService` (الخطط والحدود) · `BillingService` (بوابة الدفع عبر Adapter) · `TenantService` (الإعداد الأولي/Onboarding).

---

## 9. الاشتراكات والفوترة (Subscriptions)

### منطق الحدود (Quota Enforcement)

```php
class SubscriptionService
{
    public function assertCanAddChild(Tenant $tenant): void
    {
        $sub  = $tenant->activeSubscription;
        $plan = $sub->plan;

        if ($plan->max_children !== null
            && $tenant->children()->count() >= $plan->max_children) {
            throw new PlanLimitException('children', $plan->max_children);
        }
    }
}
```

تُفرض الحدود أيضاً عبر **Middleware** على المسارات الحسّاسة:

```php
Route::post('/children', [ChildController::class, 'store'])
    ->middleware(['auth:sanctum', 'tenant', 'plan.quota:children']);
```

### دورة حياة الاشتراك
`trialing` (14 يوم) → `active` → (فشل دفع) `past_due` → `cancelled`/`expired`. تُدار التحولات بـ Scheduled Job يومي يفحص `current_period_end` ويرسل تذكيرات الفوترة، ويُخفّض الحضانة لخطة "مجاني" بدل الإيقاف الكامل (يحافظ على البيانات).

### بوابات الدفع — Adapter Pattern
```php
interface PaymentGateway {
    public function charge(Invoice $invoice): PaymentResult;
    public function handleWebhook(Request $request): void;
}
// PaymobGateway, FawryGateway تنفّذان الواجهة — سهولة التبديل/الإضافة
```

---

## 10. هيكل الـ API (نماذج المسارات)

كل المسارات تحت `auth:sanctum` + `tenant` + Policy مناسبة. الاستجابة عبر API Resources.

| Method | Endpoint | الغرض | Policy |
|---|---|---|---|
| POST | `/api/v1/auth/login` | تسجيل دخول بالهاتف | — |
| GET | `/api/v1/children` | قائمة الأطفال (eager loaded) | viewAny |
| POST | `/api/v1/children` | إضافة طفل + أوصياء | create + quota |
| GET | `/api/v1/children/{id}` | تفاصيل الطفل | view |
| POST | `/api/v1/children/{id}/guardians` | ربط وصيّ | update |
| POST | `/api/v1/attendance/check-in` | حضور عبر QR | — (teacher) |
| POST | `/api/v1/attendance/check-out` | انصراف + تحقق الاستلام | pickup |
| GET | `/api/v1/me/wards` | لوحة الأشقّاء لولي الأمر | — |
| GET | `/api/v1/subscription` | اشتراك الحضانة الحالي | manageBilling |
| POST | `/api/v1/subscription/change-plan` | ترقية/تخفيض | manageBilling |
| POST | `/api/v1/webhooks/paymob` | Webhook الدفع | signature |

---

## 11. الأمان والامتثال والاختبار

### الأمان والخصوصية
- **عزل المستأجرين بطبقتين** (Global Scope + RLS) — §3.
- **صور الأطفال**: تخزين خاص + روابط موقّعة مؤقتة (Signed URLs)، لا روابط عامة.
- قانون حماية البيانات المصري **151/2020**: تسجيل موافقة (consent) ولي الأمر على تخزين صور/بيانات الطفل، حق الحذف (soft delete + سياسة حذف نهائي)، تشفير البيانات الحساسة (medical_notes) عند الحاجة.
- **ميزة الطوارئ/الاستلام**: تُؤطَّر قانونياً كـ"مساعدة" مع إخلاء مسؤولية، لا كضمان.
- تسجيل كامل (Audit Log) لكل عمليات الاستلام وتغيير صلاحيات الأوصياء.

### اختبارات المرحلة 1 (إلزامية في الـ Definition of Done)
- **اختبار عزل المستأجرين (الأهم):** Feature test يثبت أن مستخدم المستأجر أ **لا يمكنه** قراءة/تعديل أي صف للمستأجر ب — على كل endpoint.
- **اختبار الصلاحيات على مستوى الزوج:** وصيّ `can_pickup=false` يُمنع من الاستلام؛ `custody_flag=blocked` يُمنع من الرؤية.
- **اختبار N+1:** باستخدام `preventLazyLoading` + assertion على عدد الاستعلامات في المسارات الثقيلة.
- **اختبار حدود الخطة:** تجاوز `max_children` يرمي `PlanLimitException`.
- تغطية Service Classes بـ Unit tests، والمسارات بـ Feature tests (هدف ≥ 70%).

---

## 12. خطة التنفيذ (Sprints المقترحة)

| Sprint | المدة | المخرجات |
|---|---|---|
| **0 — الأساس** | أسبوع | إعداد Laravel + Postgres، CI/CD، Sanctum، هيكل الطبقات، `BelongsToTenant` + Global Scope + RLS، Seeders |
| **1 — الهوية والمستأجرون** | أسبوعان | users (هوية عالمية)، tenants، tenant_user، RBAC (spatie)، Onboarding للحضانة، تسجيل دخول بالهاتف |
| **2 — الأطفال والأوصياء** | أسبوعان | children + child_guardian (M:N كامل)، ChildService/GuardianService، Policies على مستوى الزوج، لوحة الأشقّاء، واجهة مبسّطة (وصيّ أساسي) |
| **3 — المعلمات والحضور** | أسبوعان | teacher_nursery، classrooms، QR check-in/out، AttendanceService + verifyPickup |
| **4 — الاشتراكات والفوترة** | أسبوعان | plans/subscriptions/invoices، SubscriptionService + حدود، Adapter دفع (Paymob/Fawry) + Webhooks، Scheduled billing job |
| **5 — التصليب** | أسبوع | اختبار العزل والصلاحيات وN+1، Audit log، Signed URLs، مراجعة امتثال 151/2020، توثيق API |

**إجمالي تقديري: ~10 أسابيع** لمنصة ويب جاهزة بقاعدة بيانات تدعم العلاقتين بالكامل.

---

## 13. ملخص القرارات الهندسية الحاسمة

1. **العلاقتان M:N تُبنيان في القاعدة الآن** عبر `child_guardian` و `teacher_nursery` بكل أعمدة الصلاحيات — حتى لو ظهرت الواجهة مبسّطة.
2. **هوية المستخدم عالمية** (بلا tenant_id)، والعضوية عبر pivot — يمهّد لهوية المعلمة المنقولة لاحقاً دون هجرة بيانات مؤلمة.
3. **تعدد المستأجرين = DB واحدة + tenant_id + عزل بطبقتين** (Global Scope + Postgres RLS). لا schema/database-per-tenant.
4. **الصلاحيات على مستوى الزوج** (user×child, user×nursery) عبر Policies تقرأ الـ pivot.
5. **التزام معماري صارم:** Form Requests للتحقق، Service Classes للمنطق، Controllers رفيعة، Eager Loading دائماً (`preventLazyLoading`)، كل كتابة داخل Transaction.
6. **أخطر مخاطرة = تسريب بيانات الأطفال** ⇒ العزل مُختبَر صراحةً وله شبكة أمان على مستوى القاعدة.
