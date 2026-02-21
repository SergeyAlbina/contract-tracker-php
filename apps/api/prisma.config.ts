import fs from 'node:fs';
import path from 'node:path';
import { defineConfig } from 'prisma/config';

function resolveDatabaseUrl() {
  if (process.env.DATABASE_URL) {
    return process.env.DATABASE_URL;
  }

  const envFilePath = path.join(__dirname, '.env');
  if (!fs.existsSync(envFilePath)) {
    return '';
  }

  const line = fs
    .readFileSync(envFilePath, 'utf8')
    .split(/\r?\n/)
    .find((entry) => entry.startsWith('DATABASE_URL='));

  if (!line) {
    return '';
  }

  return line.slice('DATABASE_URL='.length).trim().replace(/^"(.*)"$/, '$1');
}

export default defineConfig({
  schema: path.join(__dirname, 'prisma', 'schema.prisma'),
  datasource: {
    url: resolveDatabaseUrl(),
  },
});
