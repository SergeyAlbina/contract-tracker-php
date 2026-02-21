import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import {
  IsDateString,
  IsDecimal,
  IsEnum,
  IsOptional,
  IsString,
  MinLength,
  ValidateIf,
} from 'class-validator';
import { LawType } from '@ct/shared';

export class CreateContractDto {
  @ApiProperty({ example: 'КД-2024-001' })
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

  @ApiProperty()
  @IsString()
  @MinLength(2)
  supplierName: string;

  @ApiPropertyOptional()
  @IsString()
  @IsOptional()
  supplierInn?: string;

  @ApiProperty({ example: '5000000.00' })
  @IsDecimal({ decimal_digits: '0,2' })
  totalAmount: string;

  @ApiPropertyOptional({ description: 'Обязательно для 44-ФЗ' })
  @ValidateIf((o) => o.lawType === LawType.LAW_44)
  @IsDecimal({ decimal_digits: '0,2' })
  nmckAmount?: string;

  @ApiPropertyOptional()
  @IsDateString()
  @IsOptional()
  signedAt?: string;

  @ApiPropertyOptional()
  @IsDateString()
  @IsOptional()
  startDate?: string;

  @ApiPropertyOptional()
  @IsDateString()
  @IsOptional()
  endDate?: string;

  @ApiPropertyOptional()
  @IsString()
  @IsOptional()
  description?: string;

  @ApiPropertyOptional({ description: 'Привязать к закупке' })
  @IsString()
  @IsOptional()
  procurementId?: string;
}
