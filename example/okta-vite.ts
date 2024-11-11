import path from "path";
import fs from "fs";

export const viteOktaa = (publicDir: string): OktaVite => {
  const configFilePath: string = path.resolve(`${publicDir}/vite-okta`);

  const cleanUp = ():void => {
    if (fs.existsSync(configFilePath)) {
      fs.unlinkSync(configFilePath);
    }
  };

  process.on("SIGINT", () => {
    cleanUp();
    process.exit();
  });

  process.on("exit", cleanUp);

  return {
    name: "vite-okta",
    configureServer(server) {
      const { host, port } = server.config.server;
      fs.writeFileSync(configFilePath, `http://${host}:${port}`, "utf-8");
    },
  };
};



type OktaVite = {
  name: string;
  configureServer: (server: viteServer) => void;
};


type viteServer={
 config:{
  server:{
    host:string,
    port:number,
    [key:string]:any
  },
  [key:string]:any
 },
 [key:string]:any

}
