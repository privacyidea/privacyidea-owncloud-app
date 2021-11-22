$(document).ready(function ()
{
    signU2FRequest($('#u2f_challenge').val(),
        $('#u2f_keyHandle').val(),
        $('#u2f_appId').val());
});

function signU2FRequest(challenge, keyHandle, appId)
{
    var registeredKeys = [];
    registeredKeys.push({
        version: "U2F_V2",
        keyHandle: keyHandle
    });

    u2f.sign(appId, challenge, registeredKeys, function (result)
    {
        $('#signatureData').val(result.signatureData);
        $('#clientData').val(result.clientData);
        $('#piLoginForm').submit();
    });
}