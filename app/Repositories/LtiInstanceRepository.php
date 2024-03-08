<?php

namespace App\Repositories;

use App\Models\LtiInfo;

class LtiInstanceRepository
{

    /**
     * @param string $token
     * 
     * @return object
     */
    public function getLtiInfoByToken(string $token): object
    {
        $ltiInfo = LtiInfo::where('token', '=', $token)
            ->first();
        return $ltiInfo;
    }

    /**
     * @param LtiInfo $ltiInfo
     * 
     * @return bool
     */
    public function updateLtiInfo(LtiInfo $ltiInfo): bool
    {
        return $ltiInfo->save();
    }

    // public function getMoodleSession()
    // {
    // }
    // public function getSakaiSession()
    // {
    // }
}
