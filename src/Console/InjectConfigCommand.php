<?php
namespace BugFinder\VersionUpgrade\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class InjectConfigCommand extends Command
{
    protected $signature = 'version-upgrade:inject-config';
    protected $description = 'Inject version_upgradation menu item into config/generalsettings.php if not present';

    public function handle(Filesystem $files)
    {
        $path = config_path('generalsettings.php');
        if (!file_exists($path)) {
            $this->error('config/generalsettings.php not found. Please run from Laravel project root.');
            return 1;
        }

        $content = file_get_contents($path);
        if (strpos($content, "'version_upgradation' =>") !== false) {
            $this->info('version_upgradation already exists in generalsettings.php');
            return 0;
        }

        // The block to insert
        $block = "\n        'version_upgradation' => [\n            'route' => 'admin.version.upgradation',\n            'icon' => 'fal fa-solar-system',\n            'short_description' => 'Update your version to access new features and continue your journey more comfortably and efficiently.',\n        ],\n";

        // Try to find the 'settings' => [ start
        $pos = strpos($content, "'settings' => [");
        if ($pos === false) {
            $this->error("Could not locate 'settings' => [ in generalsettings.php"); 
            return 1;
        }

        // Find position of the closing bracket corresponding to 'settings' array.
        $start = strpos($content, '[', $pos);
        $i = $start;
        $len = strlen($content);
        $depth = 0;
        for (; $i < $len; $i++) {
            $char = $content[$i];
            if ($char === '[') $depth++;
            else if ($char === ']') {
                $depth--;
                if ($depth === 0) break;
            }
        }

        if ($depth !== 0) {
            $this->error('Could not parse settings array brackets correctly.');
            return 1;
        }

        // insert block before position i (closing bracket)
        $insertPos = $i;
        $newContent = substr($content, 0, $insertPos) . $block . substr($content, $insertPos);

        // Backup original file
        copy($path, $path . '.bak.version-upgrade');

        // Write new file
        file_put_contents($path, $newContent);

        $this->info('Injected version_upgradation into config/generalsettings.php and backup saved as generalsettings.php.bak.version-upgrade');
        return 0;
    }
}
