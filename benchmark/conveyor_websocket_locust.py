from locust import User, task, events
import websocket
import _thread
import time
import json


class WebSocketUser(User):
    max_messages = 20
    # max_messages = 1

    initial_time = None

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
        while self.received_count < self.max_messages - 1:
            result = self.ws.recv()
            parsed_result = json.loads(result)

            while self.initial_time == None:
                time.sleep(0.1)
            temp = self.initial_time
            self.initial_time = None

            # print(parsed_result)
            # if parsed_result['action'] != 'base-action':
            #     return

            self.received_count += 1

            response_time = int(
                # (time.time() - float(parsed_result['data'])) * 1000)
                (time.time() - temp) * 1000)
            events.request.fire(request_type="WebSocket Recv",
                                name='receive_message',
                                response_time=response_time,
                                response_length=len(result))

    @task
    def send_message(self):
        if self.msg_count < self.max_messages:
            self.initial_time = time.time()
            self.ws.send(str(time.time()))
            self.msg_count += 1
            events.request.fire(request_type="WebSocket Send",
                                name='send_message',
                                response_time=0,
                                response_length=0)
