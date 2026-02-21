import { Injectable, OnModuleInit, OnModuleDestroy } from '@nestjs/common';
import { PrismaMariaDb } from '@prisma/adapter-mariadb';
import { PrismaClient } from '../generated/prisma/client';

function buildDatabaseUrl(rawDatabaseUrl: string) {
  const databaseUrl = new URL(rawDatabaseUrl);

  if (!databaseUrl.searchParams.has('allowPublicKeyRetrieval')) {
    databaseUrl.searchParams.set('allowPublicKeyRetrieval', 'true');
  }

  if (!databaseUrl.searchParams.has('ssl')) {
    databaseUrl.searchParams.set('ssl', 'false');
  }

  return databaseUrl.toString();
}

@Injectable()
export class PrismaService extends PrismaClient implements OnModuleInit, OnModuleDestroy {
  constructor() {
    const databaseUrl = process.env.DATABASE_URL;
    if (!databaseUrl) {
      throw new Error('DATABASE_URL is not configured');
    }

    super({ adapter: new PrismaMariaDb(buildDatabaseUrl(databaseUrl)) });
  }

  async onModuleInit() {
    await this.$connect();
  }

  async onModuleDestroy() {
    await this.$disconnect();
  }
}
