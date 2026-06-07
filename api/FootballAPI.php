<?php
// api/FootballAPI.php

class FootballAPI {
    private $apiKey;
    private $baseUrl = 'https://v3.football.api-sports.io';

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    /**
     * Lấy danh sách trận đấu của một mùa giải dựa vào league ID và season
     */
    public function fetchFixtures($leagueId, $season) {
        if (empty($this->apiKey)) {
            throw new Exception("API Key chưa được cấu hình. Vui lòng cập nhật API Key trong Admin Dashboard.");
        }

        $url = $this->baseUrl . "/fixtures?league=" . (int)$leagueId . "&season=" . (int)$season;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "x-rapidapi-host: v3.football.api-sports.io",
            "x-rapidapi-key: " . $this->apiKey
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Lỗi kết nối API: " . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception("API phản hồi mã lỗi HTTP: " . $httpCode);
        }

        $data = json_decode($response, true);
        
        if (!isset($data['response'])) {
            $msg = isset($data['errors']['token']) ? $data['errors']['token'] : "Dữ liệu phản hồi API không hợp lệ.";
            throw new Exception("Lỗi API: " . $msg);
        }

        return $data['response'];
    }
}
