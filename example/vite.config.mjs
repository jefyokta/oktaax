import { defineConfig } from "vite";

import { viteOktaa } from "./okta-vite";

//your php server publicDir
const publicDir = __dirname + "/public";

export default defineConfig({
  server: {
    host: "localhost",
    port: 5173,
    proxy: {
      "/build": "http://localhost:5173",
    },
    hmr: {
      protocol: "ws",
      host: "localhost",
      port: 5173, //if you want to change the host or port, you also have to change the default host in bladedirective class
    },
  },
  build: {
    outDir: "./public/build",
    manifest: true,
    rollupOptions: {
      input: {
        app: "resources/js/app.js",
        css: "resources/css/app.css",
      },
    },
  },
  plugins: [viteOktaa(publicDir)],
});
