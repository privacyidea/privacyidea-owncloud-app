OC.L10N.register(
    "twofactor_privacyidea",
    {
    "Communication to the privacyIDEA server succeeded. The user was successfully authenticated." : "Die Kommunikation mit dem privacyIDEA Server war erfolgreich. Der Benutzer konnte erfolgreich authentifiziert werden.",
    "Failed to authenticate." : "Authentifizieren fehlgeschlagen.",
    "Communication to the privacyIDEA server succeeded. However, the user failed to authenticate." : "Die Kommunikation mit dem privacyIDEA Server war erfolgreich. Die Authentifizierung des Benutzers ist allerdings fehlgeschlagen.",
    "The service account credentials are correct!" : "Die Zugangsdaten des Service-Accounts sind korrekt.",
    "But we recommend to update your privacyIDEA server." : "Wir empfehlen jedoch, deinen privacyIDEA-Server zu aktualisieren.",
    "Verify" : "Bestätigen",
    "Alternate Login Options" : "Alternative Login Möglichkeiten",
    "Check if service account has correct permissions" : "Bitte prüfen ob das Service-Konto die richtigen Rechte/Rollen hat",
    "Failed to fetch authentication token. Wrong HTTP return code: " : "Empfang des Authentifizierungstokens fehlgeschlagen. Falsche HTTP-Antwort:",
    "Failed to fetch authentication token." : "Fehler beim Empfang des Authentifizierungs-Token.",
    "privacyIDEA 2FA" : "privacyIDEA-2FA",
    "Open documentation" : "Dokumentation öffnen",
    "Poll in browser" : "Poll in browser",
    "Poll in browser URL: " : "Poll in browser URL: ",
    "Check this to activate polling for a push token confirmation right from your browser." : "Check this to activate polling for a push token confirmation right from your browser.",
    "\n                In a second step of authentication, the user is asked to provide a one\n                time password. The users devices are managed in privacyIDEA. The\n                authentication request is forwarded to privacyIDEA.\n            " : "\nIn einem zweiten Schritt wird der Benutzer aufgefordert, ein Einmal-Passwort einzugeben. Die Authentisierungs-Geräte der Benutzer werden im privacyIDEA Server verwaltet. Die Anmelde-Anfrage wird an den privacyIDEA Server geschickt.",
    "Configuration" : "Konfiguration",
    "Activate two factor authentication with privacyIDEA " : "Zwei-Faktor-Authentifzierung mit privacyIDEA einschalten ",
    "\n                            Before activating two factor authentication with privacyIDEA, please assure, that the connection to\n                            your privacyIDEA-server is configured correctly.\n                        " : "\nBevor die Zwei-Faktor-Authentifizierung mit privacyIDEA einschaltet wird, prüfe bitte, ob die Kommunikation zum privacyIDEA Server funktioniert.",
    "URL of the privacyIDEA Server" : "Die URL des privacyIDEA Servers",
    "\n                            Please use the base URL of your privacyIDEA instance.\n                            For compatibility reasons, you may also specify the URL of the /validate/check endpoint.\n                        " : "\nBitte benutze die Basis-URL des privacyIDEA Servers. Für Kompatibilitätsgründe kannst du auch noch den Endpoint /validate/check angeben.",
    "Timeout" : "Zeitüberschreitung",
    "default is 5" : "Standard ist 5",
    "\n                            Sets timeout to privacyIDEA for login in seconds.\n                        " : "\nZeitüberschreitung der privacyIDEA-Anmeldung in Sekunden angeben.",
    "Include groups" : "Gruppen einschließen",
    "Exclude groups" : "Gruppen ausschließen",
    "\n\t\t                    If include is selected, just the groups in this field need to do 2FA.\n\t\t                " : "\nWenn Einschließen ausgewählt, müssen nur die Gruppen in diesem Feld die Zwei-Faktor-Authentifizierung verwenden.",
    "\n\t\t                    If you select exclude, these groups can use 1FA (all others need 2FA).\n\t\t                " : "\nWenn Ausschließen ausgewählt, können diese Gruppen die einfache Authentifizierung verwenden (alle anderen benötigen die Zwei-Faktor-Authentifizierung).",
    "\n\t\t                    Exclude ip addresses\n\t\t                " : "\n\t\t Ausschließen von IP-Adressen\n\t\t",
    "\n\t\t                    You can either add single IPs like 10.0.1.12,10.0.1.13, a range like 10.0.1.12-10.0.1.113 or combinations like 10.0.1.12-10.0.1.113,192.168.0.15\n\t\t                " : "\n\t\t Du kannst entweder einzelne IPs wie 10.0.1.12, 10.0.1.13, einen Bereich wie 10.0.1.12-10.0.1.113 oder Kombinationen wie 10.0.1.12-10.0.1.113, 192.168.0.15 hinzufügen.\n\t\t",
    "User Realm" : "Benutzer-Bereich",
    "\n                    Select the user realm, if it is not the default one.\n                " : "\n Wähle den Benutzerbereich aus, wenn es sich nicht um den Standardbereich handelt.",
    "Preferred token type" : "Bevorzugter Tokentyp",
    "Select the token type, which should be used first if triggered." : "Wähle den Token Typ der zuerst benutzt wird wenn angefordert.",
    "\n                    Verify the SSL certificate.\n                " : "\nDas SSL Zertifikat überprüfen.",
    "\n                        Do not uncheck this in productive environments!\n                    " : "\nBitte deaktiviere dies nicht in einer produktiven Umgebung!",
    "Ignore the system wide proxy settings and send authentication requests to privacyIDEA directly." : "Die systemweiten Proxy-Einstellungen ignorieren und den HTTP-Request für die Authentifizierung direkt an privacyIDEA senden.",
    "Test authentication by supplying username and password \n            that should be checked against privacyIDEA:" : "Sie können die Konfiguration testen, indem Sie einen Benutzernamen und das Einmal-Passwort, das an privacyIDEA gesendet werden soll, hier eingeben:",
    "User" : "Benutzer",
    "Password" : "Kennwort",
    "Test" : "Testen",
    "Challenge Response" : "Challenge-Response",
    "Auto Submit OTP Length" : "Auto Submit OTP Length",
    "Trigger challenges for challenge-response tokens. Check this if you employ, e.g., SMS or E-Mail tokens." : "Challenges für Challenge-Response-Token auslösen. Aktiviere diese Konfiguration, wenn du bspw. SMS oder E-Mail-Token verwenden möchtest.",
    "Let the user log in if the user is not found in privacyIDEA." : "Lassen Sie den Benutzer einloggen, obwohl er nicht in privacyIDEA gefunden wurde.",
    "If you want to turn on the form-auto-submit function after x number of characters \n                are entered into the OTP input field, set the expected OTP length here. \n                Note: Only digits as the parameter\\'s value allowed here." : "If you want to turn on the form-auto-submit function after x number of characters \n                are entered into the OTP input field, set the expected OTP length here. \n                Note: Only digits as the parameter\\'s value allowed here.",
    "Username of privacyIDEA service account" : "Benutzername des privacyIDEA-Dienstekontos",
    "Password of privacyIDEA service account" : "Passwort des privacyIDEA-Dienstekontos",
    "Check Credentials" : "Überprüfe die Login-Daten"
},
"nplurals=2; plural=(n != 1);");
