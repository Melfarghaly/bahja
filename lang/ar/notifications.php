<?php

/*
| Notification texts, by NotificationType. Push title + body; SMS uses the
| body. Keep bodies short (they show on a lock screen).
*/

return [
    'child_arrived' => [
        'title' => 'وصل :child الحضانة',
        'body' => 'وصل :child إلى :nursery الساعة :time.',
    ],
    'child_picked_up' => [
        'title' => 'انصرف :child',
        'body' => 'انصرف :child مع :collector من :nursery الساعة :time.',
    ],
    'late_pickup' => [
        'title' => 'لم يُستلم :child بعد',
        'body' => ':nursery: لم يتم استلام :child حتى الآن (موعد الانصراف :deadline). يرجى التواصل مع الحضانة.',
    ],
    'late_pickup_managers' => [
        'title' => 'تأخر استلام :child',
        'body' => ':nursery: :child ما زال بالحضانة بعد موعد الانصراف :deadline بأكثر من 45 دقيقة.',
    ],
];
