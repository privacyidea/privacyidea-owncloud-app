function sign_u2f_request(challenge, keyHandle, appId) {
    var signRequests = [{"challenge": challenge,
        "keyHandle": keyHandle,
        "appId": appId,
        "version": "U2F_V2"}];
    u2f.sign(signRequests, function (result) {
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