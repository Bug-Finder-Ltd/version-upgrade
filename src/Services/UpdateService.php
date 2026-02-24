<?php

namespace BugFinder\VersionUpgrade\Services;

use App\Models\BasicControl;
use ZipArchive;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class UpdateService
{
	protected $updateServer;

	public function __construct()
	{
		$this->updateServer = config('version-upgrade.update_server_url', env('UPDATE_SERVER_URL'));
	}

	protected function setStatus(string $status, ?string $message = null)
	{
		$payload = ['status' => $status, 'message' => $message, 'time' => now()->toDateTimeString()];
		Cache::put('update_status', $payload, 60 * 60);
	}

	public function checkUpdate($currentVersion)
	{
		$baseUrl = rtrim($this->updateServer, '/');

		$params = [
			'domain' => request()->getHost(), // auto current domain
			'version' => $currentVersion,
		];

		$url = $baseUrl . '?' . http_build_query($params);

		$ctx = stream_context_create(['http' => ['timeout' => 10]]);
		$json = @file_get_contents($url, false, $ctx);

		if (!$json) {
			return ['status' => 'error', 'message' => 'Cannot contact update server.'];
		}

		$data = json_decode($json, true);
		if (!$data) return ['status' => 'error', 'message' => 'Invalid response from update server.'];

		return ['status' => 'success', 'current_version' => $currentVersion, 'latest' => $data];
	}

	public function downloadAndInstall($currentVersion, $autoBackup = true)
	{
		$this->setStatus('starting', 'Starting update process');
		try {
			$check = $this->checkUpdate($currentVersion);
			if ($check['status'] !== 'success') {
				$this->setStatus('error', 'Update check failed');
				return ['status' => 'error', 'message' => 'Update check failed: ' . ($check['message'] ?? 'unknown')];
			}

			$latest = $check['latest'];
			if (version_compare($latest['latest_version'], $currentVersion, '<=')) {
				$this->setStatus('uptodate', 'Already latest');
				return ['status' => 'error', 'message' => 'You already have the latest version.'];
			}

			$backupPath = null;
			if ($autoBackup) {
				$this->setStatus('backing_up', 'Creating pre-update backup');
				$backupPath = $this->createPreUpdateBackup();
				if (!$backupPath) {
					Log::warning('Pre-update backup failed; proceeding at risk.');
					$this->setStatus('warning', 'Pre-update backup failed; proceeding');
				} else {
					$this->setStatus('backed_up', "Backup created: {$backupPath}");
				}
			}

			$this->setStatus('downloading', 'Downloading update zip');
			$tmpDir = storage_path('app/update_tmp');
			if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);
			$zipPath = $tmpDir . '/update.zip';

			$downloadUrl = $latest['download_url'];
			$this->downloadFile($downloadUrl, $zipPath);

			$this->setStatus('extracting', 'Extracting to temp folder');
			$extractTmp = $tmpDir . '/extracted';
			if (is_dir($extractTmp)) $this->rrmdir($extractTmp);
			mkdir($extractTmp, 0755, true);

			$zip = new ZipArchive();
			if ($zip->open($zipPath) !== true) {
				if ($backupPath) $this->rollbackFromBackup($backupPath);
				$this->setStatus('error', 'ZIP open failed');
				return ['status' => 'error', 'message' => 'Failed to open update zip.'];
			}
			$zip->extractTo($extractTmp);
			$zip->close();

			if (!file_exists($extractTmp . '/version.json') && !file_exists($extractTmp . '/update/update.php')) {
				$this->rrmdir($extractTmp);
				@unlink($zipPath);
				if ($backupPath) $this->rollbackFromBackup($backupPath);
				$this->setStatus('error', 'Invalid package');
				return ['status' => 'error', 'message' => 'Invalid update package.'];
			}

			$this->setStatus('applying', 'Applying update files');
			$this->moveDirectoryContents($extractTmp, base_path());

			$installer = base_path('update/update.php');
			if (file_exists($installer)) {
				$this->setStatus('installing', 'Running installer script');
				try {
					include $installer;
				} catch (\Throwable $e) {
					Log::error('Installer script error: ' . $e->getMessage());
					if ($backupPath) $this->rollbackFromBackup($backupPath);
					$this->setStatus('error', 'Installer failed - rollback attempted');
					return ['status' => 'error', 'message' => 'Installer script failed: ' . $e->getMessage()];
				}
				@unlink($installer);
			}

			$this->setStatus('migrating', 'Running migrations');
			try {
				Artisan::call('migrate', ['--force' => true]);
			} catch (\Throwable $e) {
				Log::error('Migration error during update: ' . $e->getMessage());
				if ($backupPath) $this->rollbackFromBackup($backupPath);
				$this->setStatus('error', 'Migration failed - rollback attempted');
				return ['status' => 'error', 'message' => 'Migration failed: ' . $e->getMessage()];
			}

			$basicControl = BasicControl::firstOrCreate();
			$basicControl->script_version = $latest['latest_version'];
			$basicControl->save();
			$this->setStatus('success', 'Updated to ' . $latest['latest_version']);

			@unlink($zipPath);
			$this->rrmdir($extractTmp);

			return ['status' => 'success', 'message' => 'Updated to version ' . $latest['latest_version']];
		} catch (\Throwable $e) {
			Log::error('Update error: ' . $e->getMessage());
			if (!empty($backupPath)) $this->rollbackFromBackup($backupPath);
			$this->setStatus('error', 'Update failed: ' . $e->getMessage());
			return ['status' => 'error', 'message' => 'Update failed: ' . $e->getMessage()];
		}
	}

	protected function downloadFile(string $url, string $destination)
	{
		$fp = fopen($destination, 'w');
		if (!$fp) throw new \Exception('Cannot write to ' . $destination);
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_TIMEOUT, 300);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_exec($ch);
		$err = curl_error($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		fclose($fp);
		if ($err || $httpCode >= 400) {
			@unlink($destination);
			throw new \Exception('Download failed: ' . ($err ?: 'HTTP ' . $httpCode));
		}
	}

	protected function createPreUpdateBackup(): string|false
	{
		try {
			$timestamp = now()->format('YmdHis');
			$backupDir = storage_path('app/update-backups');
			if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
			$backupFile = $backupDir . '/pre_update_backup_' . $timestamp . '.zip';
			$zip = new ZipArchive();
			if ($zip->open($backupFile, ZipArchive::CREATE) !== true) return false;
			$toBackup = [base_path('app'), base_path('routes'), base_path('resources'), base_path('public'), database_path('migrations')];
			foreach ($toBackup as $path) {
				if (!file_exists($path)) continue;
				$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path), \RecursiveIteratorIterator::LEAVES_ONLY);
				foreach ($files as $name => $file) {
					if (!$file->isDir()) {
						$filePath = $file->getRealPath();
						$relativePath = substr($filePath, strlen(base_path()) + 1);
						$zip->addFile($filePath, $relativePath);
					}
				}
			}
			if (file_exists(base_path('composer.json'))) $zip->addFile(base_path('composer.json'), 'composer.json');
			if (file_exists(base_path('composer.lock'))) $zip->addFile(base_path('composer.lock'), 'composer.lock');
			$zip->close();
			return $backupFile;
		} catch (\Throwable $e) {
			Log::error('Backup failed: ' . $e->getMessage());
			return false;
		}
	}

	public function rollbackFromBackup(string $backupZip): bool
	{
		try {
			if (!file_exists($backupZip)) return false;
			$zip = new ZipArchive();
			if ($zip->open($backupZip) === true) {
				$zip->extractTo(base_path());
				$zip->close();
				$this->setStatus('rolled_back', 'Rolled back from backup ' . $backupZip);
				return true;
			}
			return false;
		} catch (\Throwable $e) {
			Log::error('Rollback failed: ' . $e->getMessage());
			return false;
		}
	}

	protected function rrmdir(string $dir)
	{
		if (!is_dir($dir)) return;
		$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($files as $fileinfo) {
			$todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
			$todo($fileinfo->getRealPath());
		}
		rmdir($dir);
	}

	protected function moveDirectoryContents(string $src, string $dest)
	{
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
		foreach ($iterator as $item) {
			$targetPath = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
			if ($item->isDir()) {
				if (!is_dir($targetPath)) mkdir($targetPath, 0755, true);
			} else {
				$dir = dirname($targetPath);
				if (!is_dir($dir)) mkdir($dir, 0755, true);
				copy($item->getPathname(), $targetPath);
			}
		}
	}
}
