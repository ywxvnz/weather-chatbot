# chat_api.py
import os
from flask import Flask, request, jsonify
from flask_cors import CORS
from dotenv import load_dotenv

# load .env (so chat_backend can read GEMINI_API_KEY)
load_dotenv()

# import your existing functions from chat_backend.py
# make sure chat_backend.py is in the same folder or in PYTHONPATH
import chat_backend

app = Flask(__name__)
CORS(app)  # allow cross-origin requests while developing

@app.route("/api/chat", methods=["POST"])
def chat_endpoint():
    data = request.get_json(force=True) or {}
    user_msg = (data.get("message") or "").strip()
    location = data.get("location")  # optional: { name, latitude, longitude }

    if not user_msg:
        return jsonify({"error": "Empty message"}), 400

    try:
        # chatbot_reply now accepts an optional location dict
        bot_reply = chat_backend.chatbot_reply(user_msg, location=location)
        return jsonify({"reply": bot_reply})
    except Exception as e:
        # return error message for debugging (remove detail in production)
        return jsonify({"error": str(e)}), 500

if __name__ == "__main__":
    # change host/port if needed; use a process manager for production
    app.run(host="0.0.0.0", port=5000, debug=True)
