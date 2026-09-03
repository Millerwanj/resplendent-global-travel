(function(){
  'use strict';
  var root=document.querySelector('[data-esim-catalog]');
  if(!root)return;
  var destination=root.querySelector('[data-esim-destination]');
  var search=root.querySelector('[data-esim-search]');
  var datalist=root.querySelector('[data-esim-datalist]');
  var results=root.querySelector('[data-esim-results]');
  var status=root.querySelector('[data-esim-status]');
  var more=root.querySelector('[data-esim-more]');
  var popular=[].slice.call(root.querySelectorAll('[data-esim-popular]'));
  var api='/api/esim-catalog-v11732.php';
  var destinations=[], currentPlans=[], expanded=false, offerRequest=0;

  more.hidden=true;
  more.disabled=true;

  function money(value){return new Intl.NumberFormat('en-US',{style:'currency',currency:'USD'}).format(value);}
  function esc(value){var node=document.createElement('span');node.textContent=String(value);return node.innerHTML;}
  function setStatus(message,state){status.textContent=message;status.className='esim-catalogue-status'+(state?' is-'+state:'');}
  function normal(value){return String(value||'').trim().toLowerCase().replace(/\s+/g,' ');}
  function findDestination(name){
    var n=normal(name), exact=destinations.find(function(d){return normal(d.name)===n;});
    if(exact)return exact;
    return destinations.find(function(d){return normal(d.name).indexOf(n)!==-1||n.indexOf(normal(d.name))!==-1;});
  }
  function setDestination(item){
    if(!item)return;
    search.value=item.name;destination.value=item.id;expanded=false;loadOffers();
  }
  function choosePlans(plans){
    if(plans.length<=3)return plans;
    var sorted=plans.slice().sort(function(a,b){return (Number(a.data_gb)||0)-(Number(b.data_gb)||0)||(Number(a.retail_price)||0)-(Number(b.retail_price)||0);});
    return [sorted[0],sorted[Math.floor((sorted.length-1)/2)],sorted[sorted.length-1]];
  }
  function planCard(plan,index,total){
    var data=Number(plan.data_gb)>0?(plan.data_gb+' GB'):'Flexible data';
    var validity=Number(plan.validity_days)>0?(plan.validity_days+' days'):'Plan validity applies';
    var labels=total===1?['Recommended']:['Essential','Plus','Premium'];
    var label=labels[Math.min(index,labels.length-1)];
    var popularMark=(total>=3&&index===1)?'<span class="esim-plan-badge">Most popular</span>':'';
    return '<article class="esim-plan premium-card">'+popularMark+'<p class="eyebrow">'+esc(label)+'</p><h3>'+esc(data)+'</h3><p class="esim-plan-validity">'+esc(validity)+'</p><p class="esim-plan-coverage">'+esc(plan.scope==='global'?'Global coverage':'Destination coverage')+'</p><strong class="esim-plan-price">'+money(plan.retail_price)+'</strong><a class="solid-button esim-plan-select" href="esim-order?plan='+encodeURIComponent(plan.id)+'&destination='+encodeURIComponent(destination.value)+'">Select plan</a></article>';
  }
  function renderPlans(){
    var shown=expanded?currentPlans:choosePlans(currentPlans);
    results.innerHTML=shown.map(function(p,i){return planCard(p,i,shown.length);}).join('');
    if(currentPlans.length>3){more.hidden=false;more.disabled=false;more.textContent=expanded?'Show recommended plans':'View all '+currentPlans.length+' plans';}else{more.hidden=true;more.disabled=true;}
  }
  function loadOffers(){
    if(!destination.value){more.hidden=true;more.disabled=true;results.innerHTML='<div class="esim-plan-placeholder"><strong>Select a destination above.</strong><span>We will show the most useful plans here.</span></div>';setStatus('Choose a destination to compare plans.');return;}
    var requestId=++offerRequest;
    more.hidden=true;more.disabled=true;currentPlans=[];
    setStatus('Refreshing plans…');results.innerHTML='<div class="esim-plan-placeholder is-loading"><strong>Finding your best options…</strong><span>Live availability is being checked.</span></div>';
    fetch(api+'?action=offers&destination='+encodeURIComponent(destination.value),{cache:'no-store',headers:{Accept:'application/json'}})
      .then(function(r){return r.json().then(function(j){if(!r.ok)throw new Error(j.message||'Unable to load plans.');return j;});})
      .then(function(payload){if(requestId!==offerRequest)return;currentPlans=payload.offers||[];if(!currentPlans.length){results.innerHTML='<div class="esim-plan-placeholder"><strong>No plans are currently listed for this destination.</strong><span>Please choose another destination or try again shortly.</span></div>';setStatus('No plans are currently available.');more.hidden=true;more.disabled=true;return;}renderPlans();setStatus('Plans ready. Choose the option that suits your trip.','ready');})
      .catch(function(){if(requestId!==offerRequest)return;currentPlans=[];more.hidden=true;more.disabled=true;results.innerHTML='<div class="esim-plan-placeholder"><strong>Plans are refreshing.</strong><span>Please try again in a moment.</span><button type="button" class="outline-button" data-esim-retry>Try again</button></div>';setStatus('We could not refresh plans just now.','quiet');var retry=results.querySelector('[data-esim-retry]');if(retry)retry.addEventListener('click',loadOffers);});
  }
  fetch(api+'?action=destinations',{cache:'no-store',headers:{Accept:'application/json'}})
    .then(function(r){return r.json().then(function(j){if(!r.ok)throw new Error(j.message||'Unable to load destinations.');return j;});})
    .then(function(payload){destinations=payload.destinations||[];destination.innerHTML='<option value="">Choose destination</option>';datalist.innerHTML='';destinations.forEach(function(item){var o=document.createElement('option');o.value=item.id;o.textContent=item.name;destination.appendChild(o);var d=document.createElement('option');d.value=item.name;datalist.appendChild(d);});destination.disabled=false;popular.forEach(function(btn){var item=findDestination(btn.getAttribute('data-esim-popular'));if(!item)btn.disabled=true;});setStatus('Search or choose a popular destination.','ready');})
    .catch(function(){setStatus('Destinations are refreshing. Please try again shortly.','quiet');search.placeholder='Destinations refreshing…';search.disabled=true;popular.forEach(function(btn){btn.disabled=true;});});
  search.addEventListener('change',function(){var item=findDestination(search.value);if(item)setDestination(item);else if(search.value.trim())setStatus('Choose a destination from the suggestions.','quiet');});
  search.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();var item=findDestination(search.value);if(item)setDestination(item);}});
  popular.forEach(function(btn){btn.addEventListener('click',function(){setDestination(findDestination(btn.getAttribute('data-esim-popular')));});});
  more.addEventListener('click',function(){if(more.disabled||!currentPlans.length)return;expanded=!expanded;renderPlans();});
})();
