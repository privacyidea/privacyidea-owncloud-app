OC.L10N.register(
    "twofactor_privacyidea",
    {
    "Communication to the privacyIDEA server succeeded. The user was successfully authenticated." : "نجح الاتصال بخادم privacyIDEA. تمت مصادقة المستخدم بنجاح.",
    "Failed to authenticate." : "فشلت المصادقة.",
    "Communication to the privacyIDEA server succeeded. However, the user failed to authenticate." : "نجح الاتصال بخادم privacyIDEA. ومع ذلك فشلت مصادقة المستخدم.",
    "The service account credentials are correct!" : "بيانات اعتماد حساب الخدمة صحيحة!",
    "But we recommend to update your privacyIDEA server." : "لكن نوصي بتحديث خادم privacyIDEA.",
    "Verify" : "التحقق من الصحة",
    "Check if service account has correct permissions" : "تحقق مما إذا كان حساب الخدمة يحتوي على أذونات صحيحة أم لا",
    "Failed to fetch authentication token. Wrong HTTP return code: " : "تعذّر إحضار رمز المصادقة. رمز إرجاع HTTP خطأ: ",
    "Failed to fetch authentication token." : "تعذّر إحضار رمز المصادقة.",
    "privacyIDEA 2FA" : "privacyIDEA 2FA",
    "Open documentation" : "فتح المستندات",
    "\n                In a second step of authentication the user is asked to provide a one\n                time password. The users devices are managed in privacyIDEA. The\n                authentication request is forwarded to privacyIDEA.\n            " : "\n                في الخطوة الثانية من المصادقة، يُطلب من المستخدم توفير كلمة مرور\n                صالحة لمرة واحدة. تُدار أجهزة المستخدمين في خادم privacyIDEA. يُعاد توجيه\n                طلب المصادقة إلى privacyIDEA.\n            ",
    "Configuration" : "تكوين",
    "Activate two factor authentication with privacyIDEA." : "نشِّط المصادقة ثنائية العوامل باستخدام privacyIDEA.",
    "\n                            Before activating two factor authentication with privacyIDEA, please asure, that the connection to\n                            your privacyIDEA-server is configured correctly.\n                        " : "\n                            المصادقة ثنائية العوامل باستخدام privacyIDEA، يُرجى التأكد من تكوين الاتصال\n                            بخادم privacyIDEAبشكل صحيح.\n                        ",
    "URL of the privacyIDEA Server" : "عنوان URL لخادم privacyIDEA",
    "\n                            Please use the base URL of your privacyIDEA instance.\n                            For compatibility reasons, you may also specify the URL of the /validate/check endpoint.\n                        " : "\n                            يُرجى استخدام عنوان URL الأساس لمثيل privacyIDEA.\n                            لأسباب متعلقة بالتوافق، قد تحدد أيضًا عنوان URL لنقطة نهاية التحقق من الصحة/الفحص.\n                        ",
    "Timeout" : "المهلة",
    "default is 5" : "الإعدادات الافتراضية هي 5",
    "\n                            Sets timeout to privacyIDEA for login in seconds.\n                        " : "\n                            تعيين مهلة تسجيل الدخول إلى خادم privacyIDEA بالثوانٍ.\n                        ",
    "Include groups" : "تضمين مجموعات",
    "Exclude groups" : "استبعاد مجموعات",
    "\n\t\t                    If include is selected, just the groups in this field need to do 2FA.\n\t\t                " : "\n\t\t                    في حالة تحديد تضمين، تحتاج المجموعات الموجودة في هذا الحقل إلى القيام بـ 2FA.\n\t\t                ",
    "\n\t\t                    If you select exclude, these groups can use 1FA (all others need 2FA).\n\t\t                " : "\n\t\t                    في حالة تحديد استبعاد، يمكن أن تستخدم هذه المجموعات 1FA (أما جميع المجموعات الأخرى تحتاج 2FA).\n\t\t                ",
    "\n\t\t                    Exclude ip addresses\n\t\t                " : "\n\t\t                    استبعاد عناوين ip\n\t\t                ",
    "\n\t\t                    You can either add single IPs like 10.0.1.12,10.0.1.13, a range like 10.0.1.12-10.0.1.113 or combinations like 10.0.1.12-10.0.1.113,192.168.0.15\n\t\t                " : "\n\t\t                    يمكنك إضافة إمّا عناوين IP مفردة مثل، 10.0.1.12، أو 10.0.1.13، وإمّا نطاق مثل 10.0.1.12-10.0.1.113 وإمّا مجموعات مثل، 10.0.1.12-10.0.1.113,192.168.0.15\n\t\t                ",
    "User Realm" : "نطاق المستخدم",
    "\n                    Select the user realm, if it is not the default one.\n                " : "\n                    حدد نطاق المستخدم، إذا لم يكن النطاق الافتراضي.\n                ",
    "\n                    Verify the SSL certificate.\n                " : "\n                    تحقق من صحة شهادة SSL.\n                ",
    "\n                        Do not uncheck this in productive environments!\n                    " : "\n                        لا تلغ تحديد هذا في البيئات الإنتاجية!\n                    ",
    "User" : "المستخدم",
    "Password" : "كلمة المرور",
    "Test" : "الاختبار",
    "Challenge Response" : "استجابة ارتياب",
    "Trigger challenges for challenge-response tokens. Check this if you employ, e.g., SMS or E-Mail tokens." : "ابدأ التحديات لرموز استجابة الارتياب. وتحقق من ذلك إذا كنت تستخدمه، على سبيل المثال رموز الرسائل القصيرة أو البريد الإلكتروني.",
    "Let the user log in if the user is not found in privacyIDEA." : "اسمح للمستخدم بتسجيل الدخول إذا لم يتم العثور عليه في خادم privacyIDEA.",
    "Username of privacyIDEA service account" : "اسم مستخدم حساب خدمة privacyIDEA",
    "Password of privacyIDEA service account" : "كلمة مرور حساب خدمة privacyIDEA",
    "Check Credentials" : "افحص بيانات الاعتماد"
},
"nplurals=6; plural=n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 && n%100<=99 ? 4 : 5;");
