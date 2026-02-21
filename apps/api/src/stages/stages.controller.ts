import {
  Controller,
  Get,
  Post,
  Patch,
  Delete,
  Body,
  Param,
  HttpCode,
  HttpStatus,
  UseGuards,
} from '@nestjs/common';
import { ApiTags, ApiOperation, ApiBearerAuth } from '@nestjs/swagger';
import { StagesService } from './stages.service';
import { CreateStageDto } from './dto/create-stage.dto';
import { UpdateStageDto } from './dto/update-stage.dto';
import { StageResponseDto } from './dto/stage-response.dto';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';

@ApiTags('stages')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller('contracts/:contractId/stages')
export class StagesController {
  constructor(private service: StagesService) {}

  @Get()
  @ApiOperation({ summary: 'Этапы контракта' })
  findAll(@Param('contractId') contractId: string): Promise<StageResponseDto[]> {
    return this.service.findByContract(contractId);
  }

  @Post()
  @ApiOperation({ summary: 'Добавить этап' })
  create(
    @Param('contractId') contractId: string,
    @Body() dto: CreateStageDto,
  ): Promise<StageResponseDto> {
    return this.service.create(contractId, dto);
  }

  @Patch(':id')
  @ApiOperation({ summary: 'Обновить этап' })
  update(
    @Param('contractId') contractId: string,
    @Param('id') id: string,
    @Body() dto: UpdateStageDto,
  ): Promise<StageResponseDto> {
    return this.service.update(contractId, id, dto);
  }

  @Delete(':id')
  @HttpCode(HttpStatus.NO_CONTENT)
  @ApiOperation({ summary: 'Удалить этап' })
  remove(@Param('contractId') contractId: string, @Param('id') id: string): Promise<void> {
    return this.service.remove(contractId, id);
  }
}
