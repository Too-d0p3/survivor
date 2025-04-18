
import type { CodegenConfig } from '@graphql-codegen/cli';

const config: CodegenConfig = {
  overwrite: true,
  schema: "http://localhost:8000/api/graphql",
  documents: "./graphql/**/*.graphql",
  generates: {
    "./types/graphql.ts": {
      preset: "client",
      plugins: []
    }
  }
};

export default config;
