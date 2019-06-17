window.onload=function(){
    setInterval(function() {
        $.ajax({
            url:location.href,
            type:'post',
            data: $('#piLoginForm').serialize(),
            success:window.onload=function(result){
                if(result.search("<span class=\"warning\">") === -1){
                    location.reload()
                }
            }
        })
    }, 2000);
};