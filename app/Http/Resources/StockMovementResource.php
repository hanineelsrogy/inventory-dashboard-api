<?php

namespace App\Http\Resources;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'change' => $this->change,
            'stock_before' => $this->stock_before,
            'stock_after' => $this->stock_after,
            'reason' => $this->reason,
            'created_at' => $this->created_at,
        ];
    }
}
