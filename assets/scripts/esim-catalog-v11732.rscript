(function(){
  'use strict';
  var root=document.querySelector('[data-esim-catalog]');
  if(!root)return;
  var destination=root.querySelector('[data-esim-destination]');
  var results=root.querySelector('[data-esim-results]');
  var status=root.querySelector('[data-esim-status]');
  var api='/api/esim-catalog-v11732.php';

  function money(value){return new Intl.NumberFormat('en-US',{style:'currency',currency:'USD'}).format(value);}
  function setStatus(message){status.textContent=message;}
  function optionLabel(plan){
    var data=plan.data_gb>0?(plan.data_gb+' GB'):'Flexible data';
    var validity=plan.validity_days>0?(plan.validity_days+' days'):'Plan validity applies';
    return '<article class="esim-plan"><p class="eyebrow">Resplendent eSIM</p><h3>'+escapeHtml(data)+'</h3><p>'+escapeHtml(validity)+' · '+escapeHtml(plan.scope==='global'?'Global coverage':'Destination coverage')+'</p><strong>'+money(plan.retail_price)+'</strong><a class="text-link" href="esim-order?plan='+encodeURIComponent(plan.id)+'&destination='+encodeURIComponent(destination.value)+'">Select this plan</a></article>';
  }
  function escapeHtml(value){var node=document.createElement('span');node.textContent=String(value);return node.innerHTML;}
  function loadOffers(){
    if(!destination.value){results.innerHTML='';setStatus('Choose your destination to compare available plans.');return;}
    setStatus('Loading available Resplendent plans…');results.innerHTML='';
    fetch(api+'?action=offers&destination='+encodeURIComponent(destination.value),{cache:'no-store',headers:{Accept:'application/json'}})
      .then(function(r){return r.json().then(function(j){if(!r.ok)throw new Error(j.message||'Unable to load plans.');return j;});})
      .then(function(payload){var plans=payload.offers||[];results.innerHTML=plans.map(optionLabel).join('');setStatus(plans.length?plans.length+' plans available.':'No plans are currently available for this destination.');})
      .catch(function(error){setStatus(error.message);});
  }
  fetch(api+'?action=destinations',{cache:'no-store',headers:{Accept:'application/json'}})
    .then(function(r){return r.json().then(function(j){if(!r.ok)throw new Error(j.message||'Unable to load destinations.');return j;});})
    .then(function(payload){destination.innerHTML='<option value="">Choose destination</option>';(payload.destinations||[]).forEach(function(item){var o=document.createElement('option');o.value=item.id;o.textContent=item.name;destination.appendChild(o);});destination.disabled=false;setStatus('Choose your destination to compare available plans.');})
    .catch(function(error){setStatus(error.message);});
  destination.addEventListener('change',loadOffers);
})();
