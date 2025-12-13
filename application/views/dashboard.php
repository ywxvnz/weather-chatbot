<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<div class="dashboard-container">
    <div class="search-section">
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="locationSearch" placeholder="Search for a location..." autocomplete="off" aria-autocomplete="list" aria-controls="suggestions" aria-expanded="false">

            <!-- Autosuggest dropdown -->
            <ul id="suggestions" class="suggestions-list d-none" role="listbox" aria-label="Search suggestions"></ul>

            <i class="fas fa-map-marker-alt location-icon"></i>
        </div>
    </div>
    
    <div class="weather-info">
        <h1 class="location-name"><?= $location ?></h1>
        <div class="temperature"><?= $temperature ?></div>
        <div class="weather-condition"><?= $condition ?></div>
    </div>

    <div class="chat-section">
        <p class="chat-prompt">Hello, welcome to Nubi.</p>
        <div class="chat-input-container">
            <input type="text" id="chatInput" placeholder="Type your message here...">
            <button class="send-button" id="dashboardSendBtn">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sendBtn = document.getElementById('dashboardSendBtn');
    const chatInput = document.getElementById('chatInput');
    if (!sendBtn || !chatInput) return;

    function forwardToChatbot() {
        const msg = chatInput.value.trim();
        if (!msg) return;
        const target = "<?php echo site_url('chatbot'); ?>" + '?q=' + encodeURIComponent(msg);
        // Navigate to chatbot with the message as a query param
        window.location.href = target;
    }

    sendBtn.addEventListener('click', forwardToChatbot);
    chatInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            forwardToChatbot();
        }
    });
});
</script>

<script>
// Dashboard: geocode and fetch weather, and persist selected location.
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('locationSearch');
    const locationNameEl = document.querySelector('.location-name');
    const temperatureEl = document.querySelector('.temperature');
    const conditionEl = document.querySelector('.weather-condition');
    const locationIcon = document.querySelector('.location-icon');

    // Simple mapping of weather codes (reuse same mapping as chatbot)
    function getSimpleCondition(code) {
        if ([0, 1].includes(code)) return 'Sunny';
        if ([2, 3].includes(code)) return 'Cloudy';
        if ([45, 48].includes(code)) return 'Foggy';
        if ([51, 61, 80, 81, 82].includes(code)) return 'Rainy';
        if ([95, 96, 99].includes(code)) return 'Stormy';
        return '--';
    }

    async function geocode(query) {
        const url = `https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(query)}&count=5&language=en&format=json`;
        const res = await fetch(url);
        if (!res.ok) throw new Error('Geocoding failed');
        const data = await res.json();
        return data.results || [];
    }

    async function fetchWeather(lat, lon) {
        const url = `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current_weather=true&timezone=auto`;
        const res = await fetch(url);
        if (!res.ok) throw new Error('Weather fetch failed');
        return res.json();
    }

    function setSelectedLocation(obj) {
        // store: {name, latitude, longitude}
        localStorage.setItem('selectedLocation', JSON.stringify(obj));
    }

    // Format a full place string to "First, Last" (e.g. "Imus, ... , Philippines" -> "Imus, Philippines")
    function formatDisplayName(fullName) {
        if (!fullName) return '';
        const parts = fullName.split(',').map(p => p.trim()).filter(Boolean);
        if (parts.length === 0) return '';
        if (parts.length === 1) return parts[0];
        return parts[0] + ', ' + parts[parts.length - 1];
    }

    async function setLocationAndUpdateUI(result) {
        if (!result) return;
        const displayName = result.name + (result.country ? ', ' + result.country : '');
        const lat = result.latitude;
        const lon = result.longitude;

        // store selection
        setSelectedLocation({ name: displayName, latitude: lat, longitude: lon });

        // update UI optimistically
        if (locationNameEl) locationNameEl.textContent = formatDisplayName(displayName);
        if (temperatureEl) temperatureEl.textContent = 'Loading...';
        if (conditionEl) conditionEl.textContent = '--';

        try {
            const data = await fetchWeather(lat, lon);
            const current = data.current_weather;
            if (temperatureEl) temperatureEl.textContent = `${current.temperature}°C`;
            if (conditionEl) conditionEl.textContent = getSimpleCondition(current.weathercode);
        } catch (err) {
            if (conditionEl) conditionEl.textContent = 'Unable to load weather';
            console.error(err);
        }
    }

    // Initialize from stored location if available
    (function initFromStorage() {
        try {
            const stored = localStorage.getItem('selectedLocation');
            if (stored) {
                const loc = JSON.parse(stored);
                if (loc && loc.latitude && loc.longitude) {
                    if (locationNameEl) locationNameEl.textContent = formatDisplayName(loc.name);
                    // fetch weather
                    setLocationAndUpdateUI({ name: loc.name, latitude: loc.latitude, longitude: loc.longitude });
                }
            }
        } catch (e) {
            console.warn('Failed to init location from storage', e);
        }
    })();

    // Autosuggest and search by name
    const suggestionsEl = document.getElementById('suggestions');
    let suggestionItems = [];
    let activeIndex = -1;

    function clearSuggestions() {
        if (!suggestionsEl) return;
        suggestionsEl.innerHTML = '';
        suggestionsEl.classList.add('d-none');
        searchInput.setAttribute('aria-expanded', 'false');
        activeIndex = -1;
        suggestionItems = [];
    }

    function renderSuggestions(results) {
        if (!suggestionsEl) return;
        suggestionsEl.innerHTML = '';
        if (!results || results.length === 0) {
            clearSuggestions();
            return;
        }
        results.forEach((r, i) => {
            const li = document.createElement('li');
            li.className = 'suggestion-item';
            li.setAttribute('role', 'option');
            li.id = 'suggestion-' + i;
            const label = (r.name || '') + (r.admin1 ? ', ' + r.admin1 : '') + (r.country ? ', ' + r.country : '');
            li.textContent = label;
            li.dataset.idx = i;
            li.dataset.lat = r.latitude;
            li.dataset.lon = r.longitude;
            li.dataset.name = label;
            li.addEventListener('click', async function () {
                clearSuggestions();
                searchInput.value = label;
                await setLocationAndUpdateUI({ name: r.name, country: r.country, latitude: r.latitude, longitude: r.longitude });
            });
            suggestionsEl.appendChild(li);
        });
        suggestionsEl.classList.remove('d-none');
        searchInput.setAttribute('aria-expanded', 'true');
        suggestionItems = Array.from(suggestionsEl.querySelectorAll('.suggestion-item'));
        activeIndex = -1;
    }

    function debounce(fn, wait) {
        let t;
        return function (...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    async function fetchAndShowSuggestions(q) {
        if (!q) {
            clearSuggestions();
            return;
        }
        try {
            const results = await geocode(q);
            renderSuggestions(results);
        } catch (err) {
            console.error('Suggestion error', err);
            clearSuggestions();
        }
    }

    const debouncedFetch = debounce(fetchAndShowSuggestions, 300);

    if (searchInput) {
        // show suggestions while typing
        searchInput.addEventListener('input', function (e) {
            const q = searchInput.value.trim();
            debouncedFetch(q);
        });

        // keyboard navigation
        searchInput.addEventListener('keydown', function (e) {
            if (suggestionItems.length === 0) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = Math.min(activeIndex + 1, suggestionItems.length - 1);
                updateActive();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = Math.max(activeIndex - 1, 0);
                updateActive();
            } else if (e.key === 'Enter') {
                if (activeIndex >= 0 && suggestionItems[activeIndex]) {
                    e.preventDefault();
                    suggestionItems[activeIndex].click();
                } // else fallback to manual Enter behavior (choose first result if desired)
            } else if (e.key === 'Escape') {
                clearSuggestions();
            }
        });

        function updateActive() {
            suggestionItems.forEach((it, i) => {
                if (i === activeIndex) {
                    it.classList.add('active');
                    it.setAttribute('aria-selected', 'true');
                    it.scrollIntoView({ block: 'nearest' });
                    searchInput.setAttribute('aria-activedescendant', it.id);
                } else {
                    it.classList.remove('active');
                    it.setAttribute('aria-selected', 'false');
                }
            });
        }

        // fallback: Enter when no suggestion chosen -> pick first result (optional)
        searchInput.addEventListener('keypress', async function (e) {
            if (e.key !== 'Enter') return;
            const q = searchInput.value.trim();
            if (!q) return;
            try {
                const results = await geocode(q);
                if (results.length === 0) {
                    alert('Location not found');
                    return;
                }
                // choose first result
                await setLocationAndUpdateUI(results[0]);
                clearSuggestions();
            } catch (err) {
                console.error(err);
                alert('Failed to search location');
            }
        });
    }

    if (locationIcon) {
        locationIcon.addEventListener('click', function () {
            if (!navigator.geolocation) {
                alert('Geolocation not supported');
                return;
            }
            navigator.geolocation.getCurrentPosition(async function (pos) {
                const lat = pos.coords.latitude;
                const lon = pos.coords.longitude;
                // reverse geocode via open-meteo (search by lat/lon isn't directly supported in reverse in this API,
                // but we can call the search endpoint with 'name' omitted to try nearby? Instead, we'll reverse via Nominatim.
                try {
                    const rev = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lon}`);
                    const revJson = await rev.json();
                    const display = revJson.display_name || `${lat.toFixed(3)}, ${lon.toFixed(3)}`;
                    await setLocationAndUpdateUI({ name: display, latitude: lat, longitude: lon });
                } catch (err) {
                    // fallback: set coords only
                    await setLocationAndUpdateUI({ name: `${lat.toFixed(3)}, ${lon.toFixed(3)}`, latitude: lat, longitude: lon });
                }
            }, function (err) {
                alert('Unable to retrieve your location');
                console.error(err);
            });
        });
    }

    // When forwarding to chatbot, append location params if available
    const originalForward = window.forwardToChatbot;
    // Replace forwardToChatbot defined earlier by re-defining a global function if exists
    if (typeof window !== 'undefined') {
        window.forwardToChatbot = async function () {
            // try to preserve earlier behavior
            const msgEl = document.getElementById('chatInput');
            if (!msgEl) return;
            const msg = msgEl.value.trim();
            if (!msg) return;
            let target = "<?php echo site_url('chatbot'); ?>" + '?q=' + encodeURIComponent(msg);
            try {
                const stored = localStorage.getItem('selectedLocation');
                if (stored) {
                    const loc = JSON.parse(stored);
                    if (loc && loc.latitude && loc.longitude) {
                        target += '&lat=' + encodeURIComponent(loc.latitude) + '&lon=' + encodeURIComponent(loc.longitude) + '&loc=' + encodeURIComponent(loc.name);
                    }
                }
            } catch (e) {
                // ignore
            }
            window.location.href = target;
        };
    }
});
</script>
