import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { IsDateString, IsEnum, IsOptional, IsString, MinLength } from 'class-validator';
import { LawType } from '@ct/shared';

export class CreateProcurementDto {
  @ApiProperty({ example: 'ЗП-2024-001' })
  @IsString()
  @MinLength(1)
  number: string;

  @ApiProperty()
  @IsString()
  @MinLength(3)
  title: string;

  @ApiProperty({ enum: LawType })
  @IsEnum(LawType)
  lawType: LawType;

  @ApiPropertyOptional()
  @IsString()
  @IsOptional()
  description?: string;

  @ApiPropertyOptional()
  @IsDateString()
  @IsOptional()
  plannedDate?: string;
}
