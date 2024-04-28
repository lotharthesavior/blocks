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
            <div>Bot Status: <span id="bot-status">-</span></div>
            <div>
                <textarea id="message-box"></textarea>
            </div>
            <div>
                <input type="submit" />
            </div>
        </form>

        <hr />

        <div>
            <span>Channel: channel1</span>
            <input onclick="instance.subscribeChannel('channel1')" type="button" value="Subscribe" />
            <input onclick="instance.unsubscribeChannel('channel1')" type="button" value="Unsubscribe" />
            <div>Connected to: <span id="channels-connected"></span></div>
        </div>

        <hr />

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
                    this.channelsElement = document.getElementById('channels-connected');
                    this.messageBoxElement = document.getElementById('message-box');
                    this.userCountElement = document.getElementById('user-count');
                    this.messageFormElement = document.getElementById('message-form');
                    this.outputElement = document.getElementById('output');
                    this.botStatus = document.getElementById('bot-status');

                    this.connectToServer();
                    this.listenEvents();
                }

                listenEvents() {
                    this.messageFormElement
                        .addEventListener("submit", this.handleFormSubmit.bind(this), false);

                    this.messageBoxElement
                        .addEventListener('keyup', (e) => {
                            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                                this.handleFormSubmit(e);
                            }
                        });
                }

                subscribeChannel(channel) {
                    this.ws.send(JSON.stringify({
                        event: 'subscribe',
                        data: {
                            channel: channel,
                        },
                    }));
                }

                unsubscribeChannel(channel) {
                    this.ws.send(JSON.stringify({
                        event: 'unsubscribe',
                        data: {
                            channel: channel,
                        },
                    }));
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

                    const messageText = this.messageBoxElement.value.trim();

                    if (messageText === '') {
                        console.log('No message to send');
                        return;
                    }

                    if (this.channelsSubscribed.length > 0) {
                        this.channelsSubscribed.forEach(channel => {
                            const messagePayload = JSON.stringify({
                                event: 'client_message',
                                data: {
                                    channel: channel,
                                    message: messageText,
                                }
                            });

                            this.ws.send(messagePayload);
                        });
                    } else {
                        console.log('Not subscribed to any channel. Cannot send message.');
                    }

                    this.messageBoxElement.value = '';
                }

                handleIncomingMessage(data) {
                    const parsedData = JSON.parse(data);
                    console.log("Received data:", parsedData);

                    switch (parsedData.event) {
                        // Subscription to channel confirmation
                        // { event: 'subscribed', data: { channel: 'channel1' } }
                        case 'subscribed':
                            console.log(`Successfully subscribed to channel: ${parsedData.data.channel}`);

                            if (this.channelsSubscribed.indexOf(parsedData.data.channel) === -1) {
                                this.channelsSubscribed.push(parsedData.data.channel);
                            }

                            this.channelsElement.innerText = this.channelsSubscribed.join(', ');

                            break;

                        // Unsubscription from channel confirmation
                        // { event: 'unsubscribed', data: { channel: 'channel1' } }
                        case 'unsubscribed':
                            console.log(`Successfully unsubscribed from channel: ${parsedData.data.channel}`);

                            this.channelsSubscribed = this.channelsSubscribed
                                .filter(channel => channel !== parsedData.data.channel);

                            this.channelsElement.innerText = this.channelsSubscribed.join(', ');

                            if (this.channelsSubscribed.length === 0) {
                                this.userCountElement.innerText = 0;
                            }

                            break;

                        // Server message
                        // { event: 'server_message', data: { message: string, channel: string, fd: int } }
                        case 'client_message':
                            this.printMessage('server', parsedData.data.message, parsedData.data.fd);
                            break;

                        // {event: 'presence', data: {users: {1: 'John', 2: 'Doe', count: 2}}}
                        case 'presence':
                            this.updatePresence(parsedData.data);
                            break;

                        // {event: 'server-health', message: '50'}
                        case 'server-health':
                            this.showServerHealth(parsedData.message);
                            break

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

                        // {event: 'bot-update', data: 'Bot is processing...'}
                        case 'bot-update':
                            this.printBotStatus(parsedData.data.message);
                            break;

                        default:
                            console.log(`Unhandled event type: ${parsedData.event}`);
                            break;
                    }
                }

                printMessage(source, message, fd) {
                    const input = document.createElement("li");
                    input.innerText = this.presence[fd] ? `${this.presence[fd]}: ${message}` : message;
                    this.outputElement.appendChild(input);
                }

                startBotMessage(interaction) {
                    const input = document.createElement("li");
                    input.id = interaction;
                    input.append('Jenkins: ');
                    this.outputElement.appendChild(input);
                }

                printBotMessage(message, interaction) {
                    const input = document.getElementById(interaction);
                    input.append(message);
                }

                finishBotMessage(interaction) {
                    // Implementation depends on requirements
                }

                printBotStatus(message) {
                    this.botStatus.innerText = message;
                }

                showServerHealth(message) {
                    const serverHealth = document.getElementById('server-health');
                    serverHealth.innerText = `${message}%`;
                    serverHealth.style.color = message < 50 ? 'red' : 'green';
                }

                displayName(name) {
                    document.getElementById('name').innerText = name;
                }

                updatePresence(data) {
                    this.userCountElement.innerText = data.count;
                    this.presence = data.users;
                }

                changeStatus(status) {
                    const wsStatus = document.getElementById('ws-status');
                    wsStatus.innerText = status ? 'Connected' : 'Disconnected';
                    wsStatus.style.color = status ? 'green' : 'red';

                    if (!status) {
                        this.userCountElement.innerText = '0';
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
