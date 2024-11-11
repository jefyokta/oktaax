import { defineConfig } from "vite";

import { viteOktaa } from "./okta-vite";

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
      port: 5173,
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
