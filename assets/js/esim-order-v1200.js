(function(){
  'use strict';
  var q=new URLSearchParams(location.search), planId=(q.get('plan')||'').trim().toLowerCase(), destinationId=(q.get('destination')||'').trim().toLowerCase();
  var status=document.querySelector('[data-plan-status]'), form=document.querySelector('[data-esim-order-form]'), submit=form&&form.querySelector('[data-submit-button]'), formStatus=form&&form.querySelector('[data-form-status]');
  var facts={destination:document.querySelector('[data-plan-destination]'),data:document.querySelector('[data-plan-data]'),validity:document.querySelector('[data-plan-validity]'),coverage:document.querySelector('[data-plan-coverage]'),price:document.querySelector('[data-plan-price]')};
  var ready=false;
  function money(v){return new Intl.NumberFormat('en-US',{style:'currency',currency:'USD'}).format(v);}
  function fail(message){ready=false;if(status){status.textContent=message;status.className='esim-order-unavailable';}if(submit)submit.disabled=true;}
  function setFormMessage(message,error){if(!formStatus)return;formStatus.textContent=message;formStatus.className='form-status esim-order-status'+(error?' error':'');}
  if(!form||!planId||!/^[a-f0-9]{64}$/.test(planId)||!/^[a-f0-9]{32}$/.test(destinationId)){fail('This plan link is incomplete. Return to the eSIM page and choose the destination and plan again.');return;}

  Promise.all([
    fetch('/api/esim-catalog-v11732.php?action=destinations',{cache:'no-store',headers:{Accept:'application/json'}}).then(function(r){return r.json();}),
    fetch('/api/esim-catalog-v11732.php?action=offers&destination='+encodeURIComponent(destinationId),{cache:'no-store',headers:{Accept:'application/json'}}).then(function(r){return r.json();})
  ]).then(function(values){
    var destinationPayload=values[0], offerPayload=values[1];
    if(!destinationPayload.ok||!offerPayload.ok)throw new Error('Live package details could not be confirmed.');
    var destination=(destinationPayload.destinations||[]).find(function(d){return String(d.id).toLowerCase()===destinationId;});
    var plan=(offerPayload.offers||[]).find(function(p){return String(p.id).toLowerCase()===planId;});
    if(!destination||!plan)throw new Error('That package is no longer available. Please choose a current plan.');
    var data=Number(plan.data_gb)>0?plan.data_gb+' GB':'Flexible data', validity=Number(plan.validity_days)>0?plan.validity_days+' days':'Plan validity applies', coverage=plan.scope==='global'?'Global coverage':'Destination coverage';
    facts.destination.textContent=destination.name;facts.data.textContent=data;facts.validity.textContent=validity;facts.coverage.textContent=coverage;facts.price.textContent=money(plan.retail_price);
    if(status){status.textContent='Plan confirmed against the live catalogue. Complete your details to continue to secure payment.';status.className='esim-order-status';}
    ready=true;if(submit)submit.disabled=false;
  }).catch(function(error){fail(error.message||'Unable to confirm this plan.');});

  form.addEventListener('submit',async function(e){
    e.preventDefault();
    if(!ready)return;
    var name=form.querySelector('#name'), email=form.querySelector('#email'), confirm=form.querySelector('#email-confirm'), phone=form.querySelector('#phone'), consent=form.elements.consent;
    if(!name.value.trim()||!email.value.trim()||!email.checkValidity()){setFormMessage('Please enter your name and a valid email address.',true);return;}
    if(email.value.trim().toLowerCase()!==confirm.value.trim().toLowerCase()){confirm.setCustomValidity('Email addresses do not match.');confirm.reportValidity();setFormMessage('Please make sure both email addresses match.',true);return;}
    confirm.setCustomValidity('');
    if(!consent.checked){setFormMessage('Please confirm that we may send order and eSIM delivery updates.',true);return;}
    submit.disabled=true;submit.textContent='Opening secure payment…';setFormMessage('Rechecking your plan and creating your secure PesaPal checkout…',false);
    try{
      var r=await fetch('/api/connectivity/create-order.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({plan_id:planId,destination_id:destinationId,name:name.value.trim(),email:email.value.trim(),phone:phone.value.trim()})});
      var d=await r.json();if(!r.ok||!d.ok||!d.redirect_url)throw new Error(d.message||'Secure checkout could not be created.');
      window.location.assign(d.redirect_url);
    }catch(err){submit.disabled=false;submit.textContent='Continue to review & payment';setFormMessage(err.message||'Secure checkout could not be created.',true);}
  });
})();
