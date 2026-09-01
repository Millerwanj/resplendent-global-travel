(function(){
  'use strict';
  var form=document.querySelector('[data-esim-order-form]');
  if(!form)return;
  form.addEventListener('submit',function(e){
    var email=form.querySelector('#email'), confirm=form.querySelector('#email-confirm');
    if(email&&confirm&&email.value.trim().toLowerCase()!==confirm.value.trim().toLowerCase()){
      e.preventDefault();
      confirm.setCustomValidity('Email addresses do not match.');
      confirm.reportValidity();
      var status=form.querySelector('[data-form-status]');if(status){status.textContent='Please make sure both email addresses match.';status.classList.add('error');}
      return;
    }
    if(confirm)confirm.setCustomValidity('');
  });
  var email=form.querySelector('#email'), confirm=form.querySelector('#email-confirm');
  [email,confirm].forEach(function(el){if(el)el.addEventListener('input',function(){if(confirm)confirm.setCustomValidity('');});});
})();
