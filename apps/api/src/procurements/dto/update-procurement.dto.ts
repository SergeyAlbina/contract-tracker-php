import { ApiPropertyOptional } from '@nestjs/swagger';
import { IsDateString, IsEnum, IsOptional, IsString } from 'class-validator';
import { LawType, ProcurementStatus } from '@ct/shared';

export class UpdateProcurementDto {
  @ApiPropertyOptional()
  @IsString()
  @IsOptional()
  title?: string;

  @ApiPropertyOptional({ enum: LawType })
  @IsEnum(LawType)
  @IsOptional()
  lawType?: LawType;

  @ApiPropertyOptional({ enum: ProcurementStatus })
  @IsEnum(ProcurementStatus)
  @IsOptional()
  status?: ProcurementStatus;

  @ApiPropertyOptional()
  @IsString()
  @IsOptional()
  description?: string;

  @ApiPropertyOptional()
  @IsDateString()
  @IsOptional()
  plannedDate?: string;
}
