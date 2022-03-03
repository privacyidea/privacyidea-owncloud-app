OC.L10N.register(
    "twofactor_privacyidea",
    {
    "Communication to the privacyIDEA server succeeded. The user was successfully authenticated." : "Обмен с сервером privacyIDEA прошёл успешно. Пользователь успешно аутентифицирован.",
    "Failed to authenticate." : "Не удалось аутентифицироваться.",
    "Communication to the privacyIDEA server succeeded. However, the user failed to authenticate." : "Обмен с сервером privacyIDEA прошёл успешно. Однако пользователю не удалось аутентифицироваться.",
    "The service account credentials are correct!" : "Удостоверяющие данные служебной учётной записи корректны!",
    "But we recommend to update your privacyIDEA server." : "Однако рекомендуется обновить ваш сервер privacyIDEA.",
    "Verify" : "Подтвердить",
    "Check if service account has correct permissions" : "Проверьте, что служебной учётной записи даны надлежащие разрешения",
    "Failed to fetch authentication token. Wrong HTTP return code: " : "Не удалось получить токен аутентификации. Неверный код ответа HTTP:",
    "Failed to fetch authentication token." : "Не удалось получить токен аутентификации.",
    "privacyIDEA 2FA" : "Двухфакторная аутентификация privacyIDEA",
    "Open documentation" : "Открыть документацию",
    "\n                In a second step of authentication the user is asked to provide a one\n                time password. The users devices are managed in privacyIDEA. The\n                authentication request is forwarded to privacyIDEA.\n            " : "\nНа втором шаге аутентификации пользователю предлагают предоставить\nодноразовый пароль. Устройства пользователя управляются в privacyIDEA.\nЗапросы аутентификации передаются в privacyIDEA.",
    "Configuration" : "Конфигурация",
    "Activate two factor authentication with privacyIDEA." : "Включить двухфакторную аутентификацию с помощью privacyIDEA.",
    "\n                            Before activating two factor authentication with privacyIDEA, please asure, that the connection to\n                            your privacyIDEA-server is configured correctly.\n                        " : "\n                            Перед тем, как включать двухфакторную аутентификацию через privacyIDEA пожалуйста удостоверьтесь, что соединение с вашим\n                            сервером privacyIDEA настроено правильно.\n                        ",
    "URL of the privacyIDEA Server" : "Строка адреса сервера privacyIDEA",
    "\n                            Please use the base URL of your privacyIDEA instance.\n                            For compatibility reasons, you may also specify the URL of the /validate/check endpoint.\n                        " : "\n                            Пожалуйста используйте базовый URL вашего экземпляра privacyIDEA.\n                            По соображениям совместимости так же разрешается указывать URL конечной точки /validate/check.\n                        ",
    "Timeout" : "Таймаут",
    "default is 5" : "по умолчанию 5",
    "\n                            Sets timeout to privacyIDEA for login in seconds.\n                        " : "\n                            Задаёт таймаут privacyIDEA для входа, в секундах.\n                        ",
    "Include groups" : "Включить группы",
    "Exclude groups" : "Исключить группы",
    "\n\t\t                    If include is selected, just the groups in this field need to do 2FA.\n\t\t                " : "\n\t\t                    Если выбрано включить, то только группы в этом поле обязаны прохдить 2ФА.\n\t\t                ",
    "\n\t\t                    If you select exclude, these groups can use 1FA (all others need 2FA).\n\t\t                " : "\n\t\t                    Если выбрано исключить, эти группы могут использовать 1ФА (все остальные обязаны проходить 2ФА).\n\t\t                ",
    "\n\t\t                    Exclude ip addresses\n\t\t                " : "\n\t\t                    Исключить адреса IP\n\t\t                ",
    "\n\t\t                    You can either add single IPs like 10.0.1.12,10.0.1.13, a range like 10.0.1.12-10.0.1.113 or combinations like 10.0.1.12-10.0.1.113,192.168.0.15\n\t\t                " : "\n\t\t                    Можно или добавлять отдельные IP, как 10.0.1.12,10.0.1.13, диапазон, как 10.0.1.12-10.0.1.113, или их комбинации, как 10.0.1.12-10.0.1.113,192.168.0.15\n\t\t                ",
    "User Realm" : "Область пользователя",
    "\n                    Select the user realm, if it is not the default one.\n                " : "\n                    Выберите область пользователя, если не та, что по умолчанию.\n                ",
    "\n                    Verify the SSL certificate.\n                " : "\n                    Проверить сертификат SSL.\n                ",
    "\n                        Do not uncheck this in productive environments!\n                    " : "\n                        Не отключайте это в эксплуатационной среде!\n                    ",
    "Ignore the system wide proxy settings and send authentication requests to privacyIDEA directly." : "Игнорировать общесистемные настройки прокси и отправлять запросы аутентификации непосредственно в privacyIDEA.",
    "User" : "Пользователь",
    "Password" : "Пароль",
    "Test" : "Проверка",
    "Challenge Response" : "Запрос-ответ",
    "Trigger challenges for challenge-response tokens. Check this if you employ, e.g., SMS or E-Mail tokens." : "Создавать запросы для токенов запрос-ответ. Включите это, если задействуете, например, SMS или почтовые запросы.",
    "Let the user log in if the user is not found in privacyIDEA." : "Дать пользователю войти, если пользователь не найден в privacyIDEA.",
    "Username of privacyIDEA service account" : "Имя пользователя служебной учётной записи privacyIDEA",
    "Password of privacyIDEA service account" : "Пароль служебной учётной записи privacyIDEA",
    "Check Credentials" : "Проверить удостоверяющие данные"
},
"nplurals=4; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<12 || n%100>14) ? 1 : n%10==0 || (n%10>=5 && n%10<=9) || (n%100>=11 && n%100<=14)? 2 : 3);");
