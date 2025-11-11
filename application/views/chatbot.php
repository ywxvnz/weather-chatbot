<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<div class="chatbot-container">
    <!-- Top Info Row -->
    <div class="row align-items-center mb-4 text-center text-md-start">
        <div class="col-md-7 mb-2 mb-md-0">
            <span class="me-2" id="locationDisplay">Loading...</span>
            <span class="me-2" id="temperatureDisplay">--°</span>
            <span id="conditionDisplay">--</span>
        </div>
        <div class="col-md-5 d-flex justify-content-center justify-content-md-end">
            <div class="chatbot-search-box">
                <i class="fas fa-search chatbot-search-icon"></i>
                <input type="text" id="locationSearch" placeholder="     Search for a location...">
                <i class="fas fa-map-marker-alt chatbot-location-icon"></i>
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

// Imus, Cavite coordinates
const latitude = 14.4297;
const longitude = 120.9367;

async function loadWeatherData() {
    try {
        const response = await fetch(
            `https://api.open-meteo.com/v1/forecast?latitude=${latitude}&longitude=${longitude}&current_weather=true&daily=weathercode,precipitation_probability_max&timezone=Asia/Manila`
        );
        const data = await response.json();
        const current = data.current_weather;
        const daily = data.daily;

        // Update display
        document.getElementById('locationDisplay').textContent = 'Imus, Cavite';
        document.getElementById('temperatureDisplay').textContent = `${current.temperature}°C`;
        
        // Map weather code to simple condition
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

// Load weather on page load
window.addEventListener('DOMContentLoaded', loadWeatherData);
</script>
