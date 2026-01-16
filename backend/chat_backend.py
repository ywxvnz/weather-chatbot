import os
import json
from dotenv import load_dotenv
from openai import OpenAI
last_location_memory = None

# ------------------------------------------------------------
# Load API key from .env file
# ------------------------------------------------------------
load_dotenv()

# Initialize Gemini client using API key and custom base_url
client = OpenAI(
    api_key=os.getenv("GEMINI_API_KEY"),
    base_url="https://generativelanguage.googleapis.com/v1beta/openai/"
)

# ------------------------------------------------------------
# Global conversation state
# ------------------------------------------------------------
conversation_history = []
max_tokens_limit = 4000  # adjust based on your model
summary_context = ""      # long-term memory

# ------------------------------------------------------------
# Function: Send a chat completion request to Gemini
# ------------------------------------------------------------
def make_gemini_request(messages, model="gemini-2.5-flash"):
    resp = client.chat.completions.create(
        model=model,
        messages=messages,
    )
    return resp.choices[0].message.content

# ------------------------------------------------------------
# Function: Summarize conversation for memory
# ------------------------------------------------------------
def summarize_history():
    """
    Summarizes the conversation to compress memory usage.
    Stores the summary into summary_context.
    """
    global summary_context, conversation_history

    if not conversation_history:
        return

    summary_prompt = [
        {"role": "system", "content": "Summarize the following conversation briefly, keeping important context."},
        {"role": "user", "content": json.dumps(conversation_history)}
    ]

    summary = make_gemini_request(summary_prompt)
    summary_context = summary
    conversation_history = []  # clear short-term memory after summarization

# ------------------------------------------------------------
# Function: Add a message to history with token handling
# ------------------------------------------------------------
def add_message(role, content):
    global conversation_history

    conversation_history.append({"role": role, "content": content})

    total_chars = sum(len(msg["content"]) if msg.get("content") is not None else 0 for msg in conversation_history) + len(summary_context)
    approx_tokens = total_chars // 4

    if approx_tokens > max_tokens_limit * 0.8:  # near limit
        summarize_history()

# ------------------------------------------------------------
# Chatbot interface
# ------------------------------------------------------------
def safe_make_gemini_request(messages, model="gemini-2.5-flash"):
    """
    Wrapper around make_gemini_request to catch quota errors and rate limits.
    Returns a special string "__QUOTA_EXCEEDED__" if quota is hit.
    """
    try:
        return make_gemini_request(messages, model=model)
    except Exception as e:
        err = str(e).lower()
        # Check for quota or rate limit errors
        if any(k in err for k in ["429", "quota", "exhausted", "rate limit"]):
            return "__QUOTA_EXCEEDED__"
        # Otherwise, re-raise
        raise

def chatbot_reply(user_input, selected_location=None):
    global last_location_memory

    # If frontend provided selected_location, update memory
    if selected_location:
        last_location_memory = selected_location

    # Build memory about selected location
    location_memory = ""
    if selected_location:
        location_memory = (
            f"The user's currently selected location is {selected_location.get('name')} "
            f"with latitude {selected_location.get('latitude')} "
            f"and longitude {selected_location.get('longitude')}. "
            "Always use this location for weather unless user specifies another city. "
        )

    global summary_context

    add_message("user", user_input)

    tools_info = "\n".join([
        f"- {name}: {info['description']}" for name, info in tools_list.items()
    ])
    tools_key_phrases = "Key phrases for tool selection: current weather, forecast, weather by date, weather by datetime, tools list."
    system_intro = (
        "You are Gemini, a helpful assistant for weather and lifestyle guidance based on weather conditions. "
        "You have access to a set of tools for retrieving weather information. "
        "Always consider if a user's request can be answered by one of these tools. "
        "If so, select and call the most appropriate tool. "
        "Do not guess. "
        "Do not invent weather data. "
        "If data is missing, say it is unavailable. "
        "You must respond in plain text only. Do not use Markdown, asterisks, or formatting. "
        + location_memory +  # <-- Add this line
        "Here is your current tools list:\n" + tools_info + "\n" + tools_key_phrases
    )

    messages = []
    messages.append({"role": "system", "content": system_intro})
    if summary_context:
        messages.append({"role": "system", "content": f"Long-term memory summary:\n{summary_context}"})
    messages.extend(conversation_history)

    def filter_valid_messages(msgs):
        valid_roles = {"system", "user", "assistant"}
        return [
            m for m in msgs
            if isinstance(m, dict) and "role" in m and "content" in m and m["role"] in valid_roles
        ]

    messages = filter_valid_messages(messages)
    tool_check_prompt = messages.copy()

    tool_check_prompt.append({
        "role": "system",
        "content": (
        "You are a tool-selection engine. "
        "You MUST respond in valid JSON only. "
        "No markdown. No code. No extra text. "
        "If the user mentions a city or location in their query, include it in the arguments as {\"city\":\"CityName\"}. "
        "If no city is mentioned, leave arguments empty. "
        "Example output if city mentioned:\n"
        "{\"tool\":\"get_current_weather\",\"arguments\":{\"city\":\"Pasay\"}}\n"
        "Example output if no city mentioned:\n"
        "{\"tool\":\"get_current_weather\",\"arguments\":{}}\n"
        "If no tool is needed, reply exactly:\n"
        "none"
        )
    })




    tool_check_prompt = filter_valid_messages(tool_check_prompt)

    # ---- Safe tool check call ----
    tool_check_reply = safe_make_gemini_request(tool_check_prompt)
    if tool_check_reply == "__QUOTA_EXCEEDED__":
        return "The service is temporarily unavailable due to usage limits. Please try again later."

    try:
        raw = tool_check_reply.strip()

        # --- Auto repair non-JSON tool calls from Gemini ---
        if raw.startswith("get_") and "(" in raw:
            tool_name = raw.split("(")[0]
            inside = raw.split("(",1)[1].rstrip(")")
            args = {}
            if inside:
                for pair in inside.split(","):
                    if "=" in pair:
                        k, v = pair.split("=")
                        args[k.strip()] = v.strip().strip('"')
            reply_json = {"tool": tool_name, "arguments": args}
        else:
            reply_json = json.loads(raw)

        # --- Execute tool if valid ---
        if isinstance(reply_json, dict) and "tool" in reply_json and reply_json["tool"] in tools_list:
            tool_name = reply_json["tool"]
            arguments = json.dumps(reply_json.get("arguments", {}))
            tool_result = tools_router(tool_name, arguments, selected_location)

            format_prompt = messages.copy()
            format_prompt.append({
                "role": "user",
                "content": (
                    f"Here are the results from the tool '{tool_name}':\n{tool_result}\n"
                    "Please summarize and present the weather data clearly."
                )
            })

            formatted_reply = safe_make_gemini_request(format_prompt)
            add_message("assistant", formatted_reply)
            return formatted_reply

    except Exception:
        pass


    # ---- Safe fallback to general assistant reply ----
    assistant_reply = safe_make_gemini_request(messages)
    if assistant_reply == "__QUOTA_EXCEEDED__":
        return "The service is temporarily unavailable due to usage limits. Please try again later."

    add_message("assistant", assistant_reply)
    return assistant_reply

# ------------------------------------------------------------
# Tools (Weather only)
# ------------------------------------------------------------
tools_list = {
    "get_current_weather": {
        "description": "Get current weather for a specific location (city name or latitude/longitude).",
        "input_schema": {
            "type": "object",
            "properties": {
                "city": {"type": "string"},
                "latitude": {"type": "number"},
                "longitude": {"type": "number"}
            },
            "required": []
        }
    },
    "get_weather_forecast": {
        "description": "Get weather forecast for a specific location.",
        "input_schema": {
            "type": "object",
            "properties": {
                "city": {"type": "string"},
                "latitude": {"type": "number"},
                "longitude": {"type": "number"}
            },
            "required": []
        }
    },
    "get_weather_by_datetime": {
        "description": "Get weather for a specific location, date, and time.",
        "input_schema": {
            "type": "object",
            "properties": {
                "city": {"type": "string"},
                "latitude": {"type": "number"},
                "longitude": {"type": "number"},
                "date": {"type": "string"},
                "time": {"type": "string"}
            },
            "required": []
        }
    },
    "get_weather_by_date": {
        "description": "Get weather for a specific location and date.",
        "input_schema": {
            "type": "object",
            "properties": {
                "city": {"type": "string"},
                "latitude": {"type": "number"},
                "longitude": {"type": "number"},
                "date": {"type": "string"}
            },
            "required": []
        }
    },
    "get_tools_list": {
        "description": "Get the list of available tools and their descriptions.",
        "input_schema": {"type": "object", "properties": {}, "required": []}
    }
}

# ------------------------------------------------------------
# Tools Router (Weather only)
# ------------------------------------------------------------
import json
import requests

def tools_router(tool, message, selected_location=None):
    import requests, json

    try:
        if isinstance(message, dict):
            args = message
        else:
            args = json.loads(message) if message else {}
        print(f"[tools_router] Parsed args: {args}")
    except Exception as e:
        return {"error": f"Error parsing arguments: {e}"}

    def resolve_location(args, user_input=None):
        lat = args.get("latitude")
        lon = args.get("longitude")
        city = args.get("city")

        # 1️⃣ If explicit lat/lon provided → highest priority
        if lat is not None and lon is not None:
            return lat, lon, city

        # 2️⃣ Attempt to parse city from user input if city not provided
        if (not city or city.strip() == "") and user_input:
            import re
            match = re.search(r'in ([A-Za-z ]+)\??', user_input)
            if match:
                city = match.group(1).strip()

        # 3️⃣ If city provided → geocode it
        if city:
            geo_url = f"https://geocoding-api.open-meteo.com/v1/search?name={city}"
            try:
                resp = requests.get(geo_url, timeout=5)
                data = resp.json()
                if data.get("results"):
                    lat = data["results"][0]["latitude"]
                    lon = data["results"][0]["longitude"]
                    return lat, lon, city
            except:
                pass

        # 4️⃣ fallback to selected_location
        # 4️⃣ fallback to frontend-selected location
        if selected_location:
            return (selected_location.get("latitude"),
                    selected_location.get("longitude"),
                    selected_location.get("name"))

        # 5️⃣ fallback to last resolved location memory
        from __main__ import last_location_memory
        if last_location_memory:
            return (last_location_memory.get("latitude"),
                    last_location_memory.get("longitude"),
                    last_location_memory.get("name"))



    lat, lon, loc_name = resolve_location(args)
    if lat is None or lon is None:
        return {"error": "Location not found."}

    def location_info():
        return {"latitude": lat, "longitude": lon, "name": loc_name}

    try:
        if tool == "get_current_weather":
            url = f"https://api.open-meteo.com/v1/forecast?latitude={lat}&longitude={lon}&current_weather=true"
            data = requests.get(url, timeout=5).json()
            return {"location": location_info(), "current_weather": data.get("current_weather")}

        elif tool == "get_weather_forecast":
            url = f"https://api.open-meteo.com/v1/forecast?latitude={lat}&longitude={lon}&hourly=temperature_2m,precipitation,weathercode"
            data = requests.get(url, timeout=5).json()
            return {"location": location_info(), "forecast": data.get("hourly")}

        elif tool == "get_weather_by_datetime":
            date = args.get("date")
            time = args.get("time")
            if not date or not time:
                return {"error": "Please provide date and time."}

            url = f"https://api.open-meteo.com/v1/forecast?latitude={lat}&longitude={lon}&hourly=temperature_2m,precipitation,weathercode&start_date={date}&end_date={date}"
            data = requests.get(url, timeout=5).json()

            times = data.get("hourly", {}).get("time", [])
            idx = next((i for i,t in enumerate(times) if t.endswith(f"T{time}")), None)

            if idx is None:
                return {"error": "No weather data found for that date/time."}

            result = {k:v[idx] for k,v in data["hourly"].items() if isinstance(v,list)}
            return {"location": location_info(), "datetime": f"{date}T{time}", "weather": result}

        elif tool == "get_weather_by_date":
            date = args.get("date")
            if not date:
                return {"error": "Please provide a date."}

            url = f"https://api.open-meteo.com/v1/forecast?latitude={lat}&longitude={lon}&hourly=temperature_2m,precipitation,weathercode&start_date={date}&end_date={date}"
            data = requests.get(url, timeout=5).json()

            weather = data.get("hourly")
            if not weather:
                return {"error": f"No forecast data for {date}."}

            return {"location": location_info(), "date": date, "weather": weather}

        elif tool == "get_tools_list":
            return {"tools": {name: info["description"] for name,info in tools_list.items()}}

        else:
            return {"error": "Unknown tool."}

    except Exception as e:
        return {"error": str(e)}


# ------------------------------------------------------------
# Example usage
# ------------------------------------------------------------
if __name__ == "__main__":
    print("🌤️ Weather Chatbot started. Type 'exit' to quit.\n")
    while True:
        user_input = input("You: ")
        if user_input.lower() in ["exit", "quit"]:
            break
        reply = chatbot_reply(user_input)
        print(f"Bot: {reply}\n")
