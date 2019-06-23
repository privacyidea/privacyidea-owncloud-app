OC.L10N.register(
    "twofactor_privacyidea",
    {
    "Communication to the privacyIDEA server succeeded. The user was successfully authenticated." : "ผู้ใช้ได้รับการตรวจสอบสิทธิ์จากเซิร์ฟเวอร์ privacyIDEA(2FA) เรียบร้อยแล้ว",
    "Failed to authenticate." : "การตรวจสอบล้มเหลว",
    "Communication to the privacyIDEA server succeeded. However, the user failed to authenticate." : "ไม่สามารถตรวจสอบสิทธิ์ของผู้ใช้กับเซิร์ฟเวอร์ privacyIDEA(2FA)",
    "The service account credentials are correct!" : "หนังสือรับรองของบริการบัญชีถูกต้อง",
    "Failed to trigger challenges. Wrong HTTP return code: " : "เรียกทริกเกอร์ไม่สำเร็จ รหัสข้อผิดพลาดของHTTP:",
    "Failed to trigger challenges." : "ข้อผิดพลาดที่อาจก่อให้เกิดปัญหา",
    "The push token was not yet verified." : "โทเค็น push ยังไม่ได้รับการยืนยัน",
    "Check if service account has correct permissions" : "ตรวจสอบว่าบัญชีบริการมีสิทธิ์ที่ถูกต้อง",
    "Failed to fetch authentication token. Wrong HTTP return code: " : "ไม่สามารถดึงโทเค็นการรับรองความถูกต้อง รหัสข้อผิดพลาดของHTTP:",
    "Failed to fetch authentication token." : "ไม่สามารถดึงข้อมูลการตรวจสอบสิทธิ์ของโทเค็น",
    "privacyIDEA 2FA" : "privacyIDEA 2FA",
    "Open documentation" : "เปิดเอกสาร",
    "\n                In a second step of authentication the user is asked to provide a one\n                time password. The users devices are managed in privacyIDEA. The\n                authentication request is forwarded to privacyIDEA.\n            " : "\nในขั้นตอนที่สองของการตรวจสอบ ผู้ใช้จะขอให้ระบุรหัสผ่านแบบใช้เพียงครั้งเดียว\nอุปกรณ์ของผู้ใช้จะเปิดใช้งาน privacyIDEA\nแล้วคำขอการตรวจสอบสิทธิ์จะถูกส่งต่อไปยัง privacyIDEA",
    "Configuration" : "การกำหนดค่า",
    "Activate two factor authentication with privacyIDEA." : "เปิดใช้งานการพิสูจน์ตัวตนแบบสองขั้นตอนด้วย privacyIDEA",
    "\n                            Before activating two factor authentication with privacyIDEA, please asure, that the connection to\n                            your privacyIDEA-server is configured correctly.\n                        " : "\nก่อนเปิดใช้งานการพิสูจน์ตัวตนแบบสองปัจจัยกับ privacyIDEA โปรดตรวจสอบว่า\nได้เชื่อมต่อกับเซิร์ฟเวอร์ privacyIDEA ของคุณที่ได้กำหนดค่าเรียบร้อยแล้ว",
    "URL of the privacyIDEA Server" : "URL ของเซิร์ฟเวอร์ privacyIDEA",
    "\n                            Please use the base URL of your privacyIDEA instance.\n                            For compatibility reasons, you may also specify the URL of the /validate/check endpoint.\n                        " : "\nโปรดใช้ URL พื้นฐานของอินสแตนซ์ privacyIDEA ของคุณ\nด้วยเหตุผลเรื่องความเข้ากันได้คุณต้องระบุ URL ปลายทางของ /validate/check",
    "Timeout" : "หมดเวลา",
    "default is 5" : "ค่าเริ่มต้นคือ 5",
    "\n                            Sets timeout to privacyIDEA for login in seconds.\n                        " : "\nตั้งระยะหมดเวลาสำหรับ privacyIDEA เพื่อเข้าสู่ระบบ (หน่วยเป็นวินาที)",
    "Include groups" : "เฉพาะกลุ่มเหล่านี้",
    "Exclude groups" : "ยกเว้นกลุ่มเหล่านี้",
    "\n\t\t                    If include is selected, just the groups in this field need to do 2FA.\n\t\t                " : "\n\t\tเฉพาะกลุ่มที่เลือกไว้จะต้องทำ 2FA\n\t\t",
    "\n\t\t                    If you select exclude, these groups can use 1FA (all others need 2FA).\n\t\t                " : "\n\t\tหากเลือกยกเว้น กลุ่มเหล่านี้จะใช้ 1FA (ส่วนกลุ่มอื่นๆจะใช้ 2FA)\n\t\t",
    "\n\t\t                    Exclude ip addresses\n\t\t                " : "\n\t\t                     ยกเว้นที่อยู่ IP \n\t\t",
    "\n\t\t                    You can either add single IPs like 10.0.1.12,10.0.1.13, a range like 10.0.1.12-10.0.1.113 or combinations like 10.0.1.12-10.0.1.113,192.168.0.15\n\t\t                " : "\n\t\t                     คุณสามารถเพิ่ม IP แบบเดี่ยวเช่น 10.0.1.12,10.0.1.13 หรือจะเพิ่ม IP แบบกลุ่มเช่น 10.0.1.12-10.0.1.113 หรือรวมกันทั้งสอบแบบก็ได้",
    "User Realm" : "ขอบเขตของผู้ใช้",
    "\n                    Select the user realm, if it is not the default one.\n                " : "\nเลือกขอบเขตของผู้ใช้ ถ้าไม่ใช่ค่าเริ่มต้น",
    "\n                    Verify the SSL certificate.\n                " : "\nยืนยันใบรับรอง SSL",
    "\n                        Do not uncheck this in productive environments!\n                    " : "\nห้ามยกเลิกตัวเลือกนี้เพราะจะมีผลกับผลิตภัณฑ์",
    "\n                    Ignore the system wide proxy settings and send authentication\n                    requests to privacyIDEA directly.\n                " : "\nละเว้นการตั้งค่าพร็อกซีแบบกว้างของระบบและ\nให้ส่งคำขอการรับรองความถูกต้องไปยัง privacyIDEA โดยตรง",
    "Test" : "ทดสอบ",
    "Test Authentication by supplying username and password that are checked against privacyIDEA:" : "ทดสอบการตรวจสอบสิทธิ์โดยการระบุชื่อผู้ใช้และรหัสผ่านที่ได้รับการตรวจสอบกับ privacyIDEA:",
    "User" : "ชื่อผู้ใช้",
    "Password" : "รหัสผ่าน",
    "Challenge Response" : "ทดสอบการตอบสนอง",
    "Trigger challenges for challenge-response tokens. Check this if you employ, e.g., SMS or E-Mail tokens." : "เลือกตัวเลือกนี้หากคุณใช้โทเค็นทดสอบการตอบสนอง เช่น SMS หรืออีเมลโทเค็น",
    "Username of privacyIDEA service account" : "ชื่อผู้ใช้ของบัญชีบริการ privacyIDEA",
    "Password of privacyIDEA service account" : "รหัสผ่านของบัญชีบริการ privacyIDEA",
    "Check Credentials" : "ตรวจสอบหนังสือรับรอง"
},
"nplurals=1; plural=0;");
