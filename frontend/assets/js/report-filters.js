document.querySelectorAll('[data-report-filters]').forEach((panel) => {
    const clientFilter = panel.querySelector('[data-report-client-filter]');
    const technicianFilter = panel.querySelector('[data-report-technician-filter]');
    const visibleCount = panel.querySelector('[data-report-visible-count]');
    const reportRows = Array.from(panel.querySelectorAll('[data-report-filter-row]'));

    const filterRows = () => {
        const client = clientFilter?.value || '';
        const technician = technicianFilter?.value || '';
        let visibleReports = 0;

        reportRows.forEach((row) => {
            const matchesClient = !client || row.dataset.reportClient === client;
            const matchesTechnician = !technician
                || row.dataset.reportTechnician === technician
                || (row.dataset.reportTechnicians || '').split('|').includes(technician);
            row.hidden = !matchesClient || !matchesTechnician;

            if (row.hasAttribute('data-report-summary')) {
                const reportCounts = JSON.parse(row.dataset.reportTechnicianCounts || '{}');
                const count = technician
                    ? Number(reportCounts[technician] || 0)
                    : Object.values(reportCounts).reduce((total, value) => total + Number(value), 0);
                const countBadge = row.querySelector('[data-report-count]');
                if (countBadge) {
                    countBadge.textContent = String(count);
                }
            }

            if (row.hasAttribute('data-report-entry') && !row.hidden) {
                visibleReports++;
            }
        });

        if (visibleCount) {
            visibleCount.textContent = `${visibleReports} reportes visibles`;
        }
    };

    clientFilter?.addEventListener('change', filterRows);
    technicianFilter?.addEventListener('change', filterRows);
    filterRows();
});
