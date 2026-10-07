<?php

namespace App\Http\Resources;

use App\UseCases\Rack\Create\CreateRackItemOutput;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreateRackItemOutput
 */
class RackResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'levels_count' => $this->levelsCount,
            'cells_per_level' => $this->cellsPerLevel,
            'cells' => CellResource::collection($this->cells),
        ];
    }
}
