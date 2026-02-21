import { PrismaClient, UserRole } from '@prisma/client';
import * as bcrypt from 'bcrypt';

const prisma = new PrismaClient();

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
