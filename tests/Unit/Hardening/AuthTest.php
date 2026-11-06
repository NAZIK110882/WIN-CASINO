<?php
declare(strict_types=1);

use App\Security\Auth;
use PHPUnit\Framework\TestCase;

final class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('JWT_SECRET=unit-test-secret-unit-test-secret-1234');
        putenv('JWT_TTL=3600');
    }

    public function testIssuedTokenVerifies(): void
    {
        $claims = Auth::verifyToken(Auth::issueToken(42, 'admin'));
        $this->assertNotNull($claims);
        $this->assertSame(42, $claims['sub']);
        $this->assertSame('admin', $claims['role']);
    }

    public function testTamperedTokenIsRejected(): void
    {
        [$h, $p, $s] = explode('.', Auth::issueToken(1, 'player'));
        $forged = rtrim(strtr(base64_encode(json_encode(['sub' => 1, 'role' => 'admin', 'exp' => time() + 999])), '+/', '-_'), '=');
        $this->assertNull(Auth::verifyToken("$h.$forged.$s"));
    }

    public function testExpiredTokenIsRejected(): void
    {
        putenv('JWT_TTL=-10');
        $this->assertNull(Auth::verifyToken(Auth::issueToken(1)));
    }

    public function testAlgNoneIsRejected(): void
    {
        $b64 = fn (string $d) => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
        $token = $b64('{"alg":"none"}') . '.' . $b64(json_encode(['sub' => 1, 'exp' => time() + 100])) . '.';
        $this->assertNull(Auth::verifyToken($token));
    }

    public function testGarbageIsRejected(): void
    {
        $this->assertNull(Auth::verifyToken('not-a-token'));
    }
}
