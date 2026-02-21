import { Injectable } from '@nestjs/common';
import { User, UserRole } from '@prisma/client';
import { PrismaService } from '../prisma/prisma.service';

@Injectable()
export class UsersRepository {
  constructor(private prisma: PrismaService) {}

  findAll(): Promise<User[]> {
    return this.prisma.user.findMany({ orderBy: { createdAt: 'desc' } });
  }

  findById(id: string): Promise<User | null> {
    return this.prisma.user.findUnique({ where: { id } });
  }

  findByEmail(email: string): Promise<User | null> {
    return this.prisma.user.findUnique({ where: { email } });
  }

  create(data: {
    email: string;
    passwordHash: string;
    fullName: string;
    role: UserRole;
  }): Promise<User> {
    return this.prisma.user.create({ data });
  }

  update(
    id: string,
    data: Partial<{ fullName: string; role: UserRole; isActive: boolean }>,
  ): Promise<User> {
    return this.prisma.user.update({ where: { id }, data });
  }
}
