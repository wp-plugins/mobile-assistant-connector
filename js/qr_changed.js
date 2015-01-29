var $ = jQuery.noConflict();

$(document).ready(function() {
    var mobassist_login = $("#mobassist_login");
    var mobassist_pass = $("#mobassist_pass");
    var _old_login = $(mobassist_login).val();
    var _old_pass = $(mobassist_pass).val();

    var onCredetChange = function() {
        var mobassist_qr_code_changed = $("#mobassist_qr_code_changed");
        var qr = $("#mobassist_qr_code");

        if(_old_login != $(mobassist_login).val() || _old_pass != $(mobassist_pass).val()) {


            if($(qr).width() > 0 && $(qr).attr("src") != "") {
                $(mobassist_qr_code_changed).width($(qr).width()).show("fast");
                qr.css('opacity', '0.1').show('fast');
            } else {
                $(mobassist_qr_code_changed).hide("fast");
                qr.css('opacity', '1').show('fast');
            }
        } else {
            $(mobassist_qr_code_changed).hide("fast");
            qr.css('opacity', '1').show('fast');
        }
    };

    mobassist_login.on("keyup", function () {
        onCredetChange();
    });

    $(mobassist_pass).on("keyup", function () {
        onCredetChange();
    });

    $('#submit-form').click(function() {
       if (mobassist_login.val().length == 0 || mobassist_pass.val().length == 0) {
           alert('Login and password cannot be empty.');
           return false;
       }
    });
});
