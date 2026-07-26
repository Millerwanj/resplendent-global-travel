(() => {
  "use strict";

  const root = document.querySelector("[data-proposal-generator]");
  if (!root) return;

  const form = root.querySelector("[data-generator-form]");
  const field = (name) => form.elements.namedItem(name);
  const value = (name) => String(field(name)?.value || "").trim();
  const number = (name) => Math.max(0, Number(field(name)?.value || 0) || 0);
  const text = (selector, content, fallback = "To be confirmed") => {
    const node = root.querySelector(selector);
    if (node) node.textContent = content || fallback;
  };
  const formatMoney = (currency, amount) =>
    `${currency} ${amount.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  const formatDate = (date) => {
    if (!date) return "";
    const parsed = new Date(`${date}T12:00:00`);
    return Number.isNaN(parsed.valueOf()) ? date : parsed.toLocaleDateString("en-GB", { day: "numeric", month: "long", year: "numeric" });
  };
  const proposalType = field("proposal_type");
  const currencyField = field("currency");
  const serviceFee = root.querySelector("[data-auto-service-fee]");
  let serviceFeeWasEdited = false;

  serviceFee?.addEventListener("input", () => {
    if (document.activeElement === serviceFee) serviceFeeWasEdited = true;
  });

  const sentenceCount = (content) =>
    (content.trim().match(/[^.!?]+[.!?]+(?:\s|$)|[^.!?]+$/g) || []).filter((part) => part.trim()).length;

  const validateSentenceLimits = () => {
    let valid = true;
    root.querySelectorAll("[data-sentence-limit]").forEach((textarea) => {
      const count = sentenceCount(textarea.value);
      const message = textarea.parentElement?.querySelector("[data-sentence-message]");
      const tooLong = count > 2;
      textarea.setAttribute("aria-invalid", String(tooLong));
      if (message) message.textContent = tooLong ? "Keep this recommendation to no more than two sentences." : "";
      if (tooLong) valid = false;
    });
    return valid;
  };

  const selectedServices = () => {
    const selected = [...root.querySelectorAll("[data-included-services] input:checked")].map((item) => item.value);
    const additional = value("additional_services").split(",").map((item) => item.trim()).filter(Boolean);
    return [...selected, ...additional];
  };

  const applyRevisionPayload = () => {
    const node = root.querySelector("[data-revision-payload]");
    if (!node) return;
    try {
      const payload = JSON.parse(node.textContent || "{}");
      Object.entries(payload).forEach(([name, content]) => {
        if (Array.isArray(content) || content === null || typeof content === "object") return;
        const control = field(name);
        if (control && "value" in control) control.value = String(content);
      });
      const services = Array.isArray(payload.included_services) ? payload.included_services.map(String) : [];
      const standardServices = new Set(
        [...root.querySelectorAll("[data-included-services] input")].map((input) => input.value)
      );
      root.querySelectorAll("[data-included-services] input").forEach((input) => {
        input.checked = services.includes(input.value);
      });
      const additional = services.filter((service) => !standardServices.has(service));
      if (field("additional_services")) field("additional_services").value = additional.join(", ");
      serviceFeeWasEdited = Object.prototype.hasOwnProperty.call(payload, "service_fee");
    } catch {
      // Leave the safe generator defaults in place if stored revision data is unreadable.
    }
  };

  const update = () => {
    const business = value("proposal_type") === "Global Business Connections";
    root.querySelectorAll("[data-travel-section]").forEach((section) => { section.hidden = business; });
    const businessSection = root.querySelector("[data-business-section]");
    if (businessSection) businessSection.hidden = !business;
    const previewTravel = root.querySelector("[data-preview-travel]");
    const previewBusiness = root.querySelector("[data-preview-business]");
    if (previewTravel) previewTravel.hidden = business;
    if (previewBusiness) previewBusiness.hidden = !business;
    root.querySelectorAll("[data-policy-travel]").forEach((section) => { section.hidden = business; });
    root.querySelectorAll("[data-policy-business]").forEach((section) => { section.hidden = !business; });

    if (business) {
      currencyField.value = "USD";
    } else if (!serviceFeeWasEdited) {
      serviceFee.value = ((number("flight_price") + number("hotel_price")) * 0.05).toFixed(2);
    }

    const currency = business ? "USD" : value("currency") || "USD";
    const total = business
      ? 500
      : number("flight_price") + number("hotel_price") + number("additional_price") + number("service_fee");
    field("grand_total_display").value = formatMoney(currency, total);

    text("[data-preview-type]", value("proposal_type"), "Luxury Travel");
    text("[data-preview-client]", value("client_name"), "Client Name");
    text("[data-preview-company]", value("company"), "");
    text("[data-preview-date]", formatDate(value("proposal_date")), "");
    text("[data-preview-introduction]", value("introduction"), "");
    text("[data-preview-airline]", value("airline"));
    text("[data-preview-route]", value("route"));
    text("[data-preview-cabin]", value("cabin"));
    text("[data-preview-hotel]", value("hotel"));
    text("[data-preview-room]", [value("room"), value("meal_plan")].filter(Boolean).join(" · "));
    text("[data-preview-nights]", value("nights") ? `${value("nights")} nights` : "");
    text("[data-preview-flight-recommendation]", value("flight_recommendation"), "");
    text("[data-preview-hotel-recommendation]", value("hotel_recommendation"), "");
    text("[data-preview-market]", value("target_market"));
    text("[data-preview-partner]", value("partner_profile"));
    text("[data-preview-business-approach]", value("business_approach"), "");
    text("[data-preview-total]", formatMoney(currency, total));
    text("[data-preview-investment-note]", business
      ? "USD 250 on engagement · USD 150 mid-project · USD 100 on final delivery."
      : value("investment_note"), "");

    const servicesList = root.querySelector("[data-preview-services]");
    if (servicesList) {
      servicesList.textContent = "";
      const services = selectedServices();
      (services.length ? services : ["Services to be confirmed"]).forEach((service) => {
        const item = document.createElement("li");
        item.textContent = service;
        servicesList.append(item);
      });
    }
    validateSentenceLimits();
  };

  form.addEventListener("input", update);
  form.addEventListener("change", update);
  proposalType?.addEventListener("change", () => {
    serviceFeeWasEdited = false;
    const business = proposalType.value === "Global Business Connections";
    root.querySelectorAll("[data-included-services] input").forEach((input) => {
      if (business) input.checked = ["Qualified partner research", "Introduction coordination"].includes(input.value);
      else input.checked = ["Flight planning and reservation", "Accommodation coordination"].includes(input.value);
    });
    update();
  });

  form.addEventListener("submit", (event) => {
    if (!form.checkValidity()) return;
    if (!validateSentenceLimits()) {
      event.preventDefault();
      root.querySelector('[aria-invalid="true"]')?.focus();
      return;
    }

    const business = value("proposal_type") === "Global Business Connections";
    const currency = business ? "USD" : value("currency") || "USD";
    const total = business
      ? 500
      : number("flight_price") + number("hotel_price") + number("additional_price") + number("service_fee");
    const payload = {
      client_reference: value("client_reference"),
      revision_of_id: value("revision_of_id"),
      proposal_type: value("proposal_type"),
      proposal_date: value("proposal_date"),
      client_name: value("client_name"),
      company: value("company"),
      email: value("email"),
      phone: value("phone"),
      internal_requirements: value("internal_requirements"),
      introduction: value("introduction"),
      airline: value("airline"),
      route: value("route"),
      cabin: value("cabin"),
      flight_price: number("flight_price").toFixed(2),
      flight_recommendation: value("flight_recommendation"),
      hotel: value("hotel"),
      room: value("room"),
      meal_plan: value("meal_plan"),
      nights: value("nights"),
      hotel_price: number("hotel_price").toFixed(2),
      hotel_recommendation: value("hotel_recommendation"),
      target_market: value("target_market"),
      partner_profile: value("partner_profile"),
      engagement_objective: value("engagement_objective"),
      business_approach: value("business_approach"),
      included_services: selectedServices(),
      currency,
      additional_price: number("additional_price").toFixed(2),
      service_fee: number("service_fee").toFixed(2),
      grand_total: formatMoney(currency, total),
      investment_note: value("investment_note"),
    };
    root.querySelector("[data-document-payload]").value = JSON.stringify(payload);
  });

  applyRevisionPayload();
  update();
})();
