/* ==========================================================================
   RESPLENDENT GLOBAL TRAVEL SOLUTIONS
   Production JavaScript — v4.3
   Vanilla JS. No dependencies, no frameworks.
   ========================================================================== */

(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ------------------------------------------------------------------
     1. Header scroll state
     ------------------------------------------------------------------ */
  var header = document.getElementById('siteHeader');

  function updateHeaderState() {
    if (!header) return;
    if (window.scrollY > 24) {
      header.classList.add('is-scrolled');
    } else {
      header.classList.remove('is-scrolled');
    }
  }

  var headerTicking = false;
  window.addEventListener('scroll', function () {
    if (!headerTicking) {
      window.requestAnimationFrame(function () {
        updateHeaderState();
        headerTicking = false;
      });
      headerTicking = true;
    }
  }, { passive: true });

  updateHeaderState();

  /* ------------------------------------------------------------------
     2. Mobile navigation toggle
     ------------------------------------------------------------------ */
  var navToggle = document.getElementById('navToggle');
  var primaryNav = document.getElementById('primaryNav');

  function closeNav() {
    if (!navToggle || !primaryNav) return;
    navToggle.setAttribute('aria-expanded', 'false');
    navToggle.setAttribute('aria-label', 'Open navigation menu');
    primaryNav.classList.remove('is-open');
    document.body.style.overflow = '';
  }

  function openNav() {
    if (!navToggle || !primaryNav) return;
    navToggle.setAttribute('aria-expanded', 'true');
    navToggle.setAttribute('aria-label', 'Close navigation menu');
    primaryNav.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }

  if (navToggle && primaryNav) {
    navToggle.addEventListener('click', function () {
      var isOpen = navToggle.getAttribute('aria-expanded') === 'true';
      if (isOpen) { closeNav(); } else { openNav(); }
    });

    primaryNav.querySelectorAll('.primary-nav__link').forEach(function (link) {
      link.addEventListener('click', closeNav);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { closeNav(); }
    });
  }

  /* ------------------------------------------------------------------
     3. Active nav link on scroll (IntersectionObserver-driven)
     ------------------------------------------------------------------ */
  var navLinks = Array.prototype.slice.call(document.querySelectorAll('.primary-nav__link'));
  var sections = navLinks
    .map(function (link) {
      var id = link.getAttribute('href');
      if (!id || id.charAt(0) !== '#') return null;
      var el = document.querySelector(id);
      return el ? { link: link, el: el } : null;
    })
    .filter(Boolean);

  if (sections.length && 'IntersectionObserver' in window) {
    var sectionObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        var match = sections.find(function (s) { return s.el === entry.target; });
        if (!match) return;
        if (entry.isIntersecting) {
          navLinks.forEach(function (l) { l.classList.remove('is-active'); });
          match.link.classList.add('is-active');
        }
      });
    }, { rootMargin: '-45% 0px -50% 0px', threshold: 0 });

    sections.forEach(function (s) { sectionObserver.observe(s.el); });
  }

  /* ------------------------------------------------------------------
     4. Scroll reveal (respects prefers-reduced-motion)
     ------------------------------------------------------------------ */
  var revealEls = document.querySelectorAll('.reveal');

  if (reduceMotion || !('IntersectionObserver' in window)) {
    revealEls.forEach(function (el) { el.classList.add('is-visible'); });
  } else {
    var revealObserver = new IntersectionObserver(function (entries, obs) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          obs.unobserve(entry.target);
        }
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

    revealEls.forEach(function (el) { revealObserver.observe(el); });
  }

  /* ------------------------------------------------------------------
     5. Contact form — validation + submission
     ------------------------------------------------------------------

     SUBMISSION STRATEGY
     This is a static site with no server, so there is no endpoint to
     POST to out of the box. Rather than fake a success message and
     silently drop every enquiry, the default behaviour below opens the
     visitor's email client with a pre-filled message addressed to
     CONCIERGE_EMAIL — this genuinely delivers the enquiry with zero
     backend, zero signup, and zero configuration.

     TO SWITCH TO A SILENT IN-PAGE SUBMISSION (recommended once you have
     a form backend — e.g. Formspree, Basin, Getform, or your own
     serverless function): set SUBMIT_ENDPOINT below to that endpoint's
     URL. When it's set, the form will POST there with fetch() instead
     of opening a mail client, and show the success message in-page.
     ------------------------------------------------------------------ */
  var CONCIERGE_EMAIL = 'concierge@resplendentgts.com';
  var SUBMIT_ENDPOINT = ''; // e.g. 'https://formspree.io/f/xxxxxxx'

  var form = document.getElementById('contactForm');
  var formStatus = document.getElementById('formStatus');

  function setFieldError(fieldId, hasError) {
    var field = document.getElementById(fieldId);
    if (!field) return;
    field.classList.toggle('has-error', hasError);
  }

  function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
  }

  function showStatus(message, isSuccess) {
    formStatus.textContent = message;
    formStatus.className = 'form-status is-visible' + (isSuccess ? ' is-success' : '');
  }

  function buildMailtoLink(values) {
    var subjectMap = {
      leisure: 'Leisure travel enquiry',
      corporate: 'Corporate travel enquiry',
      business: 'Global Business Connections enquiry',
      other: 'General enquiry'
    };
    var subject = subjectMap[values.interest] || 'Website enquiry';
    var bodyLines = [
      'Name: ' + values.name,
      'Email: ' + values.email,
      'Organisation: ' + (values.organisation || 'Not provided'),
      'Enquiring about: ' + subject,
      '',
      values.message
    ];
    var params = 'subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(bodyLines.join('\n'));
    return 'mailto:' + CONCIERGE_EMAIL + '?' + params;
  }

  function submitViaEndpoint(values) {
    return fetch(SUBMIT_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(values)
    });
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();

      var name = document.getElementById('name');
      var email = document.getElementById('email');
      var organisation = document.getElementById('organisation');
      var interest = document.getElementById('interest');
      var message = document.getElementById('message');

      var nameValid = name.value.trim().length > 1;
      var emailValid = isValidEmail(email.value.trim());
      var messageValid = message.value.trim().length > 4;

      setFieldError('field-name', !nameValid);
      setFieldError('field-email', !emailValid);
      setFieldError('field-message', !messageValid);

      var allValid = nameValid && emailValid && messageValid;

      if (!allValid) {
        showStatus('Please check the highlighted fields and try again.', false);
        var firstInvalid = form.querySelector('.has-error input, .has-error textarea');
        if (firstInvalid) { firstInvalid.focus(); }
        return;
      }

      var values = {
        name: name.value.trim(),
        email: email.value.trim(),
        organisation: organisation ? organisation.value.trim() : '',
        interest: interest ? interest.value : '',
        message: message.value.trim()
      };

      var submitBtn = form.querySelector('button[type="submit"]');

      if (SUBMIT_ENDPOINT) {
        if (submitBtn) { submitBtn.disabled = true; }
        submitViaEndpoint(values)
          .then(function (response) {
            if (!response.ok) { throw new Error('Request failed'); }
            showStatus('Thank you. Your enquiry has been received — a member of the team will reply within one business day.', true);
            form.reset();
          })
          .catch(function () {
            showStatus('Something went wrong sending that. Please email us directly at ' + CONCIERGE_EMAIL + '.', false);
          })
          .finally(function () {
            if (submitBtn) { submitBtn.disabled = false; }
          });
      } else {
        window.location.href = buildMailtoLink(values);
        showStatus('Your email client should now be open with your enquiry pre-filled and addressed to ' + CONCIERGE_EMAIL + '. Send it from there to reach us.', true);
        form.reset();
      }
    });

    ['name', 'email', 'message'].forEach(function (id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.addEventListener('input', function () {
        setFieldError('field-' + id, false);
      });
    });
  }

  /* ------------------------------------------------------------------
     6. Footer year
     ------------------------------------------------------------------ */
  var yearEl = document.getElementById('year');
  if (yearEl) { yearEl.textContent = new Date().getFullYear(); }

})();
