(function($) {
    'use strict';

    function initChart() {
        var canvas = document.getElementById('wpAnalyticsChart');
        if (!canvas || !window.wpAnalyticsChartData || typeof Chart === 'undefined') {
            return false;
        }

        var chartData = window.wpAnalyticsChartData;
        var labels = chartData.map(function(d) { return d.label; });
        var views = chartData.map(function(d) { return d.views; });
        var visitors = chartData.map(function(d) { return d.visitors; });

        var ctx = canvas.getContext('2d');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Visualizações',
                        data: views,
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34, 113, 177, 0.08)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointRadius: labels.length > 31 ? 0 : 3,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#2271b1'
                    },
                    {
                        label: 'Visitantes Únicos',
                        data: visitors,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.05)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointRadius: labels.length > 31 ? 0 : 3,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#10b981'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            font: {
                                size: 12,
                                family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1d2327',
                        titleFont: { size: 13 },
                        bodyFont: { size: 12 },
                        padding: 10,
                        cornerRadius: 4
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: '#f0f0f1'
                        },
                        ticks: {
                            font: { size: 11 },
                            maxTicksLimit: 14
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f0f0f1'
                        },
                        ticks: {
                            precision: 0,
                            font: { size: 11 }
                        }
                    }
                }
            }
        });
        return true;
    }

    function waitForChart(retries) {
        if (retries <= 0) return;
        if (!initChart()) {
            requestAnimationFrame(function() {
                waitForChart(retries - 1);
            });
        }
    }

    window.addEventListener('load', function() {
        if (!initChart()) {
            waitForChart(60);
        }
    });
})(jQuery);
