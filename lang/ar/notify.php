<?php

return [
    'page_title' => 'إشعار واتساب',
    'breadcrumb' => 'إشعار واتساب',
    'title'      => 'خدمة إشعارات واتساب',
    'subtitle'   => 'مثل SMTP للبريد الإلكتروني — يرسل موقعك رسالة ويستقبلها المسؤول على واتساب.',

    'instance_connected'    => 'الإرسال عبر :name',
    'no_connected_instance' => 'لا توجد جلسة واتساب متصلة — قم بتوصيل واحدة لتفعيل التسليم.',

    'config_title'    => 'الإعدادات',
    'config_subtitle' => 'وجهة الإشعارات وشكلها.',
    'enable_label'    => 'تفعيل واجهة برمجة الإشعارات',
    'enable_hint'     => 'عند التعطيل، يرد /api/notify/send بالحالة 403.',

    'admin_phone_label'   => 'رقم واتساب المسؤول',
    'admin_phone_hint'    => 'صيغة E.164 مع رمز الدولة (مثال +212600000000). تُرسل جميع الإشعارات إلى هذا الرقم.',
    'admin_phone_invalid' => 'أدخل رقم هاتف صالحًا بالصيغة الدولية (7 إلى 15 رقمًا، مع + اختيارية).',

    'prefix_label' => 'بادئة الرسالة (اختياري)',
    'prefix_hint'  => 'تُضاف قبل كل إشعار، مثل [Wavadesk]. اتركها فارغة لعدم استخدام بادئة.',

    'save'  => 'حفظ التغييرات',
    'saved' => 'تم حفظ إعدادات الإشعارات.',

    'credentials_title'    => 'بيانات اعتماد الواجهة البرمجية',
    'credentials_subtitle' => 'استخدمها من موقعك أو من الخادم الخلفي.',
    'endpoint_label'       => 'نقطة النهاية',
    'api_key_label'        => 'قيمة الترويسة X-Api-Key',
    'api_key_hint'         => 'تعامل معها كأنها كلمة مرور.',
    'regenerate_from_profile' => 'إعادة الإنشاء من الملف الشخصي',
    'no_api_key'           => 'لا توجد لديك مفتاح API بعد.',
    'generate_api_key'     => 'أنشئ مفتاحًا من الملف الشخصي.',

    'test_title'       => 'إرسال اختبار',
    'test_subtitle'    => 'أرسل إشعارًا فوريًا إلى رقم المسؤول.',
    'test_placeholder' => 'نص الرسالة…',
    'test_default'     => 'إشعار اختبار من WavaDesk.',
    'test_button'      => 'إرسال إشعار الاختبار',
    'test_sent'        => 'تم إرسال إشعار الاختبار. تحقق من واتساب.',
    'test_failed'      => 'فشل إرسال إشعار الاختبار. راجع الإعدادات أعلاه.',

    'integration_title'    => 'التكامل',
    'integration_subtitle' => 'انسخ المقتطفات إلى موقعك لإرسال إشعارات الطلبات/الأحداث.',
    'laravel_hint'         => 'خزّن مفتاح API في ملف .env تحت الاسم WAVADESK_API_KEY.',

    'response_ref_title' => 'مرجع الاستجابة',
    'response_ref_ok'    => 'true عند النجاح وfalse خلاف ذلك. تتضمن الأخطاء رسالة توضيحية.',

    'recent_title'    => 'أحدث الإشعارات',
    'recent_subtitle' => 'آخر 20 رسالة أُرسلت عبر هذه الخدمة.',
    'col_sent_at'     => 'وقت الإرسال',
    'col_message'     => 'الرسالة',
    'col_status'      => 'الحالة',
];
