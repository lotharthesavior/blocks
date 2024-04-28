from locust import User, task, between, events
import websocket
import _thread
import time
import json


class WebSocketUser(User):
    max_messages = 20

    def on_start(self):
        self.msg_count = 0
        self.received_count = 0
        self.ws = websocket.create_connection(
            "ws://127.0.0.1:8080", timeout=None)
        _thread.start_new_thread(self.receive_message, ())

    def on_stop(self):
        if hasattr(self, 'ws') and self.ws:
            self.ws.close()

    def receive_message(self):
        while True:
            if self.ws.connected and self.received_count < self.max_messages:
                result = self.ws.recv()
                self.received_count += 1
                print(f"Received message: {result}")
                parsed_data = json.loads(result)
                end_time = time.time()
                start_time = float(parsed_data['message'])
                response_time = int((end_time - start_time) * 1000)
                events.request.fire(request_type="WebSocket Recv",
                                    name='receive_message',
                                    response_time=response_time,
                                    response_length=len(result))
            else:
                break

    @task
    def send_message(self):
        if self.ws.connected and self.msg_count < self.max_messages:
            start_time = time.time()
            self.ws.send(str(start_time))
            self.msg_count += 1
            events.request.fire(request_type="WebSocket Send",
                                name='send_message',
                                response_time=0,
                                response_length=0)
