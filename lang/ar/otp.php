<?php

return [
    'page_title' => 'خدمة OTP',
    'breadcrumb' => 'خدمة OTP',
    'title'      => 'خدمة OTP عبر واتساب',
    'subtitle'   => 'أرسل رموز التحقق عبر واتساب من مشروع Laravel أو أي مشروع ويب/API خاص بك.',

    'instance_connected'   => 'الإرسال عبر :name',
    'no_connected_instance'=> 'لا توجد جلسة واتساب متصلة — قم بربط جلسة لتفعيل الإرسال.',

    'config_title'    => 'الإعدادات',
    'config_subtitle' => 'يتحكم في توليد رموز OTP وطريقة إرسالها.',
    'enable_label'    => 'تفعيل واجهة OTP',
    'enable_hint'     => 'عند التعطيل، يُعيد /api/otp/send الرمز 403.',
    'code_length_label'=> 'طول الرمز',
    'digits'          => 'أرقام',
    'ttl_label'       => 'مدة الصلاحية (بالدقائق)',
    'ttl_hint'        => 'بين 1 و 60. تُطبَّق على الرموز الجديدة فقط.',
    'template_label'  => 'نص الرسالة',
    'template_hint'   => 'استخدم {code} للرمز و {ttl} لمدة الصلاحية بالدقائق. {code} إلزامي.',
    'save'            => 'حفظ التغييرات',
    'saved'           => 'تم حفظ إعدادات OTP.',
    'template_missing_code' => 'يجب أن يحتوي نص الرسالة على المتغيّر {code}.',

    'credentials_title'   => 'بيانات اعتماد الواجهة',
    'credentials_subtitle'=> 'استخدمها للمصادقة عند الاستدعاء من مشروعك الآخر.',
    'base_url_label'      => 'الرابط الأساسي',
    'api_key_label'       => 'قيمة الترويسة X-Api-Key',
    'api_key_hint'        => 'تعامل معها ككلمة مرور.',
    'regenerate_from_profile' => 'إعادة توليد من الملف الشخصي',
    'no_api_key'          => 'لا يوجد لديك مفتاح API بعد.',
    'generate_api_key'    => 'قم بتوليد مفتاح من الملف الشخصي.',

    'integration_title'   => 'الدمج',
    'integration_subtitle'=> 'مقتطفات جاهزة للنسخ لاستدعاء الخدمة من مشروعك الآخر.',
    'send_code'           => 'إرسال رمز OTP',
    'verify_code'         => 'التحقق من رمز',
    'laravel_hint'        => 'يتطلب guzzlehttp/guzzle. احفظ مفتاح API في ملف .env باسم WAVADESK_API_KEY.',

    'response_ref_title'  => 'مرجع الاستجابة',
    'response_ref_ok'     => 'true عند النجاح، false خلاف ذلك. تتضمن الأخطاء رسالة.',
    'response_ref_retry'  => 'عدد الثواني قبل إعادة المحاولة (فقط عند التبريد، HTTP 429).',
];
