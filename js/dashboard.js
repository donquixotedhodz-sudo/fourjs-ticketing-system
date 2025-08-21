document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle
    document.getElementById('sidebarCollapse').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
        document.getElementById('content').classList.toggle('expanded');
    });

    // Common chart options
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    padding: 20,
                    usePointStyle: true,
                    pointStyle: 'circle'
                }
            }
        },
        interaction: {
            intersect: false,
            mode: 'index'
        },
        layout: {
            padding: {
                top: 10,
                right: 10,
                bottom: 10,
                left: 10
            }
        }
    };

    // Orders Chart - Commented out as it's now handled in dashboard.php with real data
    // const ordersCtx = document.getElementById('ordersChart').getContext('2d');
    // new Chart(ordersCtx, {
    //     type: 'line',
    //     data: {
    //         labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
    //         datasets: [{
    //             label: 'Completed Orders',
    //             data: [65, 59, 80, 81, 56, 55],
    //             borderColor: '#2ECC71',
    //             backgroundColor: 'rgba(46, 204, 113, 0.1)',
    //             tension: 0.4,
    //             fill: true,
    //             borderWidth: 2,
    //             pointBackgroundColor: '#fff',
    //             pointBorderColor: '#2ECC71',
    //             pointBorderWidth: 2,
    //             pointRadius: 4,
    //             pointHoverRadius: 6
    //         }, {
    //             label: 'Pending Orders',
    //             data: [28, 48, 40, 19, 86, 27],
    //             borderColor: '#E74C3C',
    //             backgroundColor: 'rgba(231, 76, 60, 0.1)',
    //             tension: 0.4,
    //             fill: true,
    //             borderWidth: 2,
    //             pointBackgroundColor: '#fff',
    //             pointBorderColor: '#E74C3C',
    //             pointBorderWidth: 2,
    //             pointRadius: 4,
    //             pointHoverRadius: 6
    //         }]
    //     },
    //     options: {
    //         ...commonOptions,
    //         scales: {
    //             y: {
    //                 beginAtZero: true,
    //                 grid: {
    //                     drawBorder: false,
    //                     color: 'rgba(0, 0, 0, 0.03)'
    //                 },
    //                 ticks: {
    //                     padding: 10
    //                 }
    //             },
    //             x: {
    //                 grid: {
    //                     display: false
    //                 },
    //                 ticks: {
    //                     padding: 10
    //                 }
    //             }
    //         }
    //     }
    // });

    // Technician Performance Chart - Commented out as it's now handled in dashboard.php with real data
    // const techCtx = document.getElementById('technicianChart').getContext('2d');
    // new Chart(techCtx, {
    //     type: 'bar',
    //     data: {
    //         labels: ['John Doe', 'Jane Smith', 'Mike Johnson', 'Sarah Wilson', 'Tom Brown'],
    //         datasets: [{
    //             label: 'Completed Jobs',
    //             data: [45, 38, 42, 35, 40],
    //             backgroundColor: [
    //                 '#4A90E2',
    //                 '#2ECC71',
    //                 '#F1C40F',
    //                 '#E74C3C',
    //                 '#3498DB'
    //             ],
    //             borderColor: [
    //                 '#4A90E2',
    //                 '#2ECC71',
    //                 '#F1C40F',
    //                 '#E74C3C',
    //                 '#3498DB'
    //             ],
    //             borderWidth: 1,
    //             borderRadius: 5,
    //             barThickness: 20,
    //             maxBarThickness: 30
    //         }]
    //     },
    //     options: {
    //         ...commonOptions,
    //         scales: {
    //             y: {
    //                 beginAtZero: true,
    //                 grid: {
    //                     drawBorder: false,
    //                     color: 'rgba(0, 0, 0, 0.03)'
    //                 },
    //                 ticks: {
    //                     padding: 10
    //                 }
    //             },
    //             x: {
    //                 grid: {
    //                     display: false
    //                 },
    //                 ticks: {
    //                     padding: 10
    //                 }
    //             }
    //         }
    //     }
    // });

    // Add hover effects to cards
    const cards = document.querySelectorAll('.card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});