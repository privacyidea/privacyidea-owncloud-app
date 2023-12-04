function autoSubmit(){
    if (document.getElementById("autoSubmitOtpLength") != null
    && document.getElementById("otp").value.length === document.getElementById("autoSubmitOtpLength"))
    {
        document.forms["piLoginForm"].submit();
    }
}

function eventListeners(){
    document.getElementById("otp").addEventListener("onKeyUp", autoSubmit);
}

// Wait until the document is ready
document.addEventListener("DOMContentLoaded", function() {eventListeners();});