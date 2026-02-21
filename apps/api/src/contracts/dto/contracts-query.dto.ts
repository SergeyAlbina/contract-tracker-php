import { ApiPropertyOptional } from '@nestjs/swagger';
import { IsEnum, IsOptional, IsString } from 'class-validator';
import { ContractStatus, LawType } from '@ct/shared';
import { PaginationQueryDto } from '../../common/dto/pagination-query.dto';

export class ContractsQueryDto extends PaginationQueryDto {
  @ApiPropertyOptional({ enum: ContractStatus })
  @IsEnum(ContractStatus)
  @IsOptional()
  status?: ContractStatus;

  @ApiPropertyOptional({ enum: LawType })
  @IsEnum(LawType)
  @IsOptional()
  lawType?: LawType;

  @ApiPropertyOptional({ description: 'Поиск по номеру, названию, поставщику' })
  @IsString()
  @IsOptional()
  search?: string;

  @ApiPropertyOptional()
  @IsString()
  @IsOptional()
  ownerId?: string;
}
