window.onload=function(){
    setInterval(function() {
        $.ajax({
            url:location.href,
            type:'post',
            success:window.onload=function(result){
                if(result.search("<input type=\"hidden\" id=\"ResponseStatus\" value=\"false\">") === -1){
                    location.reload()
                }
            }
        })
    }, 1000);
};