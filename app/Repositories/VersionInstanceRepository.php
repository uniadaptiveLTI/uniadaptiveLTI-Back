<?php

namespace App\Repositories;

use App\Models\Version;

class VersionInstanceRepository
{

    /**
     * @param string $id
     * 
     * @return Version
     */
    public function getVersionsByMapId(string $id): Version
    {
        return Version::selectRaw('created_id as id, map_id, name')
            ->where('map_id', $id)
            ->get();
    }
    /**
     * @param string $id
     * 
     * @return Version
     */
    public function getVersionByCreatedId(string $id): Version
    {
        return Version::selectRaw('created_id as id, map_id, name, blocks_data')
            ->where('created_id', $id)
            ->first();
    }
    public function getMoodleSession()
    {
    }
    public function getSakaiSession()
    {
    }
}
