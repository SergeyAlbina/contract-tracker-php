import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { IsDateString, IsDecimal, IsOptional, IsString, MinLength } from 'class-validator';

export class CreateStageDto {
  @ApiProperty()
  @IsString()
  @MinLength(2)
  title: string;

  @ApiProperty()
  @IsDateString()
  plannedStart: string;

  @ApiProperty()
  @IsDateString()
  plannedEnd: string;

  @ApiPropertyOptional()
  @IsDecimal({ decimal_digits: '0,2' })
  @IsOptional()
  amount?: string;

  @ApiPropertyOptional()
  @IsString()
  @IsOptional()
  description?: string;
}
