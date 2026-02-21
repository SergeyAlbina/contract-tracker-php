import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { IsDateString, IsDecimal, IsEnum, IsOptional, IsString } from 'class-validator';
import { PaymentStatus } from '@ct/shared';

export class CreatePaymentDto {
  @ApiPropertyOptional()
  @IsString()
  @IsOptional()
  invoiceId?: string;

  @ApiProperty({ example: '250000.00' })
  @IsDecimal({ decimal_digits: '0,2' })
  amount: string;

  @ApiPropertyOptional({ enum: PaymentStatus, default: PaymentStatus.PLANNED })
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
