(function ($, Drupal, drupalSettings) {
  "use strict";

  Drupal.behaviors.otp_verification = {
    attach: function (context, settings) {

      $(once('otp_verification', `#drupal-modal .${drupalSettings.form_id}`)).on('submit', function(event) {
        event.preventDefault();
        var formData = new FormData(this);
        var enteredOtp = formData.get('otp');
        var errorBox = document.getElementById('otp-field-error');
        if (!enteredOtp) {
          errorBox.innerHTML = 'Otp field is not in required formate.';
        }
        else {
          $.ajax({
            url:  Drupal.url('validate/otp'), 
            type: 'POST', 
            dataType: 'json', 
            contentType:"application/json; charset=utf-8",
            data: enteredOtp,
            success: function (response) {
              errorBox.innerHTML = '<div class="otp-error">' + response.message + '</div>';
              if (response.status == true) {
                errorBox.innerHTML = '<div class="otp-success">' + response.message + '</div>';
                setTimeout(() => {
                  var modal = document.getElementsByClassName('ui-dialog-titlebar-close');
                  modal[0].click();
                }, 500);
              }
            },
          });
        }
      });
    }
  }
})(jQuery, Drupal, drupalSettings);
