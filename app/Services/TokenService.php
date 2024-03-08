<?php

namespace App\Services;

use App\Models\LtiInfo;
use App\Repositories\LtiInstanceRepository;
use Carbon\Carbon;

class TokenService
{
    private LtiInstanceRepository $ltiRepository;

    public function __construct(LtiInstanceRepository $ltiRepository)
    {
        $this->ltiRepository = $ltiRepository;
    }

    /**
     * @param string $token
     * 
     * @return bool
     */
    public function checkToken(string $token): bool
    {
        $result = null;
        if ($this->checkExpiredToken($token)) {
            $sessionData = $this->ltiRepository->getLtiInfoByToken($token);
            if ($sessionData !== null) {
                $sessionData->session_active = intval(Carbon::now()->addMinutes(env('TIME_LIMIT'))->valueOf());
                $result = $this->ltiRepository->updateLtiInfo($sessionData);
            }
        } else {
            $result = false;
        }

        return $result;
    }
    /**
     * @param string $token
     * 
     * @return bool
     */
    public function checkExpiredToken(string $token): bool
    {
        $now = time();
        return LtiInfo::where('token', '=', $token)
            ->where('expires_at', '>=', $now)
            ->exists();
    }
    /**
     * @param string $platform
     * @param array $lms_data
     * 
     * @return string|array
     */
    public function getTokenByPlatform(string $platform, array $lms_data): string|array
    {
        switch ($platform) {
            case 'moodle':
                return trim($lms_data['token']);
            case 'sakai':
                $token = [
                    'user' => trim($lms_data['user']),
                    'password' => trim($lms_data['password']),
                ];

                if (isset($lms_data['cookieName'])) {
                    $token['cookieName'] = trim($lms_data['cookieName']);
                }
                return $token;
            default:
                return '';
        }
    }
}
