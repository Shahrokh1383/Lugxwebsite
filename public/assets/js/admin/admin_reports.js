/**
 * public/assets/js/admin/admin_reports.js
 *
 * This file handles the functionality for the admin reports page.
 * It interacts with the AdminReportController backend to display various reports.
 */

document.addEventListener('DOMContentLoaded', function() {
    const loadingSpinner = document.getElementById('loadingSpinner');
    const reportsContainer = document.getElementById('reportsContainer');
    const messageDiv = document.getElementById('message');

    // Get base URL path from the global variable injected by PHP
    const baseUrlPath = window.AppBaseUrlPath || '';

    // Chart instances
    let salesChart = null;
    let topPagesChart = null;
    let deviceChart = null;
    let dailyStatsChart = null;

    /**
     * Fetches dashboard statistics and populates the stat cards.
     */
    async function loadDashboardStats() {
        try {
            const response = await fetch(`${baseUrlPath}/api/admin/reports/dashboard-stats`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP error! Status: ${response.status}. Response: ${errorText}`);
            }

            const data = await response.json();

            if (data.success) {
                const stats = data.data;
                
                // Update stat cards
                document.getElementById('totalUsers').textContent = stats.total_users || 0;
                document.getElementById('totalProducts').textContent = stats.total_products || 0;
                document.getElementById('totalOrders').textContent = stats.total_orders || 0;
                document.getElementById('currentMonthRevenue').textContent = `$${(stats.current_month_revenue || 0).toFixed(2)}`;
            } else {
                Admin.showAlert(data.message || 'Failed to load dashboard statistics.', 'danger', 'message');
            }
        } catch (error) {
            console.error('Network or unexpected error fetching dashboard stats:', error);
            Admin.showAlert('Network error. Could not load dashboard statistics.', 'danger', 'message');
        }
    }

    /**
     * Initializes the sales report tab.
     */
    async function initSalesReport() {
        // Populate year selector
        const yearSelector = document.getElementById('yearSelector');
        const salesYear = document.getElementById('salesYear');
        const currentYear = new Date().getFullYear();
        
        for (let year = currentYear; year >= currentYear - 5; year--) {
            const option = document.createElement('option');
            option.value = year;
            option.textContent = year;
            if (year === currentYear) {
                option.selected = true;
            }
            salesYear.appendChild(option);
        }

        // Set up event listeners
        document.getElementById('salesPeriod').addEventListener('change', function() {
            const period = this.value;
            yearSelector.style.display = period === 'year' ? 'block' : 'none';
            document.getElementById('customDateRange').style.display = period === 'custom' ? 'flex' : 'none';
        });

        document.getElementById('refreshSalesBtn').addEventListener('click', loadSalesReport);

        // Load initial sales report
        loadSalesReport();
    }

    /**
     * Loads and displays the sales report.
     */
    async function loadSalesReport() {
        const period = document.getElementById('salesPeriod').value;
        const year = document.getElementById('salesYear').value;
        const dateFrom = document.getElementById('salesDateFrom').value;
        const dateTo = document.getElementById('salesDateTo').value;

        try {
            // Build URL with parameters
            let url = `${baseUrlPath}/api/admin/reports/sales?period=${period}`;
            
            if (period === 'year') {
                url += `&year=${year}`;
            } else if (period === 'custom' && dateFrom && dateTo) {
                url += `&date_from=${dateFrom}&date_to=${dateTo}`;
            }

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP error! Status: ${response.status}. Response: ${errorText}`);
            }

            const data = await response.json();

            if (data.success) {
                renderSalesChart(data.data, data.period);
            } else {
                Admin.showAlert(data.message || 'Failed to load sales report.', 'danger', 'message');
            }
        } catch (error) {
            console.error('Network or unexpected error fetching sales report:', error);
            Admin.showAlert('Network error. Could not load sales report.', 'danger', 'message');
        }
    }

    /**
     * Renders the sales chart.
     * @param {Array} data The sales data.
     * @param {string} period The report period.
     */
    function renderSalesChart(data, period) {
        const ctx = document.getElementById('salesChart').getContext('2d');
        
        // Destroy existing chart if it exists
        if (salesChart) {
            salesChart.destroy();
        }

        // Prepare data for the chart
        const labels = [];
        const revenueData = [];
        const ordersData = [];

        if (period === 'month') {
            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            
            for (let i = 1; i <= 12; i++) {
                const monthData = data.find(item => item.month === i) || { month: i, orders_count: 0, revenue: 0 };
                labels.push(monthNames[i - 1]);
                revenueData.push(monthData.revenue || 0);
                ordersData.push(monthData.orders_count || 0);
            }
        } else if (period === 'year') {
            data.forEach(item => {
                labels.push(item.year.toString());
                revenueData.push(item.revenue || 0);
                ordersData.push(item.orders_count || 0);
            });
        } else if (period === 'custom') {
            data.forEach(item => {
                const date = new Date(item.date);
                labels.push(date.toLocaleDateString());
                revenueData.push(item.revenue || 0);
                ordersData.push(item.orders_count || 0);
            });
        }

        // Create the chart
        salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Revenue',
                        data: revenueData,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Orders',
                        data: ordersData,
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Revenue ($)'
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Orders'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    }

    /**
     * Initializes the top products report tab.
     */
    async function initTopProductsReport() {
        document.getElementById('refreshProductsBtn').addEventListener('click', loadTopProductsReport);

        // Load initial top products report
        loadTopProductsReport();
    }

    /**
     * Loads and displays the top products report.
     */
    async function loadTopProductsReport() {
        const period = document.getElementById('productsPeriod').value;
        const limit = document.getElementById('productsLimit')?.value || 10;

        try {
            const response = await fetch(`${baseUrlPath}/api/admin/reports/top-products?period=${period}&limit=${limit}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP error! Status: ${response.status}. Response: ${errorText}`);
            }

            const data = await response.json();

            if (data.success) {
                renderTopProductsTable(data.data);
            } else {
                Admin.showAlert(data.message || 'Failed to load top products report.', 'danger', 'message');
            }
        } catch (error) {
            console.error('Network or unexpected error fetching top products report:', error);
            Admin.showAlert('Network error. Could not load top products report.', 'danger', 'message');
        }
    }

    /**
     * Renders the top products table.
     * @param {Array} data The top products data.
     */
    function renderTopProductsTable(data) {
        const tableBody = document.getElementById('topProductsTableBody');
        tableBody.innerHTML = '';

        if (data.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No products found for the selected period.</td></tr>';
            return;
        }

        data.forEach(product => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${Admin.escapeHtml(product.name || 'Unknown Product')}</td>
                <td>${product.units_sold || 0}</td>
                <td>$${(product.revenue || 0).toFixed(2)}</td>
            `;
            tableBody.appendChild(row);
        });
    }

    /**
     * Initializes the user activity report tab.
     */
    async function initUserActivityReport() {
        document.getElementById('filterActivityBtn').addEventListener('click', loadUserActivityReport);

        // Load initial user activity report
        loadUserActivityReport();
    }

    /**
     * Loads and displays the user activity report.
     */
    async function loadUserActivityReport(page = 1) {
        const action = document.getElementById('activityAction').value;
        const modelType = document.getElementById('activityModelType').value;
        const dateFrom = document.getElementById('activityDateFrom').value;
        const dateTo = document.getElementById('activityDateTo').value;
        const limit = 50;

        try {
            // Build URL with parameters
            let url = `${baseUrlPath}/api/admin/reports/user-activity?page=${page}&limit=${limit}`;
            
            if (action) {
                url += `&action=${action}`;
            }
            
            if (modelType) {
                url += `&model_type=${modelType}`;
            }
            
            if (dateFrom) {
                url += `&date_from=${dateFrom}`;
            }
            
            if (dateTo) {
                url += `&date_to=${dateTo}`;
            }

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP error! Status: ${response.status}. Response: ${errorText}`);
            }

            const data = await response.json();

            if (data.success) {
                renderActivityTable(data.data.data);
                renderActivityPagination(data.data.pagination);
            } else {
                Admin.showAlert(data.message || 'Failed to load user activity report.', 'danger', 'message');
            }
        } catch (error) {
            console.error('Network or unexpected error fetching user activity report:', error);
            Admin.showAlert('Network error. Could not load user activity report.', 'danger', 'message');
        }
    }

    /**
     * Renders the user activity table.
     * @param {Array} data The activity data.
     */
    function renderActivityTable(data) {
        const tableBody = document.getElementById('activityTableBody');
        tableBody.innerHTML = '';

        if (data.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No activity logs found for the selected filters.</td></tr>';
            return;
        }

        data.forEach(log => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${log.first_name && log.last_name ? 
                    `${Admin.escapeHtml(log.first_name)} ${Admin.escapeHtml(log.last_name)}` : 
                    (log.email ? Admin.escapeHtml(log.email) : 'System')}</td>
                <td>${Admin.escapeHtml(log.action)}</td>
                <td>${Admin.escapeHtml(log.description || '')}</td>
                <td>${Admin.escapeHtml(log.ip_address || '')}</td>
                <td>${new Date(log.created_at).toLocaleString()}</td>
            `;
            tableBody.appendChild(row);
        });
    }

    /**
     * Renders the user activity pagination.
     * @param {Object} pagination The pagination data.
     */
    function renderActivityPagination(pagination) {
        const paginationContainer = document.getElementById('activityPagination');
        paginationContainer.innerHTML = '';

        if (pagination.pages <= 1) {
            return;
        }

        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${pagination.page <= 1 ? 'disabled' : ''}`;
        
        const prevLink = document.createElement('a');
        prevLink.className = 'page-link';
        prevLink.href = '#';
        prevLink.setAttribute('aria-label', 'Previous');
        prevLink.innerHTML = '<span aria-hidden="true">&laquo;</span>';
        
        if (pagination.page > 1) {
            prevLink.addEventListener('click', function(e) {
                e.preventDefault();
                loadUserActivityReport(pagination.page - 1);
            });
        }
        
        prevLi.appendChild(prevLink);
        paginationContainer.appendChild(prevLi);

        // Page numbers
        for (let i = 1; i <= pagination.pages; i++) {
            const pageLi = document.createElement('li');
            pageLi.className = `page-item ${i === pagination.page ? 'active' : ''}`;
            
            const pageLink = document.createElement('a');
            pageLink.className = 'page-link';
            pageLink.href = '#';
            pageLink.textContent = i;
            
            if (i !== pagination.page) {
                pageLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    loadUserActivityReport(i);
                });
            }
            
            pageLi.appendChild(pageLink);
            paginationContainer.appendChild(pageLi);
        }

        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${pagination.page >= pagination.pages ? 'disabled' : ''}`;
        
        const nextLink = document.createElement('a');
        nextLink.className = 'page-link';
        nextLink.href = '#';
        nextLink.setAttribute('aria-label', 'Next');
        nextLink.innerHTML = '<span aria-hidden="true">&raquo;</span>';
        
        if (pagination.page < pagination.pages) {
            nextLink.addEventListener('click', function(e) {
                e.preventDefault();
                loadUserActivityReport(pagination.page + 1);
            });
        }
        
        nextLi.appendChild(nextLink);
        paginationContainer.appendChild(nextLi);
    }

    /**
     * Initializes the page views report tab.
     */
    async function initPageViewsReport() {
        document.getElementById('pageViewReportType').addEventListener('change', function() {
            const reportType = this.value;
            
            // Hide all containers
            document.getElementById('pageViewListContainer').style.display = 'none';
            document.getElementById('topPagesContainer').style.display = 'none';
            document.getElementById('deviceStatsContainer').style.display = 'none';
            document.getElementById('dailyStatsContainer').style.display = 'none';
            
            // Show the selected container
            switch (reportType) {
                case 'list':
                    document.getElementById('pageViewListContainer').style.display = 'block';
                    break;
                case 'top_pages':
                    document.getElementById('topPagesContainer').style.display = 'block';
                    break;
                case 'device_stats':
                    document.getElementById('deviceStatsContainer').style.display = 'block';
                    break;
                case 'daily_stats':
                    document.getElementById('dailyStatsContainer').style.display = 'block';
                    break;
            }
        });

        document.getElementById('refreshPageViewsBtn').addEventListener('click', loadPageViewsReport);

        // Set default date range (last 30 days)
        const today = new Date();
        const thirtyDaysAgo = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));
        
        document.getElementById('pageViewDateFrom').value = thirtyDaysAgo.toISOString().split('T')[0];
        document.getElementById('pageViewDateTo').value = today.toISOString().split('T')[0];

        // Load initial page views report
        loadPageViewsReport();
    }

    /**
     * Loads and displays the page views report.
     */
    async function loadPageViewsReport(page = 1) {
        const reportType = document.getElementById('pageViewReportType').value;
        const dateFrom = document.getElementById('pageViewDateFrom').value;
        const dateTo = document.getElementById('pageViewDateTo').value;
        const url = document.getElementById('pageViewUrl').value;
        const deviceType = document.getElementById('pageViewDeviceType').value;
        const limit = 50;

        try {
            // Build URL with parameters
            let apiUrl = `${baseUrlPath}/api/admin/reports/page-views?report_type=${reportType}&date_from=${dateFrom}&date_to=${dateTo}&limit=${limit}`;
            
            if (url) {
                apiUrl += `&url=${url}`;
            }
            
            if (deviceType) {
                apiUrl += `&device_type=${deviceType}`;
            }
            
            if (reportType === 'list') {
                apiUrl += `&page=${page}`;
            }

            const response = await fetch(apiUrl, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP error! Status: ${response.status}. Response: ${errorText}`);
            }

            const data = await response.json();

            if (data.success) {
                switch (reportType) {
                    case 'list':
                        renderPageViewTable(data.data.data);
                        renderPageViewPagination(data.data.pagination);
                        break;
                    case 'top_pages':
                        renderTopPagesChart(data.data);
                        break;
                    case 'device_stats':
                        renderDeviceStats(data.data);
                        break;
                    case 'daily_stats':
                        renderDailyStatsChart(data.data);
                        break;
                }
            } else {
                Admin.showAlert(data.message || 'Failed to load page views report.', 'danger', 'message');
            }
        } catch (error) {
            console.error('Network or unexpected error fetching page views report:', error);
            Admin.showAlert('Network error. Could not load page views report.', 'danger', 'message');
        }
    }

    /**
     * Renders the page views table.
     * @param {Array} data The page views data.
     */
    function renderPageViewTable(data) {
        const tableBody = document.getElementById('pageViewTableBody');
        tableBody.innerHTML = '';

        if (data.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No page views found for the selected filters.</td></tr>';
            return;
        }

        data.forEach(view => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${Admin.escapeHtml(view.url)}</td>
                <td>${view.first_name && view.last_name ? 
                    `${Admin.escapeHtml(view.first_name)} ${Admin.escapeHtml(view.last_name)}` : 
                    (view.email ? Admin.escapeHtml(view.email) : 'Guest')}</td>
                <td>${Admin.escapeHtml(view.device_type || '')}</td>
                <td>${new Date(view.created_at).toLocaleString()}</td>
            `;
            tableBody.appendChild(row);
        });
    }

    /**
     * Renders the page views pagination.
     * @param {Object} pagination The pagination data.
     */
    function renderPageViewPagination(pagination) {
        const paginationContainer = document.getElementById('pageViewPagination');
        paginationContainer.innerHTML = '';

        if (pagination.pages <= 1) {
            return;
        }

        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${pagination.page <= 1 ? 'disabled' : ''}`;
        
        const prevLink = document.createElement('a');
        prevLink.className = 'page-link';
        prevLink.href = '#';
        prevLink.setAttribute('aria-label', 'Previous');
        prevLink.innerHTML = '<span aria-hidden="true">&laquo;</span>';
        
        if (pagination.page > 1) {
            prevLink.addEventListener('click', function(e) {
                e.preventDefault();
                loadPageViewsReport(pagination.page - 1);
            });
        }
        
        prevLi.appendChild(prevLink);
        paginationContainer.appendChild(prevLi);

        // Page numbers
        for (let i = 1; i <= pagination.pages; i++) {
            const pageLi = document.createElement('li');
            pageLi.className = `page-item ${i === pagination.page ? 'active' : ''}`;
            
            const pageLink = document.createElement('a');
            pageLink.className = 'page-link';
            pageLink.href = '#';
            pageLink.textContent = i;
            
            if (i !== pagination.page) {
                pageLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    loadPageViewsReport(i);
                });
            }
            
            pageLi.appendChild(pageLink);
            paginationContainer.appendChild(pageLi);
        }

        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${pagination.page >= pagination.pages ? 'disabled' : ''}`;
        
        const nextLink = document.createElement('a');
        nextLink.className = 'page-link';
        nextLink.href = '#';
        nextLink.setAttribute('aria-label', 'Next');
        nextLink.innerHTML = '<span aria-hidden="true">&raquo;</span>';
        
        if (pagination.page < pagination.pages) {
            nextLink.addEventListener('click', function(e) {
                e.preventDefault();
                loadPageViewsReport(pagination.page + 1);
            });
        }
        
        nextLi.appendChild(nextLink);
        paginationContainer.appendChild(nextLi);
    }

    /**
     * Renders the top pages chart.
     * @param {Array} data The top pages data.
     */
    function renderTopPagesChart(data) {
        const ctx = document.getElementById('topPagesChart').getContext('2d');
        
        // Destroy existing chart if it exists
        if (topPagesChart) {
            topPagesChart.destroy();
        }

        // Prepare data for the chart
        const labels = data.map(page => {
            const url = new URL(page.url);
            return url.pathname.length > 30 ? url.pathname.substring(0, 30) + '...' : url.pathname;
        });
        
        const viewsData = data.map(page => page.views || 0);
        const uniqueVisitorsData = data.map(page => page.unique_visitors || 0);

        // Create the chart
        topPagesChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Total Views',
                        data: viewsData,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Unique Visitors',
                        data: uniqueVisitorsData,
                        backgroundColor: 'rgba(255, 99, 132, 0.5)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Views'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Pages'
                        }
                    }
                }
            }
        });
    }

    /**
     * Renders the device statistics.
     * @param {Array} data The device statistics data.
     */
    function renderDeviceStats(data) {
        const ctx = document.getElementById('deviceChart').getContext('2d');
        
        // Destroy existing chart if it exists
        if (deviceChart) {
            deviceChart.destroy();
        }

        // Prepare data for the chart
        const labels = data.map(device => device.device_type || 'Unknown');
        const viewsData = data.map(device => device.views || 0);
        const uniqueVisitorsData = data.map(device => device.unique_visitors || 0);

        // Create the chart
        deviceChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [
                    {
                        data: viewsData,
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.5)',
                            'rgba(255, 99, 132, 0.5)',
                            'rgba(255, 205, 86, 0.5)',
                            'rgba(75, 192, 192, 0.5)',
                            'rgba(153, 102, 255, 0.5)',
                            'rgba(255, 159, 64, 0.5)'
                        ],
                        borderColor: '#ffffff',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Render the table
        const tableBody = document.getElementById('deviceStatsTableBody');
        tableBody.innerHTML = '';

        if (data.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No device statistics found for the selected period.</td></tr>';
            return;
        }

        data.forEach(device => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${Admin.escapeHtml(device.device_type || '')}</td>
                <td>${device.views || 0}</td>
                <td>${device.unique_visitors || 0}</td>
            `;
            tableBody.appendChild(row);
        });
    }

    /**
     * Renders the daily statistics chart.
     * @param {Array} data The daily statistics data.
     */
    function renderDailyStatsChart(data) {
        const ctx = document.getElementById('dailyStatsChart').getContext('2d');
        
        // Destroy existing chart if it exists
        if (dailyStatsChart) {
            dailyStatsChart.destroy();
        }

        // Prepare data for the chart
        const labels = data.map(stat => {
            const date = new Date(stat.date);
            return date.toLocaleDateString();
        });
        
        const viewsData = data.map(stat => stat.views || 0);
        const uniqueVisitorsData = data.map(stat => stat.unique_visitors || 0);

        // Create the chart
        dailyStatsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Total Views',
                        data: viewsData,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Unique Visitors',
                        data: uniqueVisitorsData,
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Views'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                }
            }
        });
    }

    // Initialize reports
    async function initReports() {
        // Load dashboard stats
        await loadDashboardStats();
        
        // Initialize each report tab
        await initSalesReport();
        await initTopProductsReport();
        await initUserActivityReport();
        await initPageViewsReport();
        
        // Hide loading spinner and show reports container
        if (loadingSpinner) loadingSpinner.style.display = 'none';
        if (reportsContainer) reportsContainer.style.display = 'block';
    }

    // Initialize reports when the page loads
    initReports();
});