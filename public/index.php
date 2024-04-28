<!DOCTYPE html>
<html>
<head>
    <title>Sample Socket Client UI</title>
</head>
<body>

<div class="container">

    <h1>WebSocket Client: <span id="name">-</span></h1>
    <form id="message-form">
        <div>
            <span id="ws-status">-</span>
            <input type="button" onclick="instance.connectToServer()" value="Connect" />
            <input type="button" onclick="instance.disconnect()" value="Disconnect" />
        </div>
        <div>Monitor: <span id="server-health"></span></div>
        <div>User Count: <span id="user-count"></span></div>
        <div>
            <textarea id="message-box"></textarea>
        </div>
        <div>
            <input type="submit" />
        </div>
    </form>
    <hr/>
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
                this.heartBeatInterval = null;
            }

            init() {
                this.connectToServer();
                this.listenEvents();
            }

            listenEvents() {
                document.getElementById('message-form')
                    .addEventListener("submit", this.handleFormSubmit.bind(this), false);
                document.getElementById('message-box')
                    .addEventListener('keyup', (e) => {
                        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                            this.handleFormSubmit(e);
                        }
                    });
            }

            connectToServer() {
                if (this.ws !== null) {
                    console.log('Existing connection found. Disconnecting first.');
                    return;
                }

                const urlSearchParams = new URLSearchParams(window.location.search);
                const token = urlSearchParams.get('token');

                const wsServer = `${this.config.uri}:${this.config.port}?token=${token}`;
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

            handleFormSubmit(e) {
                e.preventDefault();
                this.ws.send(document.getElementById('message-box').value);
            }

            handleIncomingMessage(data) {
                const parsedData = JSON.parse(data);
                console.log(parsedData);
                switch (parsedData.event) {
                    // {event: 'message', fd: 1, message: 'Hello World'}
                    case 'message':
                        this.printMessage(parsedData.fd, parsedData.message);
                        break;

                    // {event: 'server-health', message: 100}
                    case 'server-health':
                        this.showServerHealth(parsedData.message);
                        break;

                    // {event: 'welcome', name: 'John Doe'}
                    case 'welcome':
                        this.displayName(parsedData.name);
                        break;

                    // {event: 'presence', users: {1: 'John', 2: 'Doe'}, count: 2}
                    case 'presence':
                        this.updatePresence(parsedData.message);
                        break;

                    // $_ENV['BOT_START']
                    // {event: 'bot-start', interaction: 'Hello World'}
                    case 'bot-start':
                        this.startBotMessage(parsedData.interaction);
                        break;

                    // {event: 'bot-chunk', message: 'Hello World', interaction: 'Hello World'}
                    case 'bot-chunk':
                        this.printBotMessage(parsedData.message, parsedData.interaction);
                        break;

                    // $_ENV['HARD_STOP']
                    // {event: 'bot-stop', interaction: 'Hello World'}
                    case 'bot-stop':
                        this.finishBotMessage(parsedData.interaction);
                        break;
                }
            }

            printMessage(fd, message) {
                const input = document.createElement("li");
                input.innerText = this.presence[fd] ? `${this.presence[fd]}: ${message}` : message;
                document.getElementById('output').appendChild(input);
            }

            startBotMessage(interaction) {
                const input = document.createElement("li");
                input.id = interaction;
                input.append('Jenkins: ');
                document.getElementById('output').appendChild(input);
            }

            printBotMessage(message, interaction) {
                const input = document.getElementById(interaction);
                input.append(message);
            }

            finishBotMessage(interaction) {
                // Implementation depends on requirements
            }

            showServerHealth(message) {
                const serverHealth = document.getElementById('server-health');
                serverHealth.innerText = `${message}%`;
                serverHealth.style.color = message < 50 ? 'red' : 'green';
            }

            displayName(name) {
                document.getElementById('name').innerText = name;
            }

            updatePresence(message) {
                document.getElementById('user-count').innerText = message.count;
                this.presence = message.users;
            }

            changeStatus(status) {
                const wsStatus = document.getElementById('ws-status');
                wsStatus.innerText = status ? 'Connected' : 'Disconnected';
                wsStatus.style.color = status ? 'green' : 'red';

                if (!status) {
                    document.getElementById('user-count').innerText = '0';
                    const serverHealth = document.getElementById('server-health');
                    serverHealth.style.color = 'black';
                    serverHealth.innerText = '-';
                }
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
