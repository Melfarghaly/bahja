# Bahga API v1 — دليل المطوّر

واجهة التطبيقات (المعلمة، وليّ الأمر، الإدارة) على `/api/v1`.

## الملفات
| الملف | الوصف |
|---|---|
| `Bahga-API.postman_collection.json` | المجموعة الكاملة: 53 طلباً في 12 مجلداً، ولكل طلب أمثلة نجاح وخطأ **حقيقية** |
| `Bahga-API-local.postman_environment.json` | بيئة محلية بحسابات البيانات التجريبية |
| `samples/photo.jpg` | صورة تجريبية لطلب نشر الصور (multipart) |

## التشغيل محلياً
```bash
php artisan migrate:fresh --seed
php artisan serve
```
في Postman: استورد الملفين، اختر البيئة **Bahga — local**، ثم شغّل المجموعة كاملة (Run collection) أو ابدأ بمجلد **1. المصادقة**.

حسابات البيانات التجريبية (كلمة المرور `password`):

| الدور | الدخول |
|---|---|
| مالكة الحضانة | `owner@bahga.test` |
| معلمة | `teacher@bahga.test` |
| وليّة أمر | `01000000002` |

## كيف وُلّدت الأمثلة (ولماذا هي موثوقة)
المجموعة **لا تُكتب يدوياً**. المولّد `tests/Postman/GeneratePostmanCollectionTest.php`:
1. يبني بيانات واقعية (حضانة، مالكة، معلمة، وليّة أمر، وصيّ محظور، أطفال، فواتير).
2. ينفّذ كل سيناريو (نجاح وكل حالة خطأ) على الـ API بتوكنات Sanctum حقيقية.
3. **يتحقق** أن كل سيناريو أعاد الحالة المتوقعة — وإلا يفشل التوليد.
4. يحفظ الاستجابات الحقيقية كأمثلة Postman.

بعد أي تعديل على الـ API أعد التوليد:
```bash
php artisan test --group=postman
```
تعريفات الطلبات والأوصاف في `tests/Postman/endpoints.php`.

## اختبار السيرفر بالمجموعة (Smoke test)
كل طلب يحوي اختباراً لحالته المتوقعة، فتشغيل المجموعة على سيرفر حقيقي يكشف أي انحراف:
```bash
npx newman run docs/api/Bahga-API.postman_collection.json \
  -e docs/api/Bahga-API-local.postman_environment.json \
  --working-dir docs/api \
  --env-var base_url=http://127.0.0.1:8000/api
```
> حد الدخول 6 محاولات/دقيقة: انتظر دقيقة بين تشغيلين متتاليين.
>
> محلياً بدون بيانات بوابات الدفع يعيد طلب الدفع الإلكتروني `422`، وتعيد الـ webhooks `401` (لا تقبل إلا توقيع البوابة) — وهذا هو السلوك الصحيح.

## الـ Endpoints
| الطريقة | المسار | من | الوصف |
|---|---|---|---|
| POST | `/v1/auth/tokens` | الجميع | دخول (بريد أو هاتف) → توكن |
| POST | `/v1/auth/otp` | وليّ أمر | طلب رمز دخول بالـ SMS |
| POST | `/v1/auth/otp/verify` | وليّ أمر | التحقق من الرمز → توكن |
| PATCH | `/v1/me` | الجميع | تعديل الاسم والبريد |
| DELETE | `/v1/auth/tokens/current` | الجميع | تسجيل خروج من هذا الجهاز |
| GET | `/v1/me` | الجميع | الملف + الحضانات + الأدوار والصلاحيات |
| GET | `/v1/classrooms` | موظفون | الفصول |
| GET | `/v1/children` | موظفون | الأطفال (بحث، فصل، حالة، صفحات) |
| GET | `/v1/children/{id}` | موظفون | الطفل مع الأوصياء وحالة الحضانة |
| POST | `/v1/children` | إدارة | تسجيل طفل |
| PATCH | `/v1/children/{id}` | إدارة | تعديل طفل |
| POST | `/v1/children/{id}/guardians` | إدارة | ربط/تحديث وليّ أمر |
| DELETE | `/v1/children/{id}/guardians/{user}` | إدارة | فصل وليّ أمر |
| GET | `/v1/attendance` | موظفون | كشف الحضور اليومي |
| POST | `/v1/attendance/check-in` | موظفون | تسجيل حضور |
| POST | `/v1/attendance/check-in/bulk` | موظفون | حضور جماعي / مزامنة بدون إنترنت |
| POST | `/v1/attendance/check-out` | موظفون | انصراف مع التحقق من المستلم (وصيّ، QR، كود تصريح، أو تجاوز الإدارة) |
| POST | `/v1/attendance/pickup/verify` | موظفون | شاشة الباب: من المستلم وأي الأطفال مسموح له بهم |
| GET | `/v1/me/pickup-code` | وليّ أمر | QR الاستلام المتغيّر (60 ثانية) |
| GET | `/v1/me/wards/{id}/pickup-passes` | وليّ أمر | تصاريح الاستلام لطفلي |
| POST | `/v1/me/wards/{id}/pickup-passes` | وليّ أمر | تصريح استلام لمرة واحدة (كود SMS) |
| DELETE | `/v1/me/pickup-passes/{id}` | وليّ أمر | إلغاء تصريح |
| GET | `/v1/me/wards` | وليّ أمر | أطفالي |
| GET | `/v1/me/wards/{id}` | وليّ أمر | طفلي |
| GET | `/v1/me/wards/{id}/attendance` | وليّ أمر | سجل حضور طفلي |
| PATCH | `/v1/me/wards/{id}/notifications` | وليّ أمر | إشعارات التطبيق والـ SMS لهذا الطفل |
| POST | `/v1/me/devices` | الجميع | تسجيل جهاز التطبيق للإشعارات (توكن FCM) |
| DELETE | `/v1/me/devices` | الجميع | إيقاف الإشعارات على هذا الجهاز |
| GET | `/v1/me/notifications` | الجميع | صندوق الإشعارات + عدد غير المقروء |
| POST | `/v1/me/notifications/{id}/read` | الجميع | قراءة إشعار |
| POST | `/v1/me/notifications/read-all` | الجميع | قراءة الكل |
| GET | `/v1/moments` | موظفون | حائط الحضانة (فلاتر: طفل، فصل، تاريخ) |
| POST | `/v1/moments` | موظفون | نشر تحديث (JSON) أو صور (multipart) لطفل أو لفصل |
| DELETE | `/v1/moments/{id}` | موظفون | حذف تحديث (المعلمة خلال 24 ساعة، الإدارة دائماً) |
| GET | `/v1/me/wards/{id}/moments` | وليّ أمر | يوميات طفلي |
| POST | `/v1/me/wards/{id}/moments/{moment}/acknowledge` | وليّ أمر | الإقرار بتقرير حادثة |
| GET | `/v1/me/wards/{id}/photo-consent` | وليّ أمر | إذن التصوير الحالي |
| PUT | `/v1/me/wards/{id}/photo-consent` | وليّ أمر أساسي | منح/سحب إذن التصوير والصور الجماعية |
| GET | `/v1/me/payment-methods` | وليّ أمر | طرق الدفع المتاحة |
| GET | `/v1/me/invoices` | وليّ أمر | فواتيري |
| GET | `/v1/me/invoices/{id}` | وليّ أمر | الفاتورة والإيصالات |
| POST | `/v1/me/invoices/{id}/checkout` | وليّ أمر | الدفع الإلكتروني |
| GET | `/v1/subscription` | إدارة | الاشتراك والاستهلاك |
| GET | `/v1/subscription/plans` | إدارة | الخطط |
| POST | `/v1/subscription/change-plan` | إدارة | تغيير الخطة |
| POST | `/webhooks/payments/{paymob\|fawry}` | البوابات | إشعارات الدفع (موقّعة) |

## الإشعارات الفورية (Push)
- التطبيق يسجّل توكن FCM عبر `POST /v1/me/devices` بعد كل دخول وعند تجديد التوكن، ولا يحتاج إلغاءه عند الخروج.
- على الخادم: `PUSH_DRIVER=fcm` و`FCM_CREDENTIALS` (ملف حساب الخدمة من Firebase). الافتراضي `log` يكتب الإشعارات في السجل فقط.
- تحتاج عامل طابور: `php artisan queue:work --queue=notifications,payments,default`، والجدولة (`schedule:run`) لإرسال ما أُجِّل بسبب ساعات الهدوء.

## صور الحائط اليومي
- تُخزَّن على قرص خاص (`WALL_DISK`، افتراضياً `local` = `storage/app/private`؛ في الإنتاج bucket خاص على S3/R2) — **لا تستخدم القرص العام أبداً**.
- تُعرض فقط عبر روابط موقّعة صالحة 30 دقيقة (`photos[].url`)، والجدولة تحذف الصور بعد مدة الاحتفاظ في الخطة (`wall:prune-media`).

مسارات الاستلام الآمن متاحة فقط عندما تكون `capabilities.safe_pickup` مفعّلة للحضانة في `GET /v1/me`.

القواعد العامة (الهيدرز، شكل الاستجابة، المبالغ، أكواد الأخطاء، حدود الطلبات) موجودة في وصف المجموعة نفسها داخل Postman.
