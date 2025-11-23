<?php
namespace BugFinder\VersionUpgrade\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BasicControl;
use Illuminate\Http\Request;
use BugFinder\VersionUpgrade\Services\UpdateService;
use ZipArchive;

class UpdateVersionController extends Controller
{
    protected $updateService;

    public function __construct(UpdateService $updateService)
    {
        $this->updateService = $updateService;
    }

    public function upgradationUI()
    {
        $current = BasicControl::firstOrCreate()->script_version;
        return view('version-upgrade::version-update', compact('current'));
    }

    public function check()
    {
        $current = BasicControl::firstOrCreate()->script_version;
        $info = $this->updateService->checkUpdate($current);
        return response()->json($info);
    }

    public function install(Request $request)
    {
        $current = BasicControl::firstOrCreate()->script_version;
        $autoBackup = $request->input('auto_backup', true);
        $result = $this->updateService->downloadAndInstall($current, $autoBackup);
        if ($result['status'] === 'success') {
            return response()->json(['status' => 'success', 'message' => $result['message']]);
        }
        return response()->json(['status' => 'error', 'message' => $result['message']], 500);
    }

    public function status()
    {
        $status = cache('update_status') ?: ['status' => 'idle', 'message' => 'No update running'];
        return response()->json($status);
    }

    public function downloadServerFiles()
    {
        $zipFileName = 'laravel-project-backup-' . date('Y-m-d-H-i-s') . '.zip';
        $zipFilePath = storage_path('app/' . $zipFileName);
        $zip = new ZipArchive;
        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $rootPath = base_path();
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($rootPath), \RecursiveIteratorIterator::LEAVES_ONLY);
            foreach ($files as $name => $file) {
                if (!$file->isFile()) continue;
                $filePath = $file->getRealPath();
                if (str_contains($filePath, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;
                $relativePath = substr($filePath, strlen($rootPath) + 1);
                $zip->addFile($filePath, $relativePath);
            }
            $zip->close();
        } else {
            return back()->with('error', 'Could not create zip file.');
        }
        return response()->download($zipFilePath)->deleteFileAfterSend(true);
    }

    public function downloadDatabase()
    {
        $database = env('DB_DATABASE');
        $username = env('DB_USERNAME');
        $password = env('DB_PASSWORD');
        $host     = env('DB_HOST');
        $filename = 'database-backup-' . date('Y-m-d-H-i-s') . '.sql';
        $path = storage_path('app/' . $filename);
        $command = "mysqldump --user={$username} --password={$password} --host={$host} {$database} > {$path}";
        exec($command);
        if (!file_exists($path)) {
            return back()->with('error', 'Backup failed.');
        }
        return response()->download($path)->deleteFileAfterSend(true);
    }
}
