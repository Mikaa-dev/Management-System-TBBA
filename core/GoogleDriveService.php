<?php
/**
 * Google Drive API Service - TBBA ERP
 * Handles connection and folder/file management for Event Gallery
 */

require_once __DIR__ . '/../vendor/autoload.php';

class GoogleDriveService {
    private ?Google\Client $client = null;
    private ?Google\Service\Drive $service = null;
    private bool $isInitialized = false;

    // TODO: The master folder ID where all event folders will be created
    // The admin must create a folder in Drive and share it with the service account email, then put its ID here
    private string $masterFolderId = '';

    public function __construct() {
        $this->initialize();
    }

    private function initialize(): void {
        // This service can be instantiated independently of Firebase config,
        // so it must load its own environment configuration.
        if (!isset($_ENV['GDRIVE_MASTER_FOLDER_ID']) && class_exists('Dotenv\Dotenv')) {
            Dotenv\Dotenv::createImmutable(__DIR__ . '/../')->safeLoad();
        }

        $credentialsValue = trim(tbba_env('GOOGLE_DRIVE_CREDENTIALS_PATH', 'config/google-credentials.json'));
        $isAbsolutePath = str_starts_with($credentialsValue, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $credentialsValue) === 1;
        $credentialsPath = $isAbsolutePath
            ? $credentialsValue
            : dirname(__DIR__) . '/' . ltrim($credentialsValue, '/\\');
        
        if (!file_exists($credentialsPath)) {
            // Cannot initialize without credentials
            return;
        }

        $this->client = new Google\Client();
        $this->client->setAuthConfig($credentialsPath);
        $this->client->addScope(Google\Service\Drive::DRIVE_FILE);
        $this->client->setAccessType('offline');

        $this->service = new Google\Service\Drive($this->client);
        $this->isInitialized = true;

        $this->masterFolderId = defined('GDRIVE_MASTER_FOLDER_ID')
            ? (string) GDRIVE_MASTER_FOLDER_ID
            : trim((string) ($_ENV['GDRIVE_MASTER_FOLDER_ID'] ?? ''));
    }

    public function isReady(): bool {
        return $this->isInitialized && !empty($this->masterFolderId);
    }

    /**
     * Create a new folder for an event
     * 
     * @param string $folderName
     * @return array ['id' => string, 'link' => string] | false on failure
     */
    public function createEventFolder(string $folderName) {
        if (!$this->isReady()) return false;

        try {
            $fileMetadata = new Google\Service\Drive\DriveFile([
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$this->masterFolderId]
            ]);

            $folder = $this->service->files->create($fileMetadata, ['fields' => 'id, webViewLink']);

            // Galleries may be viewed with their link, but anonymous visitors must
            // never receive write access to company Drive content.
            $permission = new Google\Service\Drive\Permission([
                'type' => 'anyone',
                'role' => 'reader'
            ]);
            $this->service->permissions->create($folder->getId(), $permission);

            return [
                'id' => $folder->getId(),
                'link' => $folder->getWebViewLink()
            ];
        } catch (Exception $e) {
            error_log("[GoogleDriveService] Error creating folder: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get list of image files in a folder
     */
    public function getImages(string $folderId): array {
        if (!$this->isReady()) return [];

        try {
            $optParams = [
                'q' => "'{$folderId}' in parents and mimeType contains 'image/' and trashed = false",
                'fields' => 'files(id, name, thumbnailLink, webContentLink, webViewLink, createdTime)',
                'orderBy' => 'createdTime desc',
                'pageSize' => 100
            ];

            $results = $this->service->files->listFiles($optParams);
            $files = [];
            foreach ($results->getFiles() as $file) {
                // thumbnailLink is usually ~s220, we can resize it via URL trick if needed
                $files[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'thumbnail' => $file->getThumbnailLink(),
                    'downloadUrl' => $file->getWebContentLink(),
                    'viewUrl' => $file->getWebViewLink()
                ];
            }
            return $files;
        } catch (Exception $e) {
            error_log("[GoogleDriveService] Error getting images: " . $e->getMessage());
            return [];
        }
    }
}
