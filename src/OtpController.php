<?php
namespace App;

use DateTime;
use DateInterval;

class OtpController
{
    private $pdo;
    private $ttl;
    private $length;

    public function __construct()
    {
        $this->pdo    = Database::getConnection();
        $this->ttl    = (int)getenv('OTP_TTL');
        $this->length = (int)getenv('OTP_LENGTH');
    }

    public function generate(int $userId)
    {
        $code = '';
        for ($i = 0; $i < $this->length; $i++) {
            $code .= random_int(0, 9);
        }

        $expires = new DateTime();
        $expires->add(new DateInterval("PT{$this->ttl}S"));
        $expiresAt = $expires->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            "INSERT INTO otps (user_id, code, expires_at) 
             VALUES (:uid, :code, :exp)"
        );
        $stmt->execute([
            ':uid'  => $userId,
            ':code' => $code,
            ':exp'  => $expiresAt
        ]);

        http_response_code(201);
        echo json_encode([
            'user_id'    => $userId,
            'otp_code'   => $code,
            'expires_at' => $expiresAt
        ]);
    }

    public function validate(int $userId, string $code)
    {
        $stmt = $this->pdo->prepare(
            "SELECT id 
               FROM otps
              WHERE user_id = :uid
                AND code    = :code
                AND used    = 0
                AND expires_at >= NOW()
              ORDER BY created_at DESC
              LIMIT 1"
        );
        $stmt->execute([
            ':uid'  => $userId,
            ':code' => $code
        ]);

        $row = $stmt->fetch();
        if (! $row) {
            http_response_code(400);
            echo json_encode(['valid' => false, 'message' => 'OTP salah atau sudah kedaluwarsa']);
            return;
        }

        $upd = $this->pdo->prepare("UPDATE otps SET used = 1 WHERE id = :id");
        $upd->execute([':id' => $row['id']]);

        http_response_code(200);
        echo json_encode(['valid' => true, 'message' => 'OTP valid']);
    }
}