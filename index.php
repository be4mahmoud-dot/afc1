<?php
header('Content-Type: application/json; charset=utf-8');

// ==========================================
// 1. PANEL LOGISTICS CONFIGURATION
// ==========================================
$PANEL_URL   = "http://204.168.171.121:25500"; 
$PANEL_URL2   = "http://tv.gobixo.com"; 
$ADMIN_USER  = "admin";                       
$ADMIN_PASS  = "AOBOCO@M";                    

// توليد بيانات المشترك الجديد تلقائياً
$new_username = "sub" . rand(1000, 9999);
$new_password = "pwd" . rand(1000, 9999);

// حساب تاريخ الانتهاء تلقائياً ليكون بعد (يوم واحد = 24 ساعة) من الآن بالصيغة المطلوبة للوحتك
$expiry_date = date("Y-m-d H:i", strtotime("+1 day"));

// ==========================================
// 2. خطوة (1): تسجيل الدخول للحصول على جلسة (Session) حقيقية
// ==========================================
$cookie_file = dirname(__FILE__) . '/xtream_session.txt';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $PANEL_URL . "/login.php",
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        "username" => $ADMIN_USER,
        "password" => $ADMIN_PASS,
        "login"    => ""
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR      => $cookie_file, // حفظ الـ Session ID في ملف الكوكيز
    CURLOPT_COOKIEFILE     => $cookie_file,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
]);
curl_exec($ch); 

// ==========================================
// 3. خطوة (2): إرسال البيانات بنفس صيغة الـ Form بتاعت لوحتك بالظبط
// ==========================================
// استخدام الـ Array المكتوبة بنفس طريقتك وبنفس حقول المصفوفات للـ Bouquets والـ Access Output
$post_fields = [
    "bouquets_selected" => json_encode(["1", "2"]), // الباقات المختارة (1 و 2) كما أرسلتها
    "username"          => $new_username,            // اليوزر الجديد
    "password"          => $new_password,            // الباسورد الجديد
    "mac_address_mag"   => "",
    "mac_address_e2"    => "",
    "member_id"         => 1,
    "max_connections"   => 1,
    "exp_date"          => $expiry_date,             // تاريخ الانتهاء (يوم واحد من الآن)
    "admin_notes"       => "Created via Script",
    "reseller_notes"    => "",
    "force_server_id"   => 0,
    "forced_country"    => "",
    "access_output"     => [1, 2, 3],                // حقول الـ access_output[] المحددة لديك
    "submit_user"       => "Add"                     // الزر الأساسي لحفظ العميل
];

// إرسال الطلب لملف إدارة اليوزرات الأساسي في لوحتك (يُسمى عادة user.php أو api.php لتلقي الفورم)
curl_setopt_array($ch, [
    CURLOPT_URL            => $PANEL_URL . "/user.php", // يمكنك تغييرها لـ api.php لو لوحتك تدمج الفورم هناك
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($post_fields), // إرسالها بصيغة Application/x-www-form-urlencoded
    CURLOPT_COOKIEFILE     => $cookie_file,                  // استخدام الجلسة الموثقة لتخطي الحماية
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true                           // التحرك مع إعادة التوجيه لو اللوحة بتعمل Redirect بعد الإضافة
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// مسح ملف الجلسة المؤقت للأمان
if (file_exists($cookie_file)) {
    @unlink($cookie_file);
}

// ==========================================
// 4. عرض روابط التشغيل والنجاح
// ==========================================
// في لوحات الـ Form، إذا تم إضافة المستخدم بنجاح، اللوحة تعيد التوجيه (Redirect) أو ترجع كود 200 بدون أخطاء واضحة
if ($http_code === 200) {
    
    $m3u_playlist = $PANEL_URL2 . "/get.php?username=" . $new_username . "&password=" . $new_password . "&type=m3u&output=ts";

    echo json_encode([
        "status"  => "success",
        "message" => "تم معالجة طلب إنشاء العميل ليوم واحد بناءً على صيغة اللوحة الخاصة بك.",
        "account" => [
            "username" => $new_username,
            "password" => $new_password,
            "expires"  => $expiry_date
        ],
        "links" => [
            "m3u_link" => $m3u_playlist
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} else {
    echo json_encode([
        "status"       => "error",
        "message"      => "فشل الاتصال باللوحة عبر المسار المباشر للـ Form.",
        "http_code"    => $http_code
    ], JSON_PRETTY_PRINT);
}
?>
