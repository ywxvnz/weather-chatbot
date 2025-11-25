<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="<?= base_url('assets/css/dashboard.css') ?>">

<div class="chatbot-container">
    <!-- Top Info Row -->
    <div class="row align-items-center mb-4 text-center text-md-start">
        <div class="col-md-7 mb-2 mb-md-0">
            <span class="me-2" id="locationDisplay">Loading...</span>
            <span class="me-2" id="temperatureDisplay">--°</span>
            <span id="conditionDisplay">--</span>
        </div>
        <div class="col-md-5 d-flex justify-content-center justify-content-md-end">
            <div class="search-section">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="locationSearch" placeholder="Search for a location..." autocomplete="off" aria-autocomplete="list" aria-controls="suggestions" aria-expanded="false">

                    <!-- Autosuggest dropdown -->
                    <ul id="suggestions" class="suggestions-list d-none" role="listbox" aria-label="Search suggestions"></ul>

                    <i class="fas fa-map-marker-alt location-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Greeting or Chat Area -->
    <div id="chatArea">
      <div class="text-center my-5" id="greetingSection">
        <div class="avatar-wrapper mx-auto mb-3">
          <img src="<?= base_url('assets/icons/avatar.png') ?>" alt="Chatbot Logo" class="avatar-logo">
        </div>
        <h5 class="chat-greeting"><?= $greeting ?></h5>
      </div>
    </div>

    <!--message input -->
    <div class="chat-section">
        <div class="chat-input-container">
            <input type="text" id="userMessage" placeholder="Type your message here...">
            <button class="send-button" id="sendBtn">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<script>
const chatArea = document.getElementById('chatArea');
const greetingSection = document.getElementById('greetingSection');
const userMessageInput = document.getElementById('userMessage');
const sendBtn = document.getElementById('sendBtn');

// Default fallback coordinates (Imus, Cavite)
const DEFAULT_COORDS = { latitude: 14.4297, longitude: 120.9367, name: 'Imus, Cavite' };

function getSearchParams() {
    try {
        const params = new URLSearchParams(window.location.search);
        return {
            q: params.get('q') || '',
            lat: params.get('lat') ? parseFloat(params.get('lat')) : null,
            lon: params.get('lon') ? parseFloat(params.get('lon')) : null,
            loc: params.get('loc') || ''
        };
    } catch (e) { return { q: '', lat: null, lon: null, loc: '' }; }
}

async function loadWeatherData(coords) {
    // coords: { latitude, longitude, name }
    const lat = coords && coords.latitude ? coords.latitude : DEFAULT_COORDS.latitude;
    const lon = coords && coords.longitude ? coords.longitude : DEFAULT_COORDS.longitude;
    const displayName = coords && coords.name ? coords.name : DEFAULT_COORDS.name;
    try {
        const response = await fetch(`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current_weather=true&timezone=auto`);
        const data = await response.json();
        const current = data.current_weather;
        document.getElementById('locationDisplay').textContent = displayName;
        document.getElementById('temperatureDisplay').textContent = `${current.temperature}°C`;
        const condition = getSimpleCondition(current.weathercode);
        document.getElementById('conditionDisplay').textContent = condition;
    } catch (error) {
        console.error("Weather fetch error:", error);
        document.getElementById('conditionDisplay').textContent = 'Unable to load weather';
    }
}

function getSimpleCondition(code) {
    if ([0, 1].includes(code)) return 'Sunny';              // Clear sky
    if ([2, 3].includes(code)) return 'Cloudy';             // Cloudy / Partly cloudy
    if ([45, 48].includes(code)) return 'Foggy';            // Fog or mist
    if ([51, 61, 80, 81, 82].includes(code)) return 'Rainy'; // Rain or drizzle
    if ([95, 96, 99].includes(code)) return 'Stormy';       // Thunderstorm
    return '--';                                         // Default
}

function addMessage(content, type) {
    if (greetingSection) greetingSection.style.display = 'none';
    const bubble = document.createElement('div');
    bubble.classList.add('chat-bubble', type);
    bubble.textContent = content;
    chatArea.appendChild(bubble);
    chatArea.scrollTop = chatArea.scrollHeight;
}

async function simulateResponse(userMsg) {
    // Show typing indicator
    const typingBubble = document.createElement('div');
    typingBubble.classList.add('chat-bubble', 'system');
    typingBubble.textContent = "Thinking...";
    chatArea.appendChild(typingBubble);
    chatArea.scrollTop = chatArea.scrollHeight;

    try {
        const response = await fetch("http://localhost:5000/api/chat", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ message: userMsg })
        });

        typingBubble.remove(); // remove "Thinking..." bubble

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            addMessage("Error: " + (errorData.error || "Server error"), 'system');
            return;
        }

        const data = await response.json();
        if (data.reply) {
            addMessage(data.reply, 'system');
        } else {
            addMessage("No reply from chatbot.", 'system');
        }
    } catch (error) {
        typingBubble.remove();
        addMessage("Network error: " + error.message, 'system');
    }
}

function sendMessage() {
    const msg = userMessageInput.value.trim();
    if (!msg) return;
    addMessage(msg, 'user');
    simulateResponse(msg);
    userMessageInput.value = '';
}

sendBtn.addEventListener('click', sendMessage);
userMessageInput.addEventListener('keypress', e => {
    if (e.key === 'Enter') sendMessage();
});

// --- Geocoding, autosuggest and location persistence (mirrors dashboard behavior) ---
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
    localStorage.setItem('selectedLocation', JSON.stringify(obj));
}

async function setLocationAndUpdateUI(result) {
    if (!result) return;
    const displayName = result.name + (result.country ? ', ' + result.country : '') || result.name || (result.display_name || '');
    const lat = result.latitude || result.lat;
    const lon = result.longitude || result.lon;

    setSelectedLocation({ name: displayName, latitude: lat, longitude: lon });

    if (document.getElementById('locationDisplay')) document.getElementById('locationDisplay').textContent = displayName;
    if (document.getElementById('temperatureDisplay')) document.getElementById('temperatureDisplay').textContent = 'Loading...';
    if (document.getElementById('conditionDisplay')) document.getElementById('conditionDisplay').textContent = '--';

    try {
        const data = await fetchWeather(lat, lon);
        const current = data.current_weather;
        if (document.getElementById('temperatureDisplay')) document.getElementById('temperatureDisplay').textContent = `${current.temperature}°C`;
        if (document.getElementById('conditionDisplay')) document.getElementById('conditionDisplay').textContent = getSimpleCondition(current.weathercode);
    } catch (err) {
        if (document.getElementById('conditionDisplay')) document.getElementById('conditionDisplay').textContent = 'Unable to load weather';
        console.error(err);
    }
}

// Autosuggest UI
const searchInput = document.getElementById('locationSearch');
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
    searchInput.addEventListener('input', function (e) {
        const q = searchInput.value.trim();
        debouncedFetch(q);
    });

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
            }
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
            await setLocationAndUpdateUI(results[0]);
            clearSuggestions();
        } catch (err) {
            console.error(err);
            alert('Failed to search location');
        }
    });
}

const locationIcon = document.querySelector('.chatbot-location-icon');
if (locationIcon) {
    locationIcon.addEventListener('click', function () {
        if (!navigator.geolocation) {
            alert('Geolocation not supported');
            return;
        }
        navigator.geolocation.getCurrentPosition(async function (pos) {
            const lat = pos.coords.latitude;
            const lon = pos.coords.longitude;
            try {
                const rev = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lon}`);
                const revJson = await rev.json();
                const display = revJson.display_name || `${lat.toFixed(3)}, ${lon.toFixed(3)}`;
                await setLocationAndUpdateUI({ name: display, latitude: lat, longitude: lon });
            } catch (err) {
                await setLocationAndUpdateUI({ name: `${lat.toFixed(3)}, ${lon.toFixed(3)}`, latitude: lat, longitude: lon });
            }
        }, function (err) {
            alert('Unable to retrieve your location');
            console.error(err);
        });
    });
}

// Initialize page: load location (from URL params, localStorage, or default), and auto-send any query param
async function init() {
    const params = getSearchParams();
    // If lat & lon provided in URL, prefer that
    if (params.lat && params.lon) {
        await loadWeatherData({ latitude: params.lat, longitude: params.lon, name: params.loc || `${params.lat.toFixed(3)}, ${params.lon.toFixed(3)}` });
        // persist
        try { setSelectedLocation({ name: params.loc || '', latitude: params.lat, longitude: params.lon }); } catch (e) {}
    } else {
        // try localStorage
        try {
            const stored = localStorage.getItem('selectedLocation');
            if (stored) {
                const loc = JSON.parse(stored);
                if (loc && loc.latitude && loc.longitude) {
                    await loadWeatherData({ latitude: loc.latitude, longitude: loc.longitude, name: loc.name });
                } else {
                    await loadWeatherData(DEFAULT_COORDS);
                }
            } else {
                await loadWeatherData(DEFAULT_COORDS);
            }
        } catch (e) {
            console.warn('Failed to init location from storage', e);
            await loadWeatherData(DEFAULT_COORDS);
        }
    }

    // If query present, auto-send it (use small timeout so UI updates)
    if (params.q) {
        userMessageInput.value = params.q;
        setTimeout(() => sendMessage(), 250);
    }
}

// Start
window.addEventListener('DOMContentLoaded', init);
</script>
