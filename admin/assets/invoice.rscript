(() => {
  "use strict";

  const root = document.querySelector("[data-invoice-generator]");
  if (!root) return;

  const form = root.querySelector("[data-invoice-form]");
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
  const businessMode = () => value("invoice_type") === "Business Matchmaking";
  const paymentSummary = root.querySelector("[data-payment-summary]");
  let paymentProfiles = {};
  try {
    paymentProfiles = JSON.parse(paymentSummary?.dataset.paymentProfiles || "{}");
  } catch (_) {
    paymentProfiles = {};
  }

  const paymentProfile = (currency) => paymentProfiles[currency] || paymentProfiles.USD || {};
  const fillPaymentDetails = (container, entries) => {
    if (!container) return;
    container.querySelectorAll("span").forEach((node) => node.remove());
    entries.forEach(([label, content]) => {
      if (!content) return;
      const item = document.createElement("span");
      const strong = document.createElement("strong");
      item.append(document.createTextNode(label));
      strong.textContent = content;
      item.append(strong);
      container.insertBefore(item, container.querySelector("p"));
    });
  };
  const renderPaymentDetails = (currency) => {
    const payment = paymentProfile(currency);
    fillPaymentDetails(paymentSummary, [
      ["Bank", payment.bank_name],
      ["Account name", payment.account_name],
      ["Account number", payment.account_number],
      ["Account currency", payment.currency],
      ["M-Pesa", payment.mpesa_number ? `${payment.mpesa_name || "Paybill"} ${payment.mpesa_number}` : ""],
      ["Paybill account", payment.mpesa_reference],
    ]);
    fillPaymentDetails(root.querySelector(".preview-payment-details"), [
      ["Bank", payment.bank_name],
      ["Account name", payment.account_name],
      ["Account number", payment.account_number],
      ["Account currency", payment.currency],
      ["Branch", payment.branch],
      ["SWIFT / IBAN", payment.swift_iban],
      ["Bank / branch code", payment.bank_code && payment.branch_code ? `${payment.bank_code} / ${payment.branch_code}` : ""],
      ["Customer number", payment.customer_number],
      ["M-Pesa", payment.mpesa_number ? `${payment.mpesa_name || "Paybill"} ${payment.mpesa_number}` : ""],
      ["Paybill account", payment.mpesa_reference],
    ]);
    text("[data-payment-summary-note]", payment.instructions);
    text("[data-preview-payment-note]", payment.instructions);
  };

  const milestone = () => {
    const milestones = {
      engagement: { description: "Business matchmaking — engagement and research commencement", amount: 250 },
      "mid-project": { description: "Business matchmaking — mid-project research and outreach milestone", amount: 150 },
      "final-delivery": { description: "Business matchmaking — final delivery of agreed engagement output", amount: 100 },
      full: { description: "Business matchmaking — full professional engagement fee", amount: 500 },
    };
    return milestones[value("business_milestone")] || milestones.engagement;
  };

  const readItems = () => {
    if (businessMode()) {
      const selected = milestone();
      return [{ description: selected.description, quantity: 1, unit_price: selected.amount.toFixed(2), total: selected.amount.toFixed(2) }];
    }
    return [...root.querySelectorAll("[data-invoice-item]")].map((row) => {
      const description = String(row.querySelector('[name="item_description[]"]')?.value || "").trim();
      const quantity = Math.max(0, Number(row.querySelector('[name="item_quantity[]"]')?.value || 0) || 0);
      const unitPrice = Math.max(0, Number(row.querySelector('[name="item_price[]"]')?.value || 0) || 0);
      return { description, quantity, unit_price: unitPrice.toFixed(2), total: (quantity * unitPrice).toFixed(2) };
    }).filter((item) => item.description);
  };

  const calculate = () => {
    const subtotal = readItems().reduce((sum, item) => sum + Number(item.total), 0);
    const discount = businessMode() ? 0 : number("discount");
    const serviceFee = businessMode() ? 0 : number("service_fee");
    const total = Math.max(0, subtotal - discount + serviceFee);
    const paid = Math.min(total, number("amount_paid"));
    const balance = Math.max(0, total - paid);
    const dueDate = value("due_date");
    const today = new Date().toISOString().slice(0, 10);
    const status = balance <= 0.005 && total > 0
      ? "Paid"
      : paid > 0
        ? "Part-paid"
        : dueDate && dueDate < today
          ? "Overdue"
          : "Issued";
    return { subtotal, discount, serviceFee, total, paid, balance, status };
  };

  const update = () => {
    const business = businessMode();
    const standardSection = root.querySelector("[data-standard-invoice]");
    const businessSection = root.querySelector("[data-business-invoice]");
    if (standardSection) standardSection.hidden = business;
    if (businessSection) businessSection.hidden = !business;
    root.querySelectorAll("[data-policy-travel]").forEach((section) => { section.hidden = business; });
    root.querySelectorAll("[data-policy-business]").forEach((section) => { section.hidden = !business; });
    if (business) field("currency").value = "USD";

    const currency = business ? "USD" : value("currency") || "USD";
    renderPaymentDetails(currency);
    const totals = calculate();
    field("grand_total_display").value = formatMoney(currency, totals.total);
    field("balance_display").value = formatMoney(currency, totals.balance);
    field("invoice_status_display").value = totals.status;

    text("[data-preview-type]", value("invoice_type"), "Travel & Services");
    text("[data-preview-client]", value("client_name"), "Client Name");
    text("[data-preview-company]", value("company"));
    text("[data-preview-date]", formatDate(value("invoice_date")));
    text("[data-preview-due]", formatDate(value("due_date")));
    text("[data-preview-status]", totals.status);
    text("[data-preview-total]", formatMoney(currency, totals.total));
    text("[data-preview-paid]", formatMoney(currency, totals.paid));
    text("[data-preview-balance]", formatMoney(currency, totals.balance));
    text("[data-form-balance]", formatMoney(currency, totals.balance));
    text("[data-preview-note]", value("invoice_note"));

    const body = root.querySelector("[data-preview-invoice-items]");
    if (body) {
      body.textContent = "";
      const items = readItems();
      (items.length ? items : [{ description: "Invoice item", quantity: 1, total: "0.00" }]).forEach((item) => {
        const row = document.createElement("tr");
        [item.description, String(item.quantity), formatMoney(currency, Number(item.total))].forEach((content) => {
          const cell = document.createElement("td");
          cell.textContent = content;
          row.append(cell);
        });
        body.append(row);
      });
      if (totals.serviceFee > 0) {
        const row = document.createElement("tr");
        ["Professional service fee", "1", formatMoney(currency, totals.serviceFee)].forEach((content) => {
          const cell = document.createElement("td");
          cell.textContent = content;
          row.append(cell);
        });
        body.append(row);
      }
      if (totals.discount > 0) {
        const row = document.createElement("tr");
        ["Discount", "—", `−${formatMoney(currency, totals.discount)}`].forEach((content) => {
          const cell = document.createElement("td");
          cell.textContent = content;
          row.append(cell);
        });
        body.append(row);
      }
    }
  };

  const addItem = () => {
    const row = document.createElement("div");
    row.className = "quotation-item";
    row.dataset.invoiceItem = "";
    row.innerHTML = `
      <label>Description<input type="text" name="item_description[]" placeholder="Service or reservation"></label>
      <label>Qty<input type="number" name="item_quantity[]" min="0" step="1" value="1"></label>
      <label>Unit price<input type="number" name="item_price[]" min="0" step="0.01" value="0"></label>
      <button type="button" class="remove-item" data-remove-invoice-item aria-label="Remove item">×</button>`;
    root.querySelector("[data-invoice-items]")?.append(row);
    row.querySelector("input")?.focus();
    update();
  };

  root.querySelector("[data-source-quotation]")?.addEventListener("change", (event) => {
    const quotation = event.currentTarget.value;
    location.href = quotation ? `invoice.php?quotation=${encodeURIComponent(quotation)}` : "invoice.php";
  });
  root.querySelector("[data-add-invoice-item]")?.addEventListener("click", addItem);
  root.addEventListener("click", (event) => {
    const remove = event.target.closest("[data-remove-invoice-item]");
    if (!remove) return;
    remove.closest("[data-invoice-item]")?.remove();
    if (!root.querySelector("[data-invoice-item]")) addItem();
    update();
  });
  form.addEventListener("input", update);
  form.addEventListener("change", update);

  form.addEventListener("submit", (event) => {
    if (!form.checkValidity()) return;
    const items = readItems();
    if (!items.length) {
      event.preventDefault();
      window.alert("Add at least one invoice item.");
      return;
    }
    const currency = businessMode() ? "USD" : value("currency") || "USD";
    const totals = calculate();
    const payload = {
      client_reference: value("client_reference"),
      source_quotation_id: value("source_quotation"),
      quotation_number: value("quotation_number"),
      invoice_type: value("invoice_type"),
      business_milestone: businessMode() ? value("business_milestone") : "",
      invoice_date: value("invoice_date"),
      due_date: value("due_date"),
      currency,
      client_name: value("client_name"),
      company: value("company"),
      email: value("email"),
      phone: value("phone"),
      items,
      subtotal: totals.subtotal.toFixed(2),
      discount: totals.discount.toFixed(2),
      service_fee: totals.serviceFee.toFixed(2),
      total_amount: totals.total.toFixed(2),
      grand_total: formatMoney(currency, totals.total),
      amount_paid: totals.paid.toFixed(2),
      balance_amount: totals.balance.toFixed(2),
      invoice_status: totals.status,
      invoice_note: value("invoice_note"),
    };
    root.querySelector("[data-document-payload]").value = JSON.stringify(payload);
  });

  update();
})();
