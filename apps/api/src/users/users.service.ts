import { ConflictException, Injectable, NotFoundException } from '@nestjs/common';
import { User, UserRole } from '../generated/prisma/client';
import * as bcrypt from 'bcrypt';
import { UsersRepository } from './users.repository';
import { CreateUserDto } from './dto/create-user.dto';
import { UpdateUserDto } from './dto/update-user.dto';
import { UserResponseDto } from './dto/user-response.dto';

@Injectable()
export class UsersService {
  constructor(private repo: UsersRepository) {}

  async findAll(): Promise<UserResponseDto[]> {
    const users = await this.repo.findAll();
    return users.map(this.toDto);
  }

  async findById(id: string): Promise<UserResponseDto> {
    const user = await this.repo.findById(id);
    if (!user) throw new NotFoundException(`Пользователь ${id} не найден`);
    return this.toDto(user);
  }

  async create(dto: CreateUserDto): Promise<UserResponseDto> {
    const existing = await this.repo.findByEmail(dto.email);
    if (existing) throw new ConflictException('Email уже занят');

    const passwordHash = await bcrypt.hash(dto.password, 10);
    const user = await this.repo.create({
      email: dto.email,
      passwordHash,
      fullName: dto.fullName,
      role: (dto.role ?? 'SPECIALIST_CS') as UserRole,
    });
    return this.toDto(user);
  }

  async update(id: string, dto: UpdateUserDto): Promise<UserResponseDto> {
    await this.findById(id);
    const user = await this.repo.update(id, {
      fullName: dto.fullName,
      role: dto.role as unknown as UserRole | undefined,
      isActive: dto.isActive,
    });
    return this.toDto(user);
  }

  async deactivate(id: string): Promise<void> {
    await this.findById(id);
    await this.repo.update(id, { isActive: false });
  }

  // ─── Mapper ───────────────────────────────────────────────────────────────

  private toDto(user: User): UserResponseDto {
    return {
      id: user.id,
      email: user.email,
      fullName: user.fullName,
      role: user.role as unknown as import('@ct/shared').UserRole,
      isActive: user.isActive,
      createdAt: user.createdAt,
      updatedAt: user.updatedAt,
    };
  }
}
