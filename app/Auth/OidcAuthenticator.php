<?php
declare(strict_types=1);

namespace App\Auth;

use App\Security\SecurityAuditLogger;
use PDO;
use RuntimeException;
use Throwable;

final class OidcAuthenticator
{
    private bool $enabled = false;

    /**
     * @var string[]
     */
    private array $scopes = ['openid'];

    private ?string $cachedRedirectUri = null;

    public function __construct(private readonly PDO $pdo)
    {
        $this->bootConfiguration();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function redirectUri(): string
    {
        if ($this->cachedRedirectUri !== null) {
            return $this->cachedRedirectUri;
        }

        $configured = env('OIDC_REDIRECT_URI');
        if ($configured && filter_var($configured, FILTER_VALIDATE_URL)) {
            $this->cachedRedirectUri = $configured;
            return $this->cachedRedirectUri;
        }

        $this->cachedRedirectUri = base_url('sso/callback.php');
        return $this->cachedRedirectUri;
    }

    /**
     * @return array{claims: array, id_token: ?string, access_token: ?string, refresh_token: ?string}
     */
    public function completeAuthentication(): array
    {
        if (!$this->enabled) {
            throw new RuntimeException('OpenID Connect is not enabled.');
        }

        $client = $this->buildClient();

        try {
            $client->authenticate();
        } catch (Throwable $exception) {
            throw new RuntimeException('Impossibile completare l\'autenticazione OIDC: ' . $exception->getMessage(), (int) $exception->getCode(), $exception);
        }

        $userInfo = $client->requestUserInfo();
        $claims = $this->normalizeClaims($userInfo);

        return [
            'claims' => $claims,
            'id_token' => $client->getIdToken(),
            'access_token' => $client->getAccessToken(),
            'refresh_token' => $client->getRefreshToken(),
        ];
    }

    public function findLocalUser(array $claims): ?array
    {
        $email = isset($claims['email']) ? strtolower((string) $claims['email']) : null;
        $candidateUsernames = [];
        foreach (['preferred_username', 'upn', 'username', 'name', 'sub'] as $claimKey) {
            if (!empty($claims[$claimKey]) && is_string($claims[$claimKey])) {
                $candidateUsernames[] = $claims[$claimKey];
            }
        }
        if ($email) {
            $candidateUsernames[] = $email;
        }
        $candidateUsernames = array_values(array_unique(array_filter($candidateUsernames, static fn($value) => $value !== '')));

        if ($email) {
            $stmt = $this->pdo->prepare('SELECT id, username, ruolo, email, nome, cognome, theme_preference FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
            $stmt->execute([':email' => $email]);
            $match = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($match) {
                return $match;
            }
        }

        foreach ($candidateUsernames as $username) {
            $stmt = $this->pdo->prepare('SELECT id, username, ruolo, email, nome, cognome, theme_preference FROM users WHERE username = :username LIMIT 1');
            $stmt->execute([':username' => $username]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($record) {
                return $record;
            }
        }

        return null;
    }

    public function logSuccess(SecurityAuditLogger $logger, array $user, array $claims): void
    {
        $identifier = $claims['preferred_username'] ?? $claims['email'] ?? $user['username'];
        $logger->logLoginAttempt(
            (int) $user['id'],
            (string) $identifier,
            true,
            request_ip(),
            request_user_agent(),
            'sso'
        );
    }

    public function logFailure(SecurityAuditLogger $logger, ?string $identifier, ?string $reason = null): void
    {
        $logger->logLoginAttempt(
            null,
            (string) ($identifier ?? ''),
            false,
            request_ip(),
            request_user_agent(),
            $reason ?? 'sso_failed'
        );
    }

    public function buildLogoutUrl(?string $idToken): ?string
    {
        if (!$this->enabled) {
            return null;
        }
        $endSession = env('OIDC_END_SESSION_ENDPOINT');
        if (!$endSession || !$idToken) {
            return null;
        }
        $postLogout = env('OIDC_POST_LOGOUT_REDIRECT_URI', base_url('index.php'));
        $params = [
            'id_token_hint' => $idToken,
            'post_logout_redirect_uri' => $postLogout,
        ];
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $separator = str_contains($endSession, '?') ? '&' : '?';
        return $endSession . $separator . $query;
    }

    private function bootConfiguration(): void
    {
        $enabled = filter_var(env('OIDC_ENABLED', false), FILTER_VALIDATE_BOOL);
        if (!$enabled) {
            $this->enabled = false;
            return;
        }

        $issuer = env('OIDC_ISSUER');
        $clientId = env('OIDC_CLIENT_ID');
        $redirect = env('OIDC_REDIRECT_URI');

        if (!$issuer || !$clientId) {
            $this->enabled = false;
            return;
        }

        if (!$redirect) {
            $redirect = base_url('sso/callback.php');
        }

        $scopeString = env('OIDC_SCOPES', 'openid profile email');
        $scopes = preg_split('/\s+/', (string) $scopeString) ?: ['openid'];
        $scopes = array_values(array_unique(array_filter(array_map('trim', $scopes))));
        if (!$scopes) {
            $scopes = ['openid'];
        }
        $this->scopes = $scopes;
        $this->cachedRedirectUri = $redirect;
        $this->enabled = true;
    }

    private function buildClient()
    {
        $issuer = env('OIDC_ISSUER');
        $clientId = env('OIDC_CLIENT_ID');
        if (!$issuer || !$clientId) {
            throw new RuntimeException('OIDC configuration mancante.');
        }

        $clientSecret = env('OIDC_CLIENT_SECRET');
        $class = '\\Jumbojett\\OpenIDConnectClient';
        if (!class_exists($class)) {
            throw new RuntimeException('Dipendenza OpenID Connect non disponibile.');
        }

        $client = new $class((string) $issuer, (string) $clientId, $clientSecret ?: null);
        $client->setRedirectURL($this->redirectUri());
        $client->setResponseTypes(['code']);
        $client->setIssuer((string) $issuer);
        $client->setProviderURL((string) $issuer);

        $client->addScope($this->scopes);

        $verifyHost = env('OIDC_VERIFY_HOST', '1');
        $verifyPeer = env('OIDC_VERIFY_PEER', '1');
        if (!$this->toBool($verifyHost)) {
            $client->setVerifyHost(false);
        }
        if (!$this->toBool($verifyPeer)) {
            $client->setVerifyPeer(false);
        }

        $prompt = env('OIDC_PROMPT');
        if ($prompt) {
            $client->addAuthParam(['prompt' => $prompt]);
        }

        $maxAge = env('OIDC_MAX_AGE');
        if ($maxAge !== null && $maxAge !== '') {
            $client->addAuthParam(['max_age' => (int) $maxAge]);
        }

        return $client;
    }

    private function normalizeClaims(mixed $userInfo): array
    {
        if (is_object($userInfo)) {
            $encoded = json_encode($userInfo);
            if ($encoded === false) {
                return [];
            }
            $userInfo = json_decode($encoded, true);
        }

        if (!is_array($userInfo)) {
            return [];
        }

        if (isset($userInfo['email']) && is_string($userInfo['email'])) {
            $userInfo['email'] = strtolower($userInfo['email']);
        }

        return $userInfo;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === '0' || $normalized === 'false' || $normalized === 'off' || $normalized === 'no') {
                return false;
            }
        }
        return filter_var($value, FILTER_VALIDATE_BOOL) ?? true;
    }
}
