<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<div class="d-flex justify-content-center my-5">
    <div class="notif-wrapper">
        <!-- Selected location -->
        <div class="notif-header">
            <h2 id="selectedLocation">Loading location...</h2>
            <p>Weather alerts for your selected location</p>
        </div>

        <div class="notif-container">
            <!-- Loading message -->
            <div class="notification sunny">
                <div class="icon"><i class="fas fa-bell"></i></div>
                <div class="content">
                    <div class="title">Loading alerts...</div>
                    <div class="description">Please wait while we fetch the latest alerts.</div>
                </div>
                <div class="time">--:--</div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {

    async function fetchAlerts() {
        let lat = 14.4297; // default
        let lon = 120.9367; // default
        let locationName = "Imus, Cavite"; // default

        // override with dashboard-selected location if exists
        const stored = localStorage.getItem('selectedLocation');
        if (stored) {
            try {
                const loc = JSON.parse(stored);
                if (loc && loc.latitude && loc.longitude) {
                    lat = loc.latitude;
                    lon = loc.longitude;
                    locationName = loc.name;
                }
            } catch (e) { console.warn(e); }
        }

        // Update location header
        const locationEl = document.getElementById('selectedLocation');
        if (locationEl) locationEl.textContent = locationName;

        try {
            const res = await fetch(`<?= site_url('notification/weather_alerts'); ?>?lat=${lat}&lon=${lon}`);
            const data = await res.json();

            const container = document.querySelector('.notif-container');
            container.innerHTML = '';

            if (data.length === 0) {
                container.innerHTML = `
                    <div class="notification sunny">
                        <div class="icon"><i class="fas fa-bell"></i></div>
                        <div class="content">
                            <div class="title">No alerts for this location</div>
                            <div class="description">You're safe! There are currently no weather alerts.</div>
                        </div>
                        <div class="time">--:--</div>
                    </div>
                `;
                return;
            }

            data.forEach(note => {
                // Assign alert type classes based on severity
                let typeClass = "sunny";
                if (note.icon.includes('triangle')) typeClass = "stormy";
                else if (note.icon.includes('cloud')) typeClass = "rainy";
                else if (note.icon.includes('circle')) typeClass = "cloudy";

                container.innerHTML += `
                    <div class="notification ${typeClass}">
                        <div class="icon"><i class="fas ${note.icon}"></i></div>
                        <div class="content">
                            <div class="title">${note.title}</div>
                            <div class="description">${note.description}</div>
                        </div>
                        <div class="time">${note.time}</div>
                    </div>
                `;
            });
        } catch (err) {
            console.error('Failed to load alerts', err);
        }
    }

    fetchAlerts();
    // Refresh every 5 minutes
    setInterval(fetchAlerts, 5 * 60 * 1000);

});
</script>
