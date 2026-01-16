import os
from flask import Flask, request, jsonify
from flask_cors import CORS
from dotenv import load_dotenv
import chat_backend

# -------------------------------
# Clear logs at server start
# -------------------------------
log_file = "chatbot_logs.txt"
open(log_file, "w").close()  # clears previous logs

# load .env (so chat_backend can read GEMINI_API_KEY)
load_dotenv()

app = Flask(__name__)
CORS(app)  # allow cross-origin requests while developing

@app.route("/api/chat", methods=["POST"])
def chat_endpoint():
    data = request.get_json(force=True)
    user_msg = data.get("message", "").strip()
    selected_location = data.get("selectedLocation")

    if not user_msg:
        return jsonify({"error": "Empty message"}), 400

    try:
        # pass both user message and selected location
        bot_reply = chat_backend.chatbot_reply(user_msg, selected_location)
        return jsonify({"reply": bot_reply})
    except Exception as e:
        return jsonify({"error": str(e)}), 500


if __name__ == "__main__":
    print("🌤️ Weather Chatbot Flask server starting...")
    app.run(host="0.0.0.0", port=5000, debug=True)
