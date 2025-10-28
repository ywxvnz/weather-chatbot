<div class="chatbot-wrapper d-flex align-items-center justify-content-center">
  <div class="container chatbot-container py-5">
    <!-- Top Info Row -->
    <div class="row align-items-center mb-4 text-center text-md-start">
      <div class="col-md-8 mb-2 mb-md-0">
        <span class="me-2">📍 <?= $location ?></span>
        <span class="me-2"><?= $temperature ?></span>
        <span><?= $condition ?></span>
      </div>
      <div class="col-md-4 d-flex justify-content-center justify-content-md-end">
        <div class="input-group input-group-sm" style="max-width: 200px;">
          <input type="text" class="form-control rounded-pill" id="searchInput" placeholder="Search...">
          <button class="btn btn-outline-secondary rounded-pill ms-2" id="searchBtn">🔍</button>
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

    <!-- Message Input -->
    <div class="chat-input d-flex justify-content-center align-items-center mt-4">
      <input type="text" class="form-control rounded-pill me-2" id="userMessage" placeholder="Type your message..." />
      <button class="btn btn-primary rounded-circle" id="sendBtn">➤</button>
    </div>
  </div>
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
