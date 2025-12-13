import os
import json
from dotenv import load_dotenv
from openai import OpenAI

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
def chatbot_reply(user_input):
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
        "You provide clear and simple responses without using *, **, or other Markdown symbols. "
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

    def build_tool_examples():
        examples = []
        for name, info in tools_list.items():
            schema = info["input_schema"]
            props = schema.get("properties", {})
            if not props:
                arg_example = "{}"
            else:
                arg_example = json.dumps({k: f"<{k}_value>" for k in props.keys()})
            examples.append(f"{{tool: '{name}', arguments: {arg_example}}}")
        return "\n".join(examples)

    tool_examples = build_tool_examples()
    tool_check_prompt.append({
        "role": "system",
        "content": (
            "You MUST think critically and select a tool from the tools list if the user's request matches any tool. "
            "Respond ONLY with a valid JSON object: {tool: <tool_name>, arguments: <tool_args>}. "
            "Do NOT simulate or invent data. If no tool is needed, reply with 'none'.\n"
            "Here are examples for each tool:\n" + tool_examples
        )
    })
    tool_check_prompt = filter_valid_messages(tool_check_prompt)

    tool_check_reply = make_gemini_request(tool_check_prompt)

    try:
        reply_json = json.loads(tool_check_reply)
        if isinstance(reply_json, dict) and "tool" in reply_json and reply_json["tool"] in tools_list:
            tool_name = reply_json["tool"]
            arguments = json.dumps(reply_json.get("arguments", {}))
            tool_result = tools_router(tool_name, arguments)
            add_message("tool", tool_result)
            format_prompt = messages.copy()
            format_prompt.append({
                "role": "user",
                "content": (
                    f"Here are the results from the tool '{tool_name}':\n{tool_result}\n"
                    "Please summarize and present the weather data clearly."
                )
            })
            format_prompt = filter_valid_messages(format_prompt)
            formatted_reply = make_gemini_request(format_prompt)
            add_message("assistant", formatted_reply)
            return formatted_reply
            #return f"(Tool called: {tool_name})\n{formatted_reply}"
        elif tool_check_reply.strip().lower() == "none":
            pass
    except Exception:
        pass

    assistant_reply = make_gemini_request(messages)
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
def tools_router(tool, message):
    import requests
    try:
        args = json.loads(message) if message else {}
        print(f"[tools_router] Parsed args: {args}")
    except Exception as e:
        return f"Error parsing arguments: {e}"

    def resolve_location(args):
        lat = args.get("latitude")
        lon = args.get("longitude")
        city = args.get("city")
        if lat is not None and lon is not None:
            return lat, lon
        elif city:
            geo_url = f"https://geocoding-api.open-meteo.com/v1/search?name={city}"
            try:
                resp = requests.get(geo_url, timeout=5)
                data = resp.json()
                if data.get("results"):
                    lat = data["results"][0]["latitude"]
                    lon = data["results"][0]["longitude"]
                    return lat, lon
            except Exception:
                return None, None
        return None, None

    if tool == "get_current_weather":
        lat, lon = resolve_location(args)
        if lat is None or lon is None:
            return json.dumps({"error": "Location not found."})
        url = f"https://api.open-meteo.com/v1/forecast?latitude={lat}&longitude={lon}&current_weather=true"
        try:
            resp = requests.get(url, timeout=5)
            data = resp.json()
            return json.dumps({"location": {"latitude": lat, "longitude": lon}, "current_weather": data.get("current_weather")})
        except Exception as e:
            return json.dumps({"error": str(e)})

    elif tool == "get_weather_forecast":
        lat, lon = resolve_location(args)
        if lat is None or lon is None:
            return json.dumps({"error": "Location not found."})
        url = f"https://api.open-meteo.com/v1/forecast?latitude={lat}&longitude={lon}&hourly=temperature_2m,precipitation,weathercode"
        try:
            resp = requests.get(url, timeout=5)
            data = resp.json()
            return json.dumps({"location": {"latitude": lat, "longitude": lon}, "forecast": data.get("hourly")})
        except Exception as e:
            return json.dumps({"error": str(e)})

    elif tool == "get_weather_by_datetime":
        lat, lon = resolve_location(args)
        date = args.get("date")
        time = args.get("time")
        if lat is None or lon is None or not date or not time:
            return json.dumps({"error": "Please provide location, date, and time."})
        url = f"https://api.open-meteo.com/v1/forecast?latitude={lat}&longitude={lon}&hourly=temperature_2m,precipitation,weathercode&start_date={date}&end_date={date}"
        try:
            resp = requests.get(url, timeout=5)
            data = resp.json()
            times = data.get("hourly", {}).get("time", [])
            idx = None
            for i, t in enumerate(times):
                if t.endswith(f"T{time}"):
                    idx = i
                    break
            if idx is not None:
                result = {k: v[idx] for k, v in data.get("hourly", {}).items() if isinstance(v, list)}
                return json.dumps({"location": {"latitude": lat, "longitude": lon}, "datetime": f"{date}T{time}", "weather": result})
            else:
                return json.dumps({"error": "No weather data found for that date/time."})
        except Exception as e:
            return json.dumps({"error": str(e)})

    elif tool == "get_weather_by_date":
        lat, lon = resolve_location(args)
        date = args.get("date")
        if lat is None or lon is None or not date:
            return json.dumps({"error": "Please provide location and date."})
        url = f"https://api.open-meteo.com/v1/forecast?latitude={lat}&longitude={lon}&hourly=temperature_2m,precipitation,weathercode&start_date={date}&end_date={date}"
        try:
            resp = requests.get(url, timeout=5)
            data = resp.json()
            weather = data.get("hourly")
            if not weather:
                return json.dumps({"error": f"No forecast data available for {date}. Try a closer date."})
            return json.dumps({"location": {"latitude": lat, "longitude": lon}, "date": date, "weather": weather})
        except Exception as e:
            return json.dumps({"error": str(e)})


    elif tool == "get_tools_list":
        tools_info = {name: info["description"] for name, info in tools_list.items()}
        return json.dumps({"tools": tools_info})

    else:
        return json.dumps({"error": "Unknown tool."})

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
