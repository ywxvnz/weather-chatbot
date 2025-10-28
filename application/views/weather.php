<?php
// weather.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cavite Imus Weather</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="weekly_weather.css" rel="stylesheet">
</head>
<body>

  <div class="weather-container container py-4">
    <div class="main-weather text-center mb-4">
      <h2>Cavite, Imus</h2>
      <p id="condition-text">Chance of rain: 0%</p>
      <img id="main-icon" src="" alt="Weather Icon" width="100">
      <h1 id="main-temp">--°</h1>
    </div>

    <!-- Today's Forecast -->
    <div class="today-section mb-4">
      <h5 class="mb-3">TODAY'S FORECAST</h5>
      <div class="row text-center" id="hourlyRow"></div>
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
        const response = await fetch(
          `https://api.open-meteo.com/v1/forecast?latitude=${latitude}&longitude=${longitude}&current_weather=true&hourly=temperature_2m,weathercode&daily=temperature_2m_max,temperature_2m_min,weathercode,precipitation_probability_max&timezone=Asia/Manila`
        );
        const data = await response.json();
        displayCurrent(data.current_weather, data.daily);
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

    function displayCurrent(current, daily) {
      document.getElementById("main-temp").textContent = `${current.temperature}°`;
      document.getElementById("main-icon").src = getWeatherIcon(current.weathercode);

      const rainChance = daily.precipitation_probability_max[0];
      const conditionText = document.getElementById("condition-text");
      conditionText.textContent =
        rainChance > 0 ? `Chance of rain: ${rainChance}%` : "No rain expected today 🌤️";
    }

    function displayHourly(hourly) {
      const hourlyDiv = document.getElementById('hourlyRow');
      hourlyDiv.innerHTML = '';

      for (let i = 0; i < 6; i++) {
        const time = new Date(hourly.time[i]).toLocaleTimeString('en-US', { hour: 'numeric', hour12: true });
        const temp = hourly.temperature_2m[i];
        const icon = getWeatherIcon(hourly.weathercode[i]);

        hourlyDiv.innerHTML += `
          <div class="col-4 col-md-2 hour-card mb-3">
            <p>${time}</p>
            <img src="${icon}" alt="weather" width="50">
            <p>${temp}°</p>
          </div>
        `;
      }
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

    window.addEventListener('DOMContentLoaded', getWeather);
  </script>
</body>
</html>
