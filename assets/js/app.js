const currentPage=(location.pathname.split("/").pop()||"index.html").toLowerCase();
document.querySelectorAll("[data-nav] a").forEach(link=>{
  const href=(link.getAttribute("href")||"").split("?")[0].toLowerCase();
  if(href===currentPage) link.setAttribute("aria-current","page");
});

(() => {
  const header = document.querySelector('[data-header]');
  const toggle = document.querySelector('[data-nav-toggle]');
  const nav = document.querySelector('[data-nav]');
  const form = document.querySelector('[data-concierge-form]');
  const status = document.querySelector('[data-form-status]');

  const scrollState = () => header?.classList.toggle('scrolled', scrollY > 24);
  scrollState(); addEventListener('scroll', scrollState, {passive:true});

  toggle?.addEventListener('click', () => {
    const open = toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute('aria-expanded', String(!open));
    nav?.classList.toggle('open', !open);
    document.body.classList.toggle('nav-open', !open);
  });
  nav?.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
    nav.classList.remove('open'); toggle?.setAttribute('aria-expanded','false'); document.body.classList.remove('nav-open');
  }));

  const observer = 'IntersectionObserver' in window ? new IntersectionObserver((entries, obs) => {
    entries.forEach(e => { if(e.isIntersecting){e.target.classList.add('visible');obs.unobserve(e.target)}});
  }, {threshold:.12}) : null;
  document.querySelectorAll('.reveal').forEach(el => observer ? observer.observe(el) : el.classList.add('visible'));

  const params = new URLSearchParams(location.search);
  if (form && params.get('purpose')) form.elements.purpose.value = params.get('purpose');

  const validate = field => {
    const message = field.required && !field.value.trim() ? 'This field is required.'
      : field.type === 'email' && field.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value) ? 'Enter a valid email address.' : '';
    const small = field.parentElement.querySelector('small');
    if (small) small.textContent = message;
    return !message;
  };

  form?.addEventListener('submit', e => {
    e.preventDefault();
    const fields = [...form.querySelectorAll('input:not([type=checkbox]),select,textarea')];
    const valid = fields.map(validate).every(Boolean);
    if (!form.elements.consent.checked || !valid) {
      status.textContent = 'Please complete the required fields and consent to contact.';
      status.className = 'form-status error';
      return;
    }
    const d = Object.fromEntries(new FormData(form).entries());
    const subject = encodeURIComponent(`New ${d.purpose} enquiry from ${d.name}`);
    const body = encodeURIComponent(`Name: ${d.name}\nEmail: ${d.email}\nPhone: ${d.phone || '-'}\nCompany: ${d.company || '-'}\nPurpose: ${d.purpose}\nDestination: ${d.destination || '-'}\nDates: ${d.dates || '-'}\nTravellers: ${d.travellers || '-'}\n\nJourney details:\n${d.message}`);
    location.href = `mailto:resplendentglobaltravelsolutions@gmail.com?subject=${subject}&body=${body}`;
    status.textContent = 'Your email application has opened with the enquiry prepared. Please review and send it.';
    status.className = 'form-status success';
  });
})();

document.addEventListener("keydown",event=>{
  if(event.key!=="Escape") return;
  const nav=document.querySelector("[data-nav]");
  const toggle=document.querySelector("[data-nav-toggle]");
  if(nav?.classList.contains("open")){
    nav.classList.remove("open");
    toggle?.setAttribute("aria-expanded","false");
    toggle?.focus();
  }
});
