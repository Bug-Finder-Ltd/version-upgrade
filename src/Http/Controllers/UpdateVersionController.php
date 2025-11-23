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
		$DB_HOST = $_ENV['DB_HOST'];
		$DB_USER = $_ENV['DB_USERNAME'];
		$DB_PASS = $_ENV['DB_PASSWORD'];
		$DB_NAME = $_ENV['DB_DATABASE'];
		return $this->EXPORT_DATABASE($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

	}

	public function EXPORT_DATABASE($host,$user,$pass,$name,$tables=false, $backup_name=false)
	{
		set_time_limit(3000); $mysqli = new \MySQLi($host,$user,$pass,$name); $mysqli->select_db($name); $mysqli->query("SET NAMES 'utf8'");
		$queryTables = $mysqli->query('SHOW TABLES'); while($row = $queryTables->fetch_row()) { $target_tables[] = $row[0]; }	if($tables !== false) { $target_tables = array_intersect( $target_tables, $tables); }
		$content = "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\r\nSET time_zone = \"+00:00\";\r\n\r\n\r\n/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\r\n/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\r\n/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\r\n/*!40101 SET NAMES utf8 */;\r\n--\r\n-- Database: `".$name."`\r\n--\r\n\r\n\r\n";
		foreach($target_tables as $table){
			if (empty($table)){ continue; }
			$result	= $mysqli->query('SELECT * FROM `'.$table.'`');  	$fields_amount=$result->field_count;  $rows_num=$mysqli->affected_rows; 	$res = $mysqli->query('SHOW CREATE TABLE '.$table);	$TableMLine=$res->fetch_row();
			$content .= "\n\n".$TableMLine[1].";\n\n";   $TableMLine[1]=str_ireplace('CREATE TABLE `','CREATE TABLE IF NOT EXISTS `',$TableMLine[1]);
			for ($i = 0, $st_counter = 0; $i < $fields_amount;   $i++, $st_counter=0) {
				while($row = $result->fetch_row())	{ //when started (and every after 100 command cycle):
					if ($st_counter%100 == 0 || $st_counter == 0 )	{$content .= "\nINSERT INTO ".$table." VALUES";}
					$content .= "\n(";    for($j=0; $j<$fields_amount; $j++){ $row[$j] = str_replace("\n","\\n", addslashes($row[$j]) ); if (isset($row[$j])){$content .= '"'.$row[$j].'"' ;}  else{$content .= '""';}	   if ($j<($fields_amount-1)){$content.= ',';}   }        $content .=")";
					//every after 100 command cycle [or at last line] ....p.s. but should be inserted 1 cycle eariler
					if ( (($st_counter+1)%100==0 && $st_counter!=0) || $st_counter+1==$rows_num) {$content .= ";";} else {$content .= ",";}	$st_counter=$st_counter+1;
				}
			} $content .="\n\n\n";
		}
		$content .= "\r\n\r\n/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\r\n/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\r\n/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;";
		$backup_name = $backup_name ? $backup_name : $name.'___('.date('H-i-s').'_'.date('d-m-Y').').sql';
		ob_get_clean(); header('Content-Type: application/octet-stream');  header("Content-Transfer-Encoding: Binary");  header('Content-Length: '. (function_exists('mb_strlen') ? mb_strlen($content, '8bit'): strlen($content)) );    header("Content-disposition: attachment; filename=\"".$backup_name."\"");
		echo $content; exit;
	}

}
