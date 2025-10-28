<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<div class="chatbot-container">
    <!-- Top Info Row -->
    <div class="row align-items-center mb-4 text-center text-md-start">
      <div class="col-md-7 mb-2 mb-md-0">
        <span class="me-2"><?= $location ?></span>
        <span class="me-2"><?= $temperature ?></span>
        <span><?= $condition ?></span>
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
        <div class="avatar-placeholder mx-auto mb-3"></div>
        <h5 class="chat-greeting"><?= $greeting ?></h5>
      </div>
    </div>

    <div class="chat-section">
        <div class="chat-input-container">
            <input type="text" class="form-control" id="userMessage" placeholder="Type your message here...">
            <button class="send-button" id="sendBtn">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>

    <!-- Message Input 
    <div class="chat-input d-flex justify-content-center align-items-center mt-4">
      <input type="text" class="form-control rounded-pill me-2" id="userMessage" placeholder="Type your message..." />
      <button class="btn btn-primary rounded-circle" id="sendBtn">➤</button>
    </div>-->
</div>
<script>
const chatArea = document.getElementById('chatArea');
const greetingSection = document.getElementById('greetingSection');
const userMessageInput = document.getElementById('userMessage');
const sendBtn = document.getElementById('sendBtn');

function addMessage(content, type) {
  // Hide greeting if it’s still visible
  if (greetingSection) greetingSection.style.display = 'none';

  const bubble = document.createElement('div');
  bubble.classList.add('chat-bubble', type);
  bubble.textContent = content;

  chatArea.appendChild(bubble);
  chatArea.scrollTop = chatArea.scrollHeight;
}

function simulateResponse(userMsg) {
  setTimeout(() => {
    addMessage("You said: " + userMsg, 'system');
  }, 800);
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
</script>
