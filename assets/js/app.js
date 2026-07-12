(() => {
  "use strict";

  const header = document.querySelector("[data-header]");
  const toggle = document.querySelector("[data-nav-toggle]");
  const nav = document.querySelector("[data-nav]");
  const form = document.querySelector("[data-concierge-form]");
  const status = document.querySelector("[data-form-status]");
  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

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

  const params = new URLSearchParams(location.search);
  const requestedPurpose = params.get("purpose");
  if (requestedPurpose && form.elements.purpose) {
    const matchingOption = [...form.elements.purpose.options].find(
      (option) => option.value.toLowerCase() === requestedPurpose.toLowerCase()
    );
    if (matchingOption) form.elements.purpose.value = matchingOption.value;
  }

  const setFieldError = (field, message) => {
    const small = field.closest("label")?.querySelector("small");
    field.classList.toggle("invalid", Boolean(message));
    if (message) {
      field.setAttribute("aria-invalid", "true");
    } else {
      field.removeAttribute("aria-invalid");
    }
    if (small) small.textContent = message;
    return !message;
  };

  const validateField = (field) => {
    const value = field.value.trim();
    let message = "";

    if (field.required && !value) {
      message = "This field is required.";
    } else if (
      field.type === "email" &&
      value &&
      !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)
    ) {
      message = "Enter a valid email address.";
    }

    return setFieldError(field, message);
  };

  const validateConsent = () => {
    const consent = form.elements.consent;
    const error = document.querySelector("#consent-error");
    const valid = Boolean(consent?.checked);

    consent?.setAttribute("aria-invalid", String(!valid));
    if (valid) consent?.removeAttribute("aria-invalid");
    if (error) error.textContent = valid ? "" : "Please confirm that we may contact you.";

    return valid;
  };

  form.querySelectorAll("input:not([type=checkbox]), select, textarea").forEach((field) => {
    field.addEventListener("blur", () => validateField(field));
    field.addEventListener("input", () => {
      if (field.hasAttribute("aria-invalid")) validateField(field);
    });
  });

  form.elements.consent?.addEventListener("change", validateConsent);

  form.addEventListener("submit", (event) => {
    event.preventDefault();

    const fields = [...form.querySelectorAll("input:not([type=checkbox]), select, textarea")];
    const fieldsValid = fields.map(validateField).every(Boolean);
    const consentValid = validateConsent();

    if (!fieldsValid || !consentValid) {
      status.textContent = "Please review the highlighted fields.";
      status.className = "form-status error";
      form.querySelector('[aria-invalid="true"]')?.focus();
      return;
    }

    const data = Object.fromEntries(new FormData(form).entries());
    const subject = encodeURIComponent(`New ${data.purpose} enquiry from ${data.name}`);
    const body = encodeURIComponent(
      `Name: ${data.name}
Email: ${data.email}
Phone: ${data.phone || "-"}
Company: ${data.company || "-"}
Purpose: ${data.purpose}
Destination: ${data.destination || "-"}
Dates: ${data.dates || "-"}
Travellers: ${data.travellers || "-"}

Journey details:
${data.message}`
    );

    status.textContent = "Your email application is opening with the enquiry prepared.";
    status.className = "form-status success";

    window.location.href =
      `mailto:resplendentglobaltravelsolutions@gmail.com?subject=${subject}&body=${body}`;
  });
})();
