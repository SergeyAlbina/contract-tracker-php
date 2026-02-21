import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { IsEmail, IsEnum, IsOptional, IsString, MinLength } from 'class-validator';
import { UserRole } from '@ct/shared';

export class CreateUserDto {
  @ApiProperty({ example: 'user@contract-tracker.local' })
  @IsEmail()
  email: string;

  @ApiProperty({ minLength: 8 })
  @IsString()
  @MinLength(8)
  password: string;

  @ApiProperty()
  @IsString()
  fullName: string;

  @ApiPropertyOptional({ enum: UserRole, default: UserRole.SPECIALIST_CS })
  @IsEnum(UserRole)
  @IsOptional()
  role?: UserRole;
}
