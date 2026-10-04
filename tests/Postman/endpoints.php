<?php

/*
| Postman request definitions: path (relative to {{base_url}}), default
| auth persona (owner | teacher | guardian | null), default query/body for
| the success example, a description, and an optional Postman test script.
*/

$saveToken = fn (string $persona) => implode("\n", [
    'if (pm.response.code === 201) {',
    "    pm.collectionVariables.set('{$persona}_token', pm.response.json().token);",
    "    console.log('{$persona}_token saved');",
    '}',
]);

$errors = fn (string ...$rows) => "\n\n**الأخطاء المتوقعة**\n\n| الحالة | code | متى |\n|---|---|---|\n".implode("\n", $rows);

return [
    /* ------------------------------------------------------------ Auth */
    'login_owner' => [
        'expect' => [201],
        'folder' => '1. المصادقة — Auth',
        'name' => 'Login — owner (email)',
        'method' => 'POST', 'path' => 'v1/auth/tokens', 'auth' => null, 'tenant' => false,
        'body' => ['login' => '{{owner_login}}', 'password' => '{{password}}', 'device_name' => 'postman-owner'],
        'script' => $saveToken('owner'),
        'description' => "يصدر **توكن Bearer** لجهاز واحد. `login` بريد إلكتروني أو رقم هاتف.\n\n- توكن واحد لكل `device_name`: تسجيل الدخول مرة أخرى من نفس الجهاز يلغي التوكن السابق.\n- محدود بـ **6 محاولات في الدقيقة**.\n- يحفظ التوكن تلقائياً في المتغير `owner_token`."
            .$errors('`422` | `validation_failed` | بيانات ناقصة أو بيانات دخول خاطئة (`errors.login`)', '`429` | `too_many_requests` | أكثر من 6 محاولات في الدقيقة (راجع `Retry-After`)'),
    ],
    'login_teacher' => [
        'expect' => [201],
        'folder' => '1. المصادقة — Auth',
        'name' => 'Login — teacher',
        'method' => 'POST', 'path' => 'v1/auth/tokens', 'auth' => null, 'tenant' => false,
        'body' => ['login' => '{{teacher_login}}', 'password' => '{{password}}', 'device_name' => 'postman-teacher'],
        'script' => $saveToken('teacher'),
        'description' => 'نفس endpoint الدخول، بحساب معلمة. يحفظ `teacher_token`.',
    ],
    'login_guardian' => [
        'expect' => [201],
        'folder' => '1. المصادقة — Auth',
        'name' => 'Login — guardian (phone)',
        'method' => 'POST', 'path' => 'v1/auth/tokens', 'auth' => null, 'tenant' => false,
        'body' => ['login' => '{{guardian_login}}', 'password' => '{{password}}', 'device_name' => 'postman-guardian'],
        'script' => $saveToken('guardian'),
        'description' => 'دخول وليّ الأمر برقم الهاتف. يحفظ `guardian_token`.',
    ],
    'otp_request' => [
        'expect' => [202],
        'folder' => '1. المصادقة — Auth',
        'name' => 'OTP — request SMS code (parents)',
        'method' => 'POST', 'path' => 'v1/auth/otp', 'auth' => null, 'tenant' => false,
        'body' => ['phone' => '{{guardian_login}}'],
        'description' => "**الدخول بدون كلمة مرور لأولياء الأمور.** يرسل رمزاً من 6 أرقام بالـ SMS صالحاً 5 دقائق.\n\n- يقبل الرقم بأي صيغة: `01…` أو `+20…` أو `0020…`.\n- **الاستجابة واحدة سواء كان الرقم مسجلاً أم لا** (لا يكشف الأرقام المسجلة، ولا تُرسل رسالة لرقم غير مسجل).\n- `resend_after`: الثواني قبل السماح بطلب رمز جديد، `expires_in`: صلاحية الرمز.\n- حد: 5 رموز في الساعة لكل رقم، و6 طلبات في الدقيقة لكل IP."
            .$errors('`422` | `validation_failed` | رقم غير صحيح، أو طلب جديد قبل 60 ثانية، أو تجاوز 5 رموز في الساعة (`errors.phone`)', '`429` | `too_many_requests` | أكثر من 6 طلبات في الدقيقة'),
    ],
    'otp_verify' => [
        'expect' => [201, 422],
        'folder' => '1. المصادقة — Auth',
        'name' => 'OTP — verify code → token',
        'method' => 'POST', 'path' => 'v1/auth/otp/verify', 'auth' => null, 'tenant' => false,
        'body' => ['phone' => '{{guardian_login}}', 'code' => '123456', 'device_name' => 'mona-android'],
        'script' => "if (pm.response.code === 201) {\n    pm.collectionVariables.set('guardian_token', pm.response.json().token);\n}",
        'description' => "يتحقق من الرمز ويصدر **توكن الجهاز** (مثل الدخول بكلمة المرور) ويوثّق رقم الهاتف.\n\n- الرمز يُستخدم مرة واحدة، و**5 محاولات خاطئة تُبطله**.\n- يحفظ `guardian_token` عند النجاح.\n\n> في Postman ضع الرمز الذي وصلك بالـ SMS (محلياً يُكتب في `storage/logs/laravel.log`)."
            .$errors('`422` | `validation_failed` | رمز خاطئ أو منتهٍ أو استُنفدت محاولاته (`errors.code`)', '`429` | `too_many_requests` | أكثر من 10 محاولات في الدقيقة'),
    ],
    'me' => [
        'expect' => [200],
        'folder' => '1. المصادقة — Auth',
        'name' => 'Me — profile, nurseries & capabilities',
        'method' => 'GET', 'path' => 'v1/me', 'auth' => 'owner', 'tenant' => false,
        'script' => implode("\n", [
            'const nurseries = pm.response.json().data?.nurseries || [];',
            'if (nurseries.length) {',
            "    pm.collectionVariables.set('tenant_id', nurseries[0].id);",
            "    console.log('tenant_id =', nurseries[0].id);",
            '}',
        ]),
        'description' => "**أول طلب يرسله التطبيق بعد الدخول.** لا يحتاج `X-Tenant-Id`.\n\nيعيد بيانات المستخدم وكل حضانة ينتمي إليها مع:\n- `roles`: owner / admin / teacher / guardian (قد تجتمع أدوار، مثلاً معلمة وهي أيضاً وليّة أمر).\n- `capabilities`: ما يقرره التطبيق لعرض الشاشات:\n  - `take_attendance`, `view_children` → شاشات المعلمة\n  - `manage_children`, `manage_nursery` → شاشات الإدارة\n  - `guardian` → شاشات وليّ الأمر\n  - `bahga_pay` → الفواتير، `online_payments` → زر الدفع الإلكتروني\n  - `safe_pickup` → كود الاستلام (QR) والتصاريح لوليّ الأمر، وشاشة المسح عند الباب للمعلمة\n  - `daily_wall` → الحائط اليومي (نشر المعلمة، ويوميات الطفل للأسرة)\n\nأرسل `id` الحضانة المختارة في الهيدر **`X-Tenant-Id`** في كل الطلبات التالية."
            .$errors('`401` | `unauthenticated` | توكن ناقص أو منتهٍ'),
    ],
    'me_update' => [
        'expect' => [200],
        'folder' => '1. المصادقة — Auth',
        'name' => 'Me — update profile',
        'method' => 'PATCH', 'path' => 'v1/me', 'auth' => 'guardian', 'tenant' => false,
        'body' => ['name' => 'منى عبد الله', 'email' => 'mona@example.com'],
        'description' => 'تعديل الاسم والبريد (كلاهما اختياري). رقم الهاتف هو هوية الحساب ولا يُغيَّر إلا من الحضانة. تغيير البريد يلغي توثيقه.'
            .$errors('`422` | `validation_failed` | بريد غير صحيح أو مستخدم من قبل'),
    ],
    'logout' => [
        'expect' => [204],
        'folder' => '12. إنهاء الجلسة — Logout',
        'name' => 'Logout (revoke current token)',
        'method' => 'DELETE', 'path' => 'v1/auth/tokens/current', 'auth' => 'owner', 'tenant' => false,
        'description' => 'يلغي التوكن المستخدم في هذا الطلب فقط (تسجيل خروج من هذا الجهاز). لا يعيد body (`204`).'
            .$errors('`401` | `unauthenticated` | توكن غير صالح أو أُلغي من قبل'),
    ],

    /* ------------------------------------------------------- Staff */
    'classrooms' => [
        'expect' => [200],
        'folder' => '2. الموظفون: الفصول والأطفال — Staff',
        'name' => 'List classrooms',
        'method' => 'GET', 'path' => 'v1/classrooms', 'auth' => 'teacher',
        'description' => "فصول الحضانة مع عدد الأطفال النشطين في كل فصل — لفلترة كشف الحضور.\n\n**الصلاحية:** المالك / المدير / المعلمة."
            .$errors('`403` | `forbidden` | ليس من موظفي الحضانة'),
    ],
    'children_index' => [
        'expect' => [200],
        'folder' => '2. الموظفون: الفصول والأطفال — Staff',
        'name' => 'List children',
        'method' => 'GET', 'path' => 'v1/children', 'auth' => 'teacher',
        'query' => ['classroom_id' => '{{classroom_id}}', 'per_page' => '20'],
        'script' => "const [first, second] = pm.response.json().data ?? [];\nif (first) pm.collectionVariables.set('child_id', first.id);\nif (second) pm.collectionVariables.set('second_child_id', second.id);",
        'description' => "قائمة الأطفال (مقسّمة صفحات) مع الفصل والأوصياء وحضور اليوم (`today_attendance`).\n\n| المعامل | الوصف |\n|---|---|\n| `q` | بحث في الاسم الأول/العائلة |\n| `classroom_id` | فصل محدد |\n| `status` | `active` (افتراضي) / `graduated` / `withdrawn` |\n| `per_page` | 1–100 (افتراضي 20) |\n| `page` | رقم الصفحة |\n\n**الصلاحية:** موظفو الحضانة. الاستجابة تحوي `links` و`meta` للتنقل بين الصفحات."
            .$errors('`403` | `forbidden` | ليس من موظفي الحضانة', '`422` | `validation_failed` | فصل غير موجود في هذه الحضانة'),
    ],
    'children_show' => [
        'expect' => [200],
        'folder' => '2. الموظفون: الفصول والأطفال — Staff',
        'name' => 'Get child (staff view)',
        'method' => 'GET', 'path' => 'v1/children/{{child_id}}', 'auth' => 'teacher',
        'script' => implode("\n", [
            'const guardians = pm.response.json().data?.guardians || [];',
            "const mayCollect = g => g.can_pickup && g.custody_flag !== 'blocked';",
            '// Prefer the primary guardian: "Detach guardian" later removes guardian_id.',
            "const collector = guardians.find(g => g.role === 'primary' && mayCollect(g)) || guardians.find(mayCollect);",
            "if (collector) pm.collectionVariables.set('collector_id', collector.id);",
            "const other = guardians.find(g => g.role !== 'primary' && g.id !== collector?.id);",
            "if (other) pm.collectionVariables.set('guardian_id', other.id);",
        ]),
        'description' => "عرض الموظفين للطفل: كل الأوصياء بصلاحياتهم (`can_pickup`, `custody_flag`)، الملاحظات الطبية، وحضور اليوم.\n\n> ⚠️ قبل تسليم الطفل تحقّق من `custody_flag`: القيمة `blocked` تعني **ممنوع الاستلام بحكم حضانة**.\n\nأولياء الأمور يستخدمون `GET /v1/me/wards/{id}` (عرض أضيق لا يكشف باقي الأوصياء)."
            .$errors('`403` | `forbidden` | ليس من موظفي الحضانة', '`404` | `not_found` | الطفل غير موجود أو يتبع حضانة أخرى'),
    ],
    'children_store' => [
        'expect' => [201],
        'folder' => '2. الموظفون: الفصول والأطفال — Staff',
        'name' => 'Enroll child (manager)',
        'method' => 'POST', 'path' => 'v1/children', 'auth' => 'owner',
        'body' => [
            'first_name' => 'آدم', 'last_name' => 'سامي', 'birth_date' => '2023-01-15', 'gender' => 'male',
            'classroom_id' => '{{classroom_id}}',
            'guardians' => [[
                'name' => 'هبة سامي', 'phone' => '01012345678', 'relationship' => 'mother', 'role' => 'primary',
                'can_pickup' => true, 'is_payer' => true,
            ]],
        ],
        'description' => "تسجيل طفل جديد مع وليّ أمر واحد على الأقل.\n\nكل وليّ أمر إمّا `user_id` لحساب موجود، أو `name` + `phone` (يُنشأ الحساب أو يُربط إن كان الرقم مسجلاً).\n\n| الحقل | القيم |\n|---|---|\n| `gender` | `male` / `female` |\n| `guardians.*.relationship` | `mother` `father` `grandparent` `nanny` `driver` `other` |\n| `guardians.*.role` | `primary` `viewer` `pickup_authorized` `emergency_contact` |\n| `guardians.*.custody_flag` | `none` `view_only` `blocked` |\n| `guardians.*.billing_share_percent` | نسبة المساهمة في المصروفات عند تعدد الدافعين |\n\n**الصلاحية:** المالك / المدير فقط."
            .$errors('`402` | `plan_limit_reached` | بلغت الحضانة حد الأطفال في خطتها (`errors.limit`)', '`403` | `forbidden` | المعلمات لا يسجّلن أطفالاً', '`422` | `validation_failed` | بيانات ناقصة أو غير صحيحة'),
    ],
    'children_update' => [
        'expect' => [200],
        'folder' => '2. الموظفون: الفصول والأطفال — Staff',
        'name' => 'Update child (manager)',
        'method' => 'PATCH', 'path' => 'v1/children/{{child_id}}', 'auth' => 'owner',
        'body' => ['classroom_id' => '{{classroom_id}}', 'medical_notes' => ['allergies' => ['فول سوداني', 'بيض']]],
        'description' => "تعديل جزئي: أرسل الحقول المراد تغييرها فقط. إرسال `guardians` يستبدل قائمة الأوصياء بالكامل.\n\n**الصلاحية:** المالك / المدير فقط."
            .$errors('`403` | `forbidden` | المعلمات لا يعدّلن', '`404` | `not_found` | الطفل غير موجود', '`422` | `validation_failed` | قيمة غير صحيحة'),
    ],
    'guardians_store' => [
        'expect' => [200],
        'folder' => '2. الموظفون: الفصول والأطفال — Staff',
        'name' => 'Attach / update guardian (manager)',
        'method' => 'POST', 'path' => 'v1/children/{{child_id}}/guardians', 'auth' => 'owner',
        'body' => ['user_id' => '{{guardian_id}}', 'relationship' => 'grandparent', 'role' => 'pickup_authorized', 'can_pickup' => true, 'can_view_wall' => false],
        'description' => "يربط مستخدماً بالطفل أو يحدّث صلاحياته إن كان مربوطاً. كل تغيير يُسجَّل في سجل التدقيق (قبل/بعد).\n\n**الصلاحية:** المالك / المدير فقط — صلاحيات الاستلام والحضانة قرار أمان إداري."
            .$errors('`403` | `forbidden` | المعلمات لا يغيّرن الصلاحيات', '`422` | `validation_failed` | قيمة غير صحيحة'),
    ],
    'guardians_destroy' => [
        'expect' => [200],
        'folder' => '2. الموظفون: الفصول والأطفال — Staff',
        'name' => 'Detach guardian (manager)',
        'method' => 'DELETE', 'path' => 'v1/children/{{child_id}}/guardians/{{guardian_id}}', 'auth' => 'owner',
        'description' => 'فصل وليّ أمر عن الطفل. يعيد الطفل بقائمة الأوصياء المحدّثة.'
            .$errors('`403` | `forbidden` | ليس مديراً', '`404` | `not_found` | الطفل أو المستخدم غير موجود'),
    ],

    /* ---------------------------------------------------- Attendance */
    'attendance_index' => [
        'expect' => [200],
        'folder' => '3. الموظفون: الحضور والانصراف — Attendance',
        'name' => 'Daily attendance sheet',
        'method' => 'GET', 'path' => 'v1/attendance', 'auth' => 'teacher',
        'query' => ['date' => '2026-10-04', 'classroom_id' => '{{classroom_id}}'],
        'description' => "**الشاشة الرئيسية للمعلمة**: كل الأطفال النشطين بحالتهم في يوم واحد:\n\n| `status` | المعنى |\n|---|---|\n| `absent` | لم يُسجَّل حضوره |\n| `present` | حاضر |\n| `picked_up` | انصرف |\n\n- `date` اختياري (اليوم افتراضياً، ولا يقبل المستقبل)، `classroom_id` اختياري.\n- `summary` يعطي العدادات جاهزة للعرض.\n- `pickup_deadline`: آخر موعد للانصراف (توقيت القاهرة) إن حدّدته الحضانة؛ بعده يصبح `late_pickup: true` لكل طفل ما زال حاضراً (`summary.late_pickup`)، ويُرسَل SMS لأولياء الأمر بعد 15 دقيقة وللإدارة بعد 45 دقيقة."
            .$errors('`403` | `forbidden` | ليس من موظفي الحضانة', '`422` | `validation_failed` | تاريخ مستقبلي أو صيغة غير صحيحة (YYYY-MM-DD)'),
    ],
    'check_in' => [
        'expect' => [200],
        'folder' => '3. الموظفون: الحضور والانصراف — Attendance',
        'name' => 'Check in',
        'method' => 'POST', 'path' => 'v1/attendance/check-in', 'auth' => 'teacher',
        'body' => ['child_id' => '{{child_id}}', 'method' => 'qr'],
        'description' => 'تسجيل حضور الطفل اليوم (`method`: `qr` / `nfc` / `manual`). التكرار في نفس اليوم يحدّث السجل ولا ينشئ سجلاً جديداً — الاستجابة دائماً `200`.'
            .$errors('`403` | `forbidden` | ليس من موظفي الحضانة', '`422` | `validation_failed` | الطفل غير موجود في هذه الحضانة'),
    ],
    'check_in_bulk' => [
        'expect' => [200],
        'folder' => '3. الموظفون: الحضور والانصراف — Attendance',
        'name' => 'Bulk check-in (offline sync)',
        'method' => 'POST', 'path' => 'v1/attendance/check-in/bulk', 'auth' => 'teacher',
        'body' => ['method' => 'qr', 'children' => [['child_id' => '{{child_id}}', 'checked_in_at' => '{{bulk_checked_in_at}}'], ['child_id' => '{{second_child_id}}']]],
        'prerequest' => "// checked_in_at must be today and not in the future\npm.collectionVariables.set('bulk_checked_in_at', new Date(Date.now() - 5 * 60 * 1000).toISOString());",
        'description' => "تسجيل حضور عدة أطفال دفعة واحدة (حتى 300) — لصباح مزدحم أو لمزامنة ما سُجِّل **بدون إنترنت**.\n\n- `checked_in_at` اختياري: وقت المسح الفعلي على الجهاز (اليوم فقط، وليس في المستقبل). بدونه يُستخدم وقت الخادم.\n- كل الطلب يُنفَّذ أو يُرفض كاملاً، ويعيد سجلات الحضور.\n- لا يتكرر الطفل في نفس الطلب."
            .$errors('`403` | `forbidden` | ليس من موظفي الحضانة', '`422` | `validation_failed` | طفل مكرر، من حضانة أخرى، أو وقت في المستقبل/يوم سابق'),
    ],
    'check_out' => [
        'expect' => [200],
        'folder' => '3. الموظفون: الحضور والانصراف — Attendance',
        'name' => 'Check out (verified pickup)',
        'method' => 'POST', 'path' => 'v1/attendance/check-out', 'auth' => 'teacher',
        'body' => ['child_id' => '{{child_id}}', 'collector_id' => '{{collector_id}}'],
        'description' => "تسليم الطفل **بعد التحقق من المستلم**. أرسل `child_id` + **طريقة واحدة فقط** لتحديد المستلم:\n\n| الحقل | `pickup_method` | متى |\n|---|---|---|\n| `collector_id` | `staff_confirmed` | المعلمة اختارت الوصيّ من القائمة |\n| `pickup_token` | `dynamic_qr` | مسح QR وليّ الأمر (Safe Pickup) |\n| `pass_code` | `pass_code` | كود تصريح من 6 أرقام (Safe Pickup) — يُستهلك مرة واحدة |\n| `override_reason` + `collector_name` | `manual_override` | **للمالك/المدير فقط** في الطوارئ؛ لا يُعلَّم كـ «متحقَّق منه» ويُسجَّل في التدقيق |\n\n- يُرفض المستلم غير المخوَّل (`can_pickup`) أو المحظور بحكم حضانة — حتى مع QR صالح.\n- كل محاولة — ناجحة أو مرفوضة — تُسجَّل في سجل التدقيق.\n- عند الرفض: **لا تسلّمي الطفل** واعرضي الرسالة للمعلمة."
            .$errors('`403` | `forbidden` | المستلم غير مخوَّل أو محظور، تصريح لطفل آخر، أو معلمة تحاول التجاوز اليدوي', '`422` | `validation_failed` | لا توجد طريقة تحديد أو أكثر من واحدة، QR منتهي/مزوّر، كود تصريح غير صالح'),
    ],

    /* --------------------------------------------------- Safe Pickup */
    'pickup_code' => [
        'expect' => [200],
        'folder' => '4. الاستلام الآمن — Safe Pickup 2.0',
        'name' => 'Guardian — my pickup QR code',
        'method' => 'GET', 'path' => 'v1/me/pickup-code', 'auth' => 'guardian',
        'script' => "pm.collectionVariables.set('pickup_token', pm.response.json().data?.token);",
        'description' => "رمز موقَّع يعرضه تطبيق وليّ الأمر كـ **QR** عند باب الحضانة.\n\n- صالح **60 ثانية** فقط؛ اطلب رمزاً جديداً كل `refresh_after` ثانية (30) طالما الشاشة مفتوحة — لقطة الشاشة لا تنفع بعد دقيقة.\n- مرتبط بالحضانة الحالية (`X-Tenant-Id`) ولا يعمل في غيرها.\n- لا يُعطى إلا لمن له حق استلام طفل واحد على الأقل."
            .$errors('`403` | `forbidden` | ليس لك حق استلام أي طفل (أو محظور بحكم حضانة)', '`404` | `not_found` | الاستلام الآمن غير مفعّل للحضانة', '`402` | `plan_upgrade_required` | الخطة لا تشمل الاستلام الآمن'),
    ],
    'pickup_verify' => [
        'expect' => [200],
        'folder' => '4. الاستلام الآمن — Safe Pickup 2.0',
        'name' => 'Teacher — verify who is at the door',
        'method' => 'POST', 'path' => 'v1/attendance/pickup/verify', 'auth' => 'teacher',
        'body' => ['pickup_token' => '{{pickup_token}}'],
        'description' => "**شاشة الباب**: بعد مسح QR (`pickup_token`) أو كتابة كود تصريح (`pass_code`) — واحد فقط.\n\nتعيد المستلم (الاسم، الصورة، صلة القرابة، `method`) وكل طفل يمكن أن يخصّه مع:\n- `allowed: true` → أخضر، اضغطي «انصراف» بنفس `pickup_token`/`pass_code`.\n- `allowed: false` + `reason`: `custody_blocked` (**أحمر — لا تسلّمي، أبلغي الإدارة**) أو `not_authorized`.\n- `attendance_status`: هل الطفل حاضر الآن.\n\nلا يغيّر أي شيء — مجرد تحقق، ولا يستهلك التصريح.\n\nحد الطلبات: 30/دقيقة لكل مستخدم (ضد تخمين الأكواد)."
            .$errors('`403` | `forbidden` | ليس من موظفي الحضانة', '`422` | `validation_failed` | QR منتهي/مزوّر/من حضانة أخرى، أو كود تصريح مستخدم/ملغى/منتهي', '`429` | `too_many_requests` | محاولات كثيرة'),
    ],
    'pickup_passes_store' => [
        'expect' => [201],
        'folder' => '4. الاستلام الآمن — Safe Pickup 2.0',
        'name' => 'Guardian — issue a one-time pickup pass',
        'method' => 'POST', 'path' => 'v1/me/wards/{{child_id}}/pickup-passes', 'auth' => 'guardian',
        'body' => ['name' => 'عم سيد السائق', 'phone' => '01055554444', 'valid_until' => '{{pass_valid_until}}', 'note' => 'سيارة بيضاء'],
        'prerequest' => "// valid_until: within 24 hours from now\npm.collectionVariables.set('pass_valid_until', new Date(Date.now() + 4 * 3600 * 1000).toISOString());",
        'script' => "if (pm.response.code === 201) {\n    pm.collectionVariables.set('pass_code', pm.response.json().code);\n    pm.collectionVariables.set('pass_id', pm.response.json().data.id);\n}",
        'description' => "تفويض شخص **ليس لديه التطبيق** (سائق، قريب) باستلام الطفل **مرة واحدة**.\n\n- يُرسَل للمفوَّض SMS فيه كود من 6 أرقام واسم الطفل والصلاحية، والكود يظهر في الاستجابة (`code`) **مرة واحدة فقط**.\n- `valid_until` خلال 24 ساعة كحد أقصى؛ `valid_from` اختياري.\n- يتطلب أن يكون لوليّ الأمر نفسه حق الاستلام."
            .$errors('`403` | `forbidden` | ليس لك حق استلام هذا الطفل', '`404` | `not_found` | ليس من أطفالك', '`422` | `validation_failed` | رقم موبايل غير مصري، أو مدة أكثر من 24 ساعة'),
    ],
    'pickup_passes_index' => [
        'expect' => [200],
        'folder' => '4. الاستلام الآمن — Safe Pickup 2.0',
        'name' => 'Guardian — pickup passes for my child',
        'method' => 'GET', 'path' => 'v1/me/wards/{{child_id}}/pickup-passes', 'auth' => 'guardian',
        'description' => 'التصاريح التي أُصدرت لهذا الطفل (الأحدث أولاً) مع `status`: `active` / `scheduled` / `used` / `revoked` / `expired`. الكود نفسه لا يُعاد أبداً.'
            .$errors('`404` | `not_found` | ليس من أطفالك'),
    ],
    'pickup_passes_destroy' => [
        'expect' => [200],
        'folder' => '4. الاستلام الآمن — Safe Pickup 2.0',
        'name' => 'Guardian — revoke a pickup pass',
        'method' => 'DELETE', 'path' => 'v1/me/pickup-passes/{{pass_id}}', 'auth' => 'guardian',
        'description' => 'إلغاء تصريح قبل استخدامه — يتوقف الكود فوراً. يعيد التصريح بحالة `revoked`.'
            .$errors('`403` | `forbidden` | ليس لك حق استلام هذا الطفل', '`404` | `not_found` | تصريح غير موجود أو لطفل ليس من أطفالك'),
    ],

    /* ------------------------------------------------------- Guardian */
    'wards_index' => [
        'expect' => [200],
        'folder' => '5. وليّ الأمر: أطفالي — Guardian',
        'name' => 'My children',
        'method' => 'GET', 'path' => 'v1/me/wards', 'auth' => 'guardian',
        'script' => "const first = pm.response.json().data?.[0];\nif (first) pm.collectionVariables.set('child_id', first.id);",
        'description' => "أطفال وليّ الأمر في الحضانة الحالية (لوحة الإخوة) مع حضور اليوم.\n\n- `my_link`: صلاحيات **هذا** الوليّ فقط — لا تُعرض بيانات باقي الأوصياء أبداً.\n- `medical_notes` تظهر فقط لمن يملك `can_view_wall`.\n- الوصيّ المحظور بحكم حضانة **لا يرى الطفل إطلاقاً** (قائمة فارغة)."
            .$errors('`401` | `unauthenticated` | بدون توكن'),
    ],
    'wards_show' => [
        'expect' => [200],
        'folder' => '5. وليّ الأمر: أطفالي — Guardian',
        'name' => 'My child',
        'method' => 'GET', 'path' => 'v1/me/wards/{{child_id}}', 'auth' => 'guardian',
        'description' => 'تفاصيل طفل واحد من أطفال وليّ الأمر مع حالة اليوم.'
            .$errors('`404` | `not_found` | ليس من أطفالك، أو الرابط محظور'),
    ],
    'wards_attendance' => [
        'expect' => [200],
        'folder' => '5. وليّ الأمر: أطفالي — Guardian',
        'name' => 'My child — attendance history',
        'method' => 'GET', 'path' => 'v1/me/wards/{{child_id}}/attendance', 'auth' => 'guardian',
        'query' => ['from' => '2026-10-01', 'to' => '2026-10-31'],
        'description' => "سجل الحضور والانصراف (30 يوماً في الصفحة، الأحدث أولاً). `from` و`to` اختياريان بصيغة `YYYY-MM-DD`.\n\n`picked_up_by_name` يوضح من استلم الطفل."
            .$errors('`404` | `not_found` | ليس من أطفالك', '`422` | `validation_failed` | `to` قبل `from`'),
    ],
    'wards_notifications' => [
        'expect' => [200],
        'folder' => '5. وليّ الأمر: أطفالي — Guardian',
        'name' => 'My child — notification settings',
        'method' => 'PATCH', 'path' => 'v1/me/wards/{{child_id}}/notifications', 'auth' => 'guardian',
        'body' => ['push' => true, 'sms' => false],
        'description' => "اختيارات الإشعارات لهذا الطفل (أرسل أحدهما أو كليهما):\n\n| الحقل | يتحكم في |\n|---|---|\n| `push` | إشعارات التطبيق (وصل، انصرف، ...) |\n| `sms` | الرسائل النصية: تذكيرات الدفع، وبديل التنبيهات العاجلة عندما لا يصل الإشعار لأي جهاز |\n\nالقيمة الحالية في `my_link.notifications`. التنبيهات الطارئة فقط تتجاوز هذه الاختيارات."
            .$errors('`404` | `not_found` | ليس من أطفالك', '`422` | `validation_failed` | لا `push` ولا `sms`، أو قيمة غير منطقية'),
    ],

    /* --------------------------------------------------- Notifications */
    'devices_store' => [
        'expect' => [201, 200],
        'folder' => '6. الإشعارات — Notifications',
        'name' => 'Register this device for push',
        'method' => 'POST', 'path' => 'v1/me/devices', 'auth' => 'guardian', 'tenant' => false,
        'body' => ['token' => '{{device_token}}', 'platform' => 'android', 'locale' => 'ar', 'app_version' => '2.1.0'],
        'description' => "أرسله التطبيق **بعد كل تسجيل دخول، وكلما غيّر Firebase التوكن** (`onTokenRefresh`). لا يحتاج `X-Tenant-Id`: الجهاز يستقبل إشعارات كل حضانات المستخدم.\n\n- `token`: توكن FCM للجهاز. `platform`: `android` / `ios` / `web`.\n- `locale`: لغة نص الإشعار على هذا الجهاز (`ar` افتراضياً).\n- `201` جهاز جديد، `200` تحديث جهاز معروف. لو سجّل حساب آخر الدخول على نفس الجهاز ينتقل التوكن إليه.\n- الجهاز مرتبط بتوكن الدخول: **تسجيل الخروج يوقف إشعاراته تلقائياً**.\n\nحمولة الإشعار (`data`) تحوي: `notification_id`, `type`, `tenant_id`, `child_id`, `screen` — استخدمها لفتح الشاشة الصحيحة."
            .$errors('`401` | `unauthenticated` | بدون توكن', '`422` | `validation_failed` | توكن قصير أو منصة غير معروفة'),
    ],
    'notifications_index' => [
        'expect' => [200],
        'folder' => '6. الإشعارات — Notifications',
        'name' => 'My notifications (inbox)',
        'method' => 'GET', 'path' => 'v1/me/notifications', 'auth' => 'guardian',
        'script' => "const first = pm.response.json().data?.[0];\nif (first) pm.collectionVariables.set('notification_id', first.id);",
        'description' => "صندوق الإشعارات في الحضانة الحالية، الأحدث أولاً (20 في الصفحة)، مع `unread_count` لشارة العدد.\n\n- `?unread=1` غير المقروءة فقط.\n- `title` و`body` بلغة الطلب (`Accept-Language`).\n- `deep_link.screen`: الشاشة التي يفتحها التطبيق (`ward` / `attendance`)، و`child_id` للطفل المعني.\n- الأنواع الحالية: `child_arrived`, `child_picked_up`, `late_pickup`, `late_pickup_managers`."
            .$errors('`401` | `unauthenticated` | بدون توكن', '`403` | `forbidden` | لست عضواً في هذه الحضانة'),
    ],
    'notifications_read' => [
        'expect' => [200],
        'folder' => '6. الإشعارات — Notifications',
        'name' => 'Mark a notification read',
        'method' => 'POST', 'path' => 'v1/me/notifications/{{notification_id}}/read', 'auth' => 'guardian',
        'description' => 'عند فتح الإشعار. التكرار آمن (لا يغيّر وقت القراءة الأول).'
            .$errors('`404` | `not_found` | ليس إشعارك أو غير موجود'),
    ],
    'notifications_read_all' => [
        'expect' => [200],
        'folder' => '6. الإشعارات — Notifications',
        'name' => 'Mark all read',
        'method' => 'POST', 'path' => 'v1/me/notifications/read-all', 'auth' => 'guardian',
        'description' => 'كل إشعاراتي في هذه الحضانة مقروءة. يعيد `unread_count: 0`.'
            .$errors('`401` | `unauthenticated` | بدون توكن'),
    ],
    'devices_destroy' => [
        'expect' => [204],
        'folder' => '6. الإشعارات — Notifications',
        'name' => 'Stop push on this device',
        'method' => 'DELETE', 'path' => 'v1/me/devices', 'auth' => 'guardian', 'tenant' => false,
        'body' => ['token' => '{{device_token}}'],
        'description' => 'عندما يطفئ المستخدم الإشعارات من إعدادات التطبيق. آمن التكرار (`204` دائماً). لا داعي له عند تسجيل الخروج: يحدث تلقائياً.'
            .$errors('`422` | `validation_failed` | `token` مطلوب'),
    ],

    /* -------------------------------------------------------- Daily Wall */
    'photo_consent_show' => [
        'expect' => [200],
        'folder' => '7. الحائط اليومي — Daily Wall',
        'name' => 'Guardian — photo consent for my child',
        'method' => 'GET', 'path' => 'v1/me/wards/{{child_id}}/photo-consent', 'auth' => 'guardian',
        'description' => "إذن التصوير الحالي:\n\n| الحقل | المعنى |\n|---|---|\n| `wall` | يُسمح بتصوير الطفل لحائط أسرته |\n| `group_photos` | يُسمح بظهوره في صور جماعية تراها أسر أخرى (يتطلب `wall`) |\n| `can_change` | هل أنت وليّ الأمر الأساسي (وحده يغيّر الإذن) |\n\nالافتراضي **بدون إذن**: لا يمكن نشر صور للطفل حتى توافق الأسرة."
            .$errors('`404` | `not_found` | ليس من أطفالك'),
    ],
    'photo_consent_update' => [
        'expect' => [200],
        'folder' => '7. الحائط اليومي — Daily Wall',
        'name' => 'Guardian — give / withdraw photo consent',
        'method' => 'PUT', 'path' => 'v1/me/wards/{{child_id}}/photo-consent', 'auth' => 'guardian',
        'body' => ['wall' => true, 'group_photos' => true],
        'description' => "أرسل `wall` و/أو `group_photos`. كل تغيير يُسجَّل في سجل التدقيق.\n\nسحب `group_photos` يُخفي فوراً الصور الجماعية القديمة التي فيها طفلك عن باقي الأسر (وتبقى ظاهرة لك)."
            .$errors('`403` | `forbidden` | لست وليّ الأمر الأساسي', '`404` | `not_found` | ليس من أطفالك', '`422` | `validation_failed` | لا `wall` ولا `group_photos`'),
    ],
    'moments_store_photo' => [
        'expect' => [201],
        'folder' => '7. الحائط اليومي — Daily Wall',
        'name' => 'Teacher — post photos (multipart)',
        'method' => 'POST', 'path' => 'v1/moments', 'auth' => 'teacher', 'multipart' => true,
        'body' => ['type' => 'photo', 'child_ids' => ['{{child_id}}'], 'body' => 'يوم الرسم بالألوان المائية', 'photos' => ['@file:samples/photo.jpg']],
        'script' => "if (pm.response.code === 201) pm.collectionVariables.set('moment_id', pm.response.json().data.id);",
        'description' => "**`multipart/form-data`**: `photos[]` حتى 10 صور (JPEG/PNG/WebP، حتى 10MB لكل صورة — يُفضَّل ضغطها على الجهاز قبل الرفع).\n\n- الخادم يحذف بيانات EXIF/GPS، ويعدّل اتجاه الصورة، ويحفظ نسخة حتى 2048px ومصغّرة 480px.\n- طفل واحد ⇐ يحتاج `wall`؛ أكثر من طفل (صورة جماعية) ⇐ كل طفل يحتاج `wall` + `group_photos`. الرسالة تذكر أسماء من ينقصه الإذن.\n- الخطة المجانية: 3 صور يومياً لكل طفل، وتُحذف الصور بعد 30 يوماً.\n- حد الطلبات: 30 نشراً/دقيقة لكل معلمة.\n\n> في Postman: افتح تبويب Body واختر ملف الصورة، أو شغّل newman مع `--working-dir docs/api`."
            .$errors('`402` | `plan_limit_reached` | تجاوز حد الصور اليومي للطفل في الخطة', '`403` | `forbidden` | ليس من موظفي الحضانة', '`422` | `validation_failed` | لا يوجد إذن تصوير/صور جماعية، أو ملف ليس صورة'),
    ],
    'moments_store_video' => [
        'expect' => [201],
        'folder' => '7. الحائط اليومي — Daily Wall',
        'name' => 'Teacher — post a short video (multipart)',
        'method' => 'POST', 'path' => 'v1/moments', 'auth' => 'teacher', 'multipart' => true,
        'body' => ['type' => 'video', 'child_ids' => ['{{child_id}}'], 'body' => 'أول خطوات في الرقص', 'videos' => ['@file:samples/clip.mp4'], 'video_posters' => ['@file:samples/photo.jpg'], 'video_durations' => [3000]],
        'description' => "فيديو قصير مع صورة غلاف: `videos[]` (حتى 3، mp4/mov، حتى 50MB لكل فيديو)، و`video_posters[]` و`video_durations[]` (بالمللي ثانية) بنفس الترتيب.\n\n- **اضغط الفيديو على الجهاز قبل الرفع** (720p تكفي للحائط)، واستخرج إطاراً كصورة غلاف — الخادم لا يعيد ترميز الفيديو.\n- الغلاف يُعاد ترميزه (حذف EXIF/GPS) ويُعرض في `videos[].poster_url`.\n- `videos[].url` يدعم **HTTP Range** (التقديم والتأخير، ومشغّلات iOS تتطلبه)، صالح 30 دقيقة.\n- نفس قواعد إذن التصوير وحد الوسائط اليومي في الخطة."
            .$errors('`402` | `plan_limit_reached` | تجاوز حد الوسائط اليومي', '`422` | `validation_failed` | لا يوجد إذن تصوير، أو ملف ليس فيديو، أو أكبر من 50MB'),
    ],
    'moments_store' => [
        'expect' => [201],
        'folder' => '7. الحائط اليومي — Daily Wall',
        'name' => 'Teacher — post an update for a whole class',
        'method' => 'POST', 'path' => 'v1/moments', 'auth' => 'teacher',
        'body' => ['type' => 'meal', 'classroom_id' => '{{classroom_id}}', 'except_child_ids' => ['{{second_child_id}}'], 'payload' => ['meal' => 'lunch', 'amount' => 'half'], 'client_ref' => '{{$guid}}'],
        'description' => "**`client_ref` (UUID اختياري، موصى به):** معرّف التحديث من التطبيق. إعادة إرسال نفس الطلب (بعد انقطاع الشبكة) تعيد نفس التحديث بـ `200` بدل إنشاء نسخة ثانية — أساس الإرسال بدون إنترنت.\n\nتحديث واحد لعدة أطفال: `child_ids` (حتى 100) **أو** `classroom_id` مع `except_child_ids` (\"الغداء لكل الفصل ما عدا عمر\").\n\n| `type` | الحقول | مثال `summary` |\n|---|---|---|\n| `meal` | `payload.meal` (breakfast/lunch/snack/dinner)، `payload.amount` (all/most/half/little/none) | وجبة الغداء: أكل نصفه |\n| `nap` | `payload.from`, `payload.to` (HH:MM) | نام من 12:30 إلى 14:00 |\n| `diaper` | `payload.kind` (wet/dirty/dry) | تغيير حفاض (مبلل) |\n| `mood` | `payload.mood` (happy/calm/tired/sad/upset) | المزاج: سعيد |\n| `activity` | `payload.title` | نشاط: الرسم |\n| `note`, `health`, `incident` | `body` مطلوب | — |\n| `photo` | `photos[]` (multipart) | صور جديدة |\n\nكل أسرة يصلها **إشعار واحد** حتى لو لها أكثر من طفل في التحديث. `summary` يُعرض بلغة الطلب."
            .$errors('`403` | `forbidden` | ليس من موظفي الحضانة', '`422` | `validation_failed` | حقول النوع ناقصة، أو `child_ids` مع `classroom_id` معاً'),
    ],
    'incident_store' => [
        'expect' => [201],
        'folder' => '7. الحائط اليومي — Daily Wall',
        'name' => 'Teacher — report a minor incident',
        'method' => 'POST', 'path' => 'v1/moments', 'auth' => 'teacher',
        'body' => ['type' => 'incident', 'child_ids' => ['{{child_id}}'], 'body' => 'تعثّر في الحديقة وخدش ركبته خدشاً بسيطاً، تم تطهيره ووضع لاصق.'],
        'script' => "if (pm.response.code === 201) pm.collectionVariables.set('incident_id', pm.response.json().data.id);",
        'description' => "نفس `POST /v1/moments` بـ `type: incident`. يصل للأسرة **فوراً حتى في ساعات الهدوء** (أولوية عالية، وSMS إن لم يصل الإشعار)، والتفاصيل لا تظهر على شاشة القفل.\n\n`requires_ack: true` — تنتظر الإدارة إقرار وليّ الأمر (`children[].acknowledged_at`)."
            .$errors('`422` | `validation_failed` | `body` مطلوب'),
    ],
    'moments_index' => [
        'expect' => [200],
        'folder' => '7. الحائط اليومي — Daily Wall',
        'name' => 'Staff — the wall',
        'method' => 'GET', 'path' => 'v1/moments', 'auth' => 'teacher',
        'query' => ['classroom_id' => '{{classroom_id}}'],
        'description' => "حائط الحضانة للموظفين، الأحدث أولاً (20 في الصفحة). فلاتر اختيارية: `child_id`، `classroom_id`، `date` (YYYY-MM-DD بتوقيت القاهرة).\n\nالموظفون يرون كل الأطفال الموسومين وحالة إقرار الحوادث."
            .$errors('`403` | `forbidden` | ليس من موظفي الحضانة', '`422` | `validation_failed` | طفل/فصل من حضانة أخرى'),
    ],
    'ward_moments' => [
        'expect' => [200],
        'folder' => '7. الحائط اليومي — Daily Wall',
        'name' => "Guardian — my child's wall",
        'method' => 'GET', 'path' => 'v1/me/wards/{{child_id}}/moments', 'auth' => 'guardian',
        'description' => "يوميات طفلي، الأحدث أولاً. `children` يحوي طفلي فقط (لا أسماء أطفال آخرين). روابط الصور صالحة 30 دقيقة.\n\nيتطلب `my_link.can_view_wall`."
            .$errors('`403` | `forbidden` | ليست لديك صلاحية عرض الحائط', '`404` | `not_found` | ليس من أطفالك أو محظور بحكم حضانة'),
    ],
    'moment_acknowledge' => [
        'expect' => [200],
        'folder' => '7. الحائط اليومي — Daily Wall',
        'name' => 'Guardian — acknowledge an incident',
        'method' => 'POST', 'path' => 'v1/me/wards/{{child_id}}/moments/{{incident_id}}/acknowledge', 'auth' => 'guardian',
        'description' => 'وليّ الأمر يؤكد أنه اطّلع على تقرير الحادثة. التكرار آمن.'
            .$errors('`404` | `not_found` | ليس من أطفالك أو التحديث لا يخص طفلك', '`422` | `validation_failed` | التحديث ليس حادثة'),
    ],
    'moments_destroy' => [
        'expect' => [204],
        'folder' => '7. الحائط اليومي — Daily Wall',
        'name' => 'Delete an update',
        'method' => 'DELETE', 'path' => 'v1/moments/{{moment_id}}', 'auth' => 'teacher',
        'description' => 'المعلمة تحذف تحديثها خلال 24 ساعة، والإدارة تحذف أي تحديث في أي وقت. الصور تُمحى فوراً (روابطها تعيد `404`)، والحذف يُسجَّل في التدقيق.'
            .$errors('`403` | `forbidden` | بعد 24 ساعة (للمعلمة) أو ليس من الموظفين', '`404` | `not_found` | غير موجود أو محذوف'),
    ],

    /* -------------------------------------------------------- Bahga Pay */
    'payment_methods' => [
        'expect' => [200],
        'folder' => '8. وليّ الأمر: الفواتير والدفع — Bahga Pay',
        'name' => 'Payment methods',
        'method' => 'GET', 'path' => 'v1/me/payment-methods', 'auth' => 'guardian',
        'description' => "طرق الدفع الإلكتروني المتاحة في هذه الحضانة.\n\n| `kind` | سلوك التطبيق |\n|---|---|\n| `redirect` | افتح `checkout_url` في متصفح/WebView (Paymob) |\n| `payment_code` | اعرض `payment_code` ليدفعه في أي منفذ فوري |\n\nإذا كان `online_payments_enabled: false` اعرض «الدفع لدى الحضانة»."
            .$errors('`404` | `not_found` | بهجة باي غير مفعّلة لهذه الحضانة'),
    ],
    'invoices_index' => [
        'expect' => [200],
        'folder' => '8. وليّ الأمر: الفواتير والدفع — Bahga Pay',
        'name' => 'My invoices',
        'method' => 'GET', 'path' => 'v1/me/invoices', 'auth' => 'guardian',
        'script' => "const first = pm.response.json().data?.[0];\nif (first) pm.collectionVariables.set('invoice_id', first.id);",
        'description' => "الفواتير الموجّهة لوليّ الأمر **كدافع** (الأحدث أولاً، cursor pagination).\n\n- كل المبالغ بالشكل `{ \"piasters\": 185000, \"formatted\": \"1,850 ج.م\" }` — احسب بـ `piasters` (عدد صحيح) واعرض `formatted`.\n- `status`: `open` / `partially_paid` / `paid`، و`is_overdue` + `days_overdue` للتنبيه."
            .$errors('`404` | `not_found` | بهجة باي غير مفعّلة لهذه الحضانة'),
    ],
    'invoices_show' => [
        'expect' => [200],
        'folder' => '8. وليّ الأمر: الفواتير والدفع — Bahga Pay',
        'name' => 'My invoice',
        'method' => 'GET', 'path' => 'v1/me/invoices/{{invoice_id}}', 'auth' => 'guardian',
        'description' => 'الفاتورة ببنودها (`items`: رسوم موجبة وخصومات سالبة) والإيصالات المدفوعة (`receipts`).'
            .$errors('`404` | `not_found` | لست دافع هذه الفاتورة، أو غير موجودة'),
    ],
    'checkout' => [
        'expect' => [200, 201, 422],
        'folder' => '8. وليّ الأمر: الفواتير والدفع — Bahga Pay',
        'name' => 'Pay invoice online (checkout)',
        'method' => 'POST', 'path' => 'v1/me/invoices/{{invoice_id}}/checkout', 'auth' => 'guardian',
        'body' => ['gateway' => 'paymob'],
        'script' => "const d = pm.response.json().data;\nif (d?.checkout_url) console.log('Open to pay:', d.checkout_url);\nif (d?.payment_code) console.log('Fawry code:', d.payment_code);",
        'description' => "يفتح عملية دفع للمبلغ المتبقي بالكامل.\n\n| `gateway` | النتيجة |\n|---|---|\n| `paymob` | `checkout_url` — افتحه للدفع ببطاقة أو محفظة |\n| `fawry` | `payment_code` — يُدفع في أي منفذ فوري قبل `expires_at` |\n\n- `201` عملية جديدة، `200` إعادة نفس العملية السارية (لا يُنشأ كود جديد عند كل ضغطة).\n- **لا تعتبر الفاتورة مدفوعة من التطبيق**: التأكيد يصل من البوابة (webhook)؛ أعد طلب الفاتورة لمعرفة الحالة.\n- محدود بـ 10 طلبات في الدقيقة."
            .$errors('`402` | `plan_upgrade_required` | خطة الحضانة لا تشمل الدفع الإلكتروني', '`404` | `not_found` | لست دافع الفاتورة', '`422` | `validation_failed` | بوابة غير معروفة/غير متاحة (`errors.gateway`) أو لا مبلغ مستحق (`errors.invoice`)'),
    ],

    /* -------------------------------------------------------- Owner */
    'subscription' => [
        'expect' => [200],
        'folder' => '9. الإدارة: الاشتراك — Owner',
        'name' => 'Subscription & usage',
        'method' => 'GET', 'path' => 'v1/subscription', 'auth' => 'owner',
        'description' => "اشتراك الحضانة في بهجة مع `entitlements` الفعلية (الخطة + الإضافات + الاستثناءات): المزايا المتاحة، والحدود مع الاستهلاك الحالي (`limit: null` = غير محدود).\n\n**الصلاحية:** المالك / المدير."
            .$errors('`403` | `forbidden` | ليس مديراً', '`404` | `not_found` | لا يوجد اشتراك نشط'),
    ],
    'plans' => [
        'expect' => [200],
        'folder' => '9. الإدارة: الاشتراك — Owner',
        'name' => 'Plans',
        'method' => 'GET', 'path' => 'v1/subscription/plans', 'auth' => 'owner',
        'script' => "const plans = pm.response.json().data || [];\nif (plans.length) pm.collectionVariables.set('plan_id', plans[plans.length - 1].id);",
        'description' => 'الخطط المتاحة للترقية/التغيير بالسعر (ج.م/شهر) والحدود والمزايا.'
            .$errors('`403` | `forbidden` | ليس مديراً'),
    ],
    'change_plan' => [
        'expect' => [200],
        'folder' => '9. الإدارة: الاشتراك — Owner',
        'name' => 'Change plan',
        'method' => 'POST', 'path' => 'v1/subscription/change-plan', 'auth' => 'owner',
        'body' => ['subscription_plan_id' => '{{plan_id}}'],
        'description' => 'تغيير خطة الحضانة. التخفيض لخطة حدودها أقل من الاستهلاك الحالي مرفوض.'
            .$errors('`402` | `plan_limit_reached` | عدد الأطفال الحالي أكبر من حد الخطة الجديدة', '`403` | `forbidden` | ليس مديراً', '`422` | `validation_failed` | خطة غير موجودة'),
    ],

    /* ------------------------------------------------------- Webhooks */
    'webhook_paymob' => [
        'expect' => [200, 401],
        'folder' => '10. Webhooks (للبوابات فقط — Server to server)',
        'name' => 'Paymob — transaction processed callback',
        'method' => 'POST', 'path' => 'webhooks/payments/paymob', 'auth' => null, 'tenant' => false,
        'query' => ['hmac' => '<HMAC-SHA512>'],
        'body' => ['type' => 'TRANSACTION', 'obj' => ['id' => 192837465, 'success' => true, 'amount_cents' => 185000, 'order' => ['merchant_order_id' => 'BHG1-…']]],
        'description' => "**لا يستدعيه التطبيق.** تستدعيه Paymob بعد كل معاملة. للتوثيق والاختبار فقط.\n\n- الثقة من التوقيع وحده: HMAC-SHA512 على حقول المعاملة، في `?hmac=`.\n- الحدث المكرر يُقبل (`duplicate`) ولا يُطبَّق مرتين.\n- يعالَج في طابور `payments`."
            .$errors('`401` | `invalid_signature` | التوقيع غير صحيح (يُحفظ للتحقيق ولا يُعالَج)'),
    ],
    'webhook_fawry' => [
        'expect' => [200, 401],
        'folder' => '10. Webhooks (للبوابات فقط — Server to server)',
        'name' => 'Fawry — server notification V2',
        'method' => 'POST', 'path' => 'webhooks/payments/fawry', 'auth' => null, 'tenant' => false,
        'body' => ['fawryRefNumber' => '966512345', 'merchantRefNumber' => 'BHG1-…', 'orderStatus' => 'PAID', 'orderAmount' => '1850.00', 'messageSignature' => '<SHA-256>'],
        'description' => '**لا يستدعيه التطبيق.** إشعار فوري بعد الدفع بالكود، موقّع بـ SHA-256 (`messageSignature`).'
            .$errors('`401` | `invalid_signature` | التوقيع غير صحيح'),
    ],

    /* --------------------------------------------------------- Errors */
    'error_route' => [
        'expect' => [404],
        'folder' => '11. مرجع الأخطاء — Error reference',
        'name' => 'Unknown endpoint',
        'method' => 'GET', 'path' => 'v1/does-not-exist', 'auth' => null, 'tenant' => false,
        'description' => 'مثال على شكل الخطأ الموحّد لمسار غير موجود.',
    ],
    'error_language' => [
        'expect' => [422],
        'folder' => '11. مرجع الأخطاء — Error reference',
        'name' => 'Same error in English (Accept-Language: en)',
        'method' => 'PATCH', 'path' => 'v1/me/wards/{{child_id}}/notifications', 'auth' => 'guardian',
        'body' => [],
        'description' => 'نفس خطأ التحقق بالإنجليزية عند إرسال `Accept-Language: en`. الحقل `code` لا يتغير باللغة — ابنِ منطق التطبيق عليه.',
    ],
];
