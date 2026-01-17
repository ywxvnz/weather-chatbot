import os
import json
import logging
import re
from datetime import datetime
from dotenv import load_dotenv
from openai import OpenAI

last_location_memory = None

# ============================================================
# LOGGING SETUP
# ============================================================
log_file = os.path.join(os.path.dirname(__file__), 'chatbot_logs.txt')
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(log_file),
        logging.StreamHandler()  # Also print to console
    ]
)
logger = logging.getLogger(__name__)
logger.info("=" * 60)
logger.info("Weather Chatbot Started")
logger.info("=" * 60)

# ============================================================
# Load API key from .env file
# ============================================================
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
        response_format={"type": "json_object"}   # IMPORTANT
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

# ============================================================
# Function: Add a message to history with token handling
# ============================================================
def add_message(role, content):
    """Add message to history, handling None values and token limits."""
    global conversation_history
    
    # Fix NoneType: convert None to empty string
    if content is None:
        logger.warning(f"Received None content for role '{role}'. Converting to empty string.")
        content = ""
    
    # Ensure content is a string
    content = str(content) if not isinstance(content, str) else content
    
    conversation_history.append({"role": role, "content": content})
    logger.debug(f"Added message - Role: {role}, Content length: {len(content)}")

    # Calculate token usage safely
    total_chars = sum(
        len(msg.get("content", "") or "") 
        for msg in conversation_history
    ) + len(summary_context or "")
    approx_tokens = total_chars // 4

    if approx_tokens > max_tokens_limit * 0.8:  # near limit
        logger.info(f"Token limit approaching ({approx_tokens}/{max_tokens_limit}). Summarizing history...")
        summarize_history()

# ============================================================
# Function: Safe LLM wrapper with error handling
# ============================================================
def safe_make_gemini_request(messages, model="gemini-2.5-flash"):
    """
    Wrapper around make_gemini_request to catch quota errors and rate limits.
    Handles NoneType returns and ensures safe response.
    Returns special string "__QUOTA_EXCEEDED__" if quota is hit.
    Returns empty string if LLM returns None.
    """
    try:
        response = make_gemini_request(messages, model=model)
        
        # Fix NoneType: if LLM returns None, log and return empty string
        if response is None:
            logger.warning("LLM returned None. Converting to empty string.")
            return ""
        
        return response
        
    except Exception as e:
        err = str(e).lower()
        logger.error(f"Gemini request error: {str(e)}")
        
        # Check for quota or rate limit errors
        if any(k in err for k in ["429", "quota", "exhausted", "rate limit"]):
            logger.warning("Quota or rate limit exceeded.")
            return "__QUOTA_EXCEEDED__"
        
        # Log unexpected errors but don't crash
        logger.exception("Unexpected error in safe_make_gemini_request")
        return ""


def is_raw_code(text: str) -> bool:
    """Detect likely raw code in LLM responses."""
    if not text or not isinstance(text, str):
        return False
    # common signs of Python/code-like responses
    patterns = [r"\btool_code\b", r"print\s*\(", r"\bdef\s+\w+\s*\(", r"\bclass\s+\w+",
                r"\bimport\s+\w+", r"```", r"\breturn\b", r"\bresult\s*=", r"->"]
    for p in patterns:
        if re.search(p, text):
            return True
    return False


def strip_code_snippets(text: str) -> str:
    """Remove code blocks and inline code, returning a cleaned plain-text string."""
    if not text or not isinstance(text, str):
        return ""
    # remove triple backtick blocks
    text = re.sub(r"```.*?```", "", text, flags=re.S)
    # remove inline backticks
    text = re.sub(r"`+", "", text)
    # remove explicit tool markers like 'tool_code'
    text = re.sub(r"\btool_code\b", "", text)
    # remove common python-like lines (very simple sanitization)
    text = re.sub(r"^\s*(import|from|def|class|return|print)\b.*$", "", text, flags=re.M)
    # remove parentheses-heavy code fragments like get_weather(...)
    text = re.sub(r"\bget_[a-z_]+\([^\)]*\)", "", text)
    # collapse multiple blank lines
    text = re.sub(r"\n{2,}", "\n", text)
    return text.strip()


def format_tool_result(tool_name: str, tool_result: dict) -> str:
    """Convert structured tool result into human-readable plain text.

    Returns a short natural-language summary for weather tools.
    """
    try:
        if not tool_result:
            return "No data available from the tool."

        # Error case
        if isinstance(tool_result, dict) and tool_result.get("error"):
            return f"Error: {tool_result.get('error')}"

        loc = None
        if isinstance(tool_result, dict):
            loc = tool_result.get("location") or tool_result.get("location_info")

        loc_name = loc.get("name") if isinstance(loc, dict) and loc.get("name") else "the specified location"

        if tool_name == "get_current_weather":
            cw = tool_result.get("current_weather") if isinstance(tool_result, dict) else None
            if not cw:
                return f"Current weather for {loc_name} is unavailable."
            temp = cw.get("temperature") or cw.get("temperature_2m") or cw.get("temp")
            wind = cw.get("windspeed") or cw.get("wind_speed")
            code = cw.get("weathercode")
            time = cw.get("time") or cw.get("observation_time")
            parts = [f"Current weather in {loc_name}:"]
            if temp is not None:
                parts.append(f"temperature {temp}°C")
            if wind is not None:
                parts.append(f"wind {wind} m/s")
            if code is not None:
                parts.append(f"weather code {code}")
            if time:
                parts.append(f"(observed at {time})")
            return ", ".join(parts)

        if tool_name == "get_weather_forecast":
            forecast = tool_result.get("forecast") if isinstance(tool_result, dict) else None
            if not forecast or not isinstance(forecast, dict):
                return f"Forecast for {loc_name} is unavailable."
            times = forecast.get("time", [])
            temps = forecast.get("temperature_2m") or forecast.get("temperature")
            prec = forecast.get("precipitation") or forecast.get("precipitation_sum")
            # Summarize next few entries
            summary_parts = [f"Forecast for {loc_name}:"]
            count = min(6, len(times))
            for i in range(count):
                t = times[i] if i < len(times) else None
                tt = f"{temps[i]}°C" if temps and i < len(temps) else None
                pp = f"{prec[i]} mm" if prec and i < len(prec) else None
                seg = []
                if t:
                    seg.append(t)
                if tt:
                    seg.append(tt)
                if pp:
                    seg.append(pp)
                if seg:
                    summary_parts.append(" - ".join(seg))
            if count == 0:
                return f"No forecast entries available for {loc_name}."
            return "\n".join(summary_parts)

        if tool_name == "get_weather_by_datetime":
            dt = tool_result.get("datetime") or tool_result.get("time")
            weather = tool_result.get("weather") or tool_result.get("current_weather")
            if not weather:
                return f"No weather data found for {loc_name} at {dt}."
            temp = weather.get("temperature") or weather.get("temperature_2m")
            prec = weather.get("precipitation")
            code = weather.get("weathercode")
            parts = [f"Weather in {loc_name} at {dt}:"]
            if temp is not None:
                parts.append(f"temperature {temp}°C")
            if prec is not None:
                parts.append(f"precipitation {prec} mm")
            if code is not None:
                parts.append(f"weather code {code}")
            return ", ".join(parts)

        if tool_name == "get_weather_by_date":
            date = tool_result.get("date")
            weather = tool_result.get("weather")
            if not weather or not isinstance(weather, dict):
                return f"No weather data for {loc_name} on {date}."
            temps = weather.get("temperature_2m") or weather.get("temperature")
            if temps:
                try:
                    tmin = min(temps)
                    tmax = max(temps)
                    return f"On {date} in {loc_name}, temperatures will range from {tmin}°C to {tmax}°C." 
                except Exception:
                    pass
            return f"Weather data for {loc_name} on {date} is available." 

        if tool_name == "get_tools_list":
            tools = tool_result.get("tools") if isinstance(tool_result, dict) else None
            if not tools:
                return "No tools available."
            lines = ["Available tools:"]
            for k, v in tools.items():
                lines.append(f"- {k}: {v}")
            return "\n".join(lines)

        # Generic fallback: try to produce readable key: value pairs
        if isinstance(tool_result, dict):
            parts = []
            for k, v in tool_result.items():
                if isinstance(v, (str, int, float)):
                    parts.append(f"{k}: {v}")
            if parts:
                return ", ".join(parts)

        return "Tool returned data."
    except Exception as e:
        logger.exception(f"Error formatting tool result: {e}")
        return "Tool returned data." 

# ============================================================
# Function: Parse tool selection JSON with auto-repair
# ============================================================
def parse_tool_selection(raw_text):
    """
    Parse tool selection JSON from LLM response.
    Auto-repairs invalid JSON or plain text tool calls.
    Returns dict with 'tool' and 'arguments' keys, or None if invalid.
    """
    if not raw_text or not isinstance(raw_text, str):
        return None
    
    raw = raw_text.strip()
    logger.debug(f"Parsing tool selection: {raw[:100]}...")  # Log first 100 chars
    
    # Check for "none" (case-insensitive)
    if raw.lower() == "none":
        logger.info("Tool selection: none")
        return {"tool": "none", "arguments": {}}
    
    # Try parsing as JSON first
    try:
        parsed = json.loads(raw)
        if isinstance(parsed, dict) and "tool" in parsed:
            logger.info(f"Tool selected: {parsed.get('tool')}")
            return parsed
    except json.JSONDecodeError:
        pass
    
    # Auto-repair: Try to fix plain text tool calls like get_current_weather(city="Pasay")
    if raw.startswith("get_") and "(" in raw:
        logger.info("Attempting to auto-repair malformed tool call...")
        try:
            tool_name = raw.split("(")[0]
            inside = raw.split("(", 1)[1].rstrip(")")
            args = {}
            
            if inside:
                for pair in inside.split(","):
                    if "=" in pair:
                        k, v = pair.split("=", 1)
                        args[k.strip()] = v.strip().strip('"').strip("'")
            
            result = {"tool": tool_name, "arguments": args}
            logger.info(f"Auto-repaired tool call: {result}")
            return result
        except Exception as e:
            logger.warning(f"Failed to auto-repair tool call: {e}")
    
    logger.warning(f"Could not parse tool selection: {raw}")
    return None


def chatbot_reply(user_input, selected_location=None):
    """Main chatbot reply function with comprehensive error handling and logging."""
    global last_location_memory
    
    logger.info(f"\n--- User Input ---\n{user_input}")

    # If frontend provided selected_location, update memory
    if selected_location:
        last_location_memory = selected_location
        logger.info(f"Selected location updated: {selected_location.get('name')}")

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
        + location_memory
        + "Here is your current tools list:\n" + tools_info + "\n" + tools_key_phrases
    )

    messages = []
    messages.append({"role": "system", "content": system_intro})
    if summary_context:
        messages.append({"role": "system", "content": f"Long-term memory summary:\n{summary_context}"})
    messages.extend(conversation_history)

    def filter_valid_messages(msgs):
        """Filter messages, handling None values safely."""
        valid_roles = {"system", "user", "assistant"}
        filtered = []
        for m in msgs:
            if isinstance(m, dict) and "role" in m and m["role"] in valid_roles:
                # Ensure content is not None
                content = m.get("content") or ""
                filtered.append({"role": m["role"], "content": content})
        return filtered

    messages = filter_valid_messages(messages)
    tool_check_prompt = messages.copy()

    tool_check_prompt.append({
    "role": "system",
    "content": (
        "You are a tool-selection engine. "
        "You MUST reply in valid JSON only. "
        "No markdown. No explanations. No extra text. "

        "Choose ONLY one of these tool names exactly:\n"
        "get_current_weather\n"
        "get_weather_forecast\n"
        "get_weather_by_date\n"
        "get_weather_by_datetime\n"
        "get_tools_list\n"
        "none\n"

        "Rules:\n"
        "- If the user asks for current weather → use get_current_weather.\n"
        "- If the user asks for weather forecast without a specific date → use get_weather_forecast.\n"
        "- If the user asks for weather on a specific date → use get_weather_by_date and include {\"date\":\"YYYY-MM-DD\"}.\n"
        "- If the user asks for weather on a specific date and time → use get_weather_by_datetime and include "
        "{\"date\":\"YYYY-MM-DD\",\"time\":\"HH:MM\"}.\n"
        "- If the user mentions a city, include {\"city\":\"CityName\"} in arguments.\n"
        "- If no city is mentioned, omit the city field.\n"
        "- If no tool applies → return {\"tool\":\"none\",\"arguments\":{}}.\n"

        "Example (current weather with city):\n"
        "{\"tool\":\"get_current_weather\",\"arguments\":{\"city\":\"Pasay\"}}\n"

        "Example (date-based weather):\n"
        "{\"tool\":\"get_weather_by_date\",\"arguments\":{\"city\":\"Pasay\",\"date\":\"2026-02-14\"}}\n"

        "Example (no tool):\n"
        "{\"tool\":\"none\",\"arguments\":{}}"
    )
})


    tool_check_prompt = filter_valid_messages(tool_check_prompt)

    # ---- Safe tool check call ----
    tool_check_reply = safe_make_gemini_request(tool_check_prompt)
    
    if tool_check_reply == "__QUOTA_EXCEEDED__":
        logger.error("Quota exceeded on tool check request.")
        fallback_response = "The service is temporarily unavailable due to usage limits. Please try again later."
        add_message("assistant", fallback_response)
        return fallback_response
    
    # Handle empty response from LLM
    if not tool_check_reply or tool_check_reply.strip() == "":
        logger.warning("Empty response from LLM on tool check. Using fallback.")
        tool_check_reply = "none"
    
    # ============================================================
    # Parse tool selection JSON with auto-repair
    # ============================================================
    reply_json = parse_tool_selection(tool_check_reply)
    
    # Try to execute tool if valid
    if reply_json and isinstance(reply_json, dict) and "tool" in reply_json:
        tool_name = reply_json.get("tool")
        
        if tool_name and tool_name != "none" and tool_name in tools_list:
            logger.info(f"Executing tool: {tool_name}")
            
            try:
                arguments = json.dumps(reply_json.get("arguments", {}))
                logger.debug(f"Tool arguments: {arguments}")
                
                tool_result = tools_router(tool_name, arguments, selected_location)
                logger.info(f"Tool result: {str(tool_result)[:200]}...")  # Log first 200 chars

                format_prompt = messages.copy()
                format_prompt.append({
                    "role": "user",
                    "content": (
                        f"Here are the results from the tool '{tool_name}':\n{json.dumps(tool_result)}\n"
                        "Please summarize and present the weather data clearly."
                    )
                })

                formatted_reply = safe_make_gemini_request(format_prompt)
                # Because response_format forces JSON, parse it
                try:
                    parsed = json.loads(formatted_reply)
                    # If model returned a weather_summary field, use it
                    if isinstance(parsed, dict):
                        formatted_reply = parsed.get("weather_summary") or parsed.get("reply") or str(parsed)
                except:
                    pass

                # Handle quota exceeded or empty response
                if formatted_reply == "__QUOTA_EXCEEDED__":
                    logger.error("Quota exceeded on formatted reply request.")
                    fallback_response = "The service is temporarily unavailable. Please try again later."
                    add_message("assistant", fallback_response)
                    return fallback_response
                
                if not formatted_reply or formatted_reply.strip() == "":
                    logger.warning("Empty formatted reply from LLM.")
                    formatted_reply = "No response from assistant."

                # Sanitize: if LLM returned raw code-like output, convert using tool_result
                if is_raw_code(formatted_reply):
                    logger.warning("Formatted reply appears to contain raw code. Replacing with sanitized summary.")
                    try:
                        safe_summary = format_tool_result(tool_name, tool_result)
                        if safe_summary and isinstance(safe_summary, str) and safe_summary.strip():
                            formatted_reply = safe_summary
                        else:
                            # Fallback: strip code snippets and present minimal info
                            cleaned = strip_code_snippets(formatted_reply)
                            formatted_reply = cleaned or "The assistant produced a code-like response which was sanitized."
                    except Exception as e:
                        logger.exception(f"Failed to format tool result: {e}")
                        formatted_reply = strip_code_snippets(formatted_reply) or "The assistant produced a code-like response which was sanitized."

                add_message("assistant", formatted_reply)
                logger.info(f"--- Assistant Reply ---\n{formatted_reply}\n")
                return formatted_reply
                
            except Exception as e:
                logger.exception(f"Error executing tool '{tool_name}': {str(e)}")
                # Fall through to general assistant reply

    # ============================================================
    # Safe fallback to general assistant reply
    # ============================================================
    logger.info("Using general assistant reply (no tool selected or tool failed).")
    
    assistant_reply = safe_make_gemini_request(messages)
    
    if assistant_reply == "__QUOTA_EXCEEDED__":
        logger.error("Quota exceeded on general assistant request.")
        fallback_response = "The service is temporarily unavailable due to usage limits. Please try again later."
        add_message("assistant", fallback_response)
        return fallback_response
    
    # Ensure we have a valid response
    if not assistant_reply or assistant_reply.strip() == "":
        logger.warning("Empty or invalid assistant reply. Using fallback.")
        assistant_reply = "No response from assistant."

    # If assistant produced raw code and no tool output available, strip/sanitize it
    if is_raw_code(assistant_reply):
        logger.warning("Assistant reply contains raw code. Sanitizing before sending to frontend.")
        cleaned = strip_code_snippets(assistant_reply)
        assistant_reply = cleaned or "The assistant produced a code-like response which was sanitized."

    add_message("assistant", assistant_reply)
    logger.info(f"--- Assistant Reply ---\n{assistant_reply}\n")
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
    """Route tool execution with comprehensive logging."""
    import requests, json

    try:
        if isinstance(message, dict):
            args = message
        else:
            args = json.loads(message) if message else {}
        logger.debug(f"[tools_router] Parsed args: {args}")
    except Exception as e:
        logger.error(f"[tools_router] Error parsing arguments: {e}")
        return {"error": f"Error parsing arguments: {e}"}

    def resolve_location(args, user_input=None):
        """Resolve location with proper logging."""
        lat = args.get("latitude")
        lon = args.get("longitude")
        city = args.get("city")

        # 1️⃣ If explicit lat/lon provided → highest priority
        if lat is not None and lon is not None:
            logger.info(f"Location resolved from explicit lat/lon: {lat}, {lon}")
            return lat, lon, city

        # 2️⃣ Attempt to parse city from user input if city not provided
        if (not city or city.strip() == "") and user_input:
            import re
            match = re.search(r'in ([A-Za-z ]+)\??', user_input)
            if match:
                city = match.group(1).strip()
                logger.info(f"City parsed from user input: {city}")

        # 3️⃣ If city provided → geocode it
        if city:
            geo_url = f"https://geocoding-api.open-meteo.com/v1/search?name={city}"
            try:
                resp = requests.get(geo_url, timeout=5)
                data = resp.json()
                if data.get("results"):
                    lat = data["results"][0]["latitude"]
                    lon = data["results"][0]["longitude"]
                    logger.info(f"Location geocoded - City: {city}, Lat: {lat}, Lon: {lon}")
                    return lat, lon, city
            except Exception as e:
                logger.warning(f"Failed to geocode city '{city}': {e}")

        # 4️⃣ fallback to selected_location
        if selected_location:
            logger.info(f"Location resolved from selected_location: {selected_location.get('name')}")
            return (selected_location.get("latitude"),
                    selected_location.get("longitude"),
                    selected_location.get("name"))

        # 5️⃣ fallback to last resolved location memory
        global last_location_memory
        if last_location_memory:
            logger.info(f"Location resolved from memory: {last_location_memory.get('name')}")
            return (last_location_memory.get("latitude"),
                    last_location_memory.get("longitude"),
                    last_location_memory.get("name"))



    lat, lon, loc_name = resolve_location(args)
    if lat is None or lon is None:
        logger.error("Location resolution failed.")
        return {"error": "Location not found."}

    def location_info():
        return {"latitude": lat, "longitude": lon, "name": loc_name}

    try:
        if tool == "get_current_weather":
            logger.info(f"Getting current weather for {loc_name} ({lat}, {lon})")
            url = f"https://api.open-meteo.com/v1/forecast?latitude={lat}&longitude={lon}&current_weather=true"
            data = requests.get(url, timeout=5).json()
            result = {"location": location_info(), "current_weather": data.get("current_weather")}
            logger.info(f"Current weather result: {result}")
            return result

        elif tool == "get_weather_forecast":
            logger.info(f"Getting weather forecast for {loc_name} ({lat}, {lon})")
            url = f"https://api.open-meteo.com/v1/forecast?latitude={lat}&longitude={lon}&hourly=temperature_2m,precipitation,weathercode"
            data = requests.get(url, timeout=5).json()
            result = {"location": location_info(), "forecast": data.get("hourly")}
            logger.info(f"Forecast retrieved for {loc_name}")
            return result

        elif tool == "get_weather_by_datetime":
            date = args.get("date")
            time = args.get("time")
            if not date or not time:
                logger.warning("Missing date or time for get_weather_by_datetime")
                return {"error": "Please provide date and time."}

            logger.info(f"Getting weather for {loc_name} on {date} at {time}")
            url = f"https://api.open-meteo.com/v1/forecast?latitude={lat}&longitude={lon}&hourly=temperature_2m,precipitation,weathercode&start_date={date}&end_date={date}"
            data = requests.get(url, timeout=5).json()

            times = data.get("hourly", {}).get("time", [])
            idx = next((i for i,t in enumerate(times) if t.endswith(f"T{time}")), None)

            if idx is None:
                logger.warning(f"No weather data found for {date}T{time}")
                return {"error": "No weather data found for that date/time."}

            result = {k:v[idx] for k,v in data["hourly"].items() if isinstance(v,list)}
            return {"location": location_info(), "datetime": f"{date}T{time}", "weather": result}

        elif tool == "get_weather_by_date":
            date = args.get("date")
            if not date:
                logger.warning("Missing date for get_weather_by_date")
                return {"error": "Please provide a date."}

            logger.info(f"Getting weather for {loc_name} on {date}")
            url = f"https://api.open-meteo.com/v1/forecast?latitude={lat}&longitude={lon}&hourly=temperature_2m,precipitation,weathercode&start_date={date}&end_date={date}"
            data = requests.get(url, timeout=5).json()

            weather = data.get("hourly")
            if not weather:
                logger.warning(f"No forecast data for {date}")
                return {"error": f"No forecast data for {date}."}

            return {"location": location_info(), "date": date, "weather": weather}

        elif tool == "get_tools_list":
            logger.info("Returning tools list")
            return {"tools": {name: info["description"] for name,info in tools_list.items()}}

        else:
            logger.error(f"Unknown tool: {tool}")
            return {"error": "Unknown tool."}

    except Exception as e:
        logger.exception(f"Error executing tool '{tool}': {str(e)}")
        return {"error": str(e)}


# ============================================================
# Example usage
# ============================================================
if __name__ == "__main__":
    logger.info("🌤️ Weather Chatbot started. Type 'exit' to quit.")
    print("🌤️ Weather Chatbot started. Type 'exit' to quit.\n")
    while True:
        try:
            user_input = input("You: ").strip()
            if not user_input:
                continue
            if user_input.lower() in ["exit", "quit"]:
                logger.info("Chatbot exited by user.")
                break
            reply = chatbot_reply(user_input)
            print(f"Bot: {reply}\n")
        except KeyboardInterrupt:
            logger.info("Chatbot interrupted by user.")
            break
        except Exception as e:
            logger.exception(f"Unexpected error in main loop: {e}")
            print(f"Error: {e}\n")
