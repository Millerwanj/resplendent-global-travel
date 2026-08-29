(function(){
  'use strict';
  var q=new URLSearchParams(location.search), planId=(q.get('plan')||'').trim(), destinationId=(q.get('destination')||'').trim();
  var status=document.querySelector('[data-plan-status]'), form=document.querySelector('[data-esim-order-form]'), submit=form&&form.querySelector('[data-submit-button]');
  var facts={destination:document.querySelector('[data-plan-destination]'),data:document.querySelector('[data-plan-data]'),validity:document.querySelector('[data-plan-validity]'),coverage:document.querySelector('[data-plan-coverage]'),price:document.querySelector('[data-plan-price]')};
  function money(v){return new Intl.NumberFormat('en-US',{style:'currency',currency:'USD'}).format(v);}
  function fail(message){if(status){status.textContent=message;status.className='esim-order-unavailable';}if(submit)submit.disabled=true;}
  if(!planId||!/^[a-f0-9]{32}$/i.test(destinationId)){fail('This plan link is incomplete. Return to the eSIM page and choose the destination and plan again.');return;}
  Promise.all([
    fetch('/api/esim-catalog-v11732.php?action=destinations',{cache:'no-store',headers:{Accept:'application/json'}}).then(function(r){return r.json();}),
    fetch('/api/esim-catalog-v11732.php?action=offers&destination='+encodeURIComponent(destinationId),{cache:'no-store',headers:{Accept:'application/json'}}).then(function(r){return r.json();})
  ]).then(function(values){
    var destinationPayload=values[0], offerPayload=values[1];
    if(!destinationPayload.ok||!offerPayload.ok)throw new Error('Live package details could not be confirmed.');
    var destination=(destinationPayload.destinations||[]).find(function(d){return d.id===destinationId;});
    var plan=(offerPayload.offers||[]).find(function(p){return String(p.id)===planId;});
    if(!destination||!plan)throw new Error('That package is no longer available. Please choose a current plan.');
    var data=Number(plan.data_gb)>0?plan.data_gb+' GB':'Flexible data';
    var validity=Number(plan.validity_days)>0?plan.validity_days+' days':'Plan validity applies';
    var coverage=plan.scope==='global'?'Global coverage':'Destination coverage';
    facts.destination.textContent=destination.name;facts.data.textContent=data;facts.validity.textContent=validity;facts.coverage.textContent=coverage;facts.price.textContent=money(plan.retail_price);
    form.elements.destination.value=destination.name;
    form.elements.esim_destination_name.value=destination.name;
    form.elements.esim_package_id.value=String(plan.id);
    form.elements.esim_package.value=data+' · '+validity+' · '+coverage;
    form.elements.esim_price.value=money(plan.retail_price);
    form.elements.message.value='I have selected the '+data+' eSIM plan for '+destination.name+' at '+money(plan.retail_price)+'. Please continue this order through payment and provisioning.';
    if(status){status.textContent='Plan confirmed against the live catalogue. Complete your details to continue.';status.className='esim-order-status';}
    if(submit)submit.disabled=false;
  }).catch(function(error){fail(error.message||'Unable to confirm this plan.');});
})();
