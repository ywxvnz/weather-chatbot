<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">


<div class="notification-container">
  <div class="main-notification text-center">
    <!-- Selected location -->
        <div class="notif-header">
                <!-- circular search button that expands into input (copied from weather page) -->
                <div class="weather-search-box" aria-hidden="false">
                    <button id="weatherSearchBtn" class="search-toggle" aria-expanded="false" aria-label="Search location">
                        <i class="fas fa-search"></i>
                    </button>
                    <input type="text" id="weatherSearch" class="search-input" placeholder="Search location..." autocomplete="off" aria-autocomplete="list" aria-controls="weatherSuggestions" aria-expanded="false">
                    <ul id="weatherSuggestions" class="suggestions-list d-none" role="listbox" aria-label="Search suggestions"></ul>
                </div>

                <h2 id="selectedLocation">Loading location...</h2>
        
                <p>Weather-based tips and advisories for your selected location</p>
        </div>

    <div class="notif-container">
        <!-- Loading message -->
        <div class="notification default">
            <!--<div class="icon"><i class="fas fa-bell" style="color: white;  box-shadow: 0 3px 8px rgba(0,0,0,0.1);"></i></div>-->
            <div class="content">
                <div class="title">Loading alerts...</div>
                <div class="description">Please wait while we fetch the latest alerts.</div>
            </div>
            <div class="time">--:--</div>
        </div>
    </div>

    <!-- Metrics (chance of rain, temp, feels-like, UV, AQI) -->
    <div class="notif-metrics mt-3 mb-3"></div>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // --- Search box / autosuggest (initialize once) ---
    (function initNotifSearch() {
        async function geocode(query) {
            const url = `https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(query)}&count=5&language=en&format=json`;
            const res = await fetch(url);
            if (!res.ok) throw new Error('Geocoding failed');
            const data = await res.json();
            return data.results || [];
        }

        function setSelectedLocation(obj) {
            localStorage.setItem('selectedLocation', JSON.stringify(obj));
        }

        function formatDisplayName(fullName) {
            if (!fullName) return '';
            const parts = fullName.split(',').map(p => p.trim()).filter(Boolean);
            if (parts.length === 0) return '';
            if (parts.length === 1) return parts[0];
            // Prefer the 2nd part (admin1) when available, otherwise fall back to the last part
            if (parts.length >= 2) return parts[0] + ', ' + parts[1];
            return parts[0];
        }

        function clearSuggestions(el) {
            if (!el) return;
            el.innerHTML = '';
            el.classList.add('d-none');
        }

        function renderSuggestions(results, suggestionsEl, inputEl) {
            if (!suggestionsEl) return;
            suggestionsEl.innerHTML = '';
            if (!results || results.length === 0) {
                clearSuggestions(suggestionsEl);
                return;
            }
            results.forEach((r, i) => {
                const li = document.createElement('li');
                li.className = 'suggestion-item';
                li.setAttribute('role', 'option');
                li.id = 'weather-suggestion-' + i;
                const label = (r.name || '') + (r.admin1 ? ', ' + r.admin1 : '') + (r.country ? ', ' + r.country : '');
                li.textContent = label;
                li.dataset.lat = r.latitude;
                li.dataset.lon = r.longitude;
                li.dataset.name = label;
                li.addEventListener('click', async function () {
                    clearSuggestions(suggestionsEl);
                    inputEl.value = label;
                    const lat = r.latitude;
                    const lon = r.longitude;
                    setSelectedLocation({ name: label, latitude: lat, longitude: lon });
                    const locationEl = document.getElementById('selectedLocation');
                    if (locationEl) locationEl.textContent = formatDisplayName(label);
                    // refresh both metrics and alerts for new location
                    try { await fetchMetrics(lat, lon); } catch(e){}
                    try { await fetchAlerts(); } catch(e){}
                });
                suggestionsEl.appendChild(li);
            });
            suggestionsEl.classList.remove('d-none');
        }

        function debounce(fn, wait) {
            let t;
            return function (...args) {
                clearTimeout(t);
                t = setTimeout(() => fn.apply(this, args), wait);
            };
        }

        // wire up expanding search button + input
        const weatherSearchBtn = document.getElementById('weatherSearchBtn');
        const weatherSearchInput = document.getElementById('weatherSearch');
        const weatherSuggestions = document.getElementById('weatherSuggestions');
        const weatherSearchBox = document.querySelector('.weather-search-box');

        async function fetchAndShowWeatherSuggestions(q) {
            if (!q) { clearSuggestions(weatherSuggestions); return; }
            try {
                const results = await geocode(q);
                renderSuggestions(results, weatherSuggestions, weatherSearchInput);
            } catch (err) {
                console.error('Suggestion error', err);
                clearSuggestions(weatherSuggestions);
            }
        }

        const debouncedWeatherFetch = debounce(fetchAndShowWeatherSuggestions, 250);

        if (weatherSearchBtn && weatherSearchInput) {
            weatherSearchBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (weatherSearchBox.classList.contains('expanded')) {
                    weatherSearchBox.classList.remove('expanded');
                    weatherSearchBtn.setAttribute('aria-expanded', 'false');
                    weatherSearchInput.setAttribute('aria-expanded', 'false');
                    weatherSearchInput.value = '';
                    clearSuggestions(weatherSuggestions);
                } else {
                    weatherSearchBox.classList.add('expanded');
                    weatherSearchBtn.setAttribute('aria-expanded', 'true');
                    weatherSearchInput.setAttribute('aria-expanded', 'true');
                    weatherSearchInput.focus();
                }
            });

            weatherSearchInput.addEventListener('input', function (e) {
                const q = weatherSearchInput.value.trim();
                debouncedWeatherFetch(q);
            });

            weatherSearchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    const q = weatherSearchInput.value.trim();
                    if (!q) return;
                    e.preventDefault();
                    geocode(q).then(results => {
                        if (results.length === 0) { alert('Location not found'); return; }
                        const r = results[0];
                        const label = (r.name || '') + (r.admin1 ? ', ' + r.admin1 : '') + (r.country ? ', ' + r.country : '');
                        setSelectedLocation({ name: label, latitude: r.latitude, longitude: r.longitude });
                        const locationEl = document.getElementById('selectedLocation');
                        if (locationEl) locationEl.textContent = formatDisplayName(label);
                        clearSuggestions(weatherSuggestions);
                        weatherSearchBox.classList.remove('expanded');
                        fetchMetrics(r.latitude, r.longitude).catch(()=>{});
                        fetchAlerts();
                    }).catch(err => { console.error(err); alert('Failed to search location'); });
                } else if (e.key === 'Escape') {
                    weatherSearchBox.classList.remove('expanded');
                    weatherSearchBtn.setAttribute('aria-expanded', 'false');
                    weatherSearchInput.setAttribute('aria-expanded', 'false');
                    weatherSearchInput.value = '';
                    clearSuggestions(weatherSuggestions);
                }
            });

            // click outside collapses
            document.addEventListener('click', function (ev) {
                if (!weatherSearchBox) return;
                if (!weatherSearchBox.contains(ev.target) && weatherSearchBox.classList.contains('expanded')) {
                    weatherSearchBox.classList.remove('expanded');
                    weatherSearchBtn.setAttribute('aria-expanded', 'false');
                    weatherSearchInput.setAttribute('aria-expanded', 'false');
                    weatherSearchInput.value = '';
                    clearSuggestions(weatherSuggestions);
                }
            });
        }
    })();

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

        // (search initialized separately) -- no-op here

        // Fetch and render metric notifications first
        try {
            await fetchMetrics(lat, lon);
        } catch (merr) {
            console.warn('Failed to load metrics', merr);
        }

        try {
            // Build the alerts endpoint using CodeIgniter's site_url so it
            // works whether or not index.php rewrite is enabled.
            const alertsEndpoint = '<?php echo site_url("notification/weather_alerts"); ?>';
            const alertsUrl = alertsEndpoint + `?lat=${lat}&lon=${lon}`;
            console.debug('Fetching alerts from', alertsUrl);
            const res = await fetch(alertsUrl);

            if (!res.ok) {
                const txt = await res.text();
                console.error('Alerts endpoint error', res.status, txt);
                throw new Error('Alerts endpoint returned ' + res.status);
            }

            let data;
            try {
                data = await res.json();
            } catch (e) {
                console.error('Invalid JSON from alerts endpoint', e);
                throw e;
            }

            const container = document.querySelector('.notif-container');

            // simple change detection: only re-render if data changed
            const dataJSON = JSON.stringify(data || []);
            if (window._lastAlertsJSON === dataJSON) {
                // nothing changed
                // still update the 'updated' timestamp below
            } else {
                window._lastAlertsJSON = dataJSON;
                container.innerHTML = '';

                if (!data || data.length === 0) {
                    container.innerHTML = `
                        <div class="notification default">
                            <!--<div class="icon"><i class="fas fa-bell"></i></div>-->
                            <div class="content">
                                <div class="title">No alerts for this location</div>
                                <div class="description">You're safe! There are currently no weather alerts.</div>
                            </div>
                            <div class="time">--:--</div>
                        </div>
                    `;
                } else {
                    // map severity to visual class as needed
                    const severityMap = { danger: 'stormy', warn: 'rainy', ok: 'sunny' };
                    data.forEach(note => {
                        const sev = note.severity || '';
                        const typeClass = severityMap[sev] || 'sunny';
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
                }
            }

            // Update last-updated time in header
            const now = new Date();
            const timeStr = now.toLocaleTimeString();
            const headerP = document.querySelector('.notif-header p');
            if (headerP) headerP.innerText = `Weather-based tips • Updated ${timeStr}`;

        } catch (err) {
            console.error('Failed to load alerts', err);
            const container = document.querySelector('.notif-container');
            if (container) container.innerHTML = `
                <div class="notification cloudy">
                    <div class="icon"><i class="fas fa-circle-exclamation"></i></div>
                    <div class="content">
                        <div class="title">Unable to load alerts</div>
                        <div class="description">Please check your connection and try again.</div>
                    </div>
                    <div class="time">--:--</div>
                </div>
            `;
        }
    }

    fetchAlerts();
    // Refresh every 5 minutes
    setInterval(fetchAlerts, 5 * 60 * 1000);

});

// --- Metrics fetcher & renderer ---
async function fetchMetrics(lat, lon) {
    const metricsEl = document.querySelector('.notif-metrics');
    if (!metricsEl) return;
    metricsEl.innerHTML = ''; // clear

    try {
        // fetch forecast + hourly data for apparent temp & uv and daily precipitation chance
        const url = `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current_weather=true&hourly=apparent_temperature,uv_index&daily=precipitation_probability_max&timezone=Asia/Manila`;
        const res = await fetch(url);
        const fw = await res.json();

        // air quality (us_aqi)
        let aqi = null;
        try {
            const airRes = await fetch(`https://air-quality-api.open-meteo.com/v1/air-quality?latitude=${lat}&longitude=${lon}&hourly=us_aqi&timezone=Asia/Manila`);
            const airJson = await airRes.json();
            if (airJson && airJson.hourly && Array.isArray(airJson.hourly.us_aqi)) {
                // pick nearest hour (0 index)
                aqi = Math.round(airJson.hourly.us_aqi[0]);
            }
        } catch (e) {
            console.debug('AQI fetch failed', e);
        }

        const current = fw.current_weather || {};
        const daily = fw.daily || {};
        const hourly = fw.hourly || {};

        const rainChance = (daily.precipitation_probability_max && daily.precipitation_probability_max[0] != null) ? daily.precipitation_probability_max[0] : 0;
        const temp = current.temperature != null ? Math.round(current.temperature) : null;

        // nearest hourly index (use first available)
        let feels = null, uv = null;
        if (hourly && Array.isArray(hourly.apparent_temperature) && hourly.apparent_temperature.length) {
            feels = Math.round(hourly.apparent_temperature[0]);
        }
        if (hourly && Array.isArray(hourly.uv_index) && hourly.uv_index.length) {
            uv = Math.round(hourly.uv_index[0]);
        }

        const metrics = [];

        function pushMetric(key, label, value, status, message, icon) {
            metrics.push({ key, label, value, status, message, icon });
        }

        // 🌧 Chance of rain
        if (rainChance < 30) {
            pushMetric('rain', 'Chance of Rain', `${rainChance}%`, 'ok',
                'Low chance of rain — enjoy your day!', 'fa-cloud-sun');
        } else if (rainChance < 70) {
            pushMetric('rain', 'Chance of Rain', `${rainChance}%`, 'warn',
                'There might be rain later. Bring an umbrella just in case.', 'fa-cloud');
        } else {
            pushMetric('rain', 'Chance of Rain', `${rainChance}%`, 'danger',
                'High chance of rain — expect wet conditions.', 'fa-cloud-showers-heavy');
        }

        // 🌡 Temperature
        if (temp !== null) {
            if (temp < 30) {
                pushMetric('temp', 'Temperature', `${temp}°C`, 'ok',
                    'Comfortable temperature today.', 'fa-thermometer-half');
            } else if (temp < 36) {
                pushMetric('temp', 'Temperature', `${temp}°C`, 'warn',
                    'It’s getting warm. Stay hydrated.', 'fa-temperature-high');
            } else {
                pushMetric('temp', 'Temperature', `${temp}°C`, 'danger',
                    'Extreme heat detected. Avoid prolonged sun exposure.', 'fa-temperature-high');
            }
        }

        // 🧍 Feels like
        if (feels !== null) {
            if (feels < 35) {
                pushMetric('feels', 'Feels Like', `${feels}°C`, 'ok',
                    'Feels comfortable outside.', 'fa-user');
            } else {
                pushMetric('feels', 'Feels Like', `${feels}°C`, 'danger',
                    'Feels extremely hot. Take frequent breaks.', 'fa-user-shield');
            }
        }

        // ☀ UV Index
        if (uv !== null) {
            if (uv <= 2) {
                pushMetric('uv', 'UV Index', uv, 'ok',
                    'Low UV levels. Minimal protection needed.', 'fa-sun');
            } else if (uv <= 7) {
                pushMetric('uv', 'UV Index', uv, 'warn',
                    'Moderate UV. Use sunscreen if outdoors.', 'fa-sun');
            } else {
                pushMetric('uv', 'UV Index', uv, 'danger',
                    'Very high UV. Sunscreen and shade are essential.', 'fa-sun');
            }
        }

        // 🌫 Air Quality
        if (aqi !== null) {
            if (aqi <= 50) {
                pushMetric('aqi', 'Air Quality', `AQI ${aqi}`, 'ok',
                    'Air quality is good.', 'fa-wind');
            } else if (aqi <= 100) {
                pushMetric('aqi', 'Air Quality', `AQI ${aqi}`, 'warn',
                    'Air quality is moderate. Sensitive groups should take care.', 'fa-smog');
            } else {
                pushMetric('aqi', 'Air Quality', `AQI ${aqi}`, 'danger',
                    'Poor air quality. Wearing a face mask is recommended.', 'fa-mask-face');
            }
        }

        // desired visual order
        const metricOrder = ['temp', 'rain', 'feels', 'aqi', 'uv'];

        // sort ONCE
        metrics.sort((a, b) =>
            metricOrder.indexOf(a.key) - metricOrder.indexOf(b.key)
        );

        // render
        metricsEl.innerHTML = metrics.map(m => `
            <div class="notification metric ${m.status} ${m.key === 'temp' ? 'metric-wide' : ''}">
                <div class="icon"><i class="fas ${m.icon}"></i></div>
                <div class="content">
                    <div class="title">${m.label}</div>
                    <div class="description">${m.message}</div>
                </div>
                <div class="time">${m.value}</div>
            </div>
        `).join('');

    } catch (err) {
        console.error('Failed to load metrics', err);
    }
}
</script>
