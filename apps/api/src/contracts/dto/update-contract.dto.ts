import { ApiPropertyOptional } from '@nestjs/swagger';
import { IsDateString, IsDecimal, IsEnum, IsOptional, IsString } from 'class-validator';
import { ContractStatus, LawType } from '@ct/shared';

export class UpdateContractDto {
  @ApiPropertyOptional()
  @IsString()
  @IsOptional()
  title?: string;

  @ApiPropertyOptional({ enum: ContractStatus })
  @IsEnum(ContractStatus)
  @IsOptional()
  status?: ContractStatus;

  @ApiPropertyOptional({ enum: LawType })
  @IsEnum(LawType)
  @IsOptional()
  lawType?: LawType;

  @ApiPropertyOptional()
  @IsString()
  @IsOptional()
  supplierName?: string;

  @ApiPropertyOptional()
  @IsString()
  @IsOptional()
  supplierInn?: string;

  @ApiPropertyOptional()
  @IsDecimal({ decimal_digits: '0,2' })
  @IsOptional()
  totalAmount?: string;

  @ApiPropertyOptional()
  @IsDecimal({ decimal_digits: '0,2' })
  @IsOptional()
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
}
