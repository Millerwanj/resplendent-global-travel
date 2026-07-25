(() => {
  "use strict";

  const root = document.querySelector("[data-quotation-generator]");
  if (!root) return;

  const form = root.querySelector("[data-quotation-form]");
  const field = (name) => form.elements.namedItem(name);
  const value = (name) => String(field(name)?.value || "").trim();
  const number = (name) => Math.max(0, Number(field(name)?.value || 0) || 0);
  const formatMoney = (currency, amount) =>
    `${currency} ${amount.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  const formatDate = (date) => {
    if (!date) return "";
    const parsed = new Date(`${date}T12:00:00`);
    return Number.isNaN(parsed.valueOf()) ? date : parsed.toLocaleDateString("en-GB", { day: "numeric", month: "long", year: "numeric" });
  };
  const text = (selector, content, fallback = "") => {
    const node = root.querySelector(selector);
    if (node) node.textContent = content || fallback;
  };
  const businessMode = () => value("quotation_type") === "Business Matchmaking";

  const readItems = () => [...root.querySelectorAll("[data-quotation-item]")].map((row) => {
    const description = String(row.querySelector('[name="item_description[]"]')?.value || "").trim();
    const quantity = Math.max(0, Number(row.querySelector('[name="item_quantity[]"]')?.value || 0) || 0);
    const unitPrice = Math.max(0, Number(row.querySelector('[name="item_price[]"]')?.value || 0) || 0);
    return { description, quantity, unit_price: unitPrice.toFixed(2), total: (quantity * unitPrice).toFixed(2) };
  }).filter((item) => item.description);

  const totals = () => {
    if (businessMode()) return { subtotal: 500, discount: 0, serviceFee: 0, grandTotal: 500 };
    const subtotal = readItems().reduce((sum, item) => sum + Number(item.total), 0);
    const discount = number("discount");
    const serviceFee = number("service_fee");
    return { subtotal, discount, serviceFee, grandTotal: Math.max(0, subtotal - discount + serviceFee) };
  };

  const update = () => {
    const business = businessMode();
    const travelSection = root.querySelector("[data-travel-quotation]");
    const businessSection = root.querySelector("[data-business-quotation]");
    const previewTravel = root.querySelector("[data-preview-travel-quote]");
    const previewBusiness = root.querySelector("[data-preview-business-quote]");
    if (travelSection) travelSection.hidden = business;
    if (businessSection) businessSection.hidden = !business;
    if (previewTravel) previewTravel.hidden = business;
    if (previewBusiness) previewBusiness.hidden = !business;
    if (business) field("currency").value = "USD";

    const currency = business ? "USD" : value("currency") || "USD";
    const calculated = totals();
    text("[data-preview-type]", value("quotation_type"), "Travel & Services");
    text("[data-preview-client]", value("client_name"), "Client Name");
    text("[data-preview-company]", value("company"));
    text("[data-preview-date]", formatDate(value("quotation_date")));
    text("[data-preview-valid]", formatDate(value("valid_until")));
    text("[data-preview-total]", formatMoney(currency, calculated.grandTotal));
    text("[data-form-total]", formatMoney(currency, calculated.grandTotal));
    text("[data-preview-payment-terms]", value("payment_terms"));
    text("[data-preview-payment-details]", value("payment_details"));

    const body = root.querySelector("[data-preview-items]");
    if (body && !business) {
      body.textContent = "";
      const items = readItems();
      (items.length ? items : [{ description: "Quotation item", quantity: 1, total: "0.00" }]).forEach((item) => {
        const row = document.createElement("tr");
        [item.description, String(item.quantity), formatMoney(currency, Number(item.total))].forEach((content) => {
          const cell = document.createElement("td");
          cell.textContent = content;
          row.append(cell);
        });
        body.append(row);
      });
      if (calculated.serviceFee > 0) {
        const row = document.createElement("tr");
        row.innerHTML = `<td>Professional service fee</td><td>1</td><td>${formatMoney(currency, calculated.serviceFee)}</td>`;
        body.append(row);
      }
      if (calculated.discount > 0) {
        const row = document.createElement("tr");
        row.innerHTML = `<td>Discount</td><td>—</td><td>−${formatMoney(currency, calculated.discount)}</td>`;
        body.append(row);
      }
    }
  };

  const addItem = () => {
    const row = document.createElement("div");
    row.className = "quotation-item";
    row.dataset.quotationItem = "";
    row.innerHTML = `
      <label>Description<input type="text" name="item_description[]" placeholder="Service or reservation"></label>
      <label>Qty<input type="number" name="item_quantity[]" min="0" step="1" value="1"></label>
      <label>Unit price<input type="number" name="item_price[]" min="0" step="0.01" value="0"></label>
      <button type="button" class="remove-item" data-remove-item aria-label="Remove item">×</button>`;
    root.querySelector("[data-quotation-items]")?.append(row);
    row.querySelector("input")?.focus();
    update();
  };

  root.querySelector("[data-add-item]")?.addEventListener("click", addItem);
  root.addEventListener("click", (event) => {
    const remove = event.target.closest("[data-remove-item]");
    if (!remove) return;
    remove.closest("[data-quotation-item]")?.remove();
    if (!root.querySelector("[data-quotation-item]")) addItem();
    update();
  });
  form.addEventListener("input", update);
  form.addEventListener("change", update);

  form.addEventListener("submit", (event) => {
    if (!form.checkValidity()) return;
    const items = businessMode()
      ? [
          { description: "On acceptance — engagement and research commencement", quantity: 1, unit_price: "250.00", total: "250.00" },
          { description: "Mid-project research and outreach milestone", quantity: 1, unit_price: "150.00", total: "150.00" },
          { description: "Final delivery of agreed engagement output", quantity: 1, unit_price: "100.00", total: "100.00" },
        ]
      : readItems();
    if (!items.length) {
      event.preventDefault();
      window.alert("Add at least one quotation item.");
      return;
    }
    const currency = businessMode() ? "USD" : value("currency") || "USD";
    const calculated = totals();
    const payload = {
      client_reference: value("client_reference"),
      quotation_type: value("quotation_type"),
      quotation_date: value("quotation_date"),
      valid_until: value("valid_until"),
      currency,
      client_name: value("client_name"),
      company: value("company"),
      email: value("email"),
      phone: value("phone"),
      items,
      subtotal: calculated.subtotal.toFixed(2),
      discount: calculated.discount.toFixed(2),
      service_fee: calculated.serviceFee.toFixed(2),
      grand_total: formatMoney(currency, calculated.grandTotal),
      scope_note: value("scope_note"),
      payment_terms: value("payment_terms"),
      payment_details: value("payment_details"),
    };
    root.querySelector("[data-document-payload]").value = JSON.stringify(payload);
  });

  update();
})();
