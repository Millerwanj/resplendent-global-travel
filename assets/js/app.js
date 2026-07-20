(() => {
  "use strict";

  const header = document.querySelector("[data-header]");
  const toggle = document.querySelector("[data-nav-toggle]");
  const nav = document.querySelector("[data-nav]");
  const form = document.querySelector("[data-concierge-form]");
  const status = document.querySelector("[data-form-status]");
  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const navFocusableSelector = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';

  const validateSiteConfiguration = () => {
    const config = window.RESPLENDENT_CONFIG;
    if (!config) { console.warn("Resplendent site configuration is unavailable."); return; }
    try { new URL(config.siteUrl); } catch { console.warn("Resplendent siteUrl is not valid."); }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(config.enquiryEmail || "")) {
      console.warn("Resplendent enquiryEmail is not valid.");
    }
  };
  validateSiteConfiguration();

  document.querySelectorAll("[data-config-email]").forEach((link) => {
    const email = window.RESPLENDENT_CONFIG?.enquiryEmail;
    if (!email) return;
    link.textContent = email;
    link.setAttribute("href", `mailto:${email}`);
  });

  document.querySelectorAll("[data-current-year]").forEach((year) => {
    year.textContent = String(new Date().getFullYear());
  });

  const currentPage = (location.pathname.split("/").pop() || "index.html").toLowerCase();
  nav?.querySelectorAll("a").forEach((link) => {
    const href = (link.getAttribute("href") || "").split("?")[0].toLowerCase();
    if (href === currentPage) link.setAttribute("aria-current", "page");
  });

  const setHeaderState = () => {
    header?.classList.toggle("scrolled", window.scrollY > 24);
  };

  const closeNavigation = ({ restoreFocus = false } = {}) => {
    nav?.classList.remove("open");
    toggle?.setAttribute("aria-expanded", "false");
    document.body.classList.remove("nav-open");
    if (restoreFocus) toggle?.focus();
  };

  setHeaderState();
  window.addEventListener("scroll", setHeaderState, { passive: true });

  toggle?.addEventListener("click", () => {
    const open = toggle.getAttribute("aria-expanded") === "true";
    toggle.setAttribute("aria-expanded", String(!open));
    nav?.classList.toggle("open", !open);
    document.body.classList.toggle("nav-open", !open);
    if (!open) nav?.querySelector("a")?.focus();
  });

  nav?.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => closeNavigation());
  });

  document.addEventListener("click", (event) => {
    if (!nav?.classList.contains("open")) return;
    if (nav.contains(event.target) || toggle?.contains(event.target)) return;
    closeNavigation();
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && nav?.classList.contains("open")) {
      closeNavigation({ restoreFocus: true });
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Tab" || !nav?.classList.contains("open")) return;
    const focusable = [...nav.querySelectorAll(navFocusableSelector)];
    if (toggle) focusable.unshift(toggle);
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });

  window.addEventListener("resize", () => {
    if (window.innerWidth > 900) closeNavigation();
  });

  const revealItems = document.querySelectorAll(".reveal");
  if (reduceMotion || !("IntersectionObserver" in window)) {
    revealItems.forEach((item) => item.classList.add("visible"));
  } else {
    const observer = new IntersectionObserver(
      (entries, currentObserver) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          entry.target.classList.add("visible");
          currentObserver.unobserve(entry.target);
        });
      },
      { threshold: 0.12, rootMargin: "0px 0px -6% 0px" }
    );
    revealItems.forEach((item) => observer.observe(item));
  }

  if (!form) return;

  const serviceSelect = form.querySelector("[data-service-category]");
  const servicePanels = [...form.querySelectorAll("[data-service-panel]")];
  const submitButton = form.querySelector("[data-submit-button]");
  const params = new URLSearchParams(location.search);

  const generateReference = () => {
    const now = new Date();
    const date = [now.getFullYear(), String(now.getMonth() + 1).padStart(2, "0"), String(now.getDate()).padStart(2, "0")].join("");
    const token = Math.random().toString(36).slice(2, 6).toUpperCase();
    return `RGTS-${date}-${token}`;
  };

  const updateServicePanels = () => {
    const selected = serviceSelect?.value || "";
    servicePanels.forEach((panel) => {
      const active = panel.dataset.servicePanel === selected;
      panel.hidden = !active;
      panel.querySelectorAll("input, select, textarea").forEach((field) => {
        field.disabled = !active;
      });
    });
  };

  const requestedService = params.get("service") || params.get("purpose");
  if (requestedService && serviceSelect) {
    const aliases = { Leisure: "Leisure Travel", Corporate: "Corporate Travel", Business: "Business Connections", Accounts: "Accounts & Billing", Support: "Customer Support", Mixed: "Multi-service Request" };
    const target = aliases[requestedService] || requestedService;
    const option = [...serviceSelect.options].find((item) => item.value.toLowerCase() === target.toLowerCase());
    if (option) serviceSelect.value = option.value;
  }
  updateServicePanels();
  serviceSelect?.addEventListener("change", updateServicePanels);

  const setFieldError = (field, message) => {
    const small = field.closest("label")?.querySelector("small");
    field.classList.toggle("invalid", Boolean(message));
    if (message) field.setAttribute("aria-invalid", "true");
    else field.removeAttribute("aria-invalid");
    if (small) small.textContent = message;
    return !message;
  };

  const validateField = (field) => {
    if (field.disabled) return true;
    const value = field.value.trim();
    let message = "";
    if (field.required && !value) message = "This field is required.";
    else if (field.type === "email" && value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) message = "Enter a valid email address.";
    else if (field.name === "phone" && value && !/^[+()0-9\s.-]{7,24}$/.test(value)) message = "Enter a valid phone number with country code.";
    return setFieldError(field, message);
  };

  const validateConsent = () => {
    const consent = form.elements.consent;
    const error = document.querySelector("#consent-error");
    const valid = Boolean(consent?.checked);
    if (!valid) consent?.setAttribute("aria-invalid", "true");
    else consent?.removeAttribute("aria-invalid");
    if (error) error.textContent = valid ? "" : "Please confirm that we may contact you.";
    return valid;
  };

  form.querySelectorAll("input:not([type=checkbox]), select, textarea").forEach((field) => {
    field.addEventListener("blur", () => validateField(field));
    field.addEventListener("input", () => { if (field.hasAttribute("aria-invalid")) validateField(field); });
  });
  form.elements.consent?.addEventListener("change", validateConsent);

  const readableLabel = (name) => ({
    travel_flexibility: "Travel flexibility", accommodation_preference: "Accommodation preference",
    corporate_industry: "Industry", corporate_objective: "Travel objective", business_industry: "Industry / sector",
    partner_profile: "Target partner profile", meeting_objective: "Meeting objective", combined_services: "Combined services"
  }[name] || name.replaceAll("_", " ").replace(/\b\w/g, (letter) => letter.toUpperCase()));

  const returnedStatus = params.get("status");
  const returnedReference = params.get("reference");
  if (returnedStatus === "success") {
    status.textContent = `Thank you. Your enquiry${returnedReference ? ` ${returnedReference}` : ""} has been delivered to the appropriate Resplendent department.`;
    status.className = "form-status success";
    status.scrollIntoView({ behavior: reduceMotion ? "auto" : "smooth", block: "center" });
    history.replaceState({}, "", location.pathname);
  } else if (returnedStatus === "error") {
    status.textContent = "We could not send your enquiry. Please review the form and try again, or contact info@resplendentglobaltravel.com.";
    status.className = "form-status error";
  }

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    const fields = [...form.querySelectorAll("input:not([type=checkbox]):not([type=hidden]), select, textarea")];
    const fieldsValid = fields.map(validateField).every(Boolean);
    const consentValid = validateConsent();
    if (!fieldsValid || !consentValid) {
      status.textContent = "Please review the highlighted fields.";
      status.className = "form-status error";
      form.querySelector('[aria-invalid="true"]')?.focus();
      return;
    }

    const reference = generateReference();
    form.elements.reference.value = reference;
    form.elements.submitted_at.value = new Date().toISOString();
    submitButton?.setAttribute("aria-disabled", "true");
    submitButton && (submitButton.textContent = "Sending Enquiry…");
    status.textContent = "Securely sending your enquiry to the appropriate department…";
    status.className = "form-status";
    form.submit();
  });
})();
