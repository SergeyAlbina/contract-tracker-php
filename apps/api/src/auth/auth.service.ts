import { Injectable, UnauthorizedException } from '@nestjs/common';
import { JwtService } from '@nestjs/jwt';
import { ConfigService } from '@nestjs/config';
import { User } from '../generated/prisma/client';
import { UserRole } from '@ct/shared';
import * as bcrypt from 'bcrypt';
import { AuthRepository } from './auth.repository';
import { LoginDto } from './dto/login.dto';
import { TokenResponseDto } from './dto/token-response.dto';
import { MeResponseDto } from './dto/me-response.dto';
import { JwtPayload } from './strategies/jwt-access.strategy';

@Injectable()
export class AuthService {
  constructor(
    private repo: AuthRepository,
    private jwt: JwtService,
    private config: ConfigService,
  ) {}

  async login(dto: LoginDto): Promise<TokenResponseDto> {
    const user = await this.repo.findUserByEmail(dto.email);
    if (!user || !user.isActive) throw new UnauthorizedException('Неверные данные');

    const valid = await bcrypt.compare(dto.password, user.passwordHash);
    if (!valid) throw new UnauthorizedException('Неверные данные');

    return this.generateTokens(user.id, user.email, user.role);
  }

  async refresh(token: string): Promise<TokenResponseDto> {
    const record = await this.repo.findRefreshTokenWithUser(token);

    if (!record || record.expiresAt < new Date() || !record.user.isActive) {
      throw new UnauthorizedException('Токен недействителен');
    }

    await this.repo.deleteRefreshToken(record.id);
    return this.generateTokens(record.user.id, record.user.email, record.user.role);
  }

  async logout(token: string): Promise<void> {
    await this.repo.deleteRefreshTokensByValue(token);
  }

  async getMe(userId: string): Promise<MeResponseDto> {
    const user = await this.repo.findUserById(userId);
    if (!user) throw new UnauthorizedException();
    return this.toMeDto(user);
  }

  // ─── Приватные методы ──────────────────────────────────────────────────────

  private async generateTokens(
    userId: string,
    email: string,
    role: string,
  ): Promise<TokenResponseDto> {
    const payload: JwtPayload = { sub: userId, email, role };

    const accessTtl = Number(this.config.get('JWT_ACCESS_TTL_SECONDS', 900));
    const refreshTtl = Number(this.config.get('JWT_REFRESH_TTL_SECONDS', 2592000));

    const accessToken = this.jwt.sign(payload, {
      secret: this.config.getOrThrow('JWT_ACCESS_SECRET'),
      expiresIn: accessTtl,
    });

    const refreshToken = this.jwt.sign(payload, {
      secret: this.config.getOrThrow('JWT_REFRESH_SECRET'),
      expiresIn: refreshTtl,
    });

    await this.repo.saveRefreshToken({
      token: refreshToken,
      userId,
      expiresAt: new Date(Date.now() + refreshTtl * 1000),
    });

    return { accessToken, refreshToken };
  }

  private toMeDto(user: User): MeResponseDto {
    return {
      id: user.id,
      email: user.email,
      fullName: user.fullName,
      role: user.role as unknown as UserRole,
      isActive: user.isActive,
      createdAt: user.createdAt,
    };
  }
}
