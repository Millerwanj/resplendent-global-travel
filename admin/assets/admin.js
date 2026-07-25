(() => {
  "use strict";

  const sidebar = document.querySelector("#admin-sidebar");
  const toggle = document.querySelector("[data-sidebar-toggle]");
  toggle?.addEventListener("click", () => {
    const open = sidebar?.classList.toggle("open") || false;
    toggle.setAttribute("aria-expanded", String(open));
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      sidebar?.classList.remove("open");
      toggle?.setAttribute("aria-expanded", "false");
      document.querySelector(".live-preview-panel.expanded")?.classList.remove("expanded");
    }
  });

  document.querySelectorAll("[data-preview-expand]").forEach((button) => {
    button.addEventListener("click", () => {
      const panel = button.closest(".live-preview-panel");
      const expanded = panel?.classList.toggle("expanded") || false;
      button.textContent = expanded ? "Close preview" : "Full screen";
    });
  });

  const search = document.querySelector("[data-workflow-search]");
  const status = document.querySelector("[data-workflow-status]");
  const rows = [...document.querySelectorAll("[data-workflow-row]")];
  const empty = document.querySelector("[data-workflow-empty]");
  const filterWorkflow = () => {
    const query = (search?.value || "").trim().toLowerCase();
    const selectedStatus = status?.value || "";
    let visible = 0;
    rows.forEach((row) => {
      const matchesText = !query || (row.dataset.search || "").includes(query);
      const matchesStatus = !selectedStatus || row.dataset.status === selectedStatus;
      row.hidden = !(matchesText && matchesStatus);
      if (!row.hidden) visible += 1;
    });
    if (empty) empty.hidden = visible !== 0;
  };
  search?.addEventListener("input", filterWorkflow);
  status?.addEventListener("change", filterWorkflow);
})();
