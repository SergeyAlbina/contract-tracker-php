import { Injectable } from '@nestjs/common';
import { User, RefreshToken } from '@prisma/client';
import { PrismaService } from '../prisma/prisma.service';

@Injectable()
export class AuthRepository {
  constructor(private prisma: PrismaService) {}

  findUserByEmail(email: string): Promise<User | null> {
    return this.prisma.user.findUnique({ where: { email } });
  }

  findUserById(id: string): Promise<User | null> {
    return this.prisma.user.findUnique({ where: { id } });
  }

  saveRefreshToken(data: {
    token: string;
    userId: string;
    expiresAt: Date;
  }): Promise<RefreshToken> {
    return this.prisma.refreshToken.create({ data });
  }

  findRefreshTokenWithUser(token: string) {
    return this.prisma.refreshToken.findUnique({
      where: { token },
      include: { user: true },
    });
  }

  deleteRefreshToken(id: string): Promise<RefreshToken> {
    return this.prisma.refreshToken.delete({ where: { id } });
  }

  deleteRefreshTokensByValue(token: string) {
    return this.prisma.refreshToken.deleteMany({ where: { token } });
  }
}
