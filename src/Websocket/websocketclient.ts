// Browser client for examples/guide/serve.php websocket.
const socket = new WebSocket('ws://127.0.0.1:3000/socket');

type SocketMessage<T = unknown> = {
    event: string;
    data?: T;
    id?: string;
};

socket.onopen = () => {
    const message: SocketMessage<string> = {
        event: 'echo',
        data: 'Hello from the browser',
        id: 'example-1',
    };
    socket.send(JSON.stringify(message));
};
socket.onmessage = ({ data }) => {
    const message = JSON.parse(data) as SocketMessage;
    console.log(message.event, message.data, message.id);
};
socket.onclose = () => console.log('Disconnected');
