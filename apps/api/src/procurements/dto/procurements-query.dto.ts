import { ApiPropertyOptional } from '@nestjs/swagger';
import { IsEnum, IsOptional, IsString } from 'class-validator';
import { LawType, ProcurementStatus } from '@ct/shared';
import { PaginationQueryDto } from '../../common/dto/pagination-query.dto';

export class ProcurementsQueryDto extends PaginationQueryDto {
  @ApiPropertyOptional({ enum: ProcurementStatus })
  @IsEnum(ProcurementStatus)
  @IsOptional()
  status?: ProcurementStatus;

  @ApiPropertyOptional({ enum: LawType })
  @IsEnum(LawType)
  @IsOptional()
  lawType?: LawType;

  @ApiPropertyOptional({ description: 'Поиск по номеру или названию' })
  @IsString()
  @IsOptional()
  search?: string;
}
