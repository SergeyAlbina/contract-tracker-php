import { ApiPropertyOptional } from '@nestjs/swagger';
import { IsDateString, IsDecimal, IsEnum, IsOptional, IsString } from 'class-validator';
import { StageStatus } from '@ct/shared';

export class UpdateStageDto {
  @ApiPropertyOptional()
  @IsString()
  @IsOptional()
  title?: string;

  @ApiPropertyOptional({ enum: StageStatus })
  @IsEnum(StageStatus)
  @IsOptional()
  status?: StageStatus;

  @ApiPropertyOptional()
  @IsDateString()
  @IsOptional()
  plannedStart?: string;

  @ApiPropertyOptional()
  @IsDateString()
  @IsOptional()
  plannedEnd?: string;

  @ApiPropertyOptional()
  @IsDateString()
  @IsOptional()
  actualStart?: string;

  @ApiPropertyOptional()
  @IsDateString()
  @IsOptional()
  actualEnd?: string;

  @ApiPropertyOptional()
  @IsDecimal({ decimal_digits: '0,2' })
  @IsOptional()
  amount?: string;

  @ApiPropertyOptional()
  @IsString()
  @IsOptional()
  description?: string;
}
