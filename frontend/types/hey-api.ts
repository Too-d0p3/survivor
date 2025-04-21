import type { CreateClientConfig } from './api/client.gen';

export const createClientConfig: CreateClientConfig = (config: any) => ({
    ...config,
    baseURL: 'http://localhost:8000/',
});