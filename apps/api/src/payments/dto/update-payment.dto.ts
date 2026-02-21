import { ApiPropertyOptional } from '@nestjs/swagger';
import { IsDateString, IsDecimal, IsEnum, IsOptional, IsString } from 'class-validator';
import { PaymentStatus } from '@ct/shared';

export class UpdatePaymentDto {
  @ApiPropertyOptional()
  @IsDecimal({ decimal_digits: '0,2' })
  @IsOptional()
  amount?: string;

  @ApiPropertyOptional({ enum: PaymentStatus })
  @IsEnum(PaymentStatus)
  @IsOptional()
  status?: PaymentStatus;

  @ApiPropertyOptional()
  @IsDateString()
  @IsOptional()
  plannedAt?: string;

  @ApiPropertyOptional()
  @IsDateString()
  @IsOptional()
  paidAt?: string;

  @ApiPropertyOptional()
  @IsString()
  @IsOptional()
  reference?: string;

  @ApiPropertyOptional()
  @IsString()
  @IsOptional()
  notes?: string;
}
