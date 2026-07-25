(() => {
  "use strict";

  const params = new URLSearchParams(location.search);
  const reference = (params.get("reference") || "").trim().toUpperCase();
  const referenceNode = document.querySelector("[data-reference]");
  const card = document.querySelector("[data-reference-card]");
  const validReference = /^RGTS-\d{8}-[A-Z0-9]{4}$/.test(reference);

  if (referenceNode) referenceNode.textContent = validReference ? reference : "Available in your confirmation email";
  if (card && !validReference) card.querySelector("button")?.remove();

  document.querySelector("[data-copy-reference]")?.addEventListener("click", async (event) => {
    if (!validReference) return;
    try {
      await navigator.clipboard.writeText(reference);
      event.currentTarget.textContent = "Copied";
    } catch {
      event.currentTarget.textContent = reference;
    }
  });

  if (validReference && typeof window.gtag === "function") {
    window.gtag("event", "generate_lead", {
      form_name: "Website Contact Enquiry",
      enquiry_reference: reference,
    });
  }
})();
