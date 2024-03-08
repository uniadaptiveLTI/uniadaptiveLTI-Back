<?php

namespace App\Repositories;

use App\Models\Map;

class MapInstanceRepository
{
    /**
     * @param string $createdId
     * 
     * @return Map
     */
    public function getMapByCreatedId(string $createdId): Map
    {
        return Map::where('created_id', $createdId)
            ->first();
    }
    /**
     * @param string $createdId
     * 
     * @return Map
     */
    public function getMapIdByCreatedId(string $createdId): Map
    {
        return Map::select('id')
            ->where('created_id', $createdId)
            ->first();
    }
}
