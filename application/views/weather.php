<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<div class="weather-container">
  <div class="main-weather text-center mb-4">
    <!-- circular search button that expands into input -->
    <div class="weather-search-box" aria-hidden="false">
      <button id="weatherSearchBtn" class="search-toggle" aria-expanded="false" aria-label="Search location">
        <i class="fas fa-search"></i>
      </button>
      <input type="text" id="weatherSearch" class="search-input" placeholder="Search location..." autocomplete="off" aria-autocomplete="list" aria-controls="weatherSuggestions" aria-expanded="false">
      <ul id="weatherSuggestions" class="suggestions-list d-none" role="listbox" aria-label="Search suggestions"></ul>
    </div>

    <h2 id="weather-location"></h2>
    <p id="condition-text">Chance of rain: 0%</p>
    <img id="main-icon" src="" alt="Weather Icon" width="100">
    <h1 id="main-temp">--°</h1>
    <div class="insights-row mt-3" aria-hidden="false">
      <div class="insight-card">
        <div class="insight-label">Feels like</div>
        <div class="insight-value" id="feels-like-val">--°</div>
      </div>
      <div class="insight-card">
        <div class="insight-label">UV Index</div>
        <div class="insight-value" id="uv-val">--</div>
      </div>
      <div class="insight-card">
        <div class="insight-label">Air Quality</div>
        <div class="insight-value" id="aqi-val">--</div>
        <div class="aqi-label" id="aqi-label" aria-hidden="true"></div>
      </div>
      <div class="insight-card">
        <div class="insight-label">Last updated</div>
        <div class="insight-value" id="time-val">--</div>
      </div>
    </div>
  </div>

  <!-- Today's Forecast -->
  <div class="today-section mb-4">
    <h5 class="mb-3">TODAY'S FORECAST</h5>

    <div class="hourly-wrapper">
      <button class="scroll-btn left" id="scrollLeft">
        <i class="fas fa-chevron-left"></i>
      </button>

      <div class="hourly-scroll" id="hourlyRow"></div>

      <button class="scroll-btn right" id="scrollRight">
        <i class="fas fa-chevron-right"></i>
      </button>
    </div>
  </div>

  <!-- 7-Day Forecast -->
  <div class="daily-section">
    <h5 class="mb-3">7-DAY FORECAST</h5>
    <div id="dailyForecast"></div>
  </div>
</div>


<script>
  const latitude = 14.4297;   // Cavite, Imus
  const longitude = 120.9367;

  async function getWeather() {
    try {
      // fallback coords (Imus, Cavite) will be used if no coords provided via selectedLocation/localStorage
      let lat = latitude;
      let lon = longitude;
      const stored = localStorage.getItem('selectedLocation');
      if (stored) {
        try {
          const s = JSON.parse(stored);
          if (s && s.latitude && s.longitude) {
            lat = s.latitude;
            lon = s.longitude;
            if (s.name) document.getElementById('weather-location').textContent = formatDisplayName(s.name);
          }
        } catch (e) {}
      }

      // Fetch weather forecast (excluding AQI/pollutants) and fetch air-quality separately
      const response = await fetch(
        `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current_weather=true&hourly=temperature_2m,weathercode,apparent_temperature,uv_index&daily=temperature_2m_max,temperature_2m_min,weathercode,precipitation_probability_max&timezone=Asia/Manila`
      );
      const data = await response.json();

      // Fetch air quality from the dedicated Air Quality API and merge hourly values
      let airHourly = null;
      try {
        const airRes = await fetch(
          `https://air-quality-api.open-meteo.com/v1/air-quality?latitude=${lat}&longitude=${lon}&hourly=us_aqi,pm2_5&timezone=Asia/Manila`
        );
        const airData = await airRes.json();
        airHourly = airData.hourly || null;
      } catch (e) {
        console.debug('Air quality fetch failed', e);
      }

      await displayCurrent(data.current_weather, data.daily, data.hourly, airHourly, lat, lon);
      displayHourly(data.hourly);
      displayDaily(data.daily);
    } catch (error) {
      console.error("Weather fetch error:", error);
      document.getElementById("condition-text").textContent = "Unable to load weather data.";
    }
  }

  // 🔆 Use your local icons
  function getWeatherIcon(code) {
    if ([0, 1].includes(code)) return "assets/icons/sun.png";            // Clear sky
    if ([2, 3].includes(code)) return "assets/icons/weather.png";        // Cloudy / Partly cloudy
    if ([45, 48].includes(code)) return "assets/icons/crescent-moon.png"; // Fog or mist
    if ([51, 61, 80, 81, 82].includes(code)) return "assets/icons/storm.png"; // Rain or drizzle
    if ([95, 96, 99].includes(code)) return "assets/icons/storm.png";    // Thunderstorm
    return "assets/icons/weather.png";                                   // Default cloudy
  }

  function displayCurrent(current, daily, hourly, airHourly, lat, lon) {
    document.getElementById("main-temp").textContent = `${current.temperature}°`;
    document.getElementById("main-icon").src = getWeatherIcon(current.weathercode);

    const rainChance = (daily && daily.precipitation_probability_max && daily.precipitation_probability_max[0] != null)
      ? daily.precipitation_probability_max[0]
      : null;
    const conditionText = document.getElementById("condition-text");
    conditionText.textContent =
      rainChance > 0 ? `Chance of rain: ${rainChance}%` : "No rain expected today 🌤️";

    // Insights: find nearest hourly index to now
    if (hourly && hourly.time && hourly.time.length) {
      const now = new Date();
      let nearestIdx = 0;
      let minDiff = Infinity;
      for (let i = 0; i < hourly.time.length; i++) {
        const t = new Date(hourly.time[i]).getTime();
        const diff = Math.abs(t - now.getTime());
        if (diff < minDiff) {
          minDiff = diff;
          nearestIdx = i;
        }
      }

        // Feels like (apparent_temperature)
        const feels = hourly.apparent_temperature && hourly.apparent_temperature[nearestIdx] != null
          ? Math.round(hourly.apparent_temperature[nearestIdx])
          : null;
        document.getElementById('feels-like-val').textContent = feels != null ? `${feels}°` : '--°';

        // UV index
        const uv = hourly.uv_index && hourly.uv_index[nearestIdx] != null
          ? Math.round(hourly.uv_index[nearestIdx])
          : null;
        // Show as current / maximum (standard UV scale uses 0-11+, display denominator as 11 like Weather.com)
        const uvDenominator = 11;
        document.getElementById('uv-val').textContent = uv != null ? `${uv}/${uvDenominator}` : `--/${uvDenominator}`;

        // Air quality: use values from the dedicated Air Quality API when available
        const rawAqi = (airHourly && Array.isArray(airHourly.us_aqi) && airHourly.us_aqi[nearestIdx] != null)
          ? airHourly.us_aqi[nearestIdx]
          : null;
        const aqi = rawAqi != null ? Math.round(rawAqi) : null;
        const aqiEl = document.getElementById('aqi-val');
        const aqiLabelEl = document.getElementById('aqi-label');
        if (aqi != null) {
          aqiEl.textContent = aqi;
          const cat = aqiCategory(aqi);
          if (aqiLabelEl) {
            aqiLabelEl.textContent = cat.label;
            aqiLabelEl.style.color = cat.color;
          }
        } else {
          aqiEl.textContent = '--';
          if (aqiLabelEl) {
            aqiLabelEl.textContent = '';
            aqiLabelEl.style.color = '';
          }
              // No AQI available from Open-Meteo Air Quality API for this hour
        }

        // show retrieved time (use hourly time at nearestIdx if available)
        const timeEl = document.getElementById('time-val');
        if (timeEl && hourly.time && hourly.time[nearestIdx]) {
          const now = new Date();
          timeEl.textContent = now.toLocaleString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
          });
        }

        // Use global aqiCategory below
    }
  }

  function displayHourly(hourly) {
      const hourlyDiv = document.getElementById('hourlyRow');
      hourlyDiv.innerHTML = '';

      for (let i = 0; i < 12; i++) {
          const time = new Date(hourly.time[i]).toLocaleTimeString('en-US', { hour: 'numeric', hour12: true });
          const temp = hourly.temperature_2m[i];
          const icon = getWeatherIcon(hourly.weathercode[i]);

          hourlyDiv.innerHTML += `
              <div class="hour-card">
                  <p>${time}</p>
                  <img src="${icon}" alt="weather" width="50">
                  <p>${temp}°</p>
              </div>
          `;
      }

      setTimeout(updateScrollButtons, 0);
  }

  function displayDaily(daily) {
    const dailyDiv = document.getElementById('dailyForecast');
    dailyDiv.innerHTML = '';

    for (let i = 0; i < 7; i++) {
      const dayName = i === 0 ? 'Today' : new Date(daily.time[i]).toLocaleDateString('en-US', { weekday: 'short' });
      const maxTemp = daily.temperature_2m_max[i];
      const minTemp = daily.temperature_2m_min[i];
      const icon = getWeatherIcon(daily.weathercode[i]);

      dailyDiv.innerHTML += `
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
          <span>${dayName}</span>
          <img src="${icon}" alt="weather" width="40">
          <span class="day-temp">${maxTemp}° / ${minTemp}°</span>
        </div>
      `;
    }
  }

  // -----------------------
  // Autosuggest / geocoding for weather page
  // -----------------------
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

  // Format a full place string to "First, Last" (e.g. "Imus, ... , Philippines" -> "Imus, Philippines")
  function formatDisplayName(fullName) {
    if (!fullName) return '';
    const parts = fullName.split(',').map(p => p.trim()).filter(Boolean);
    if (parts.length === 0) return '';
    if (parts.length === 1) return parts[0];
    return parts[0] + ', ' + parts[parts.length - 1];
  }

  function aqiCategory(aqiVal) {
    if (aqiVal == null || isNaN(aqiVal)) return {label: '', color: ''};
    if (aqiVal <= 50) return {label: 'Good', color: '#0b9b3b'};
    if (aqiVal <= 100) return {label: 'Moderate', color: '#f0ad4e'};
    if (aqiVal <= 150) return {label: 'Unhealthy for SG', color: '#f57c00'};
    if (aqiVal <= 200) return {label: 'Unhealthy', color: '#d9534f'};
    if (aqiVal <= 300) return {label: 'Very Unhealthy', color: '#7e2a7e'};
    return {label: 'Hazardous', color: '#6b0019'};
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
        document.getElementById('weather-location').textContent = formatDisplayName(label);
        document.getElementById('temperatureDisplay');
        await getWeather();
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

  function expandSearch() {
    if (!weatherSearchBox) return;
    weatherSearchBox.classList.add('expanded');
    weatherSearchBtn.setAttribute('aria-expanded', 'true');
    weatherSearchInput.setAttribute('aria-expanded', 'true');
    weatherSearchInput.focus();
  }

  function collapseSearch() {
    if (!weatherSearchBox) return;
    weatherSearchBox.classList.remove('expanded');
    weatherSearchBtn.setAttribute('aria-expanded', 'false');
    weatherSearchInput.setAttribute('aria-expanded', 'false');
    weatherSearchInput.value = '';
    clearSuggestions(weatherSuggestions);
  }

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
        collapseSearch();
      } else {
        expandSearch();
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
          document.getElementById('weather-location').textContent = formatDisplayName(label);
          clearSuggestions(weatherSuggestions);
          collapseSearch();
          getWeather();
        }).catch(err => { console.error(err); alert('Failed to search location'); });
      } else if (e.key === 'Escape') {
        collapseSearch();
      }
    });

    // click outside collapses
    document.addEventListener('click', function (ev) {
      if (!weatherSearchBox) return;
      if (!weatherSearchBox.contains(ev.target) && weatherSearchBox.classList.contains('expanded')) {
        collapseSearch();
      }
    });
  }

  // initialize: apply stored location if present
  window.addEventListener('DOMContentLoaded', function () {
    const stored = localStorage.getItem('selectedLocation');
    if (stored) {
      try {
        const s = JSON.parse(stored);
        if (s && s.name) document.getElementById('weather-location').textContent = formatDisplayName(s.name);
      } catch (e) {}
    }
    getWeather();
  });

  const hourlyRow = document.getElementById('hourlyRow');
  const scrollLeftBtn = document.getElementById('scrollLeft');
  const scrollRightBtn = document.getElementById('scrollRight');

  const scrollAmount = 300;

  scrollLeftBtn.addEventListener('click', () => {
      hourlyRow.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
      setTimeout(updateScrollButtons, 300);
  });

  scrollRightBtn.addEventListener('click', () => {
      hourlyRow.scrollBy({ left: scrollAmount, behavior: 'smooth' });
      setTimeout(updateScrollButtons, 300);
  });

  function updateScrollButtons() {
      const maxScrollLeft = hourlyRow.scrollWidth - hourlyRow.clientWidth;

      // Hide LEFT button if at start
      if (hourlyRow.scrollLeft <= 0) {
          scrollLeftBtn.style.display = 'none';
      } else {
          scrollLeftBtn.style.display = 'flex';
      }

      // Hide RIGHT button if at end
      if (hourlyRow.scrollLeft >= maxScrollLeft - 1) {
          scrollRightBtn.style.display = 'none';
      } else {
          scrollRightBtn.style.display = 'flex';
      }

      // If no scrolling needed at all
      if (hourlyRow.scrollWidth <= hourlyRow.clientWidth) {
          scrollLeftBtn.style.display = 'none';
          scrollRightBtn.style.display = 'none';
      }
  }

  hourlyRow.addEventListener('scroll', updateScrollButtons);
  window.addEventListener('resize', updateScrollButtons);
  window.addEventListener('DOMContentLoaded', getWeather);
</script>
