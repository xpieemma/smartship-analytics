// js/dashboard.js - SmartShip Analytics Dashboard

class SmartShipDashboard {
  constructor() {
    this.api = new SmartShipAPI();
    this.data = null;
    this.filters = {
      type: "all",
      severity: "all",
      status: "all",
      minSavings: 0,
    };
    this.sortConfig = {
      column: "savings",
      direction: "desc",
    };
    this.pagination = {
      page: 1,
      perPage: 10,
      total: 0,
    };
    this.charts = {};

    this.init();
  }

  async init() {
    this.showLoading();
    await this.loadData();
    this.initEventListeners();
    this.renderDashboard();
    this.hideLoading();
  }

  showLoading() {
    // Add loading overlay if needed
  }

  hideLoading() {
    // Remove loading overlay
  }

  async loadData() {
    try {
      this.data = await this.api.getDashboardData();
    } catch (error) {
      this.showToast("Failed to load dashboard data", "error");
      console.error("Load error:", error);
    }
  }

  initEventListeners() {
    // Filter events
    $("#filterType").on("change", (e) => {
      this.filters.type = e.target.value;
      this.pagination.page = 1;
      this.renderExceptionsTable();
    });

    $("#filterSeverity").on("change", (e) => {
      this.filters.severity = e.target.value;
      this.pagination.page = 1;
      this.renderExceptionsTable();
    });

    $("#filterStatus").on("change", (e) => {
      this.filters.status = e.target.value;
      this.pagination.page = 1;
      this.renderExceptionsTable();
    });

    $("#savingsSlider").on("input", (e) => {
      const value = e.target.value;
      $("#savingsValue").text("$" + value);
      this.filters.minSavings = parseInt(value);
    });

    $("#savingsSlider").on("change", () => {
      this.pagination.page = 1;
      this.renderExceptionsTable();
    });

    // Reset filters
    $("#resetFilters").on("click", () => {
      this.filters = {
        type: "all",
        severity: "all",
        status: "all",
        minSavings: 0,
      };
      $("#filterType").val("all");
      $("#filterSeverity").val("all");
      $("#filterStatus").val("all");
      $("#savingsSlider").val(0);
      $("#savingsValue").text("$0");
      this.pagination.page = 1;
      this.renderExceptionsTable();
    });

    // Sorting
    $(".sortable").on("click", (e) => {
      const column = $(e.currentTarget).data("sort");
      if (this.sortConfig.column === column) {
        this.sortConfig.direction =
          this.sortConfig.direction === "asc" ? "desc" : "asc";
      } else {
        this.sortConfig.column = column;
        this.sortConfig.direction = "desc";
      }
      this.renderExceptionsTable();
    });

    // Pagination
    $("#prevPage").on("click", () => {
      if (this.pagination.page > 1) {
        this.pagination.page--;
        this.renderExceptionsTable();
      }
    });

    $("#nextPage").on("click", () => {
      const maxPage = Math.ceil(
        this.pagination.total / this.pagination.perPage,
      );
      if (this.pagination.page < maxPage) {
        this.pagination.page++;
        this.renderExceptionsTable();
      }
    });

    // Chart filters
    $("#trendPeriod").on("change", () => this.renderTrendChart());
    $("#typeChartFilter").on("change", () => this.renderTypeChart());

    // Export
    $("#exportBtn").on("click", () => this.exportData());

    // Modal
    $(".modal-close, #closeModal").on("click", () => {
      $("#exceptionModal").removeClass("active");
    });

    $(window).on("click", (e) => {
      if ($(e.target).is("#exceptionModal")) {
        $("#exceptionModal").removeClass("active");
      }
    });

    // Dispute button
    $("#disputeException").on("click", () => this.disputeException());
  }

  renderDashboard() {
    this.renderKPIs();
    this.renderHeatmap();
    this.renderTrendChart();
    this.renderTypeChart();
    this.renderExceptionsTable();
  }

  renderKPIs() {
    if (!this.data || !this.data.metrics) return;

    const m = this.data.metrics;
    const kpiHtml = `
            <div class="kpi-card">
                <div class="kpi-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="kpi-label">Total Spend</div>
                <div class="kpi-value">$${this.formatNumber(m.total_spend)}</div>
                <div class="kpi-trend">
                    <i class="fas fa-chart-line trend-up"></i>
                    <span class="trend-up">Last 30 days</span>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="kpi-label">Exceptions</div>
                <div class="kpi-value">${this.formatNumber(m.total_exceptions)}</div>
                <div class="kpi-trend">
                    <i class="fas fa-percent"></i>
                    <span>${m.exception_rate}% of shipments</span>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon">
                    <i class="fas fa-piggy-bank"></i>
                </div>
                <div class="kpi-label">Potential Savings</div>
                <div class="kpi-value">$${this.formatNumber(m.potential_savings)}</div>
                <div class="kpi-trend">
                    <i class="fas fa-arrow-up trend-up"></i>
                    <span class="trend-up">${m.savings_percentage}% of spend</span>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="kpi-label">Delayed Shipments</div>
                <div class="kpi-value">${this.formatNumber(m.delayed_shipments || 0)}</div>
                <div class="kpi-trend">
                    <i class="fas fa-exclamation-circle ${m.delayed_shipments > 0 ? "trend-down" : "trend-up"}"></i>
                    <span>Need attention</span>
                </div>
            </div>
        `;

    $("#kpiContainer").html(kpiHtml);
  }

  renderHeatmap() {
    if (!this.data || !this.data.lanes) return;

    let heatmapHtml = "";

    this.data.lanes.forEach((lane) => {
      // Calculate intensity (0-100) based on exception density
      const exceptionRate =
        lane.shipment_count > 0
          ? (lane.exception_count / lane.shipment_count) * 100
          : 0;

      // Determine color based on exception rate
      let color;
      if (exceptionRate < 20) {
        color = "#10b981"; // green
      } else if (exceptionRate < 50) {
        color = "#f59e0b"; // orange
      } else {
        color = "#ef4444"; // red
      }

      // Calculate opacity based on severity
      const opacity = 0.6 + (exceptionRate / 100) * 0.4;

      heatmapHtml += `
                <div class="heatmap-cell" 
                     style="background-color: ${color}; opacity: ${opacity};"
                     data-lane-id="${lane.id}"
                     data-origin="${lane.origin}"
                     data-destination="${lane.destination}"
                     title="${lane.origin} → ${lane.destination}: ${lane.exception_count} exceptions">
                    <span>${this.getAirportCode(lane.origin)}</span>
                    <span class="lane-code">→ ${this.getAirportCode(lane.destination)}</span>
                    <span class="exception-badge">${lane.exception_count}</span>
                </div>
            `;
    });

    $("#heatmapContainer").html(heatmapHtml);

    // Add click handlers to heatmap cells
    $(".heatmap-cell").on("click", (e) => {
      const cell = $(e.currentTarget);
      const laneId = cell.data("lane-id");
      this.filterByLane(laneId);
    });
  }

  renderTrendChart() {
    const ctx = document.getElementById("trendChart").getContext("2d");
    const period = $("#trendPeriod").val();

    // Sample trend data - in real app, fetch from API
    const labels = this.getLastNDays(parseInt(period));
    const exceptionData = this.generateTrendData(parseInt(period));

    if (this.charts.trend) {
      this.charts.trend.destroy();
    }

    this.charts.trend = new Chart(ctx, {
      type: "line",
      data: {
        labels: labels,
        datasets: [
          {
            label: "Exceptions",
            data: exceptionData,
            borderColor: "#2563eb",
            backgroundColor: "rgba(37, 99, 235, 0.1)",
            tension: 0.4,
            fill: true,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: {
              color: "#e2e8f0",
            },
          },
          x: {
            grid: {
              display: false,
            },
          },
        },
      },
    });
  }


  renderTypeChart() {
    const ctx = document.getElementById("typeChart").getContext("2d");
    const viewBy = $("#typeChartFilter").val();

    if (!this.data || !this.data.exceptions_by_type) return;

    const types = Object.keys(this.data.exceptions_by_type);
    const data = types.map((type) =>
      viewBy === "count"
        ? this.data.exceptions_by_type[type].count
        : this.data.exceptions_by_type[type].savings,
    );

    if (this.charts.type) {
      this.charts.type.destroy();
    }

    this.charts.type = new Chart(ctx, {
      type: "doughnut",
      data: {
        labels: types.map((t) => this.formatExceptionType(t)),
        datasets: [
          {
            data: data,
            backgroundColor: [
              "#ef4444",
              "#f59e0b",
              "#3b82f6",
              "#8b5cf6",
              "#10b981",
            ],
            borderWidth: 0,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "bottom",
          },
        },
      },
    });
  }

// renderTrendChart() {
//     const ctx = document.getElementById('trendChart').getContext('2d');
    
//     if (!this.data || !this.data.charts || !this.data.charts.trend) {
//         console.warn('No trend data available');
//         return;
//     }
    
//     const trendData = this.data.charts.trend;
//     const period = $('#trendPeriod').val();
    
//     let labels = trendData.labels || [];
//     let data = trendData.counts || [];
    
//     if (period === '7' && labels.length > 7) {
//         labels = labels.slice(-7);
//         data = data.slice(-7);
//     }
    
//     if (this.charts.trend) {
//         this.charts.trend.destroy();
//     }
    
//     try {
//         this.charts.trend = new Chart(ctx, {
//             type: 'line',
//             data: {
//                 labels: labels,
//                 datasets: [{
//                     label: 'Exceptions',
//                     data: data,
//                     borderColor: '#2563eb',
//                     backgroundColor: 'rgba(37, 99, 235, 0.1)',
//                     tension: 0.4,
//                     fill: true
//                 }]
//             },
//             options: {
//                 responsive: true,
//                 maintainAspectRatio: false,
//                 plugins: {
//                     legend: { display: false }
//                 },
//                 scales: {
//                     y: {
//                         beginAtZero: true,
//                         ticks: { stepSize: 1 }
//                     }
//                 }
//             }
//         });
//     } catch (error) {
//         console.error('Error rendering trend chart:', error);
//     }
// }

// renderTypeChart() {
//     const ctx = document.getElementById('typeChart').getContext('2d');
//     const viewBy = $('#typeChartFilter').val();
    
//     if (!this.data || !this.data.exceptions_by_type) {
//         console.warn('No type data available');
//         return;
//     }
    
//     const types = this.data.exceptions_by_type;
//     const typeLabels = Object.keys(types).map(t => this.formatExceptionType(t));
    
//     let data;
//     if (viewBy === 'count') {
//         data = Object.values(types).map(t => t.count);
//     } else {
//         data = Object.values(types).map(t => t.savings);
//     }
    
//     const colors = ['#ef4444', '#f59e0b', '#3b82f6', '#8b5cf6', '#10b981'];
    
//     if (this.charts.type) {
//         this.charts.type.destroy();
//     }
    
//     try {
//         this.charts.type = new Chart(ctx, {
//             type: 'doughnut',
//             data: {
//                 labels: typeLabels,
//                 datasets: [{
//                     data: data,
//                     backgroundColor: colors.slice(0, typeLabels.length),
//                     borderWidth: 0
//                 }]
//             },
//             options: {
//                 responsive: true,
//                 maintainAspectRatio: false,
//                 cutout: '65%',
//                 plugins: {
//                     legend: { position: 'bottom' }
//                 }
//             }
//         });
//     } catch (error) {
//         console.error('Error rendering type chart:', error);
//     }
// }
  renderExceptionsTable() {
    if (!this.data || !this.data.exceptions) return;

    // Apply filters
    let filtered = this.data.exceptions.filter((ex) => {
      if (this.filters.type !== "all" && ex.type !== this.filters.type)
        return false;

      if (this.filters.severity !== "all") {
        if (this.filters.severity === "high" && ex.severity < 8) return false;
        if (
          this.filters.severity === "medium" &&
          (ex.severity < 4 || ex.severity > 7)
        )
          return false;
        if (this.filters.severity === "low" && ex.severity > 3) return false;
      }

      if (this.filters.status !== "all" && ex.status !== this.filters.status)
        return false;

      if (ex.potential_savings < this.filters.minSavings) return false;

      return true;
    });

    // Apply sorting
    filtered = this.sortExceptions(filtered);

    // Update total count
    this.pagination.total = filtered.length;
    $("#exceptionCount").text(`${filtered.length} exceptions`);

    // Apply pagination
    const start = (this.pagination.page - 1) * this.pagination.perPage;
    const end = start + this.pagination.perPage;
    const pageData = filtered.slice(start, end);

    // Render table rows
    let tableHtml = "";

    if (pageData.length === 0) {
      tableHtml = `
                <tr>
                    <td colspan="8" class="loading-row">
                        <div class="loading-spinner">No exceptions match your filters</div>
                    </td>
                </tr>
            `;
    } else {
      pageData.forEach((ex) => {
        tableHtml += this.renderExceptionRow(ex);
      });
    }

    $("#tableBody").html(tableHtml);

    // Update pagination
    this.renderPagination();

    // Add view button handlers
    $(".btn-view").on("click", (e) => {
      const id = $(e.currentTarget).data("id");
      this.showExceptionDetails(id);
    });
  }

//   renderExceptionRow(ex) {
//     const badgeClass = this.getExceptionBadgeClass(ex.type);
//     const severityColor = this.getSeverityColor(ex.severity);

//     return `
//             <tr data-exception-id="${ex.id}">
//                 <td>${ex.tracking_number || "N/A"}</td>
//                 <td>${ex.lane || `${ex.origin || "?"} → ${ex.destination || "?"}`}</td>
//                 <td>
//                     <span class="exception-badge ${badgeClass}">
//                         ${this.formatExceptionType(ex.type)}
//                     </span>
//                 </td>
//                 <td>
//                     <span class="detail-preview" title="${this.getExceptionSummary(ex)}">
//                         ${this.truncate(this.getExceptionSummary(ex), 30)}
//                     </span>
//                 </td>
//                 <td>
//                     <div style="display: flex; align-items: center; gap: 0.5rem;">
//                         <span>${ex.severity}/10</span>
//                         <div class="severity-bar">
//                             <div class="severity-fill" style="width: ${ex.severity * 10}%; background: ${severityColor};"></div>
//                         </div>
//                     </div>
//                 </td>
//                 <td><strong>$${this.formatNumber(ex.potential_savings)}</strong></td>
//                 <td>
//                     <span class="status-badge status-${ex.status || "new"}">
//                         ${this.capitalize(ex.status || "new")}
//                     </span>
//                 </td>
//                 <td>
//                     <button class="btn-view" data-id="${ex.id}">
//                         <i class="fas fa-eye"></i> View
//                     </button>
//                 </td>
//             </tr>
//         `;
//   }
renderExceptionRow(ex) {
    const badgeClass = this.getExceptionBadgeClass(ex.exception_type || ex.type);
    const severityColor = this.getSeverityColor(ex.severity);
    
    return `
        <tr data-exception-id="${ex.id}">
            <td><span class="tracking-number">${ex.tracking_number || 'N/A'}</span></td>
            <td>${ex.lane || `${ex.origin || '?'} → ${ex.destination || '?'}`}</td>
            <td>
                <span class="exception-badge ${badgeClass}">
                    ${this.formatExceptionType(ex.exception_type || ex.type)}
                </span>
            </td>
            <td>
                <span class="detail-preview" title="${ex.summary || 'No details'}">
                    ${this.truncate(ex.summary || 'No details', 40)}
                </span>
            </td>
            <td>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-weight: 600; color: ${severityColor};">${ex.severity}</span>
                    <div class="severity-bar">
                        <div class="severity-fill" style="width: ${ex.severity * 10}%; background: ${severityColor};"></div>
                    </div>
                </div>
            </td>
            <td><strong style="color: #059669;">$${this.formatNumber(ex.potential_savings)}</strong></td>
            <td>
                <span class="status-badge status-${ex.status || 'new'}">
                    ${this.capitalize(ex.status || 'new')}
                </span>
            </td>
            <td>
                <button class="btn-view" data-id="${ex.id}">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        </tr>
    `;
}

  renderPagination() {
    const totalPages = Math.ceil(
      this.pagination.total / this.pagination.perPage,
    );
    const start = (this.pagination.page - 1) * this.pagination.perPage + 1;
    const end = Math.min(
      this.pagination.page * this.pagination.perPage,
      this.pagination.total,
    );

    $("#paginationInfo").html(
      `Showing <strong>${start}-${end}</strong> of <strong>${this.pagination.total}</strong> exceptions`,
    );

    // Generate page numbers
    let pageHtml = "";
    for (let i = 1; i <= Math.min(totalPages, 5); i++) {
      pageHtml += `<button class="page-number ${i === this.pagination.page ? "active" : ""}" data-page="${i}">${i}</button>`;
    }

    $("#pageNumbers").html(pageHtml);

    // Add page number click handlers
    $(".page-number").on("click", (e) => {
      const page = parseInt($(e.currentTarget).data("page"));
      this.pagination.page = page;
      this.renderExceptionsTable();
    });

    // Update prev/next buttons
    $("#prevPage").prop("disabled", this.pagination.page === 1);
    $("#nextPage").prop(
      "disabled",
      this.pagination.page === totalPages || totalPages === 0,
    );
  }

  showExceptionDetails(exceptionId) {
    const exception = this.data.exceptions.find((ex) => ex.id === exceptionId);
    if (!exception) return;

    let detailsHtml = `
            <div class="exception-detail">
                <div class="detail-row">
                    <span class="detail-label">Tracking:</span>
                    <span class="detail-value">${exception.tracking_number || "N/A"}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Lane:</span>
                    <span class="detail-value">${exception.lane || `${exception.origin} → ${exception.destination}`}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Type:</span>
                    <span class="detail-value">${this.formatExceptionType(exception.type)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Severity:</span>
                    <span class="detail-value">
                        <span style="color: ${this.getSeverityColor(exception.severity)}; font-weight: bold;">
                            ${exception.severity}/10
                        </span>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Savings:</span>
                    <span class="detail-value"><strong>$${this.formatNumber(exception.potential_savings)}</strong></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value">
                        <span class="status-badge status-${exception.status || "new"}">
                            ${this.capitalize(exception.status || "new")}
                        </span>
                    </span>
                </div>
            </div>
            <h4 style="margin: 1rem 0 0.5rem;">Exception Details</h4>
        `;

    // Parse and display JSON details
    if (exception.details) {
      if (typeof exception.details === "string") {
        try {
          exception.details = JSON.parse(exception.details);
        } catch (e) {
          // Not JSON, use as is
        }
      }

      if (typeof exception.details === "object") {
        Object.entries(exception.details).forEach(([key, value]) => {
          if (value !== null && value !== "") {
            detailsHtml += `
                            <div class="detail-row">
                                <span class="detail-label">${this.formatLabel(key)}:</span>
                                <span class="detail-value">${this.formatDetailValue(value)}</span>
                            </div>
                        `;
          }
        });
      } else {
        detailsHtml += `<p>${exception.details}</p>`;
      }
    }

    $("#modalBody").html(detailsHtml);
    $("#exceptionModal").data("exception-id", exceptionId).addClass("active");
  }

  disputeException() {
    const exceptionId = $("#exceptionModal").data("exception-id");
    this.showToast("Exception marked for dispute", "success");
    $("#exceptionModal").removeClass("active");

    // In real app, call API to update status
    setTimeout(() => {
      this.loadData();
    }, 500);
  }

  filterByLane(laneId) {
    // In real app, filter exceptions by lane
    this.showToast(`Filtering by lane ID: ${laneId}`, "info");
  }

  exportData() {
    if (!this.data || !this.data.exceptions) return;

    // Create CSV
    const headers = [
      "Tracking",
      "Lane",
      "Type",
      "Severity",
      "Savings",
      "Status",
    ];
    const csv = [
      headers.join(","),
      ...this.data.exceptions.map((ex) =>
        [
          ex.tracking_number,
          ex.lane || `${ex.origin}-${ex.destination}`,
          ex.type,
          ex.severity,
          ex.potential_savings,
          ex.status || "new",
        ].join(","),
      ),
    ].join("\n");

    // Download
    const blob = new Blob([csv], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `smartShip_exceptions_${new Date().toISOString().split("T")[0]}.csv`;
    a.click();

    this.showToast("Data exported successfully", "success");
  }

  showToast(message, type = "info") {
    const toast = $(`
            <div class="toast ${type}">
                <i class="fas ${this.getToastIcon(type)}"></i>
                <span>${message}</span>
            </div>
        `);

    $("#toastContainer").append(toast);

    setTimeout(() => {
      toast.fadeOut(300, function () {
        $(this).remove();
      });
    }, 3000);
  }

  // Helper methods
  sortExceptions(exceptions) {
    const { column, direction } = this.sortConfig;
    const multiplier = direction === "asc" ? 1 : -1;

    return [...exceptions].sort((a, b) => {
      let aVal = a[column];
      let bVal = b[column];

      if (column === "savings") {
        aVal = a.potential_savings;
        bVal = b.potential_savings;
      }

      if (typeof aVal === "string") {
        return aVal.localeCompare(bVal) * multiplier;
      }
      return ((aVal || 0) - (bVal || 0)) * multiplier;
    });
  }

  getLastNDays(n) {
    const labels = [];
    for (let i = n - 1; i >= 0; i--) {
      const d = new Date();
      d.setDate(d.getDate() - i);
      labels.push(
        d.toLocaleDateString("en-US", { month: "short", day: "numeric" }),
      );
    }
    return labels;
  }

  generateTrendData(n) {
    // Generate realistic-looking trend data
    return Array.from({ length: n }, () => Math.floor(Math.random() * 15) + 5);
  }

  getAirportCode(city) {
    const codes = {
      "New York": "JFK",
      "Los Angeles": "LAX",
      Chicago: "ORD",
      Houston: "IAH",
      Phoenix: "PHX",
      Philadelphia: "PHL",
      "San Antonio": "SAT",
      "San Diego": "SAN",
      Dallas: "DFW",
      "San Jose": "SJC",
    };
    return codes[city] || city.substring(0, 3).toUpperCase();
  }

  getExceptionBadgeClass(type) {
    const classes = {
      weight_discrepancy: "badge-weight",
      late_delivery: "badge-delivery",
      rate_abuse: "badge-rate",
      duplicate_invoice: "badge-duplicate",
      fuel_surcharge_error: "badge-fuel",
    };
    return classes[type] || "badge-weight";
  }

  getSeverityColor(severity) {
    if (severity >= 8) return "#ef4444";
    if (severity >= 4) return "#f59e0b";
    return "#10b981";
  }

  getExceptionSummary(ex) {
    if (!ex.details) return "No details";

    let details = ex.details;
    if (typeof details === "string") {
      try {
        details = JSON.parse(details);
      } catch (e) {
        return details;
      }
    }

    if (ex.type === "weight_discrepancy") {
      return `Billed: ${details.billed_weight}lbs, Actual: ${details.actual_weight}lbs`;
    }
    if (ex.type === "late_delivery") {
      return `Delayed by ${details.days_late} days`;
    }
    if (ex.type === "rate_abuse") {
      return `Overcharged by $${details.overcharge}`;
    }

    return JSON.stringify(details).substring(0, 50);
  }

  getToastIcon(type) {
    const icons = {
      success: "fa-check-circle",
      error: "fa-exclamation-circle",
      info: "fa-info-circle",
    };
    return icons[type] || icons.info;
  }

  formatNumber(num) {
    if (num === undefined || num === null) return "0";
    return new Intl.NumberFormat("en-US", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(num);
  }

  formatExceptionType(type) {
    return type
      .split("_")
      .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
      .join(" ");
  }

  formatLabel(str) {
    return str
      .split("_")
      .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
      .join(" ");
  }

  formatDetailValue(value) {
    if (typeof value === "object") {
      return JSON.stringify(value);
    }
    if (typeof value === "number") {
      if (value.toString().includes(".")) {
        return value.toFixed(2);
      }
      return value.toString();
    }
    return value || "N/A";
  }

  capitalize(str) {
    if (!str) return "";
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  truncate(str, length) {
    if (!str) return "";
    return str.length > length ? str.substring(0, length) + "..." : str;
  }
}

// API Client
class SmartShipAPI {
  constructor(baseUrl = "/smartship/api") {
    this.baseUrl = baseUrl;
  }

  // async getDashboardData() {
  //     try {
  //         // In production, call your real API
  //         // const response = await fetch(`${this.baseUrl}/audit.php?action=dashboard`);
  //         // return await response.json();

  //         // For demo, return mock data
  //         return this.getMockData();
  //     } catch (error) {
  //         console.error('API Error:', error);
  //         throw error;
  //     }
  // }

  async getDashboardData() {
    try {
      const response = await fetch(`${this.baseUrl}/dashboard-data.php`);
      const data = await response.json();

      if (!data.success) {
        throw new Error(data.error || "Failed to load data");
      }

      return data;
    } catch (error) {
      console.error("API Error:", error);
      // Fallback to mock data
      return this.getMockData();
    }
  }

//   getMockData() {
//     // Mock data for demonstration
//     return {
//       metrics: {
//         total_spend: 45892.5,
//         total_exceptions: 47,
//         potential_savings: 8450.75,
//         savings_percentage: 18.4,
//         exception_rate: 12.3,
//         delayed_shipments: 8,
//       },
//       lanes: [
//         {
//           id: 1,
//           origin: "New York",
//           destination: "Chicago",
//           shipment_count: 15,
//           exception_count: 3,
//         },
//         {
//           id: 2,
//           origin: "Los Angeles",
//           destination: "Dallas",
//           shipment_count: 12,
//           exception_count: 7,
//         },
//         {
//           id: 3,
//           origin: "Chicago",
//           destination: "Houston",
//           shipment_count: 10,
//           exception_count: 2,
//         },
//         {
//           id: 4,
//           origin: "Houston",
//           destination: "Phoenix",
//           shipment_count: 8,
//           exception_count: 4,
//         },
//         {
//           id: 5,
//           origin: "Phoenix",
//           destination: "San Diego",
//           shipment_count: 14,
//           exception_count: 1,
//         },
//         {
//           id: 6,
//           origin: "Philadelphia",
//           destination: "Boston",
//           shipment_count: 9,
//           exception_count: 5,
//         },
//         {
//           id: 7,
//           origin: "San Antonio",
//           destination: "Austin",
//           shipment_count: 11,
//           exception_count: 6,
//         },
//         {
//           id: 8,
//           origin: "San Diego",
//           destination: "San Jose",
//           shipment_count: 7,
//           exception_count: 2,
//         },
//       ],
//       exceptions_by_type: {
//         weight_discrepancy: { count: 18, savings: 3250.5 },
//         late_delivery: { count: 15, savings: 2850.25 },
//         rate_abuse: { count: 8, savings: 1650.0 },
//         duplicate_invoice: { count: 3, savings: 450.0 },
//         fuel_surcharge_error: { count: 3, savings: 250.0 },
//       },
//       exceptions: [
//         {
//           id: 1,
//           tracking_number: "1Z999AA10123456784",
//           origin: "New York",
//           destination: "Chicago",
//           lane: "New York → Chicago",
//           type: "weight_discrepancy",
//           severity: 8,
//           potential_savings: 345.5,
//           status: "new",
//           details: {
//             actual_weight: 145,
//             billed_weight: 195,
//             difference: 50,
//             rate_applied: 6.91,
//             audit_notes: "Billed weight exceeds actual by 34.5%",
//           },
//         },
//         {
//           id: 2,
//           tracking_number: "1Z888BB20234567895",
//           origin: "Los Angeles",
//           destination: "Dallas",
//           lane: "Los Angeles → Dallas",
//           type: "late_delivery",
//           severity: 6,
//           potential_savings: 75.0,
//           status: "reviewed",
//           details: {
//             expected: "2026-02-15",
//             actual: "2026-02-18",
//             days_late: 3,
//             service_level: "Standard",
//             impact: "Minor delay",
//           },
//         },
//         {
//           id: 3,
//           tracking_number: "1Z777CC30345678906",
//           origin: "Chicago",
//           destination: "Houston",
//           lane: "Chicago → Houston",
//           type: "rate_abuse",
//           severity: 9,
//           potential_savings: 567.25,
//           status: "new",
//           details: {
//             expected_rate: 425.0,
//             billed_rate: 725.5,
//             difference: 300.5,
//             contract_section: "Section 4.2",
//             notes: "Applied incorrect class rating",
//           },
//         },
//         {
//           id: 4,
//           tracking_number: "1Z666DD40456789017",
//           origin: "Houston",
//           destination: "Phoenix",
//           lane: "Houston → Phoenix",
//           type: "duplicate_invoice",
//           severity: 10,
//           potential_savings: 450.0,
//           status: "disputed",
//           details: {
//             tracking: "1Z666DD40456789017",
//             duplicate_count: 2,
//             current_invoice: "INV-2026-1234",
//             action_required: "Review for duplicate billing",
//           },
//         },
//         {
//           id: 5,
//           tracking_number: "1Z555EE50567890128",
//           origin: "Phoenix",
//           destination: "San Diego",
//           lane: "Phoenix → San Diego",
//           type: "fuel_surcharge_error",
//           severity: 5,
//           potential_savings: 89.5,
//           status: "resolved",
//           details: {
//             charged: 125.75,
//             expected_max: 95.25,
//             overcharge: 30.5,
//             contract_rate: "15% standard",
//           },
//         },
//         {
//           id: 6,
//           tracking_number: "1Z444FF60678901239",
//           origin: "Philadelphia",
//           destination: "Boston",
//           lane: "Philadelphia → Boston",
//           type: "weight_discrepancy",
//           severity: 4,
//           potential_savings: 123.75,
//           status: "new",
//           details: {
//             actual_weight: 67,
//             billed_weight: 82,
//             difference: 15,
//             rate_applied: 8.25,
//           },
//         },
//         {
//           id: 7,
//           tracking_number: "1Z333GG70789012340",
//           origin: "San Antonio",
//           destination: "Austin",
//           lane: "San Antonio → Austin",
//           type: "late_delivery",
//           severity: 3,
//           potential_savings: 25.0,
//           status: "reviewed",
//           details: {
//             expected: "2026-02-20",
//             actual: "2026-02-21",
//             days_late: 1,
//             service_level: "Express",
//           },
//         },
//         {
//           id: 8,
//           tracking_number: "1Z222HH80890123451",
//           origin: "San Diego",
//           destination: "San Jose",
//           lane: "San Diego → San Jose",
//           type: "rate_abuse",
//           severity: 7,
//           potential_savings: 234.5,
//           status: "new",
//           details: {
//             expected_rate: 315.0,
//             billed_rate: 425.75,
//             difference: 110.75,
//             contract_section: "Section 3.1",
//           },
//         },
//       ],
//     };
//   }

getMockData() {
    // Generate last 30 days of trend data
    const trendLabels = [];
    const trendCounts = [];
    const trendSavings = [];
    
    for (let i = 29; i >= 0; i--) {
        const date = new Date();
        date.setDate(date.getDate() - i);
        trendLabels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
        
        // Create realistic patterns: weekdays have more exceptions, weekends less
        const dayOfWeek = date.getDay();
        let baseCount;
        if (dayOfWeek === 0 || dayOfWeek === 6) {
            baseCount = Math.floor(Math.random() * 3) + 1; // 1-3 on weekends
        } else {
            baseCount = Math.floor(Math.random() * 8) + 5; // 5-12 on weekdays
        }
        
        // Add some trend - more exceptions in recent days
        const trend = Math.floor(i / 5) * 0.5;
        const count = Math.max(0, Math.floor(baseCount + trend));
        trendCounts.push(count);
        trendSavings.push(count * (Math.random() * 50 + 25));
    }
    
    return {
        metrics: {
            total_spend: 45892.50,
            total_exceptions: 47,
            potential_savings: 8450.75,
            savings_percentage: 18.4,
            exception_rate: 12.3,
            delayed_shipments: 8
        },
        lanes: [
            { id: 1, origin: 'New York', destination: 'Chicago', shipment_count: 15, exception_count: 8 },
            { id: 2, origin: 'Los Angeles', destination: 'Dallas', shipment_count: 12, exception_count: 12 },
            { id: 3, origin: 'Chicago', destination: 'Houston', shipment_count: 10, exception_count: 4 },
            { id: 4, origin: 'Houston', destination: 'Phoenix', shipment_count: 8, exception_count: 6 },
            { id: 5, origin: 'Phoenix', destination: 'San Diego', shipment_count: 14, exception_count: 3 },
            { id: 6, origin: 'Philadelphia', destination: 'Boston', shipment_count: 9, exception_count: 7 },
            { id: 7, origin: 'San Antonio', destination: 'Austin', shipment_count: 11, exception_count: 9 },
            { id: 8, origin: 'San Diego', destination: 'San Jose', shipment_count: 7, exception_count: 5 }
        ],
        charts: {
            trend: {
                labels: trendLabels,
                counts: trendCounts,
                savings: trendSavings
            },
            types: {
                'weight_discrepancy': { count: 18, savings: 3250.50, avg_severity: 7.2 },
                'late_delivery': { count: 15, savings: 2850.25, avg_severity: 5.8 },
                'rate_abuse': { count: 8, savings: 1650.00, avg_severity: 8.1 },
                'duplicate_invoice': { count: 3, savings: 450.00, avg_severity: 9.5 },
                'fuel_surcharge_error': { count: 3, savings: 250.00, avg_severity: 4.3 }
            }
        },
        exceptions: [
            {
                id: 1,
                tracking_number: '1Z999AA10123456784',
                origin: 'New York',
                destination: 'Chicago',
                lane: 'New York → Chicago',
                exception_type: 'weight_discrepancy',
                severity: 8,
                potential_savings: 345.50,
                status: 'new',
                created_at_formatted: 'Feb 15, 2026',
                summary: 'Billed: 195lbs, Actual: 145lbs (34.5% over)',
                details: {
                    actual_weight: 145,
                    billed_weight: 195,
                    difference: 50,
                    difference_percent: 34.5,
                    rate_applied: 6.91,
                    audit_notes: 'Billed weight exceeds actual by 34.5%'
                }
            },
            {
                id: 2,
                tracking_number: '1Z888BB20234567895',
                origin: 'Los Angeles',
                destination: 'Dallas',
                lane: 'Los Angeles → Dallas',
                exception_type: 'late_delivery',
                severity: 6,
                potential_savings: 75.00,
                status: 'reviewed',
                created_at_formatted: 'Feb 14, 2026',
                summary: 'Delayed by 3 days (Expected: 2026-02-15)',
                details: {
                    expected: '2026-02-15',
                    actual: '2026-02-18',
                    days_late: 3,
                    service_level: 'Standard',
                    impact: 'Minor delay'
                }
            },
            {
                id: 3,
                tracking_number: '1Z777CC30345678906',
                origin: 'Chicago',
                destination: 'Houston',
                lane: 'Chicago → Houston',
                exception_type: 'rate_abuse',
                severity: 9,
                potential_savings: 567.25,
                status: 'new',
                created_at_formatted: 'Feb 14, 2026',
                summary: 'Overcharged by $300.50',
                details: {
                    expected_rate: 425.00,
                    billed_rate: 725.50,
                    overcharge: 300.50,
                    contract_section: 'Section 4.2',
                    notes: 'Applied incorrect class rating'
                }
            },
            {
                id: 4,
                tracking_number: '1Z666DD40456789017',
                origin: 'Houston',
                destination: 'Phoenix',
                lane: 'Houston → Phoenix',
                exception_type: 'duplicate_invoice',
                severity: 10,
                potential_savings: 450.00,
                status: 'disputed',
                created_at_formatted: 'Feb 13, 2026',
                summary: 'Duplicate #2 found',
                details: {
                    tracking: '1Z666DD40456789017',
                    duplicate_count: 2,
                    current_invoice: 'INV-2026-1234',
                    action_required: 'Review for duplicate billing'
                }
            },
            {
                id: 5,
                tracking_number: '1Z555EE50567890128',
                origin: 'Phoenix',
                destination: 'San Diego',
                lane: 'Phoenix → San Diego',
                exception_type: 'fuel_surcharge_error',
                severity: 5,
                potential_savings: 89.50,
                status: 'resolved',
                created_at_formatted: 'Feb 12, 2026',
                summary: 'Fuel surcharge $30.50 over max',
                details: {
                    charged: 125.75,
                    expected_max: 95.25,
                    overcharge: 30.50,
                    contract_rate: '15% standard'
                }
            }
        ]
    };
}
}

// Initialize dashboard when document is ready
$(document).ready(() => {
  window.dashboard = new SmartShipDashboard();
});
