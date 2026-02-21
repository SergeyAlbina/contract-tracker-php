import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { IsDateString, IsDecimal, IsOptional, IsString, MinLength } from 'class-validator';

export class CreateProposalDto {
  @ApiProperty()
  @IsString()
  @MinLength(2)
  supplierName: string;

  @ApiProperty({ example: '1500000.00' })
  @IsDecimal({ decimal_digits: '0,2' })
  offeredAmount: string;

  @ApiPropertyOptional()
  @IsString()
  @IsOptional()
  notes?: string;

  @ApiPropertyOptional()
  @IsDateString()
  @IsOptional()
  submittedAt?: string;
}
