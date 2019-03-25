function sign_u2f_request(challenge, keyHandle, appId) {

    var registeredKeys = [];

    registeredKeys.push({
        version: "U2F_V2",
        keyHandle: keyHandle
    });

    u2f.sign(appId, challenge, registeredKeys, function (result) {
        $('#signatureData').val(result.signatureData);
        $('#clientData').val(result.clientData);
        $('#piLoginForm').submit();
    });
}

$(document).ready(function(){
    sign_u2f_request($('#u2f_challenge').val(),
        $('#u2f_keyHandle').val(),
        $('#u2f_appId').val());
});
