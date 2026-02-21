import { PrismaMariaDb } from '@prisma/adapter-mariadb';
import * as bcrypt from 'bcrypt';
import fs from 'node:fs';
import path from 'node:path';
import { PrismaClient, UserRole } from '../src/generated/prisma/client';

function resolveDatabaseUrl() {
  if (process.env.DATABASE_URL) {
    return process.env.DATABASE_URL;
  }

  const envFilePath = path.join(__dirname, '..', '.env');
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

const databaseUrl = resolveDatabaseUrl();
if (!databaseUrl) {
  throw new Error('DATABASE_URL is not configured');
}

const prisma = new PrismaClient({
  adapter: new PrismaMariaDb(databaseUrl),
});

async function main() {
  const adminPassword = await bcrypt.hash('Admin1234!', 10);

  const admin = await prisma.user.upsert({
    where: { email: 'admin@contract-tracker.local' },
    update: {},
    create: {
      email: 'admin@contract-tracker.local',
      passwordHash: adminPassword,
      fullName: 'Администратор',
      role: UserRole.ADMIN,
    },
  });

  const head = await prisma.user.upsert({
    where: { email: 'head@contract-tracker.local' },
    update: {},
    create: {
      email: 'head@contract-tracker.local',
      passwordHash: await bcrypt.hash('Head1234!', 10),
      fullName: 'Руководитель КС',
      role: UserRole.HEAD_CS,
    },
  });

  console.log('Seed выполнен:', { admin: admin.email, head: head.email });
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
