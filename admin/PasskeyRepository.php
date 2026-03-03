<?php
namespace Admin\Services;

use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;

class PasskeyRepository implements PublicKeyCredentialSourceRepository
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $stmt = $this->pdo->prepare('SELECT credential_data FROM passkeys WHERE id = ?');
        $stmt->execute([base64_encode($publicKeyCredentialId)]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $data = json_decode($row['credential_data'], true);
        return PublicKeyCredentialSource::createFromArray($data);
    }

    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        $stmt = $this->pdo->prepare('SELECT credential_data FROM passkeys WHERE admin_id = ?');
        $stmt->execute([$publicKeyCredentialUserEntity->getId()]);
        
        $sources = [];
        while ($row = $stmt->fetch()) {
            $data = json_decode($row['credential_data'], true);
            $sources[] = PublicKeyCredentialSource::createFromArray($data);
        }
        
        return $sources;
    }

    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        $idBase64 = base64_encode($publicKeyCredentialSource->getPublicKeyCredentialId());
        $userHandle = $publicKeyCredentialSource->getUserHandle(); // Esse é o admin_id
        $dataJson = json_encode($publicKeyCredentialSource->jsonSerialize());

        $stmt = $this->pdo->prepare('
            INSERT INTO passkeys (id, admin_id, credential_data) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE credential_data = ?
        ');
        $stmt->execute([$idBase64, $userHandle, $dataJson, $dataJson]);
    }
}
