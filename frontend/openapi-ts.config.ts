import { defineConfig } from '@hey-api/openapi-ts';

export default defineConfig({
    input: 'http://localhost:8000/api/docs.jsonopenapi', // nebo cesta k tvé OpenAPI specifikaci
    output: 'types/api',
    plugins: [
        {
            name: '@hey-api/client-nuxt',
            runtimeConfigPath: './types/hey-api.ts'
        }
    ],
});
