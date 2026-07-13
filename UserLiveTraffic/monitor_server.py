import asyncio
import websockets
import json
import requests

BASE_URL = "http://103.111.39.174"

def fetch_user_interface(username):
    try:
        r = requests.get(f"{BASE_URL}/get_user_interface.php?username={username}", timeout=15)
        if r.status_code == 200:
            data = r.json()
            if data.get('status') == 'success':
                return data['interface']
    except Exception as e:
        print(f"[-] Interface error for {username}: {e}")
    return None

def fetch_live_traffic(interface):
    try:
        r = requests.get(f"{BASE_URL}/get_live_traffic.php?interface={interface}", timeout=10)
        if r.status_code == 200:
            return r.json()
    except Exception as e:
        return {"status": "error", "message": str(e)}
    return {"status": "error", "message": "Invalid response"}

async def handler(websocket, path):
    username = "unknown"
    if "?username=" in path:
        username = path.split("?username=")[1]
    print(f"[+] Connected: {username}")

    interface = fetch_user_interface(username)
    if not interface:
        await websocket.send(json.dumps({"status": "error", "message": "User offline"}))
        return

    print(f"[+] Interface: {interface} for {username}")

    while True:
        try:
            data = fetch_live_traffic(interface)
            # Add interface name to every response
            if isinstance(data, dict):
                data['interface'] = interface
                data['username']  = username
            await websocket.send(json.dumps(data))
            await asyncio.sleep(2)
        except Exception as e:
            print(f"[-] Disconnected {username}: {e}")
            break

start_server = websockets.serve(handler, "0.0.0.0", 8081)
print("[*] WebSocket Server started on port 8081...")
asyncio.get_event_loop().run_until_complete(start_server)
asyncio.get_event_loop().run_forever()
