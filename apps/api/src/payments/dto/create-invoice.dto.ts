import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { IsDateString, IsDecimal, IsOptional, IsString, MinLength } from 'class-validator';

export class CreateInvoiceDto {
  @ApiPropertyOptional()
  @IsString()
  @IsOptional()
  stageId?: string;

  @ApiProperty()
  @IsString()
  @MinLength(1)
  number: string;

  @ApiProperty({ example: '250000.00' })
  @IsDecimal({ decimal_digits: '0,2' })
  amount: string;

  @ApiProperty()
  @IsDateString()
  issuedAt: string;

  @ApiPropertyOptional()
  @IsDateString()
  @IsOptional()
  dueAt?: string;

  @ApiPropertyOptional()
  @IsString()
  @IsOptional()
  notes?: string;
}
