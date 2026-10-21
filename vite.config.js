import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: [
                "app/**",
                "bootstrap/**",
                "config/**",
                "database/migrations/**",
                "database/seeders/**",
                "lang/**",
                "public/images/**",
                "resources/lang/**",
                "resources/views/**",
                "routes/**",
                "storage/app/private/restaurant-profile/**",
            ],
        }),
        tailwindcss(),
    ],
    server: {
        host: "0.0.0.0",
        origin: "http://localhost:5173",
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
