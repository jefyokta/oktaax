//Oktaax ws client example

const ws :  WebSocket = new WebSocket("ws://127.0.0.1:8000")

type Message = {
    event : string;
    message: any
}

ws.onmessage = (event)=>{

    console.log(event.data);
    
}

const msg : Message = {
    event:"even",
    message:"Hallo Server, pls say i love you to every even fd!"
}

ws.send(JSON.stringify(msg));