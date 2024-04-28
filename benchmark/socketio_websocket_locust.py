import time
from locust import User, task, events
import socketio


class MySocketIOUser(User):
    max_messages = 20

    def on_start(self):
        self.ws = socketio.Client()
        self.ws.on('message', self.on_message)
        self.ws.connect('ws://127.0.0.1:8080')
        self.messages_sent = 0
        self.messages_received = 0
        self.waiting_for_response = False

    def on_stop(self):
        self.ws.disconnect()

    @task
    def my_task(self):
        if not self.ws.connected or self.messages_sent >= self.max_messages:
            return

        # Wait for response from previous message before sending a new one
        if not self.waiting_for_response:
            self.send_message("test")
            self.messages_sent += 1
            self.waiting_for_response = True
        else:
            # Check if a response has been received
            if self.messages_received >= self.messages_sent:
                self.waiting_for_response = False

    def send_message(self, message):
        self.start_time = time.time()
        self.ws.emit("message", message)  # Adjust the event name if necessary
        events.request.fire(request_type="SocketIO Send",
                            name='send_message',
                            response_time=0,
                            response_length=len(message))

    def on_message(self, data):
        self.messages_received += 1
        end_time = time.time()
        response_time = int((end_time - self.start_time) * 1000)

        events.request.fire(request_type="SocketIO Recv",
                            name='receive_message',
                            response_time=response_time,
                            response_length=len(data))
