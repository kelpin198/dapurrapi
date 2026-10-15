import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: [
                "app/**",
                "config/**",
                "database/seeders/**",
                "lang/**",
                "resources/lang/**",
                "resources/views/**",
                "routes/**",
            ],
        }),
        tailwindcss(),
    ],
    server: {
        origin: "https://earn-injection-fraction-captain.trycloudflare.com",
        allowedHosts: [".trycloudflare.com"],
        hmr: {
            host: "earn-injection-fraction-captain.trycloudflare.com",
            protocol: "wss",
            clientPort: 443,
        },
        cors: {
            origin: [
                "http://apk-sederhana.test",
                /^https:\/\/.*\.trycloudflare\.com$/,
            ],
        },
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
    },
});
