<!DOCTYPE html>
<html>

<head>
    <title>Sample Socket Client UI</title>
</head>

<body>

    <div class="container">

        <h1>WebSocket Client: <span id="name">-</span></h1>
        <div>
            <span id="ws-status">-</span>
            <input type="button" onclick="instance.connectToServer()" value="Connect" />
            <input type="button" onclick="instance.disconnect()" value="Disconnect" />
        </div>
        <br/>
        <div><input id="ping" type="button" value="Ping" /></div>
        <ul id="output"></ul>

    </div>

    <script type="text/javascript">
        const instance = (function() {
            class App {
                constructor(port) {
                    this.ws = null;
                    this.config = {
                        uri: 'ws://127.0.0.1',
                        port: port,
                    };
                    this.presence = {};
                    this.heartBeatInterval = 4000;
                    this.channelsSubscribed = [];
                }

                init() {
                    this.pingElement = document.getElementById('ping');
                    this.outputElement = document.getElementById('output');
                    this.wsStatus = document.getElementById('ws-status');

                    this.connectToServer();
                    this.listenEvents();
                }

                listenEvents() {
                    this.pingElement
                        .addEventListener("click", this.handlePing.bind(this), false);
                }

                connectToServer() {
                    if (this.ws !== null) {
                        console.log('Existing connection found. Disconnecting first.');
                        return;
                    }

                    const wsServer = `${this.config.uri}:${this.config.port}`;
                    this.ws = new WebSocket(wsServer);

                    this.ws.onopen = () => {
                        console.log("Connected to WebSocket server.");
                        this.changeStatus(true);
                        if (this.heartBeatInterval) this.startHeartBeat();
                    };

                    this.ws.onclose = () => {
                        console.log("Disconnected");
                        this.changeStatus(false);
                        if (this.heartBeatInterval) clearInterval(this.heartBeatInterval);
                        this.ws = null;
                    };

                    this.ws.onmessage = (evt) => {
                        console.log('Retrieved data from server: ' + evt.data);
                        this.handleIncomingMessage(evt.data);
                    };

                    this.ws.onerror = (evt) => {
                        console.log('Error occurred: ' + evt.data);
                    };
                }

                disconnect() {
                    if (this.ws === null) {
                        return;
                    }

                    this.ws.close();
                }

                handlePing(e) {
                    e.preventDefault();
                    this.ws.send('ping');
                }

                handleIncomingMessage(data) {
                    console.log("Received data:", data);
                    this.printMessage(data);
                }

                printMessage(message) {
                    const input = document.createElement("li");
                    input.innerText = message;
                    this.outputElement.appendChild(input);
                }

                changeStatus(status) {
                    this.wsStatus.innerText = status ? 'Connected' : 'Disconnected';
                    this.wsStatus.style.color = status ? 'green' : 'red';
                }

                startHeartBeat() {
                    this.heartBeatInterval = setInterval(() => {
                        const pingFrame = new Uint8Array(2);
                        pingFrame[0] = 0x89;
                        pingFrame[1] = 0x00;
                        this.ws.send(pingFrame);
                    }, 5000);
                }
            }

            const app = new App(<?php echo $port; ?>);
            app.init();

            return app;
        })();
    </script>

</body>

</html>
