import sys
import re
import time
import argparse
import paramiko
import json

# ================= MULTI-OLT CONFIGURATION =================
OLT_LIST = [
    {
        "name": "Huawei-Main",
        "type": "huawei",
        "ip": "192.168.80.11",
        "user": "root",
        "pass": "Npdsl@786"
    },
    {
        "name": "VSOL-KTA",
        "type": "vsol",
        "ip": "192.168.80.12",
        "user": "admin",
        "pass": "admin",
        "enable_pass": "admin"
    }
]

# ================= ZALPRO DB CREDENTIALS =================
DB_HOST = "localhost"
DB_USER = "root"
DB_PASS = "Holdon@123"
DB_NAME = "zalpro"
# =========================================================

def format_mac_huawei(mac_raw):
    clean = re.sub(r'[^a-fA-F0-9]', '', str(mac_raw)).lower()
    if len(clean) != 12:
        return None
    return f"{clean[0:4]}-{clean[4:8]}-{clean}"

def format_mac_vsol(mac_raw):
    clean = re.sub(r'[^a-fA-F0-9]', '', str(mac_raw)).lower()
    if len(clean) != 12:
        return None
    return f"{clean[0:4]}.{clean[4:8]}.{clean}"

def get_mac_from_zalpro(username):
    try:
        import pymysql
        conn = pymysql.connect(
            host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME
        )
        cursor = conn.cursor()
        query = """
            SELECT mac FROM radpostauth 
            WHERE username = %s AND mac IS NOT NULL AND mac != '' 
            ORDER BY id DESC LIMIT 1
        """
        cursor.execute(query, (username,))
        result = cursor.fetchone()
        conn.close()

        if result and result[0]:
            return result[0]
        return None
    except Exception:
        return None

def query_huawei_olt(olt, raw_mac):
    huawei_mac = format_mac_huawei(raw_mac)
    if not huawei_mac:
        return None

    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

    try:
        ssh.connect(olt['ip'], username=olt['user'], password=olt['pass'], timeout=8)
        shell = ssh.invoke_shell()
        time.sleep(1)

        shell.send("enable\n")
        time.sleep(0.3)
        shell.send("undo smart\n")
        time.sleep(0.3)
        shell.send("scroll 512\n")
        time.sleep(0.3)

        if shell.recv_ready():
            shell.recv(65535)

        shell.send(f"display mac-address all | include {huawei_mac}\n")
        time.sleep(2.5)

        output = ""
        while shell.recv_ready():
            output += shell.recv(65535).decode('utf-8', errors='ignore')

        match = re.search(r'gpon\s+[\w\-]+\s+\w+\s+(\d+)\s*\/\s*(\d+)\s*\/\s*(\d+)\s+(\d+)', output)
        if not match:
            ssh.close()
            return None

        frame, slot, port, ont_id = match.groups()

        shell.send("config\n")
        time.sleep(0.3)
        shell.send(f"interface gpon {frame}/{slot}\n")
        time.sleep(0.3)
        shell.send(f"display ont optical-info {port} {ont_id}\n")
        time.sleep(2)

        opt_output = ""
        while shell.recv_ready():
            opt_output += shell.recv(65535).decode('utf-8', errors='ignore')

        ssh.close()

        rx_ont = re.search(r'Rx optical power\(dBm\)\s*:\s*([\-\d\.]+)', opt_output)
        tx_ont = re.search(r'Tx optical power\(dBm\)\s*:\s*([\-\d\.]+)', opt_output)
        rx_olt = re.search(r'OLT Rx ONT optical power\(dBm\)\s*:\s*([\-\d\.]+)', opt_output)

        return {
            "status": "success",
            "olt_name": olt['name'],
            "mac": huawei_mac,
            "location": f"[{olt['name']}] Frame {frame}/Slot {slot}/Port {port} (ONT: {ont_id})",
            "rx_ont": rx_ont.group(1) if rx_ont else "N/A",
            "tx_ont": tx_ont.group(1) if tx_ont else "N/A",
            "rx_olt": rx_olt.group(1) if rx_olt else "N/A"
        }
    except Exception:
        return None

def query_vsol_olt(olt, raw_mac):
    vsol_mac = format_mac_vsol(raw_mac)
    if not vsol_mac:
        return None

    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

    try:
        ssh.connect(olt['ip'], username=olt['user'], password=olt['pass'], timeout=8)
        shell = ssh.invoke_shell()
        time.sleep(1)

        if shell.recv_ready():
            shell.recv(65535)

        shell.send("enable\n")
        time.sleep(0.5)

        enable_pass = olt.get("enable_pass", olt['pass'])
        shell.send(f"{enable_pass}\n")
        time.sleep(0.5)

        shell.send("configure terminal\n")
        time.sleep(0.5)

        if shell.recv_ready():
            shell.recv(65535)

        shell.send(f"show mac address-table | include {vsol_mac}\n")
        time.sleep(2.5)

        output = ""
        while shell.recv_ready():
            output += shell.recv(65535).decode('utf-8', errors='ignore')

        match = re.search(r'GPON\s+([\d\/]+):(\d+)', output, re.IGNORECASE)
        if not match:
            ssh.close()
            return None

        gpon_port, ont_id = match.groups()

        shell.send(f"interface gpon {gpon_port}\n")
        time.sleep(0.5)
        shell.send(f"show onu {ont_id} optical_info\n")
        time.sleep(2.5)

        opt_output = ""
        while shell.recv_ready():
            opt_output += shell.recv(65535).decode('utf-8', errors='ignore')

        ssh.close()

        rx_ont = re.search(r'Rx optical level\(ONU\)\s*:\s*([\-\d\.]+)', opt_output)
        tx_ont = re.search(r'Tx optical level\s*:\s*([\-\d\.]+)', opt_output)
        rx_olt = re.search(r'Rx optical level\(OLT\)\s*:\s*([\-\d\.]+)', opt_output)

        return {
            "status": "success",
            "olt_name": olt['name'],
            "mac": vsol_mac,
            "location": f"[{olt['name']}] GPON {gpon_port} (ONT: {ont_id})",
            "rx_ont": rx_ont.group(1) if rx_ont else "N/A",
            "tx_ont": tx_ont.group(1) if tx_ont else "N/A",
            "rx_olt": rx_olt.group(1) if rx_olt else "N/A"
        }
    except Exception:
        return None

def find_mac_across_olts(raw_mac):
    for olt in OLT_LIST:
        if olt['type'] == 'huawei':
            result = query_huawei_olt(olt, raw_mac)
        elif olt['type'] == 'vsol':
            result = query_vsol_olt(olt, raw_mac)
        else:
            result = None

        if result:
            return result

    return None

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description="Multi-OLT Signal Checker CLI")
    parser.add_argument("--mac", help="MAC address")
    parser.add_argument("--user", help="Zalpro Username")
    parser.add_argument("--json", action="store_true", help="Output JSON format")

    args = parser.parse_args()

    raw_mac = None
    if args.user:
        raw_mac = get_mac_from_zalpro(args.user)
    elif args.mac:
        raw_mac = args.mac

    if not raw_mac:
        res = {"status": "error", "message": "User MAC not found in DB"}
        print(json.dumps(res) if args.json else res["message"])
        sys.exit(1)

    data = find_mac_across_olts(raw_mac)

    if data:
        if args.json:
            print(json.dumps(data))
        else:
            print("\n================ RESULT ================")
            print(f"Matched OLT     : {data['olt_name']}")
            print(f"Target MAC      : {data['mac']}")
            print(f"GPON Location   : {data['location']}")
            print(f"ONU Rx Power    : {data['rx_ont']} dBm")
            print(f"ONU Tx Power    : {data['tx_ont']} dBm")
            print(f"OLT Rx Power    : {data['rx_olt']} dBm")
            print("========================================\n")
    else:
        res = {"status": "error", "message": f"MAC {raw_mac} not found on any active OLT"}
        print(json.dumps(res) if args.json else res["message"])