<?php

namespace App\Repositories;

use App\Models\LtiInfo;

class LtiInstanceRepository
{
    /**
     * @param string $token
     * 
     * @return [type]
     */
    public function getLtiInfoByToken(string $token)
    {
        $ltiInfo = LtiInfo::where('token', '=', $token)
            ->first();
        return $ltiInfo;
    }
    public function checkExpiredToken(string $token): bool
    {
        $now = time();
        return LtiInfo::where('token', '=', $token)
            ->where('expires_at', '>=', $now)
            ->exists();
    }
    public function updateLtiInfo(LtiInfo $ltiInfo)
    {

        return $ltiInfo->save();
    }
    public function getMoodleSession()
    {
    }
    public function getSakaiSession()
    {
    }
}